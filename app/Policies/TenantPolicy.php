<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $tenant->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $tenant->isVisibleTo($user) && ! $user->hasRole('tenant');
    }
}