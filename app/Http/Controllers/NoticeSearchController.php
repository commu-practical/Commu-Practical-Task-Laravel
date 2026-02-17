<?php

namespace App\Http\Controllers;

use App\Services\AreaSummaryService;
use App\Services\CommuNoticeService;
use App\Services\GeocodingService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class NoticeSearchController extends Controller
{
    public function index()
    {
        return view('notices.index');
    }

    public function search(
        Request $request,
        GeocodingService $geocodingService,
        CommuNoticeService $noticeService,
        AreaSummaryService $summaryService
    ) {
        $town = trim((string) $request->input('town', ''));

        if ($town === '') {
            if ($request->isMethod('post')) {
                return back()
                    ->withInput()
                    ->withErrors(['town' => 'Please enter a town.']);
            }

            return redirect()
                ->route('notices.index')
                ->withErrors(['town' => 'Please enter a town.']);
        }

        $page = max(1, (int) $request->integer('page', 1));
        $distance = $request->filled('distance') ? max(1, (int) $request->integer('distance')) : null;

        $location = $geocodingService->geocodeTown($town);

        if (! $location) {
            return redirect()
                ->route('notices.index')
                ->with('error', 'No geocoding result found for that town.');
        }

        $searchResult = $noticeService->searchNearbyNotices(
            $location['lat'],
            $location['long'],
            $page,
            $distance
        );

        $allNotices = $searchResult['notices'];
        $usedDistance = (int) $searchResult['distance'];
        $paginatorInfo = $searchResult['paginator'];

        if (! $searchResult['successful']) {
            $message = match ($searchResult['error_code'] ?? null) {
                'auth_error' => 'Commu API authentication failed. Refresh COMMU_BEARER_TOKEN and try again.',
                'network_error' => 'Network error when contacting Commu API. Please try again shortly.',
                default => 'Unable to fetch help posts from Commu API right now. Please try again.',
            };

            return view('notices.index', [
                'town' => $town,
                'location' => $location,
                'allNotices' => collect(),
                'noticesPaginator' => $this->buildPaginator(collect(), $paginatorInfo, $town, $usedDistance),
                'recentNotices' => collect(),
                'summary' => null,
                'error' => $message,
                'usedDistance' => $usedDistance,
            ]);
        }

        if ($allNotices->isEmpty()) {
            return view('notices.index', [
                'town' => $town,
                'location' => $location,
                'allNotices' => collect(),
                'noticesPaginator' => $this->buildPaginator(collect(), $paginatorInfo, $town, $usedDistance),
                'recentNotices' => collect(),
                'summary' => null,
                'error' => 'No help posts found for this area.',
                'usedDistance' => $usedDistance,
            ]);
        }

        $displayNotices = $allNotices->map(function (array $notice): array {
            $categoryKey = (string) Arr::get($notice, 'categories.main.key', 'uncategorized');
            $type = (string) Arr::get($notice, 'type', 'unknown');
            $createdAt = Arr::get($notice, 'created_at');

            return [
                ...$notice,
                'category_label' => Str::headline(str_replace('_', ' ', $categoryKey)),
                'type_label' => Str::headline(str_replace('_', ' ', $type)),
                'created_date' => $createdAt ? Carbon::parse($createdAt)->toDateString() : null,
            ];
        });

        $summarySourceNotices = $page === 1
            ? $allNotices
            : $noticeService->searchNearbyNotices(
                $location['lat'],
                $location['long'],
                1,
                $usedDistance
            )['notices'];

        $recentNotices = $noticeService->recentNotices($summarySourceNotices);
        $summaryInputNotices = $recentNotices->isNotEmpty() ? $recentNotices : $summarySourceNotices;
        $summaryBasis = $recentNotices->isNotEmpty()
            ? 'recent'
            : 'all';

        $summary = null;

        try {
            $summary = $summaryService->buildSummary($summaryInputNotices, $town);
        } catch (Throwable) {
            $summary = 'Bedrock summary unavailable right now. Check AWS credentials/model access and try again.';
        }

        return view('notices.index', [
            'town' => $town,
            'location' => $location,
            'allNotices' => $displayNotices,
            'noticesPaginator' => $this->buildPaginator($displayNotices, $paginatorInfo, $town, $usedDistance),
            'recentNotices' => $recentNotices,
            'summaryPostCount' => $summaryInputNotices->count(),
            'summaryBasis' => $summaryBasis,
            'summary' => $summary,
            'error' => null,
            'usedDistance' => $usedDistance,
        ]);
    }

    private function buildPaginator(
        $items,
        array $paginatorInfo,
        string $town,
        int $distance
    ): LengthAwarePaginator {
        $perPage = max(1, (int) Arr::get($paginatorInfo, 'perPage', config('services.commu.page_size')));
        $currentPage = max(1, (int) Arr::get($paginatorInfo, 'currentPage', 1));
        $total = max(0, (int) Arr::get($paginatorInfo, 'total', $items->count()));

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => route('notices.search'),
                'query' => [
                    'town' => $town,
                    'distance' => $distance,
                ],
            ]
        );
    }
}
