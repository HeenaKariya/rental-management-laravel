<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class EnsureUserIsNotLocked
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @param  callable  $next
     * @return mixed
     *
     * @throws ValidationException
     */
    public function handle($request, $next)
    {
        $username = $request->input(Fortify::username());

        if (! $username) {
            return $next($request);
        }

        $user = User::query()->where(Fortify::username(), $username)->first();

        if (! $user instanceof User) {
            return $next($request);
        }

        $user->clearExpiredSoftLock();

        if (! $user->isAuthLocked()) {
            return $next($request);
        }

        throw ValidationException::withMessages([
            Fortify::username() => [$user->activeAuthLockMessage()],
        ]);
    }
}
