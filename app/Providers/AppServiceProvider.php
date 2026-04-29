<?php

namespace App\Providers;

use App\Models\AuthAuditLog;
use App\Models\PreSession;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if (! app()->bound('request')) {
                return;
            }

            $request = request();
            $token = $request->session()->pull('auth.pre_session_token');

            PreSession::completeByToken($token);

            if ($token) {
                AuthAuditLog::record($event->user, 'two_factor.passed', [
                    'method' => $request->filled('recovery_code') ? 'recovery_code' : 'authenticator_code',
                ]);
            }
        });

        Event::listen(Logout::class, function (): void {
            if (! app()->bound('request')) {
                return;
            }

            $request = request();
            $token = $request->session()->pull('auth.pre_session_token');

            PreSession::completeByToken($token);
        });

        Event::listen(TwoFactorAuthenticationChallenged::class, function (TwoFactorAuthenticationChallenged $event): void {
            AuthAuditLog::record($event->user, 'two_factor.challenged');
        });

        Event::listen(TwoFactorAuthenticationEnabled::class, function (TwoFactorAuthenticationEnabled $event): void {
            AuthAuditLog::record($event->user, 'two_factor.enabled');
        });

        Event::listen(TwoFactorAuthenticationConfirmed::class, function (TwoFactorAuthenticationConfirmed $event): void {
            AuthAuditLog::record($event->user, 'two_factor.confirmed');
        });

        Event::listen(TwoFactorAuthenticationDisabled::class, function (TwoFactorAuthenticationDisabled $event): void {
            AuthAuditLog::record($event->user, 'two_factor.disabled');
        });

        Event::listen(RecoveryCodeReplaced::class, function (RecoveryCodeReplaced $event): void {
            AuthAuditLog::record($event->user, 'two_factor.recovery_code_used', [
                'code_suffix' => substr($event->code, -4),
            ]);
        });

        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        Gate::define('access-super-admin-panel', fn (User $user) => $user->hasRole('super_admin'));
        Gate::define('access-manager-panel', fn (User $user) => $user->hasRole('manager'));
        Gate::define('access-owner-portal', fn (User $user) => $user->hasRole('owner'));
        Gate::define('access-tenant-portal', fn (User $user) => $user->hasRole('tenant'));
    }
}
