@php
    $headingBlocks = \App\Models\Block::query()
        ->where('page_id', $block->page_id)
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
                <div class="wb-link-list-item">
                    <div class="wb-link-list-main">
                        <a href="#{{ $headingBlock->url }}" class="wb-link-list-title">{{ $headingBlock->title ?: $headingBlock->content }}</a>
                        @if ($headingBlock->variant === 'h3')
                            <div class="wb-link-list-meta">Section detail</div>
                        @endif
                    </div>
                </div>
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
