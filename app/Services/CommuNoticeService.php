<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommuNoticeService
{
    public function searchNearbyNotices(float $lat, float $long, int $page = 1, ?int $preferredDistance = null): array
    {
        $distances = $preferredDistance
            ? [$preferredDistance]
            : array_values(array_unique([
                (int) config('services.commu.distance_km'),
                50,
                100,
                200,
            ]));

        $firstSuccessful = null;
        $lastFailure = null;

        foreach ($distances as $distance) {
            $result = $this->queryNotices($distance, $lat, $long, $page);

            if (! $result['successful']) {
                $lastFailure = $result;
                continue;
            }

            if ($firstSuccessful === null) {
                $firstSuccessful = $result;
            }

            if ($result['notices']->isNotEmpty()) {
                return $result;
            }
        }

        if ($lastFailure !== null) {
            return [
                'successful' => false,
                'distance' => $lastFailure['distance'] ?? ($distances[0] ?? (int) config('services.commu.distance_km')),
                'notices' => collect(),
                'error_code' => $lastFailure['error_code'] ?? 'upstream_error',
                'error_message' => $lastFailure['error_message'] ?? 'Unable to fetch notices from Commu API.',
                'paginator' => [
                    'count' => 0,
                    'total' => 0,
                    'currentPage' => $page,
                    'lastPage' => 1,
                    'perPage' => (int) config('services.commu.page_size'),
                    'hasMorePages' => false,
                ],
            ];
        }

        return $firstSuccessful ?? [
            'successful' => false,
            'distance' => $distances[0] ?? (int) config('services.commu.distance_km'),
            'notices' => collect(),
            'error_code' => 'upstream_error',
            'error_message' => 'Unable to fetch notices from Commu API.',
            'paginator' => [
                'count' => 0,
                'total' => 0,
                'currentPage' => $page,
                'lastPage' => 1,
                'perPage' => (int) config('services.commu.page_size'),
                'hasMorePages' => false,
            ],
        ];
    }

    private function queryNotices(int $distance, float $lat, float $long, int $page): array
    {
        $cacheTtl = max(0, (int) config('services.commu.cache_ttl_seconds', 180));
        $cacheKey = $this->buildCacheKey($distance, $lat, $long, $page);

        if ($cacheTtl > 0 && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $hitResult = $this->hydrateCachedResult($cached);

            Log::info('Commu query telemetry', [
                'cache' => 'hit',
                'distance' => $distance,
                'page' => $page,
                'lat' => round($lat, 4),
                'long' => round($long, 4),
                'total' => $hitResult['paginator']['total'] ?? null,
            ]);

            return $hitResult;
        }

        $start = microtime(true);

        try {
            $response = Http::acceptJson()
                ->withToken((string) config('services.commu.bearer_token'))
                ->retry(
                    max(1, (int) config('services.commu.retry_attempts', 2)),
                    max(0, (int) config('services.commu.retry_sleep_ms', 200)),
                    null,
                    false
                )
                ->timeout(15)
                ->post(config('services.commu.endpoint'), [
                    'query' => <<<'GRAPHQL'
query NearbyNotices($distance: Int!, $lat: Float!, $long: Float!, $first: Int!, $page: Int!) {
  noticesWhereDistance(distance: $distance, lat: $lat, long: $long, first: $first, page: $page) {
    paginatorInfo {
      count
      total
      currentPage
      lastPage
      perPage
      hasMorePages
    }
    data {
      id
      title
      description
      type
      side
      created_at
      expires_at
      position {
        latitude
        longitude
      }
      categories {
        main {
          key
        }
        sub {
          key
        }
      }
    }
  }
}
GRAPHQL,
                    'variables' => [
                        'distance' => $distance,
                        'lat' => $lat,
                        'long' => $long,
                        'first' => (int) config('services.commu.page_size'),
                        'page' => $page,
                    ],
                ]);
        } catch (Throwable $exception) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            Log::warning('Commu noticesWhereDistance request exception', [
                'distance' => $distance,
                'page' => $page,
                'latency_ms' => $latencyMs,
                'message' => $exception->getMessage(),
            ]);

            return [
                'successful' => false,
                'distance' => $distance,
                'notices' => collect(),
                'error_code' => 'network_error',
                'error_message' => 'Network error while contacting Commu API.',
                'paginator' => [],
            ];
        }

        if (! $response->successful() || ! empty($response->json('errors'))) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);
            $status = $response->status();
            $errorMessage = $this->extractGraphQlErrorMessage($response->json('errors', []));
            $errorCode = in_array($status, [401, 403], true) || str_contains(mb_strtolower($errorMessage), 'unauth')
                ? 'auth_error'
                : 'upstream_error';

            Log::warning('Commu noticesWhereDistance failed', [
                'status' => $status,
                'distance' => $distance,
                'page' => $page,
                'latency_ms' => $latencyMs,
                'errors' => $response->json('errors'),
            ]);

            return [
                'successful' => false,
                'distance' => $distance,
                'notices' => collect(),
                'error_code' => $errorCode,
                'error_message' => $errorMessage ?: 'Commu API returned an unsuccessful response.',
                'paginator' => [],
            ];
        }

        $notices = collect($response->json('data.noticesWhereDistance.data', []))
            ->filter(fn ($notice) => is_array($notice))
            ->values();

        $result = [
            'successful' => true,
            'distance' => $distance,
            'notices' => $notices,
            'error_code' => null,
            'error_message' => null,
            'paginator' => $response->json('data.noticesWhereDistance.paginatorInfo', []),
        ];

        $latencyMs = (int) round((microtime(true) - $start) * 1000);
        Log::info('Commu query telemetry', [
            'cache' => 'miss',
            'distance' => $distance,
            'page' => $page,
            'lat' => round($lat, 4),
            'long' => round($long, 4),
            'latency_ms' => $latencyMs,
            'total' => $result['paginator']['total'] ?? null,
        ]);

        if ($cacheTtl > 0) {
            Cache::put($cacheKey, $this->dehydrateResult($result), now()->addSeconds($cacheTtl));
        }

        return $result;
    }

    private function extractGraphQlErrorMessage(array $errors): string
    {
        return collect($errors)
            ->pluck('message')
            ->filter()
            ->implode(' | ');
    }

    private function buildCacheKey(int $distance, float $lat, float $long, int $page): string
    {
        return sprintf(
            'commu_notices:%s:%s:%d:%d:%d',
            number_format($lat, 4, '.', ''),
            number_format($long, 4, '.', ''),
            $distance,
            (int) config('services.commu.page_size'),
            $page
        );
    }

    private function dehydrateResult(array $result): array
    {
        return [
            ...$result,
            'notices' => $result['notices'] instanceof Collection
                ? $result['notices']->all()
                : (array) $result['notices'],
        ];
    }

    private function hydrateCachedResult(array $result): array
    {
        return [
            ...$result,
            'notices' => collect($result['notices'] ?? [])->filter(fn ($item) => is_array($item))->values(),
        ];
    }

    public function recentNotices(Collection $notices): Collection
    {
        $threshold = Carbon::now()->subDays((int) config('services.commu.recent_days'));

        return $notices
            ->filter(function (array $notice) use ($threshold) {
                $createdAt = Arr::get($notice, 'created_at');

                if (! $createdAt) {
                    return false;
                }

                return Carbon::parse($createdAt)->greaterThanOrEqualTo($threshold);
            })
            ->values();
    }
}
