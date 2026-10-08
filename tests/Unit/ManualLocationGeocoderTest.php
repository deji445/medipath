<?php

namespace Tests\Unit;

use App\Services\Routing\ManualLocationGeocoder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManualLocationGeocoderTest extends TestCase
{
    public function test_it_resolves_a_ghana_location_without_exposing_it_in_the_url_path(): void
    {
        config()->set([
            'medipath.routing.geocoding.enabled' => true,
            'medipath.routing.geocoding.endpoint' => 'https://nominatim.openstreetmap.org/search',
            'medipath.routing.geocoding.user_agent' => 'MediPath-Test/1.0',
        ]);
        Cache::flush();
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([[
                'lat' => '5.603717',
                'lon' => '-0.186964',
            ]]),
        ]);

        $coordinates = app(ManualLocationGeocoder::class)->coordinates('Osu, Accra');

        $this->assertSame([
            'latitude' => 5.603717,
            'longitude' => -0.186964,
        ], $coordinates);
        Http::assertSent(function (Request $request): bool {
            $this->assertTrue($request->hasHeader('User-Agent', 'MediPath-Test/1.0'));
            $this->assertSame('Osu, Accra, Ghana', $request['q']);
            $this->assertSame('gh', $request['countrycodes']);

            return str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/search?');
        });
    }
}
