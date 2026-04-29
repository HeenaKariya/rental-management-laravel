<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuthAuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

class SecuritySettingsController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $twoFactorPendingConfirmation = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;
        $twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();

        return view('settings.security', [
            'auditLogs' => AuthAuditLog::query()
                ->where('user_id', $user->id)
                ->latest('occurred_at')
                ->limit(12)
                ->get(),
            'recoveryCodes' => ($twoFactorEnabled || $twoFactorPendingConfirmation) && $user->two_factor_recovery_codes
                ? $user->recoveryCodes()
                : [],
            'twoFactorEnabled' => $twoFactorEnabled,
            'twoFactorPendingConfirmation' => $twoFactorPendingConfirmation,
            'twoFactorQrCodeSvg' => $twoFactorPendingConfirmation ? $user->twoFactorQrCodeSvg() : null,
            'user' => $user,
        ]);
    }

    public function enableTwoFactor(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $enable($request->user(), $request->boolean('force', false));

        return to_route('settings.security')->with('status', 'Two-factor setup started. Scan the QR code and confirm with a one-time code.');
    }

    public function confirmTwoFactor(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $confirm($request->user(), (string) $request->string('code'));

        return to_route('settings.security')->with('status', 'Two-factor authentication is now confirmed.');
    }

    public function disableTwoFactor(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $disable($request->user());

        return to_route('settings.security')->with('status', 'Two-factor authentication has been disabled.');
    }

    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $generate($request->user());
        AuthAuditLog::record($request->user(), 'two_factor.recovery_codes_regenerated');

        return to_route('settings.security')->with('status', 'A fresh set of recovery codes has been generated.');
    }
}
