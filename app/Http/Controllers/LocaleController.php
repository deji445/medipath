<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => [
                'required',
                'string',
                Rule::in(array_keys(config('localization.supported', []))),
            ],
        ]);
        $locale = (string) $validated['locale'];

        $request->session()->put('locale', $locale);

        return back()->withCookie(cookie(
            'locale',
            $locale,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));
    }
}
