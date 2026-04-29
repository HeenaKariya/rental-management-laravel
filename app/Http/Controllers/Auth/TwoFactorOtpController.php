<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Services\TwoFactorOtpBroker;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TwoFactorOtpController extends Controller
{
    public function resend(Request $request, TwoFactorOtpBroker $broker): RedirectResponse
    {
        $userId = $request->session()->get('login.id');
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user instanceof User) {
            return to_route('login');
        }

        $otpChallenge = $broker->dispatch(
            $user,
            TwoFactorOtpBroker::PURPOSE_LOGIN_CHALLENGE,
            true,
            $request->string('channel')->toString() ?: null,
        );

        return back()->with('otpStatus', 'A new '.$otpChallenge['channelLabel'].' was sent.');
    }
}
