@php
    $manualItems = collect(preg_split('/\r\n|\r|\n/', (string) $block->content))
        ->map(function ($line) {
            $parts = collect(explode('|', (string) $line, 4))
                ->map(fn ($part) => trim((string) $part))
                ->values();

            if (($parts->get(0) ?? '') === '' || ($parts->get(1) ?? '') === '') {
                return null;
            }

            return [
                'title' => $parts->get(0),
                'url' => $parts->get(1),
                'meta' => $parts->get(2),
                'description' => $parts->get(3),
            ];
        })
        ->filter()
        ->values();

    $page = $block->page;
    $publishedPages = \App\Models\Page::query()
        ->where('status', 'published')
        ->when($page?->site_id, fn ($query, $siteId) => $query->where('site_id', $siteId))
        ->with(['translations', 'site'])
        ->orderBy('id')
        ->get();
    $relatedPages = $publishedPages
        ->reject(fn ($candidate) => $candidate->id === $page?->id)
        ->filter(fn ($candidate) => $candidate->page_type === $page?->page_type || in_array($candidate->slug, $block->setting('related_slugs', []), true))
        ->take(3)
        ->values();
    $items = $manualItems->isNotEmpty()
        ? $manualItems->map(fn ($item) => [
            'title' => $item['title'],
            'url' => $item['url'],
            'meta' => $item['meta'],
            'description' => $item['description'],
        ])
        : $relatedPages->map(fn ($relatedPage) => [
            'title' => $relatedPage->title,
            'url' => $relatedPage->publicPath(),
            'meta' => null,
            'description' => null,
        ]);
@endphp

@if ($items->isNotEmpty())
    <section class="wb-stack wb-gap-3">
        @if ($block->title || $block->subtitle)
            <div class="wb-stack wb-gap-1">
                @if ($block->title)
                    <h3>{{ $block->title }}</h3>
                @endif
                @if ($block->subtitle)
                    <p>{{ $block->subtitle }}</p>
                @endif
            </div>
        @endif

        <div class="wb-link-list">
            @foreach ($items as $item)
                <div class="wb-link-list-item">
                    <div class="wb-link-list-main">
                        <a href="{{ $item['url'] }}" class="wb-link-list-title">{{ $item['title'] }}</a>
                        @if ($item['meta'])
                            <div class="wb-link-list-meta">{{ $item['meta'] }}</div>
                        @endif
                        @if ($item['description'])
                            <p class="wb-link-list-desc">{{ $item['description'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
