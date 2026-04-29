<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function view(User $user, Property $property): bool
    {
        return $user->hasRole('super_admin') || $property->isManagedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function update(User $user, Property $property): bool
    {
        return $user->hasRole('super_admin') || $property->isManagedBy($user);
    }

    public function archive(User $user, Property $property): bool
    {
        return $user->hasRole('super_admin');
    }

    public function assignManager(User $user, Property $property): bool
    {
        return $user->hasRole('super_admin');
    }
}
