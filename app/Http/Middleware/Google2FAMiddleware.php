<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Google2FAMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip 2FA for non-authenticated users
        if (!$user) {
            return $next($request);
        }

        // Skip 2FA for users who haven't enabled it
        if (!$user->google2fa_enabled) {
            return $next($request);
        }

        // Check if user is already verified in this session
        if ($request->session()->get('google2fa_verified', false)) {
            return $next($request);
        }

        // Redirect to 2FA verification page
        return redirect()->route('2fa.verify');
    }
}
