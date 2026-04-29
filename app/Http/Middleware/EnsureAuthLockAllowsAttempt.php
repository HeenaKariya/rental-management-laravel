<?php

namespace App\Http\Middleware;

use App\Models\AuthAuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthLockAllowsAttempt
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveTargetUser($request);

        if (! $user instanceof User) {
            return $next($request);
        }

        $user->clearExpiredSoftLock();

        if (! $user->isAuthLocked()) {
            return $next($request);
        }

        AuthAuditLog::record($user, 'auth.lock.blocked', [
            'state' => $user->authLockStatus(),
            'surface' => $this->surface($request),
        ]);

        if ($request->routeIs('two-factor.login', 'two-factor.login.store')) {
            $request->session()->forget([
                'auth.pre_session_token',
                'login.id',
                'login.remember',
            ]);

            return redirect()->route('login')->withErrors([
                'email' => $user->activeAuthLockMessage(),
            ]);
        }

        throw ValidationException::withMessages([
            Fortify::username() => [$user->activeAuthLockMessage()],
        ])->status(Response::HTTP_LOCKED);
    }

    protected function resolveTargetUser(Request $request): ?User
    {
        if ($request->routeIs('login.store')) {
            $username = $request->input(Fortify::username());

            return $username
                ? User::query()->where(Fortify::username(), $username)->first()
                : null;
        }

        if ($request->routeIs('two-factor.login', 'two-factor.login.store')) {
            $userId = $request->session()->get('login.id');

            return $userId ? User::query()->find($userId) : null;
        }

        return null;
    }

    protected function surface(Request $request): string
    {
        return $request->routeIs('two-factor.login', 'two-factor.login.store')
            ? 'two_factor'
            : 'login';
    }
}
