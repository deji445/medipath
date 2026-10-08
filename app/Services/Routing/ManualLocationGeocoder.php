<?php

namespace App\Services\Routing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ManualLocationGeocoder
{
    /** @return array{latitude: float, longitude: float}|null */
    public function coordinates(?string $location): ?array
    {
        $location = trim((string) $location);

        if ($location === '' || ! config('medipath.routing.geocoding.enabled')) {
            return null;
        }

        $cacheKey = 'medipath:geocode:'.hash('sha256', mb_strtolower($location));

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($location): ?array {
            try {
                $response = Http::acceptJson()
                    ->withHeaders([
                        'User-Agent' => (string) config('medipath.routing.geocoding.user_agent'),
                    ])
                    ->connectTimeout(2)
                    ->timeout(3)
                    ->get((string) config('medipath.routing.geocoding.endpoint'), [
                        'q' => $location.', Ghana',
                        'format' => 'jsonv2',
                        'limit' => 1,
                        'countrycodes' => 'gh',
                    ]);
            } catch (ConnectionException) {
                return null;
            } catch (Throwable $exception) {
                report($exception);

                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            $match = $response->json('0');

            if (! is_array($match) || ! is_numeric($match['lat'] ?? null) || ! is_numeric($match['lon'] ?? null)) {
                return null;
            }

            $latitude = (float) $match['lat'];
            $longitude = (float) $match['lon'];

            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                return null;
            }

            return compact('latitude', 'longitude');
        });
    }
}
