<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider as TwoFactorAuthenticationProviderContract;
use Laravel\Fortify\TwoFactorAuthenticationProvider as TotpProvider;
use PragmaRX\Google2FA\Google2FA;

class HybridTwoFactorAuthenticationProvider implements TwoFactorAuthenticationProviderContract
{
    protected TotpProvider $totpProvider;

    public function __construct(
        Google2FA $google2FA,
        Repository $cache,
        protected readonly TwoFactorOtpBroker $otpBroker,
    ) {
        $this->totpProvider = new TotpProvider($google2FA, $cache);
    }

    public function generateSecretKey($length = 16)
    {
        $user = $this->resolveCurrentUser();

        if ($user?->usesDeliveredOtpTwoFactor()) {
            return 'otp:'.Str::random((int) $length);
        }

        return $this->totpProvider->generateSecretKey($length);
    }

    public function qrCodeUrl($companyName, $companyEmail, $secret)
    {
        if (str_starts_with((string) $secret, 'otp:')) {
            return '';
        }

        return $this->totpProvider->qrCodeUrl($companyName, $companyEmail, $secret);
    }

    public function verify($secret, $code)
    {
        $user = $this->resolveCurrentUser();

        if ($user?->usesDeliveredOtpTwoFactor()) {
            return $this->otpBroker->verify($user, (string) $code, $this->resolvePurpose());
        }

        return $this->totpProvider->verify($secret, $code);
    }

    protected function resolveCurrentUser(): ?User
    {
        $authUser = Auth::user();

        if ($authUser instanceof User) {
            return $authUser;
        }

        if (! app()->bound('request')) {
            return null;
        }

        $userId = request()->session()->get('login.id');

        return $userId ? User::query()->find($userId) : null;
    }

    protected function resolvePurpose(): string
    {
        return Auth::check()
            ? TwoFactorOtpBroker::PURPOSE_SETUP_CONFIRMATION
            : TwoFactorOtpBroker::PURPOSE_LOGIN_CHALLENGE;
    }
}
