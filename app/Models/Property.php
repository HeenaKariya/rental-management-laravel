<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'residential',
        'commercial',
        'mixed_use',
        'land',
    ];

    public const LIFECYCLE_STAGES = [
        'draft',
        'active',
        'stabilized',
        'under_maintenance',
        'off_market',
    ];

    protected $fillable = [
        'title',
        'slug',
        'type',
        'street_address',
        'city',
        'state',
        'postal_code',
        'country',
        'area',
        'area_unit',
        'lifecycle_stage',
        'lifecycle_stage_changed_at',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'deleted_at' => 'datetime',
            'lifecycle_stage_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $property): void {
            if (! $property->slug) {
                $property->slug = static::uniqueSlugFor($property->title);
            }

            if (! $property->lifecycle_stage_changed_at) {
                $property->lifecycle_stage_changed_at = now();
            }
        });

        static::updating(function (self $property): void {
            if ($property->isDirty('title')) {
                $property->slug = static::uniqueSlugFor($property->title, $property->id);
            }

            if ($property->isDirty('lifecycle_stage')) {
                $property->lifecycle_stage_changed_at = now();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function managerAssignments(): HasMany
    {
        return $this->hasMany(PropertyManagerAssignment::class);
    }

    public function activeManagerAssignments(): HasMany
    {
        return $this->managerAssignments()->whereNull('revoked_at');
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_manager_assignments', 'property_id', 'manager_id')
            ->withPivot(['id', 'assigned_by', 'assigned_at', 'revoked_at', 'revoked_by'])
            ->wherePivotNull('revoked_at')
            ->withTimestamps();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class)->orderBy('sort_order');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(PropertyActivityLog::class)->latest('occurred_at');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        if ($user->hasRole('manager')) {
            return $query->whereHas('activeManagerAssignments', function (Builder $assignmentQuery) use ($user) {
                $assignmentQuery->where('manager_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function isManagedBy(User $user): bool
    {
        if (! $user->hasRole('manager')) {
            return false;
        }

        return $this->activeManagerAssignments()->where('manager_id', $user->id)->exists();
    }

    public function assignManager(User $manager, ?User $actor = null): PropertyManagerAssignment
    {
        $activeAssignment = $this->activeManagerAssignments()
            ->where('manager_id', $manager->id)
            ->first();

        if ($activeAssignment) {
            return $activeAssignment;
        }

        $assignment = $this->managerAssignments()->create([
            'manager_id' => $manager->id,
            'assigned_by' => $actor?->id,
            'assigned_at' => now(),
        ]);

        PropertyActivityLog::record($this, 'property.manager_assigned', $actor, [
            'manager_name' => $manager->name,
        ], $manager);

        return $assignment;
    }

    public function revokeManagerAssignment(PropertyManagerAssignment $assignment, ?User $actor = null): void
    {
        if ($assignment->property_id !== $this->id || $assignment->revoked_at) {
            return;
        }

        $assignment->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $actor?->id,
        ])->save();

        PropertyActivityLog::record($this, 'property.manager_revoked', $actor, [
            'manager_name' => $assignment->manager?->name,
        ], $assignment->manager);
    }

    public function refreshCoverPhoto(?int $coverPhotoId = null): void
    {
        $photos = $this->photos()->get();

        if ($photos->isEmpty()) {
            return;
        }

        $coverPhotoId ??= $photos->first()->id;

        $this->photos()->update(['is_cover' => false]);
        $this->photos()->whereKey($coverPhotoId)->update(['is_cover' => true]);
    }

    public function reorderPhotos(array $photoOrders): void
    {
        if ($photoOrders === []) {
            return;
        }

        $normalizedOrders = collect($photoOrders)
            ->mapWithKeys(fn ($sortOrder, $photoId) => [(int) $photoId => max(0, (int) $sortOrder)])
            ->sortBy(fn (int $sortOrder) => $sortOrder)
            ->keys()
            ->values();

        if ($normalizedOrders->isEmpty()) {
            return;
        }

        $sortOrder = 1;

        foreach ($normalizedOrders as $photoId) {
            $this->photos()->whereKey($photoId)->update(['sort_order' => $sortOrder]);
            $sortOrder++;
        }
    }

    protected static function uniqueSlugFor(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'property';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
