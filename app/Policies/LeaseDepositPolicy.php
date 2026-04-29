<?php

namespace App\Policies;

use App\Models\LeaseDeposit;
use App\Models\User;

class LeaseDepositPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function view(User $user, LeaseDeposit $leaseDeposit): bool
    {
        return $leaseDeposit->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function postEntry(User $user, LeaseDeposit $leaseDeposit): bool
    {
        return $leaseDeposit->isVisibleTo($user) && ! $user->hasRole('tenant');
    }
}