<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController
{
    /**
     * Update the application locale for the current user/session.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $supported = array_keys((array) config('app.supported_locales', []));

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $supported)],
        ]);

        $locale = $validated['locale'];

        // Persist to session
        $request->session()->put('locale', $locale);

        // Optional: Persist to user profile if column exists in your DB (commented out to avoid errors)
        // if ($request->user() && Schema::hasColumn('users', 'locale')) {
        //     $request->user()->forceFill(['locale' => $locale])->save();
        // }

        return back();
    }
}
