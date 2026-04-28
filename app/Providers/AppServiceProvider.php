<?php

namespace App\Providers;

use App\Models\PreSession;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Event::listen(Login::class, function (): void {
            if (! app()->bound('request')) {
                return;
            }

            $request = request();
            $token = $request->session()->pull('auth.pre_session_token');

            PreSession::completeByToken($token);
        });

        Event::listen(Logout::class, function (): void {
            if (! app()->bound('request')) {
                return;
            }

            $request = request();
            $token = $request->session()->pull('auth.pre_session_token');

            PreSession::completeByToken($token);
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
