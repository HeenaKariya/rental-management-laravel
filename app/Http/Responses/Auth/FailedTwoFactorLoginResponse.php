<?php

namespace App\Http\Responses\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse as FailedTwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class FailedTwoFactorLoginResponse implements FailedTwoFactorLoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     *
     * @throws ValidationException
     */
    public function toResponse($request)
    {
        if (! $request->hasChallengedUser()) {
            return redirect()->route('login');
        }

        $user = $request->challengedUser();

        if ($user instanceof User) {
            $user->clearExpiredSoftLock();
        }

        if ($user instanceof User && $user->isAuthLocked()) {
            $message = $user->activeAuthLockMessage();

            if ($request->wantsJson()) {
                throw ValidationException::withMessages([
                    'code' => [$message],
                ]);
            }

            return redirect()->route('login')->withErrors([
                'email' => $message,
            ]);
        }

        [$key, $message] = $request->filled('recovery_code')
            ? ['recovery_code', __('The provided two factor recovery code was invalid.')]
            : ['code', __('The provided two factor authentication code was invalid.')];

        if ($request->wantsJson()) {
            throw ValidationException::withMessages([
                $key => [$message],
            ]);
        }

        return redirect()->route('two-factor.login')->withErrors([$key => $message]);
    }
}
