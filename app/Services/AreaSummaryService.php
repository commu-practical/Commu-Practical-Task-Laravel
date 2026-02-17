<?php

namespace App\Services;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Throwable;

class AreaSummaryService
{
    public function buildSummary(Collection $notices, string $town): string
    {
        if ($notices->isEmpty()) {
            return 'Not enough recent help post data to produce a reliable area summary.';
        }

        $promptPayload = $this->buildPromptPayload($notices);
        $cacheKey = $this->buildCacheKey($town, $promptPayload);
        $ttl = max(60, (int) config('services.bedrock.cache_ttl_seconds', 21600));
        $lockSeconds = max(1, (int) config('services.bedrock.lock_wait_seconds', 3));

        if (($cached = Cache::get($cacheKey)) !== null) {
            return (string) $cached;
        }

        try {
            return Cache::lock('lock:'.$cacheKey, $lockSeconds + 2)->block($lockSeconds, function () use ($cacheKey, $ttl, $town, $promptPayload): string {
                if (($cached = Cache::get($cacheKey)) !== null) {
                    return (string) $cached;
                }

                $prompt = sprintf(
                    "You summarize local community help posts.\nTown: %s\nRecent help posts JSON:\n%s\n\nInstructions:\n- Write 2-4 sentences in plain English.\n- Do not use markdown, headings, bullets, asterisks, or hashtags.\n- Focus on common themes and relative frequency.\n- Ground your summary only in the provided data.\n- If patterns are weak, explicitly say confidence is low.",
                    $town,
                    json_encode($promptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                );

                $raw = trim((string) Arr::get(
                    $this->callBedrock($prompt),
                    'output.message.content.0.text',
                    'Summary could not be generated from Bedrock response.'
                ));

                $summary = $this->cleanSummaryText($raw);
                Cache::put($cacheKey, $summary, now()->addSeconds($ttl));

                return $summary;
            });
        } catch (LockTimeoutException) {
            return (string) Cache::get($cacheKey, 'Summary is being prepared. Please try again.');
        }
    }

    protected function buildPromptPayload(Collection $notices): array
    {
        return $notices
            ->take(20)
            ->map(function (array $notice): array {
                return [
                    'title' => Arr::get($notice, 'title'),
                    'description' => Arr::get($notice, 'description'),
                    'type' => Arr::get($notice, 'type'),
                    'side' => Arr::get($notice, 'side'),
                    'main_category' => Arr::get($notice, 'categories.main.key'),
                    'sub_categories' => collect(Arr::get($notice, 'categories.sub', []))
                        ->pluck('key')
                        ->filter()
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    protected function callBedrock(string $prompt): array
    {
        $attempts = max(1, (int) config('services.bedrock.retry_attempts', 2));
        $sleepMs = max(0, (int) config('services.bedrock.retry_sleep_ms', 300));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $client = new BedrockRuntimeClient([
                    'version' => 'latest',
                    'region' => config('services.bedrock.region'),
                    'credentials' => [
                        'key' => config('services.bedrock.key'),
                        'secret' => config('services.bedrock.secret'),
                    ],
                ]);

                return $client->converse([
                    'modelId' => config('services.bedrock.model_id'),
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                    'inferenceConfig' => [
                        'maxTokens' => (int) config('services.bedrock.max_tokens'),
                        'temperature' => (float) config('services.bedrock.temperature'),
                    ],
                    'additionalModelRequestFields' => [
                        'top_k' => (int) config('services.bedrock.top_k'),
                    ],
                ])->toArray();
            } catch (Throwable $exception) {
                $lastException = $exception;

                if ($attempt < $attempts) {
                    usleep($sleepMs * 1000);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Bedrock request failed.');
    }

    protected function buildCacheKey(string $town, array $promptPayload): string
    {
        $signature = [
            'town' => mb_strtolower(trim($town)),
            'model' => (string) config('services.bedrock.model_id'),
            'prompt_version' => (string) config('services.bedrock.prompt_version', 'v1'),
            'payload' => $promptPayload,
        ];

        return 'bedrock_summary:'.hash('sha256', json_encode($signature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function cleanSummaryText(string $summary): string
    {
        $summary = preg_replace('/^[#>\-\*\s]+/m', '', $summary) ?? $summary;
        $summary = str_replace(['**', '__', '`'], '', $summary);
        $summary = preg_replace('/\s+/', ' ', $summary) ?? $summary;

        return trim($summary);
    }
}
