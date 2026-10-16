<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('localization.supported', []);
        $requestedLocale = $request->session()->get(
            'locale',
            $request->cookie('locale', config('localization.default', 'en')),
        );
        $locale = is_string($requestedLocale) && array_key_exists($requestedLocale, $supported)
            ? $requestedLocale
            : (string) config('localization.default', 'en');

        App::setLocale($locale);

        return $next($request);
    }
}
