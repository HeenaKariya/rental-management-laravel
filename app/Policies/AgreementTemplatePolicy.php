<?php

namespace App\Policies;

use App\Models\AgreementTemplate;
use App\Models\User;

class AgreementTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function update(User $user, AgreementTemplate $template): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function delete(User $user, AgreementTemplate $template): bool
    {
        return $user->hasRole('super_admin');
    }
}