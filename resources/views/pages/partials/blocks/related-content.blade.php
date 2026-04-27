@php
    $contentLines = collect(preg_split('/\r\n|\r|\n/', (string) $block->content))
        ->map(fn ($line) => trim((string) $line))
        ->filter()
        ->values();

    $parsedManualItems = $contentLines
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

    $usesLegacyManualItems = $contentLines->isNotEmpty() && $parsedManualItems->count() === $contentLines->count();

    $childItems = $block->children
        ->filter(fn ($child) => in_array($child->typeSlug(), ['button', 'column_item'], true))
        ->map(function ($child) {
            return [
                'title' => trim((string) $child->title),
                'url' => trim((string) $child->url),
                'meta' => trim((string) $child->subtitle),
                'description' => trim((string) $child->content),
            ];
        })
        ->filter(fn ($item) => $item['title'] !== '')
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

    $items = $childItems->isNotEmpty()
        ? $childItems
        : ($usesLegacyManualItems
            ? $parsedManualItems->map(fn ($item) => [
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
        ]));

    $intro = $usesLegacyManualItems ? '' : trim((string) $block->content);
    $variant = trim((string) ($block->variant ?: $block->setting('style', 'links')));
@endphp

@if ($items->isNotEmpty())
    <section class="wb-stack wb-gap-3">
        @if ($block->title || $block->subtitle || $intro !== '')
            <div class="wb-stack wb-gap-1">
                @if ($block->subtitle)
                    <p class="wb-eyebrow">{{ $block->subtitle }}</p>
                @endif
                @if ($block->title)
                    <h3>{{ $block->title }}</h3>
                @endif
                @if ($intro !== '')
                    <p>{{ $intro }}</p>
                @endif
            </div>
        @endif

        @if ($variant === 'cards')
            <div class="wb-grid wb-grid-2">
                @foreach ($items as $item)
                    <article class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}"><strong>{{ $item['title'] }}</strong></a>
                            @else
                                <strong>{{ $item['title'] }}</strong>
                            @endif
                            @if ($item['meta'])
                                <div class="wb-text-sm wb-text-muted">{{ $item['meta'] }}</div>
                            @endif
                            @if ($item['description'])
                                <p>{{ $item['description'] }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="wb-link-list">
                @foreach ($items as $item)
                    <div class="wb-link-list-item">
                        <div class="wb-link-list-main">
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}" class="wb-link-list-title">{{ $item['title'] }}</a>
                            @else
                                <div class="wb-link-list-title">{{ $item['title'] }}</div>
                            @endif
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
        @endif
    </section>
@endif
