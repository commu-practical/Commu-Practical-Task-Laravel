<?php

namespace App\Services;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class AreaSummaryService
{
    public function buildSummary(Collection $notices, string $town): string
    {
        if ($notices->isEmpty()) {
            return 'Not enough recent help post data to produce a reliable area summary.';
        }

        $client = new BedrockRuntimeClient([
            'version' => 'latest',
            'region' => config('services.bedrock.region'),
            'credentials' => [
                'key' => config('services.bedrock.key'),
                'secret' => config('services.bedrock.secret'),
            ],
        ]);

        $promptPayload = $notices
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

        $prompt = sprintf(
            "You summarize local community help posts.\nTown: %s\nRecent help posts JSON:\n%s\n\nInstructions:\n- Write 2-4 sentences in plain English.\n- Do not use markdown, headings, bullets, asterisks, or hashtags.\n- Focus on common themes and relative frequency.\n- Ground your summary only in the provided data.\n- If patterns are weak, explicitly say confidence is low.",
            $town,
            json_encode($promptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $result = $client->converse([
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
        ]);

        $raw = trim((string) Arr::get(
            $result->toArray(),
            'output.message.content.0.text',
            'Summary could not be generated from Bedrock response.'
        ));

        return $this->cleanSummaryText($raw);
    }

    private function cleanSummaryText(string $summary): string
    {
        $summary = preg_replace('/^[#>\-\*\s]+/m', '', $summary) ?? $summary;
        $summary = str_replace(['**', '__', '`'], '', $summary);
        $summary = preg_replace('/\s+/', ' ', $summary) ?? $summary;

        return trim($summary);
    }
}
