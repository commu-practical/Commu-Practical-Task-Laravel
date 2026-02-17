<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commu Nearby Help Posts</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-1: #f1f5f9;
            --bg-2: #dbeafe;
            --card: #ffffff;
            --ink: #0f172a;
            --muted: #475569;
            --line: #dbe3ef;
            --brand: #0f766e;
            --brand-strong: #115e59;
            --summary-bg: #ecfeff;
            --summary-line: #67e8f9;
            --error-bg: #fff1f2;
            --error-line: #fecdd3;
            --error-ink: #be123c;
        }
        body {
            font-family: "Manrope", "Segoe UI", sans-serif;
            background: radial-gradient(1200px 600px at 10% -20%, var(--bg-2), transparent 50%), var(--bg-1);
            color: var(--ink);
            margin: 0;
            padding: 2.25rem 1rem;
        }
        .container {
            max-width: 920px;
            margin: 0 auto;
            background: var(--card);
            border-radius: 20px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
            border: 1px solid var(--line);
            padding: 1.75rem;
        }
        h1 {
            margin: 0 0 0.8rem;
            font-size: 3rem;
            line-height: 1;
            letter-spacing: -0.02em;
        }
        h2 { margin: 1.4rem 0 0.8rem; font-size: 2rem; letter-spacing: -0.01em; }
        form {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.15rem;
            flex-wrap: wrap;
        }
        input[type="text"] {
            flex: 1;
            min-width: 220px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 0.78rem 0.9rem;
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--ink);
        }
        button {
            background: linear-gradient(180deg, var(--brand), var(--brand-strong));
            color: #fff;
            border: 0;
            border-radius: 12px;
            padding: 0.7rem 1.1rem;
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
        }
        button:hover { transform: translateY(-1px); }
        .hint {
            color: var(--muted);
            margin-top: 0;
            font-size: 1.05rem;
        }
        .error {
            background: var(--error-bg);
            border: 1px solid var(--error-line);
            color: var(--error-ink);
            border-radius: 12px;
            padding: 0.85rem;
            margin: 1rem 0;
            font-weight: 600;
        }
        .summary {
            background: var(--summary-bg);
            border: 1px solid var(--summary-line);
            border-radius: 14px;
            padding: 1rem;
            margin: 1.1rem 0;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.6rem;
            margin: 0.85rem 0 1rem;
        }
        .stat-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f8fafc;
            padding: 0.65rem 0.7rem;
        }
        .stat-label {
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .stat-value {
            margin-top: 0.2rem;
            font-size: 1.05rem;
            font-weight: 800;
        }
        ul {
            list-style: none;
            margin: 0.8rem 0 0;
            padding: 0;
        }
        .notice-item {
            margin: 0.75rem 0;
            padding: 0.85rem;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f8fafc;
        }
        .meta {
            color: var(--muted);
            font-size: 0.95rem;
            margin-top: 0.2rem;
        }
        .title { font-size: 1.45rem; }
        .summary p { margin: 0.55rem 0; line-height: 1.45; }
        .notice-title { font-size: 1.15rem; line-height: 1.3; }
        .desc { margin-top: 0.45rem; line-height: 1.45; }
        .pill {
            display: inline-block;
            margin-top: 0.35rem;
            padding: 0.16rem 0.56rem;
            border-radius: 999px;
            background: #d1fae5;
            color: #065f46;
            font-size: 0.82rem;
            font-weight: 700;
        }
        .pager {
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .pager-link {
            color: #fff;
            text-decoration: none;
            background: var(--brand);
            border-radius: 10px;
            padding: 0.52rem 0.72rem;
            font-weight: 800;
            font-size: 0.92rem;
        }
        .pager-link.off {
            pointer-events: none;
            background: #94a3b8;
        }
        .pager-text {
            color: var(--muted);
            font-weight: 700;
            font-size: 0.92rem;
        }
        .filters {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f8fafc;
            padding: 0.8rem;
            margin: 0.6rem 0 1rem;
        }
        .filters-title {
            font-weight: 800;
            margin: 0 0 0.55rem;
            font-size: 0.95rem;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.55rem;
        }
        .filters select,
        .filters input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0.5rem 0.6rem;
            font: inherit;
            background: #fff;
        }
        .filter-empty {
            margin-top: 0.7rem;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 0.75rem;
            color: var(--muted);
            background: #ffffff;
            display: none;
        }
        @media (max-width: 740px) {
            h1 { font-size: 2.35rem; }
            .container { padding: 1.05rem; border-radius: 14px; }
            input[type="text"] { font-size: 1.05rem; }
            button { width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1 class="title">Nearby Commu Help Posts</h1>
    <p class="hint">Enter a town to find nearby notices and generate an AWS Bedrock area summary.</p>

    <form method="GET" action="{{ route('notices.search') }}">
        <input type="text" name="town" value="{{ old('town', $town ?? '') }}" placeholder="e.g. Helsinki" required>
        @if (!empty($usedDistance))
            <input type="hidden" name="distance" value="{{ $usedDistance }}">
        @endif
        <button type="submit">Search</button>
    </form>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    @if (session('error'))
        <div class="error">{{ session('error') }}</div>
    @endif

    @if (!empty($error))
        <div class="error">{{ $error }}</div>
    @endif

    @if (!empty($location))
        <p class="meta">
            Geocoded location: {{ $location['name'] }}
            ({{ number_format($location['lat'], 4) }}, {{ number_format($location['long'], 4) }})
        </p>
    @endif

    @if (!empty($noticesPaginator))
        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">Results</div>
                <div class="stat-value">{{ $noticesPaginator->total() }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Search radius</div>
                <div class="stat-value">{{ $usedDistance ?? config('services.commu.distance_km') }} km</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Page</div>
                <div class="stat-value">{{ $noticesPaginator->currentPage() }} / {{ max(1, $noticesPaginator->lastPage()) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Recent posts</div>
                <div class="stat-value">{{ $recentNotices->count() }}</div>
            </div>
        </div>
    @endif

    @if (!empty($summary))
        <div class="summary">
            <strong>Area summary</strong>
            <p>{{ $summary }}</p>
            @if (($summaryBasis ?? 'recent') === 'recent')
                <p class="meta">Based on {{ $summaryPostCount ?? $recentNotices->count() }} recent posts.</p>
            @else
                <p class="meta">Based on {{ $summaryPostCount ?? $allNotices->count() }} fetched posts (no posts in recent window).</p>
            @endif
        </div>
    @endif

    @if (!empty($allNotices) && $allNotices->isNotEmpty())
        @php
            $categoryOptions = $allNotices->pluck('category_label')->filter()->unique()->sort()->values();
            $typeOptions = $allNotices->pluck('type_label')->filter()->unique()->sort()->values();
        @endphp

        <h2>Help posts (<span id="visibleCount">{{ $allNotices->count() }}</span> shown / {{ $noticesPaginator->total() ?? $allNotices->count() }} total)</h2>
        <div class="filters">
            <p class="filters-title">Filter loaded results</p>
            <div class="filters-grid">
                <select id="filterCategory">
                    <option value="">All categories</option>
                    @foreach ($categoryOptions as $option)
                        <option value="{{ strtolower($option) }}">{{ $option }}</option>
                    @endforeach
                </select>
                <select id="filterType">
                    <option value="">All types</option>
                    @foreach ($typeOptions as $option)
                        <option value="{{ strtolower($option) }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div id="filterEmpty" class="filter-empty">No loaded results match current filters.</div>
        </div>
        <ul>
            @foreach ($allNotices as $notice)
                <li
                    class="notice-item"
                    data-category="{{ strtolower($notice['category_label'] ?? '') }}"
                    data-type="{{ strtolower($notice['type_label'] ?? '') }}"
                >
                    <strong class="notice-title">{{ $notice['title'] }}</strong>
                    <div class="meta">
                        {{ $notice['type_label'] ?? 'Unknown Type' }}
                        @if (!empty($notice['category_label']))
                            | category: {{ $notice['category_label'] }}
                        @endif
                        @if (!empty($notice['created_date']))
                            | created: {{ $notice['created_date'] }}
                        @endif
                    </div>
                    @if (!empty($notice['category_label']))
                        <div class="pill">{{ $notice['category_label'] }}</div>
                    @endif
                    @if (!empty($notice['description']))
                        <div class="desc">{{ \Illuminate\Support\Str::limit($notice['description'], 220) }}</div>
                    @endif
                </li>
            @endforeach
        </ul>
        @if (!empty($noticesPaginator) && $noticesPaginator->lastPage() > 1)
            <div class="pager">
                @if ($noticesPaginator->onFirstPage())
                    <span class="pager-link off">Previous</span>
                @else
                    <a class="pager-link" href="{{ $noticesPaginator->previousPageUrl() }}">Previous</a>
                @endif
                <span class="pager-text">
                    Showing page {{ $noticesPaginator->currentPage() }} of {{ $noticesPaginator->lastPage() }}
                </span>
                @if ($noticesPaginator->hasMorePages())
                    <a class="pager-link" href="{{ $noticesPaginator->nextPageUrl() }}">Next</a>
                @else
                    <span class="pager-link off">Next</span>
                @endif
            </div>
        @endif
    @endif
</div>
<script>
    (() => {
        const items = Array.from(document.querySelectorAll('.notice-item'));
        if (!items.length) return;

        const byId = (id) => document.getElementById(id);
        const category = byId('filterCategory');
        const type = byId('filterType');
        const visibleCount = byId('visibleCount');
        const emptyState = byId('filterEmpty');

        const normalize = (value) => (value || '').toString().trim().toLowerCase();

        const applyFilters = () => {
            const categoryValue = normalize(category?.value);
            const typeValue = normalize(type?.value);
            let visible = 0;

            items.forEach((item) => {
                const itemCategory = normalize(item.dataset.category);
                const itemType = normalize(item.dataset.type);

                const categoryOk = !categoryValue || itemCategory === categoryValue;
                const typeOk = !typeValue || itemType === typeValue;
                const match = categoryOk && typeOk;

                item.style.display = match ? '' : 'none';
                if (match) visible += 1;
            });

            if (visibleCount) visibleCount.textContent = String(visible);
            if (emptyState) emptyState.style.display = visible === 0 ? 'block' : 'none';
        };

        [category, type].forEach((el) => {
            if (!el) return;
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        });
    })();
</script>
</body>
</html>
