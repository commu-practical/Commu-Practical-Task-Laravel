<?php

namespace Tests\Feature;

use App\Services\CommuNoticeService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommuNoticeServiceTest extends TestCase
{
    public function test_returns_unsuccessful_result_when_graphql_errors_are_returned(): void
    {
        Http::fake([
            '*' => Http::response([
                'errors' => [
                    ['message' => 'Unauthorized'],
                ],
            ], 200),
        ]);

        Config::set('services.commu.endpoint', 'https://office.commuapp.fi/graphql');
        Config::set('services.commu.bearer_token', 'test-token');
        Config::set('services.commu.page_size', 25);

        $result = app(CommuNoticeService::class)->searchNearbyNotices(60.1699, 24.9384, 1, 25);

        $this->assertFalse($result['successful']);
        $this->assertSame(25, $result['distance']);
        $this->assertTrue($result['notices']->isEmpty());
        $this->assertSame('auth_error', $result['error_code']);
        $this->assertSame(0, $result['paginator']['total']);
        $this->assertSame(1, $result['paginator']['currentPage']);
    }

    public function test_classifies_auth_failure_from_status_code(): void
    {
        Http::fake([
            '*' => Http::response([], 401),
        ]);

        Config::set('services.commu.endpoint', 'https://office.commuapp.fi/graphql');
        Config::set('services.commu.bearer_token', 'expired-token');
        Config::set('services.commu.page_size', 25);

        $result = app(CommuNoticeService::class)->searchNearbyNotices(60.1699, 24.9384, 1, 25);

        $this->assertFalse($result['successful']);
        $this->assertSame('auth_error', $result['error_code']);
    }

    public function test_parses_successful_graphql_response_and_paginator_info(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'noticesWhereDistance' => [
                        'paginatorInfo' => [
                            'count' => 1,
                            'total' => 3,
                            'currentPage' => 2,
                            'lastPage' => 3,
                            'perPage' => 1,
                            'hasMorePages' => true,
                        ],
                        'data' => [
                            [
                                'id' => 'n1',
                                'title' => 'Need help',
                                'description' => 'Desc',
                                'type' => 'NEED_TRADE',
                                'created_at' => now()->toISOString(),
                                'categories' => ['main' => ['key' => 'housework'], 'sub' => []],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        Config::set('services.commu.endpoint', 'https://office.commuapp.fi/graphql');
        Config::set('services.commu.bearer_token', 'test-token');
        Config::set('services.commu.page_size', 1);

        $result = app(CommuNoticeService::class)->searchNearbyNotices(60.1699, 24.9384, 2, 25);

        $this->assertTrue($result['successful']);
        $this->assertSame(25, $result['distance']);
        $this->assertCount(1, $result['notices']);
        $this->assertSame('Need help', $result['notices'][0]['title']);
        $this->assertSame(3, $result['paginator']['total']);
        $this->assertSame(2, $result['paginator']['currentPage']);
        $this->assertTrue($result['paginator']['hasMorePages']);
    }

    public function test_caches_successful_commu_response_and_reuses_it(): void
    {
        Cache::flush();
        Http::fake([
            '*' => Http::response([
                'data' => [
                    'noticesWhereDistance' => [
                        'paginatorInfo' => [
                            'count' => 1,
                            'total' => 1,
                            'currentPage' => 1,
                            'lastPage' => 1,
                            'perPage' => 25,
                            'hasMorePages' => false,
                        ],
                        'data' => [
                            [
                                'id' => 'n-cache',
                                'title' => 'Cached notice',
                                'description' => 'Desc',
                                'type' => 'GIVE_TRADE',
                                'created_at' => now()->toISOString(),
                                'categories' => ['main' => ['key' => 'events'], 'sub' => []],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        Config::set('services.commu.endpoint', 'https://office.commuapp.fi/graphql');
        Config::set('services.commu.bearer_token', 'test-token');
        Config::set('services.commu.page_size', 25);
        Config::set('services.commu.cache_ttl_seconds', 300);

        $service = app(CommuNoticeService::class);
        $first = $service->searchNearbyNotices(60.1699, 24.9384, 1, 25);
        $second = $service->searchNearbyNotices(60.1699, 24.9384, 1, 25);

        $this->assertTrue($first['successful']);
        $this->assertTrue($second['successful']);
        $this->assertSame('Cached notice', $second['notices'][0]['title']);
        Http::assertSentCount(1);
    }
}
