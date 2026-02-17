<?php

namespace Tests\Feature;

use App\Services\AreaSummaryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AreaSummaryServiceTest extends TestCase
{
    public function test_returns_no_data_message_when_notices_are_empty(): void
    {
        $service = new class extends AreaSummaryService {
            protected function callBedrock(string $prompt): array
            {
                return ['output' => ['message' => ['content' => [['text' => 'Should not be called']]]]];
            }
        };

        $summary = $service->buildSummary(collect(), 'Helsinki');

        $this->assertSame('Not enough recent help post data to produce a reliable area summary.', $summary);
    }

    public function test_uses_cached_summary_for_same_input_without_recalling_bedrock(): void
    {
        Cache::flush();
        $counter = (object) ['count' => 0];

        $service = new class($counter) extends AreaSummaryService {
            private object $counter;

            public function __construct(object $counter)
            {
                $this->counter = $counter;
            }

            protected function callBedrock(string $prompt): array
            {
                $this->counter->count += 1;

                return [
                    'output' => [
                        'message' => [
                            'content' => [
                                ['text' => 'Cached summary response.'],
                            ],
                        ],
                    ],
                ];
            }
        };

        $notices = new Collection([
            [
                'title' => 'Need help with moving',
                'description' => 'Boxes and transport',
                'type' => 'NEED_TRADE',
                'side' => 'NEED',
                'categories' => ['main' => ['key' => 'transportation'], 'sub' => []],
            ],
        ]);

        $first = $service->buildSummary($notices, 'Helsinki');
        $second = $service->buildSummary($notices, 'Helsinki');

        $this->assertSame('Cached summary response.', $first);
        $this->assertSame('Cached summary response.', $second);
        $this->assertSame(1, $counter->count);
    }

    public function test_notice_content_change_creates_new_cache_key_and_reinvokes_bedrock(): void
    {
        Cache::flush();
        $counter = (object) ['count' => 0];

        $service = new class($counter) extends AreaSummaryService {
            private object $counter;

            public function __construct(object $counter)
            {
                $this->counter = $counter;
            }

            protected function callBedrock(string $prompt): array
            {
                $this->counter->count += 1;

                return [
                    'output' => [
                        'message' => [
                            'content' => [
                                ['text' => 'Summary '.$this->counter->count],
                            ],
                        ],
                    ],
                ];
            }
        };

        $baseNotice = [
            'description' => 'Boxes and transport',
            'type' => 'NEED_TRADE',
            'side' => 'NEED',
            'categories' => ['main' => ['key' => 'transportation'], 'sub' => []],
        ];

        $summary1 = $service->buildSummary(new Collection([[...$baseNotice, 'title' => 'Need help with moving']]), 'Helsinki');
        $summary2 = $service->buildSummary(new Collection([[...$baseNotice, 'title' => 'Need help with moving urgently']]), 'Helsinki');

        $this->assertSame('Summary 1', $summary1);
        $this->assertSame('Summary 2', $summary2);
        $this->assertSame(2, $counter->count);
    }
}
