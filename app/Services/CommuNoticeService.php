<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        foreach ($distances as $distance) {
            $result = $this->queryNotices($distance, $lat, $long, $page);

            if (! $result['successful']) {
                continue;
            }

            if ($firstSuccessful === null) {
                $firstSuccessful = $result;
            }

            if ($result['notices']->isNotEmpty()) {
                return $result;
            }
        }

        return $firstSuccessful ?? [
            'successful' => false,
            'distance' => $distances[0] ?? (int) config('services.commu.distance_km'),
            'notices' => collect(),
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
        $response = Http::acceptJson()
            ->withToken((string) config('services.commu.bearer_token'))
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

        if (! $response->successful() || ! empty($response->json('errors'))) {
            Log::warning('Commu noticesWhereDistance failed', [
                'status' => $response->status(),
                'distance' => $distance,
                'errors' => $response->json('errors'),
            ]);

            return [
                'successful' => false,
                'distance' => $distance,
                'notices' => collect(),
                'paginator' => [],
            ];
        }

        $notices = collect($response->json('data.noticesWhereDistance.data', []))
            ->filter(fn ($notice) => is_array($notice))
            ->values();

        return [
            'successful' => true,
            'distance' => $distance,
            'notices' => $notices,
            'paginator' => $response->json('data.noticesWhereDistance.paginatorInfo', []),
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
