@php
    $items = $block->children
        ->where('status', 'published')
        ->filter(fn ($child) => $child->isLinkListItem())
        ->sortBy('sort_order')
        ->values();

    $eyebrow = trim((string) $block->subtitle);
    $heading = trim((string) $block->title);
    $intro = trim((string) $block->content);
@endphp

@if ($items->isNotEmpty())
    <section class="wb-stack wb-gap-3">
        @if ($eyebrow !== '' || $heading !== '' || $intro !== '')
            <div class="wb-stack wb-gap-1">
                @if ($eyebrow !== '')
                    <p class="wb-eyebrow">{{ $eyebrow }}</p>
                @endif
                @if ($heading !== '')
                    <h3>{{ $heading }}</h3>
                @endif
                @if ($intro !== '')
                    <p>{{ $intro }}</p>
                @endif
            </div>
        @endif

        <div class="wb-link-list">
            @foreach ($items as $item)
                @include('pages.partials.blocks.link-list-item', ['block' => $item])
            @endforeach
        </div>
    </section>
@endif
