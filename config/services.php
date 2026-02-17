<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'geocoding' => [
        'endpoint' => env('GEOCODING_ENDPOINT', 'https://nominatim.openstreetmap.org/search'),
        'fallback_endpoint' => env('GEOCODING_FALLBACK_ENDPOINT', 'https://geocoding-api.open-meteo.com/v1/search'),
        'country_codes' => env('GEOCODING_COUNTRY_CODES', 'fi'),
        'user_agent' => env('GEOCODING_USER_AGENT', 'commu-practical-task/1.0 (contact@example.com)'),
    ],

    'commu' => [
        'endpoint' => env('COMMU_GRAPHQL_ENDPOINT', 'https://office.commuapp.fi/graphql'),
        'bearer_token' => env('COMMU_BEARER_TOKEN'),
        'distance_km' => env('COMMU_DISTANCE_KM', 25),
        'page_size' => env('COMMU_PAGE_SIZE', 25),
        'recent_days' => env('COMMU_RECENT_DAYS', 30),
        'cache_ttl_seconds' => env('COMMU_CACHE_TTL_SECONDS', 180),
        'retry_attempts' => env('COMMU_RETRY_ATTEMPTS', 2),
        'retry_sleep_ms' => env('COMMU_RETRY_SLEEP_MS', 200),
        'rate_limit_per_minute' => env('COMMU_RATE_LIMIT_PER_MINUTE', 30),
    ],

    'bedrock' => [
        'region' => env('AWS_DEFAULT_REGION', 'eu-central-1'),
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'model_id' => env('BEDROCK_MODEL_ID', 'anthropic.claude-3-haiku-20240307-v1:0'),
        'max_tokens' => env('BEDROCK_MAX_TOKENS', 220),
        'temperature' => env('BEDROCK_TEMPERATURE', 0.2),
        'top_k' => env('BEDROCK_TOP_K', 250),
        'cache_ttl_seconds' => env('BEDROCK_SUMMARY_CACHE_TTL_SECONDS', 21600),
        'prompt_version' => env('BEDROCK_PROMPT_VERSION', 'v1'),
        'retry_attempts' => env('BEDROCK_RETRY_ATTEMPTS', 2),
        'retry_sleep_ms' => env('BEDROCK_RETRY_SLEEP_MS', 300),
        'lock_wait_seconds' => env('BEDROCK_SUMMARY_LOCK_WAIT_SECONDS', 3),
    ],

];
