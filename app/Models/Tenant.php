<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    public const STATUSES = [
        'prospect',
        'active',
        'notice',
        'former',
    ];

    public const KYC_STATUSES = [
        'pending',
        'submitted',
        'verified',
        'rejected',
    ];

    protected $fillable = [
        'unit_id',
        'user_id',
        'full_name',
        'email',
        'phone',
        'status',
        'kyc_status',
        'move_in_on',
        'move_out_on',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'move_in_on' => 'date',
            'move_out_on' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TenantDocument::class)->latest('uploaded_at');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class)->latest('start_on');
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
            return $query->where('user_id', $user->id);
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

        return $user->hasRole('tenant') && $this->user_id === $user->id;
    }
}