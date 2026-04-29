<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthAuditLog;
use App\Models\User;
use Illuminate\View\View;

class TwoFactorOversightController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['roles', 'latestAuthAuditLog'])
            ->orderBy('name')
            ->get();

        return view('admin.security.two-factor.index', [
            'auditLogs' => AuthAuditLog::query()
                ->with('user.roles')
                ->latest('occurred_at')
                ->limit(16)
                ->get(),
            'summary' => [
                'confirmed' => $users->filter(fn (User $user) => $user->hasEnabledTwoFactorAuthentication())->count(),
                'hardLocked' => $users->filter(fn (User $user) => $user->isHardLocked())->count(),
                'softLocked' => $users->filter(fn (User $user) => $user->isSoftLocked())->count(),
                'pending' => $users->filter(fn (User $user) => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null)->count(),
                'notEnabled' => $users->filter(fn (User $user) => $user->two_factor_secret === null)->count(),
            ],
            'users' => $users,
        ]);
    }
}
