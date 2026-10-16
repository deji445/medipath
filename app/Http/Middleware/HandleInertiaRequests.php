<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $supportedLocales = config('localization.supported', []);
        $locales = [];

        if (is_array($supportedLocales)) {
            foreach ($supportedLocales as $code => $details) {
                if (! is_string($code) || ! is_array($details)) {
                    continue;
                }

                $locales[] = [
                    'code' => $code,
                    'label' => is_string($details['label'] ?? null)
                        ? $details['label']
                        : $code,
                    'nativeLabel' => is_string($details['native_label'] ?? null)
                        ? $details['native_label']
                        : $code,
                ];
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'localization' => [
                'current' => App::currentLocale(),
                'locales' => $locales,
                'translations' => [
                    'language' => __('common.language'),
                    'english' => __('common.english'),
                    'twi' => __('common.twi'),
                ],
            ],
        ];
    }
}
