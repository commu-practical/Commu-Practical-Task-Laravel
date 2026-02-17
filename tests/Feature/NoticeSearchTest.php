<?php

namespace Tests\Feature;

use App\Services\AreaSummaryService;
use App\Services\CommuNoticeService;
use App\Services\GeocodingService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NoticeSearchTest extends TestCase
{
    public function test_home_page_loads(): void
    {
        $this->get('/')
            ->assertStatus(200)
            ->assertSee('Nearby Commu Help Posts');
    }

    public function test_town_is_required(): void
    {
        $this->post('/search', [])
            ->assertSessionHasErrors(['town']);
    }

    #[DataProvider('finnishTownProvider')]
    public function test_search_works_for_required_finnish_locations(string $town, float $lat, float $long): void
    {
        $this->mock(GeocodingService::class, function ($mock) use ($town, $lat, $long): void {
            $mock->shouldReceive('geocodeTown')
                ->once()
                ->andReturn([
                    'name' => $town.', Finland',
                    'lat' => $lat,
                    'long' => $long,
                ]);
        });

        $notices = collect([
            [
                'id' => 'notice-1',
                'title' => "Help post in {$town}",
                'description' => 'Example description',
                'created_at' => now()->subDays(2)->toISOString(),
                'categories' => ['main' => ['key' => 'housework'], 'sub' => []],
                'type' => 'NEED_TRADE',
            ],
        ]);

        $this->mock(CommuNoticeService::class, function ($mock) use ($notices): void {
            $mock->shouldReceive('searchNearbyNotices')
                ->once()
                ->andReturn($this->successfulSearchResult($notices, 25, 1, 1, 1, 25));
            $mock->shouldReceive('recentNotices')->once()->andReturn(new Collection($notices->all()));
        });

        $this->mock(AreaSummaryService::class, function ($mock): void {
            $mock->shouldReceive('buildSummary')->once()->andReturn('Summary text.');
        });

        $this->get('/search?town='.urlencode($town))
            ->assertStatus(200)
            ->assertSee("Help post in {$town}")
            ->assertSee('Summary text.');
    }

    public function test_external_location_can_work_when_geocoding_and_notices_succeed(): void
    {
        $this->mock(GeocodingService::class, function ($mock): void {
            $mock->shouldReceive('geocodeTown')
                ->once()
                ->andReturn([
                    'name' => 'Stockholm, Sweden',
                    'lat' => 59.3293,
                    'long' => 18.0686,
                ]);
        });

        $notices = collect([
            [
                'id' => 'notice-2',
                'title' => 'Stockholm sample help post',
                'description' => 'External city test',
                'created_at' => now()->subDays(1)->toISOString(),
                'categories' => ['main' => ['key' => 'events'], 'sub' => []],
                'type' => 'GIVE_FREE',
            ],
        ]);

        $this->mock(CommuNoticeService::class, function ($mock) use ($notices): void {
            $mock->shouldReceive('searchNearbyNotices')
                ->once()
                ->andReturn($this->successfulSearchResult($notices, 100, 1, 1, 1, 25));
            $mock->shouldReceive('recentNotices')->once()->andReturn(new Collection($notices->all()));
        });

        $this->mock(AreaSummaryService::class, function ($mock): void {
            $mock->shouldReceive('buildSummary')->once()->andReturn('External location summary.');
        });

        $this->get('/search?town=Stockholm')
            ->assertStatus(200)
            ->assertSee('Stockholm sample help post')
            ->assertSee('External location summary.');
    }

    public function test_external_location_shows_error_when_geocoding_fails(): void
    {
        $this->mock(GeocodingService::class, function ($mock): void {
            $mock->shouldReceive('geocodeTown')->once()->andReturnNull();
        });

        $this->followingRedirects()
            ->get('/search?town=Atlantis')
            ->assertSee('No geocoding result found for that town.');
    }

    public function test_search_renders_notice_results_and_summary(): void
    {
        $this->mock(GeocodingService::class, function ($mock): void {
            $mock->shouldReceive('geocodeTown')
                ->once()
                ->andReturn([
                    'name' => 'Helsinki',
                    'lat' => 60.1699,
                    'long' => 24.9384,
                ]);
        });

        $allNotices = collect([
            [
                'id' => '1',
                'title' => 'Need moving help',
                'description' => 'Need help carrying boxes',
                'created_at' => now()->subDays(3)->toISOString(),
                'categories' => ['main' => ['key' => 'moving'], 'sub' => []],
                'type' => 'NEED_TRADE',
            ],
        ]);

        $recentNotices = new Collection($allNotices->all());

        $this->mock(CommuNoticeService::class, function ($mock) use ($allNotices, $recentNotices): void {
            $mock->shouldReceive('searchNearbyNotices')
                ->once()
                ->andReturn($this->successfulSearchResult($allNotices));
            $mock->shouldReceive('recentNotices')->once()->andReturn($recentNotices);
        });

        $this->mock(AreaSummaryService::class, function ($mock): void {
            $mock->shouldReceive('buildSummary')
                ->once()
                ->andReturn('Mostly moving-related requests with occasional practical aid.');
        });

        $this->post('/search', ['town' => 'Helsinki'])
            ->assertStatus(200)
            ->assertSee('Need moving help')
            ->assertSee('Mostly moving-related requests with occasional practical aid.');
    }

    public function test_search_shows_error_when_geocoding_fails(): void
    {
        $this->mock(GeocodingService::class, function ($mock): void {
            $mock->shouldReceive('geocodeTown')->once()->andReturnNull();
        });

        $this->followingRedirects()
            ->post('/search', ['town' => 'UnknownTown'])
            ->assertSee('No geocoding result found for that town.');
    }

    public function test_search_supports_get_pagination_urls(): void
    {
        $this->mock(GeocodingService::class, function ($mock): void {
            $mock->shouldReceive('geocodeTown')
                ->once()
                ->andReturn([
                    'name' => 'Helsinki',
                    'lat' => 60.1699,
                    'long' => 24.9384,
                ]);
        });

        $notices = collect([
            [
                'id' => '2',
                'title' => 'Need event helper',
                'description' => 'Need one extra hand for setup.',
                'created_at' => now()->subDays(2)->toISOString(),
                'categories' => ['main' => ['key' => 'events'], 'sub' => []],
                'type' => 'NEED_FREE',
            ],
        ]);

        $this->mock(CommuNoticeService::class, function ($mock) use ($notices): void {
            $mock->shouldReceive('searchNearbyNotices')
                ->twice()
                ->withAnyArgs()
                ->andReturn($this->successfulSearchResult($notices, 100, 2, 3, 3, 1));
            $mock->shouldReceive('recentNotices')->once()->andReturn($notices);
        });

        $this->mock(AreaSummaryService::class, function ($mock): void {
            $mock->shouldReceive('buildSummary')->once()->andReturn('Summary text.');
        });

        $this->get('/search?town=Helsinki&page=2&distance=100')
            ->assertStatus(200)
            ->assertSee('Showing page 2 of 3')
            ->assertSee('distance=100');
    }

    public function test_summary_falls_back_to_all_posts_when_recent_window_has_no_posts(): void
    {
        $this->mock(GeocodingService::class, function ($mock): void {
            $mock->shouldReceive('geocodeTown')
                ->once()
                ->andReturn([
                    'name' => 'Helsinki',
                    'lat' => 60.1699,
                    'long' => 24.9384,
                ]);
        });

        $oldNotices = collect([
            [
                'id' => 'n-1',
                'title' => 'Older but relevant help post',
                'description' => 'Legacy post description',
                'created_at' => now()->subDays(120)->toISOString(),
                'categories' => ['main' => ['key' => 'events'], 'sub' => []],
                'type' => 'NEED_TRADE',
            ],
        ]);

        $this->mock(CommuNoticeService::class, function ($mock) use ($oldNotices): void {
            $mock->shouldReceive('searchNearbyNotices')
                ->once()
                ->andReturn($this->successfulSearchResult($oldNotices));
            $mock->shouldReceive('recentNotices')
                ->once()
                ->andReturn(collect());
        });

        $this->mock(AreaSummaryService::class, function ($mock): void {
            $mock->shouldReceive('buildSummary')
                ->once()
                ->andReturn('Fallback summary from fetched posts.');
        });

        $this->get('/search?town=Helsinki')
            ->assertStatus(200)
            ->assertSee('Fallback summary from fetched posts.')
            ->assertSee('Based on 1 fetched posts (no posts in recent window).');
    }

    public static function finnishTownProvider(): array
    {
        return [
            'Helsinki' => ['Helsinki', 60.1699, 24.9384],
            'Vantaa' => ['Vantaa', 60.2934, 25.0378],
            'Tampere' => ['Tampere', 61.4978, 23.7610],
            'Turku' => ['Turku', 60.4518, 22.2666],
        ];
    }

    private function successfulSearchResult(
        Collection $notices,
        int $distance = 25,
        int $currentPage = 1,
        int $lastPage = 1,
        int $total = 1,
        int $perPage = 25
    ): array {
        return [
            'successful' => true,
            'distance' => $distance,
            'notices' => $notices,
            'paginator' => [
                'count' => $notices->count(),
                'total' => $total,
                'currentPage' => $currentPage,
                'lastPage' => $lastPage,
                'perPage' => $perPage,
                'hasMorePages' => $currentPage < $lastPage,
            ],
        ];
    }
}
