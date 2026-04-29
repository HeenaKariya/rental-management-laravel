<?php

namespace App\Models;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Lease extends Model
{
    use HasFactory;

    public const STATUSES = [
        'draft',
        'active',
        'renewed',
        'completed',
        'terminated',
    ];

    protected $fillable = [
        'lease_number',
        'unit_id',
        'tenant_id',
        'previous_lease_id',
        'start_on',
        'end_on',
        'rent_amount',
        'billing_day',
        'grace_period_days',
        'late_fee_mode',
        'late_fee_value',
        'status',
        'active_lease_guard',
        'activated_at',
        'terminated_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'end_on' => 'date',
            'grace_period_days' => 'integer',
            'late_fee_value' => 'decimal:2',
            'rent_amount' => 'decimal:2',
            'start_on' => 'date',
            'terminated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $lease): void {
            if (! $lease->lease_number) {
                $lease->lease_number = static::uniqueLeaseNumber();
            }

            $lease->syncDerivedState();
        });

        static::updating(function (self $lease): void {
            $lease->syncDerivedState();
        });

        static::saved(function (self $lease): void {
            $lease->syncUnitOccupancyState();
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function previousLease(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_lease_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'previous_lease_id');
    }

    public function deposit(): HasOne
    {
        return $this->hasOne(LeaseDeposit::class);
    }

    public function rentReturn(): HasOne
    {
        return $this->hasOne(RentReturn::class);
    }

    public function rentLedgers(): HasMany
    {
        return $this->hasMany(RentLedger::class)->orderBy('payment_month');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        if ($user->hasRole('manager')) {
            return $query->whereHas('unit.property.activeManagerAssignments', function (Builder $assignmentQuery) use ($user) {
                $assignmentQuery->where('manager_id', $user->id);
            });
        }

        if ($user->hasRole('tenant')) {
            return $query->whereHas('tenant', fn (Builder $tenantQuery) => $tenantQuery->where('user_id', $user->id));
        }

        return $query->whereRaw('1 = 0');
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $this->unit?->isVisibleTo($user) === true;
        }

        return $user->hasRole('tenant') && $this->tenant?->user_id === $user->id;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasOverlappingActiveLease(): bool
    {
        return static::query()
            ->where('unit_id', $this->unit_id)
            ->where('status', 'active')
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->id))
            ->exists();
    }

    public function syncDerivedState(): void
    {
        $this->active_lease_guard = $this->status === 'active' ? 1 : null;
        $this->billing_day = $this->billing_day ?: 1;
        $this->grace_period_days = $this->grace_period_days ?? 5;
        $this->late_fee_mode = $this->late_fee_mode ?: 'fixed';
        $this->late_fee_value = $this->late_fee_value ?? 0;

        if ($this->status === 'active' && ! $this->activated_at) {
            $this->activated_at = now();
        }

        if ($this->status === 'terminated' && ! $this->terminated_at) {
            $this->terminated_at = now();
        }

        if ($this->status !== 'terminated') {
            $this->terminated_at = null;
        }
    }

    public function ensureRentLedgers(?User $actor = null): void
    {
        if ($this->status === 'draft') {
            return;
        }

        foreach (CarbonPeriod::create($this->start_on->copy()->startOfMonth(), '1 month', $this->end_on->copy()->startOfMonth()) as $monthStart) {
            $month = Carbon::instance($monthStart);

            $this->rentLedgers()->firstOrCreate(
                ['payment_month' => $month->toDateString()],
                [
                    'due_on' => $this->dueOnForMonth($month)->toDateString(),
                    'base_rent_amount' => $this->rent_amount,
                    'status' => 'unpaid',
                    'created_by' => $actor?->id ?? $this->created_by,
                    'updated_by' => $actor?->id ?? $this->updated_by,
                ]
            );
        }

        $this->syncRentLedgerTimeline($actor);
    }

    public function syncRentLedgerTimeline(?User $actor = null): void
    {
        $carryArrears = 0.0;
        $creditBroughtForward = 0.0;

        $ledgers = $this->rentLedgers()->with('instalments')->get();

        foreach ($ledgers as $ledger) {
            $ledger->forceFill([
                'due_on' => $this->dueOnForMonth($ledger->payment_month)->toDateString(),
            ])->save();

            $ledger->syncComputedState($carryArrears, $creditBroughtForward, $actor);

            $carryArrears = max((float) $ledger->outstanding_balance, 0);
            $creditBroughtForward = max(((float) $ledger->outstanding_balance) * -1, 0);
        }
    }

    public function dueOnForMonth(Carbon $month): Carbon
    {
        return $month->copy()->startOfMonth()->day(min($this->billing_day, $month->daysInMonth));
    }

    public function syncUnitOccupancyState(): void
    {
        $this->loadMissing('unit');

        if (! $this->unit) {
            return;
        }

        $hasActiveLease = $this->unit->leases()->where('status', 'active')->exists();

        $this->unit->forceFill([
            'occupancy_status' => $hasActiveLease ? 'occupied' : 'vacant',
            'vacant_since' => $hasActiveLease ? null : ($this->unit->vacant_since ?: now()->toDateString()),
        ])->save();
    }

    public function latestPaidThroughDate(): ?Carbon
    {
        $paidLedger = $this->rentLedgers()
            ->where('status', 'fully_paid')
            ->latest('payment_month')
            ->first();

        return $paidLedger?->payment_month?->copy()->endOfMonth();
    }

    public function rentReturnDraft(?Carbon $vacationDate = null): array
    {
        $vacationDate = ($vacationDate ?: $this->terminated_at ?: $this->end_on)->copy()->startOfDay();
        $lastPaidThroughDate = $this->latestPaidThroughDate()?->copy()->startOfDay();
        $billingMonth = ($lastPaidThroughDate ?: $vacationDate)->copy()->startOfMonth();
        $billingMonthDays = $billingMonth->daysInMonth;
        $calculation = RentReturn::calculateSuggestion(
            $vacationDate,
            $lastPaidThroughDate,
            (float) $this->rent_amount,
            $billingMonthDays,
        );

        $latestLedger = $this->rentLedgers()->latest('payment_month')->first();

        return [
            'billing_month' => $billingMonth,
            'billing_month_days' => $billingMonthDays,
            'daily_rate' => $calculation['daily_rate'],
            'last_paid_through_date' => $lastPaidThroughDate,
            'monthly_rent_amount' => (float) $this->rent_amount,
            'outstanding_arrears' => max((float) ($latestLedger?->outstanding_balance ?? 0), 0),
            'suggested_amount' => $calculation['suggested_amount'],
            'unused_days' => $calculation['unused_days'],
            'vacation_date' => $vacationDate,
        ];
    }

    public function canInitiateRentReturn(): bool
    {
        if (! $this->terminated_at || $this->rentReturn()->exists()) {
            return false;
        }

        return (float) $this->rentReturnDraft()['suggested_amount'] > 0;
    }

    protected static function uniqueLeaseNumber(): string
    {
        $base = 'LS-'.now()->format('Y').'-';

        do {
            $leaseNumber = $base.Str::upper(Str::random(6));
        } while (static::query()->where('lease_number', $leaseNumber)->exists());

        return $leaseNumber;
    }
}