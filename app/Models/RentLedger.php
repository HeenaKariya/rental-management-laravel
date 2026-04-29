<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RentLedger extends Model
{
    use HasFactory;

    public const STATUSES = [
        'unpaid',
        'partially_paid',
        'fully_paid',
        'overdue',
    ];

    protected $fillable = [
        'lease_id',
        'payment_month',
        'due_on',
        'base_rent_amount',
        'carried_arrears',
        'credit_brought_forward',
        'total_due',
        'total_received',
        'late_fee_total',
        'outstanding_balance',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_month' => 'date',
            'due_on' => 'date',
            'base_rent_amount' => 'decimal:2',
            'carried_arrears' => 'decimal:2',
            'credit_brought_forward' => 'decimal:2',
            'total_due' => 'decimal:2',
            'total_received' => 'decimal:2',
            'late_fee_total' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function instalments(): HasMany
    {
        return $this->hasMany(RentInstalment::class)->orderBy('payment_date')->orderBy('instalment_number');
    }

    public function activeInstalments(): HasMany
    {
        return $this->instalments()->whereNull('voided_at');
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
        return $query->whereHas('lease', fn (Builder $leaseQuery) => $leaseQuery->visibleTo($user));
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->lease?->isVisibleTo($user) === true;
    }

    public function recordInstalment(array $attributes, User $actor): RentInstalment
    {
        return DB::transaction(function () use ($attributes, $actor): RentInstalment {
            $instalment = $this->instalments()->create([
                ...$attributes,
                'instalment_number' => $this->nextInstalmentNumber(),
                'recorded_by' => $actor->id,
            ]);

            $this->lease->syncRentLedgerTimeline($actor);

            return $instalment;
        });
    }

    public function syncComputedState(float $carriedArrears, float $creditBroughtForward, ?User $actor = null): void
    {
        $totals = $this->activeInstalments()
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as paid_total, COALESCE(SUM(late_fee_charged), 0) as late_fee_total')
            ->first();

        $baseRentAmount = (float) $this->lease->rent_amount;
        $totalReceived = (float) ($totals?->paid_total ?? 0);
        $lateFeeTotal = (float) ($totals?->late_fee_total ?? 0);
        $totalDue = $baseRentAmount + $carriedArrears - $creditBroughtForward;
        $outstandingBalance = $totalDue + $lateFeeTotal - $totalReceived;

        $this->forceFill([
            'base_rent_amount' => $baseRentAmount,
            'carried_arrears' => $carriedArrears,
            'credit_brought_forward' => $creditBroughtForward,
            'total_due' => $totalDue,
            'total_received' => $totalReceived,
            'late_fee_total' => $lateFeeTotal,
            'outstanding_balance' => $outstandingBalance,
            'status' => $this->resolveStatus($outstandingBalance, $totalReceived),
            'updated_by' => $actor?->id ?? $this->updated_by,
        ])->save();
    }

    public function suggestedLateFeeForDate(Carbon $paymentDate): float
    {
        if ($paymentDate->lte($this->due_on->copy()->addDays($this->lease->grace_period_days))) {
            return 0;
        }

        $outstandingBeforePayment = max((float) $this->outstanding_balance, 0);

        if ($outstandingBeforePayment <= 0) {
            return 0;
        }

        if ($this->lease->late_fee_mode === 'percentage') {
            return round($outstandingBeforePayment * ((float) $this->lease->late_fee_value / 100), 2);
        }

        return round((float) $this->lease->late_fee_value, 2);
    }

    private function nextInstalmentNumber(): int
    {
        return ((int) $this->instalments()->max('instalment_number')) + 1;
    }

    private function resolveStatus(float $outstandingBalance, float $totalReceived): string
    {
        if ($outstandingBalance <= 0) {
            return 'fully_paid';
        }

        if ($totalReceived > 0) {
            return 'partially_paid';
        }

        if (now()->gt($this->due_on->copy()->addDays($this->lease->grace_period_days))) {
            return 'overdue';
        }

        return 'unpaid';
    }
}