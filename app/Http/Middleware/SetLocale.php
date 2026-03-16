<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Determine locale from session or fallback to config
        $supported = array_keys((array) config('app.supported_locales', []));
        $fallback = config('app.locale');

        $locale = $request->session()->get('locale');

        if (! $locale && $request->user()) {
            // If you later add a 'locale' column on users table, you may use it here
            // $locale = $request->user()->locale;
        }

        if (! $locale) {
            $locale = $fallback;
        }

        if ($supported) {
            $locale = in_array($locale, $supported, true) ? $locale : $fallback;
        }

        App::setLocale($locale);

        return $next($request);
    }
}
