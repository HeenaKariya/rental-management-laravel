<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;

class LeasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function view(User $user, Lease $lease): bool
    {
        return $lease->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function update(User $user, Lease $lease): bool
    {
        return $lease->isVisibleTo($user) && ! $user->hasRole('tenant');
    }

    public function renew(User $user, Lease $lease): bool
    {
        return $lease->isVisibleTo($user) && ! $user->hasRole('tenant');
    }
}