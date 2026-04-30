<?php

namespace App\Providers;

use App\Domain\Auth\Contracts\WhatsappOtpGateway;
use App\Domain\Auth\Services\ConfiguredWhatsappOtpGateway;
use App\Domain\Auth\Services\HybridTwoFactorAuthenticationProvider;
use App\Domain\Auth\Services\TwoFactorOtpBroker;
use App\Domain\Notifications\Contracts\WhatsappNotificationGateway;
use App\Domain\Notifications\Services\LogWhatsappNotificationGateway;
use App\Domain\Notifications\Services\WpsmsWhatsappNotificationGateway;
use App\Http\Responses\Auth\FailedTwoFactorLoginResponse;
use App\Models\AuthAuditLog;
use App\Models\PreSession;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse as FailedTwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider as TwoFactorAuthenticationProviderContract;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsappOtpGateway::class, ConfiguredWhatsappOtpGateway::class);
        $this->app->singleton(WhatsappNotificationGateway::class, function () {
            $driver = (string) config('notifications.whatsapp.driver', 'log');

            return match ($driver) {
                'wpsms' => app(WpsmsWhatsappNotificationGateway::class),
                default => app(LogWhatsappNotificationGateway::class),
            };
        });
        $this->app->singleton(TwoFactorAuthenticationProviderContract::class, function ($app) {
            return new HybridTwoFactorAuthenticationProvider(
                $app->make(Google2FA::class),
                $app->make(Repository::class),
                $app->make(TwoFactorOtpBroker::class),
            );
        });
        $this->app->singleton(FailedTwoFactorLoginResponseContract::class, FailedTwoFactorLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Failed::class, function (Failed $event): void {
            $user = $event->user;

            if (! $user instanceof User && app()->bound('request')) {
                $username = request()->input(Fortify::username());
                $user = User::query()->where(Fortify::username(), $username)->first();
            }

            if (! $user instanceof User) {
                return;
            }

            $triggeredEvent = $user->recordPrimaryAuthenticationFailure();

            AuthAuditLog::record($user, 'auth.login_failed');

            if ($triggeredEvent) {
                AuthAuditLog::record($user, $triggeredEvent, [
                    'minutes' => User::SOFT_LOCK_MINUTES,
                    'surface' => 'login',
                ]);
            }
        });

        Event::listen(Login::class, function (Login $event): void {
            if (! app()->bound('request')) {
                return;
            }

            if (! $event->user instanceof User) {
                return;
            }

            $request = request();
            $token = $request->session()->pull('auth.pre_session_token');

            PreSession::completeByToken($token);
            $event->user->clearPrimaryAuthenticationFailures();

            if ($token) {
                $event->user->clearTwoFactorFailures();
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
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->clearPrimaryAuthenticationFailures();
            AuthAuditLog::record($event->user, 'two_factor.challenged');
        });

        Event::listen(TwoFactorAuthenticationFailed::class, function (TwoFactorAuthenticationFailed $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $triggeredEvent = $event->user->recordTwoFactorFailure();

            AuthAuditLog::record($event->user, 'auth.two_factor_failed', [
                'method' => request()->filled('recovery_code') ? 'recovery_code' : 'authenticator_code',
            ]);

            if ($triggeredEvent) {
                AuthAuditLog::record($event->user, $triggeredEvent, [
                    'minutes' => User::SOFT_LOCK_MINUTES,
                    'surface' => 'two_factor',
                ]);
            }
        });

        Event::listen(ValidTwoFactorAuthenticationCodeProvided::class, function (ValidTwoFactorAuthenticationCodeProvided $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->clearTwoFactorFailures();
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
