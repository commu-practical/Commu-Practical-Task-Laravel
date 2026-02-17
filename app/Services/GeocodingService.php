<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeocodingService
{
    public function geocodeTown(string $town): ?array
    {
        $endpoint = config('services.geocoding.endpoint');
        $fallbackEndpoint = config('services.geocoding.fallback_endpoint');
        $town = trim($town);

        if (! $endpoint && ! $fallbackEndpoint) {
            return $this->fallbackTownCoordinates($town);
        }

        if ($endpoint) {
            $response = $this->requestGeocode($endpoint, [
                'q' => $town,
                'format' => 'jsonv2',
                'limit' => 1,
                'countrycodes' => config('services.geocoding.country_codes'),
            ]);

            if ($response && $response->successful()) {
                $result = $response->json('0');

                if (is_array($result) && isset($result['lat'], $result['lon'])) {
                    return [
                        'name' => $result['display_name'] ?? $town,
                        'lat' => (float) $result['lat'],
                        'long' => (float) $result['lon'],
                    ];
                }
            }

            // Retry once without country limiter for broader matching.
            $response = $this->requestGeocode($endpoint, [
                'q' => $town,
                'format' => 'jsonv2',
                'limit' => 1,
            ]);

            if ($response && $response->successful()) {
                $result = $response->json('0');

                if (is_array($result) && isset($result['lat'], $result['lon'])) {
                    return [
                        'name' => $result['display_name'] ?? $town,
                        'lat' => (float) $result['lat'],
                        'long' => (float) $result['lon'],
                    ];
                }
            }

            Log::warning('Primary geocoding provider could not resolve town', [
                'town' => $town,
            ]);
        }

        if ($fallbackEndpoint) {
            $result = $this->requestOpenMeteo($fallbackEndpoint, $town, (string) config('services.geocoding.country_codes'));

            if ($result) {
                return $result;
            }

            $result = $this->requestOpenMeteo($fallbackEndpoint, $town, null);

            if ($result) {
                return $result;
            }
        }

        return $this->fallbackTownCoordinates($town);
    }

    private function fallbackTownCoordinates(string $town): ?array
    {
        $lookup = [
            'helsinki' => ['lat' => 60.1699, 'long' => 24.9384],
            'vantaa' => ['lat' => 60.2934, 'long' => 25.0378],
            'tampere' => ['lat' => 61.4978, 'long' => 23.7610],
            'turku' => ['lat' => 60.4518, 'long' => 22.2666],
        ];

        $normalized = mb_strtolower(trim($town));

        if (! isset($lookup[$normalized])) {
            return null;
        }

        return [
            'name' => ucfirst($normalized).', Finland (fallback coordinates)',
            'lat' => $lookup[$normalized]['lat'],
            'long' => $lookup[$normalized]['long'],
        ];
    }

    private function requestGeocode(string $endpoint, array $query): ?Response
    {
        try {
            return Http::acceptJson()
                ->withHeaders([
                    'User-Agent' => config('services.geocoding.user_agent'),
                ])
                ->timeout(10)
                ->get($endpoint, $query);
        } catch (Throwable $exception) {
            Log::warning('Geocoding network exception', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function requestOpenMeteo(string $endpoint, string $town, ?string $countryCode): ?array
    {
        $query = [
            'name' => $town,
            'count' => 1,
            'language' => 'en',
            'format' => 'json',
        ];

        if ($countryCode) {
            $query['countryCode'] = strtoupper($countryCode);
        }

        $response = $this->requestGeocode($endpoint, $query);

        if (! $response || ! $response->successful()) {
            return null;
        }

        $result = $response->json('results.0');

        if (! is_array($result) || ! isset($result['latitude'], $result['longitude'])) {
            return null;
        }

        $country = $result['country'] ?? null;

        return [
            'name' => $country
                ? sprintf('%s, %s', $result['name'] ?? $town, $country)
                : ($result['name'] ?? $town),
            'lat' => (float) $result['latitude'],
            'long' => (float) $result['longitude'],
        ];
    }
}
