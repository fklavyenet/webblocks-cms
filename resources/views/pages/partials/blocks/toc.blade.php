@php
    $headingBlocks = \App\Models\Block::query()
        ->where('page_id', $block->renderPageId())
        ->where('status', 'published')
        ->where('type', 'heading')
        ->whereIn('variant', ['h2', 'h3'])
        ->orderBy('sort_order')
        ->get(['title', 'content', 'url', 'variant'])
        ->filter(fn ($heading) => filled($heading->url))
        ->values();
@endphp

@if ($headingBlocks->isNotEmpty())
    <div class="wb-stack wb-gap-2">
        @if ($block->title)
            <strong>{{ $block->title }}</strong>
        @endif

        <div class="wb-link-list">
            @foreach ($headingBlocks as $headingBlock)
                <a class="wb-link-list-item" href="#{{ $headingBlock->url }}">
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
