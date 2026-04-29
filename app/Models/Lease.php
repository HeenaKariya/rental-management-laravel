<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    protected static function uniqueLeaseNumber(): string
    {
        $base = 'LS-'.now()->format('Y').'-';

        do {
            $leaseNumber = $base.Str::upper(Str::random(6));
        } while (static::query()->where('lease_number', $leaseNumber)->exists());

        return $leaseNumber;
    }
}