@php
    $headingBlocks = collect($block->renderPage()?->blocks ?? [])
        ->where('status', 'published')
        ->where('type', 'header')
        ->whereIn('variant', ['h2', 'h3'])
        ->sortBy(fn ($heading) => sprintf('%010d-%010d', (int) $heading->sort_order, (int) $heading->id))
        ->values();
    $headingBlocks = app(\App\Support\Blocks\BlockTranslationResolver::class)
        ->resolveCollection($headingBlocks, $block->renderLocaleCode())
        ->filter(fn ($heading) => filled($heading->setting('anchor', $heading->url)))
        ->values();
@endphp

@if ($headingBlocks->isNotEmpty())
    <div class="wb-stack wb-gap-2">
        @if ($block->title)
            <strong>{{ $block->title }}</strong>
        @endif

        <div class="wb-link-list">
            @foreach ($headingBlocks as $headingBlock)
                <a class="wb-link-list-item" href="#{{ $headingBlock->setting('anchor', $headingBlock->url) }}">
                    <div class="wb-link-list-main">
                        <span class="wb-link-list-title">{{ $headingBlock->title ?: $headingBlock->content }}</span>
                        @if ($headingBlock->variant === 'h3')
                            <span class="wb-link-list-meta">Section detail</span>
                        @endif
                    </div>

                    <div class="wb-link-list-desc">{{ $headingBlock->variant === 'h3' ? 'Jump to subsection' : 'Jump to section' }}</div>
                </a>
            @endforeach
        </div>
    </div>
@endif

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
