<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceRequest extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'plumbing',
        'electrical',
        'carpentry',
        'cleaning',
        'other',
    ];

    public const PRIORITIES = [
        'low',
        'medium',
        'high',
        'urgent',
    ];

    public const STATUSES = [
        'open',
        'in_progress',
        'pending_tenant_confirmation',
        'resolved',
        'closed',
        'rejected',
    ];

    private const STATUS_TRANSITIONS = [
        'open' => ['in_progress', 'pending_tenant_confirmation', 'resolved', 'closed', 'rejected'],
        'in_progress' => ['pending_tenant_confirmation', 'resolved', 'closed', 'rejected'],
        'pending_tenant_confirmation' => ['in_progress', 'resolved', 'closed', 'rejected'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => [],
        'rejected' => [],
    ];

    protected $fillable = [
        'unit_id',
        'tenant_id',
        'submitted_by',
        'title',
        'category',
        'priority',
        'description',
        'status',
        'internal_notes',
        'vendor_name',
        'repair_cost',
        'resolved_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'repair_cost' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MaintenanceRequestPhoto::class)->latest('uploaded_at');
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
            return $query->where(function (Builder $tenantQuery) use ($user): void {
                $tenantQuery
                    ->where('submitted_by', $user->id)
                    ->orWhereHas('tenant', fn (Builder $query) => $query->where('user_id', $user->id));
            });
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

        if ($user->hasRole('tenant')) {
            return $this->submitted_by === $user->id || $this->tenant?->user_id === $user->id;
        }

        return false;
    }

    public function canTransitionTo(string $nextStatus): bool
    {
        $allowed = self::STATUS_TRANSITIONS[$this->status] ?? [];

        return in_array($nextStatus, $allowed, true);
    }
}
