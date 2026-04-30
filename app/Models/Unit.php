<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    public const OCCUPANCY_STATUSES = [
        'vacant',
        'occupied',
        'reserved',
        'under_maintenance',
    ];

    protected $fillable = [
        'property_id',
        'unit_number',
        'floor',
        'bedrooms',
        'bathrooms',
        'area',
        'area_unit',
        'occupancy_status',
        'vacant_since',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'bathrooms' => 'decimal:1',
            'vacant_since' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $unit): void {
            if ($unit->occupancy_status === 'vacant') {
                $unit->vacant_since ??= now()->toDateString();
            } elseif ($unit->isDirty('occupancy_status')) {
                $unit->vacant_since = null;
            }

            $unit->area_unit = $unit->area_unit ?: 'sqft';
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class)->latest();
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class)->latest('start_on');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class)->latest();
    }

    public function activeLease(): HasMany
    {
        return $this->leases()->where('status', 'active');
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
            return $query->whereHas('property.activeManagerAssignments', function (Builder $assignmentQuery) use ($user) {
                $assignmentQuery->where('manager_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->hasRole('super_admin') || $this->property?->isManagedBy($user) === true;
    }
}