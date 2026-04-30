<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'tenant']);
    }

    public function view(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $maintenanceRequest->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'tenant']);
    }

    public function update(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        if (! $user->hasAnyRole(['super_admin', 'manager'])) {
            return false;
        }

        return $maintenanceRequest->isVisibleTo($user);
    }
}
