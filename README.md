# Commu Practical Task (Laravel)

Small Laravel app that:
- takes a home town as input
- geocodes it to latitude/longitude
- fetches nearby Commu help posts (`noticesWhereDistance`)
- generates an area summary with AWS Bedrock

## Setup

### 1. Install dependencies
If you have PHP + Composer locally:
```bash
composer install
```

If not, you can use Docker (same approach used in this repo):
```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
```

### 2. Configure environment
```bash
cp .env.example .env
```

Set these values in `.env`:
- `COMMU_BEARER_TOKEN` (copied from browser DevTools as requested in the task)
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_DEFAULT_REGION`
- `BEDROCK_MODEL_ID` (default currently set to Claude 3 Haiku)
- `GEOCODING_USER_AGENT` (set to a real app/contact string)

### 3. Run the app
If you have local PHP:
```bash
php artisan serve
```
Then open `http://127.0.0.1:8000`.

If you do not have local PHP:
```bash
docker run --rm -it -p 8000:8000 -v "$PWD":/app -w /app composer:2 sh -lc "php artisan config:clear && php artisan serve --host=0.0.0.0 --port=8000"
```
Then open `http://127.0.0.1:8000`.

## Running tests

With local PHP:
```bash
php artisan test
```

With Docker:
```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php artisan test
```

## Technical Choices

### Geocoding API
Used **OpenStreetMap Nominatim** (`https://nominatim.openstreetmap.org/search`) with **Open-Meteo Geocoding** (`https://geocoding-api.open-meteo.com/v1/search`) as fallback.

Why:
- simple HTTP API with no account required for a small practical task
- reliable enough for city-level geocoding
- response includes clear `lat` / `lon` output
- fallback provider keeps town search working when Nominatim is rate-limited/blocked

Scoped search to Finland by default (`GEOCODING_COUNTRY_CODES=fi`) because the required test towns are in Finland.

### Chosen distance
Used **25 km** (`COMMU_DISTANCE_KM=25`) as primary radius, with automatic fallback expansion to `50`, `100`, then `200` km if no posts are found.

Why:
- keeps results local when data exists nearby
- avoids empty UX in sparse areas by progressively widening radius
- worked reliably in testing with current API data

### Caching, Rate Limiting, and Telemetry
- Commu responses are cached by request fingerprint (`lat`, `long`, `distance`, `page`, page size) with configurable TTL (`COMMU_CACHE_TTL_SECONDS`).
- Search endpoint is rate-limited (`throttle:commu-search`) with configurable limit (`COMMU_RATE_LIMIT_PER_MINUTE`) to protect upstream APIs and Bedrock costs.
- Telemetry logs are emitted for Commu queries including cache hit/miss and request latency (`latency_ms`).

### Selected Notice fields
Queried these fields from `noticesWhereDistance.data`:
- `id`
- `title`
- `description`
- `type`
- `side`
- `created_at`
- `expires_at`
- `position { latitude longitude }`
- `categories { main { key } sub { key } }`

Why:
- enough for useful UI rendering
- enough structure (`type`, `categories`) for grounded Bedrock summarization
- keeps payload limited and readable

### What counts as “recent”
Used **last 30 days** (`COMMU_RECENT_DAYS=30`) based on `created_at`.

Why:
- balances recency and sample size
- avoids summarizing very old posts as current situation

### Bedrock model and summary approach
Used AWS SDK for PHP (`aws/aws-sdk-php`) with Bedrock Runtime `converse`.

Default model:
- `anthropic.claude-3-haiku-20240307-v1:0`
- Also supports Bedrock inference profile ARNs (used via the Bedrock `Converse` API).

Approach:
- fetch nearby notices
- filter to recent notices (30 days)
- send compact JSON of up to 20 recent posts to Bedrock
- request 2-4 sentence summary focused on common themes and relative frequency
- cache summary responses by content fingerprint (town + model + prompt version + notice payload hash) with configurable TTL
- use cache locking to avoid duplicate concurrent Bedrock summary generation for identical inputs
- retry transient Bedrock failures with small bounded retries
- if recent posts exist, summarize based on recent posts
- if recent posts are 0 but fetched posts exist, summarize based on fetched posts and label that basis in UI
- if there are no fetched posts at all, show a “not enough data” style message

## Error Handling Implemented
Handled obvious cases requested in task:
- empty input (`town` validation)
- no geocoding result
- no notices returned
- Bedrock failure fallback message
- distinct Commu API auth/network/upstream failure messaging (not treated as empty data)

## UI / UX Notes
- Simple but structured server-rendered UI with clear cards and metadata
- Notice metadata labels are normalized (human-readable type/category labels)
- Search results support URL-based pagination (`/search?town=...&page=...&distance=...`)
- Pagination stays within the selected distance radius for consistent browsing
- Pagination controls appear when more than one page of results is available (`lastPage > 1`)
- Dynamic client-side filters for loaded results: category and type

## What I'd Improve Next
- Add focused feature tests with mocked Geocoding/Commu/Bedrock responses
- Add richer summary UI (confidence indicator, top themes extracted as chips)
- Add interactive filtering by category/type and recency window
- Add request logging/observability for API failures and latency
