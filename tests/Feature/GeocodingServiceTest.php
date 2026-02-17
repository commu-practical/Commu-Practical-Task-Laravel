<?php

namespace Tests\Feature;

use App\Services\GeocodingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    public function test_geocodes_finnish_town_from_api_response(): void
    {
        Http::fake([
            '*' => Http::response([
                [
                    'lat' => '60.1666204',
                    'lon' => '24.9435408',
                    'display_name' => 'Helsinki, Finland',
                ],
            ], 200),
        ]);

        Config::set('services.geocoding.endpoint', 'https://nominatim.openstreetmap.org/search');
        Config::set('services.geocoding.fallback_endpoint', 'https://geocoding-api.open-meteo.com/v1/search');
        Config::set('services.geocoding.country_codes', 'fi');
        Config::set('services.geocoding.user_agent', 'commu-test/1.0');

        $result = app(GeocodingService::class)->geocodeTown('Helsinki');

        $this->assertNotNull($result);
        $this->assertSame('Helsinki, Finland', $result['name']);
        $this->assertEquals(60.1666204, $result['lat']);
        $this->assertEquals(24.9435408, $result['long']);
    }

    public function test_retries_without_country_filter_when_first_request_fails(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([], 500)
                ->push([
                    [
                        'lat' => '59.3293',
                        'lon' => '18.0686',
                        'display_name' => 'Stockholm, Sweden',
                    ],
                ], 200),
        ]);

        Config::set('services.geocoding.endpoint', 'https://nominatim.openstreetmap.org/search');
        Config::set('services.geocoding.fallback_endpoint', 'https://geocoding-api.open-meteo.com/v1/search');
        Config::set('services.geocoding.country_codes', 'fi');
        Config::set('services.geocoding.user_agent', 'commu-test/1.0');

        $result = app(GeocodingService::class)->geocodeTown('Stockholm');

        $this->assertNotNull($result);
        $this->assertSame('Stockholm, Sweden', $result['name']);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'countrycodes=fi'));
        Http::assertSent(fn (Request $request) =>
            str_contains($request->url(), 'q=Stockholm')
            && ! str_contains($request->url(), 'countrycodes=fi')
        );
    }

    #[DataProvider('fallbackFinnishTownProvider')]
    public function test_uses_fallback_coordinates_for_required_finnish_towns_when_api_fails(
        string $town,
        float $expectedLat,
        float $expectedLong
    ): void {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        Config::set('services.geocoding.endpoint', 'https://nominatim.openstreetmap.org/search');
        Config::set('services.geocoding.fallback_endpoint', 'https://geocoding-api.open-meteo.com/v1/search');

        $result = app(GeocodingService::class)->geocodeTown($town);

        $this->assertNotNull($result);
        $this->assertSame($expectedLat, $result['lat']);
        $this->assertSame($expectedLong, $result['long']);
        $this->assertStringContainsString('fallback coordinates', $result['name']);
    }

    public function test_returns_null_for_unknown_external_town_when_api_fails(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        Config::set('services.geocoding.endpoint', 'https://nominatim.openstreetmap.org/search');
        Config::set('services.geocoding.fallback_endpoint', 'https://geocoding-api.open-meteo.com/v1/search');

        $result = app(GeocodingService::class)->geocodeTown('NowhereCityZZZ');

        $this->assertNull($result);
    }

    public function test_can_geocode_external_town_when_api_has_result(): void
    {
        Http::fake([
            '*' => Http::response([
                [
                    'lat' => '52.5200',
                    'lon' => '13.4050',
                    'display_name' => 'Berlin, Germany',
                ],
            ], 200),
        ]);

        Config::set('services.geocoding.endpoint', 'https://nominatim.openstreetmap.org/search');
        Config::set('services.geocoding.fallback_endpoint', 'https://geocoding-api.open-meteo.com/v1/search');

        $result = app(GeocodingService::class)->geocodeTown('Berlin');

        $this->assertNotNull($result);
        $this->assertSame('Berlin, Germany', $result['name']);
        $this->assertEquals(52.52, $result['lat']);
        $this->assertEquals(13.405, $result['long']);
    }

    public function test_uses_open_meteo_when_nominatim_is_blocked_or_fails(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/*' => Http::response([], 403),
            'https://geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [
                    [
                        'name' => 'Oulu',
                        'country' => 'Finland',
                        'latitude' => 65.0121,
                        'longitude' => 25.4651,
                    ],
                ],
            ], 200),
        ]);

        Config::set('services.geocoding.endpoint', 'https://nominatim.openstreetmap.org/search');
        Config::set('services.geocoding.fallback_endpoint', 'https://geocoding-api.open-meteo.com/v1/search');
        Config::set('services.geocoding.country_codes', 'fi');

        $result = app(GeocodingService::class)->geocodeTown('Oulu');

        $this->assertNotNull($result);
        $this->assertSame('Oulu, Finland', $result['name']);
        $this->assertEquals(65.0121, $result['lat']);
        $this->assertEquals(25.4651, $result['long']);
    }

    public static function fallbackFinnishTownProvider(): array
    {
        return [
            'Helsinki fallback' => ['Helsinki', 60.1699, 24.9384],
            'Vantaa fallback' => ['Vantaa', 60.2934, 25.0378],
            'Tampere fallback' => ['Tampere', 61.4978, 23.7610],
            'Turku fallback' => ['Turku', 60.4518, 22.2666],
        ];
    }
}
