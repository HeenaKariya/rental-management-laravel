<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\EnsureUserIsNotLocked;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\Invitation;
use App\Models\PreSession;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        Fortify::loginView(fn () => View::make('auth.login'));
        Fortify::registerView(function (Request $request) {
            return View::make('auth.register', [
                'invitation' => Invitation::validToken($request->query('invite')),
                'invitationToken' => $request->query('invite'),
            ]);
        });
        Fortify::requestPasswordResetLinkView(fn () => View::make('auth.forgot-password'));
        Fortify::resetPasswordView(fn ($request) => View::make('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => View::make('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => View::make('auth.confirm-password'));
        Fortify::twoFactorChallengeView(function (Request $request) {
            $preSession = null;
            $userId = $request->session()->get('login.id');

            if ($userId) {
                $preSession = PreSession::issueForUser((int) $userId);
                $request->session()->put('auth.pre_session_token', $preSession->token);
            }

            return View::make('auth.two-factor-challenge', [
                'preSession' => $preSession,
            ]);
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
        Fortify::authenticateThrough(fn () => array_filter([
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            EnsureUserIsNotLocked::class,
            RedirectIfTwoFactorAuthenticatable::class,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
