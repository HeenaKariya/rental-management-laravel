<?php

namespace App\Http\Middleware;

use App\Models\PreSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFullAuthSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->session()->get('auth.pre_session_token');
        $preSession = PreSession::activeByToken($token);

        if (! $preSession) {
            $request->session()->forget('auth.pre_session_token');

            return $next($request);
        }

        auth()->guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
