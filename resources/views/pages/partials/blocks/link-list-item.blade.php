<div class="wb-link-list-item">
    <div class="wb-link-list-main">
        @if ($block->url)
            <a href="{{ $block->url }}" class="wb-link-list-title">{{ $block->title ?: $block->url }}</a>
        @elseif ($block->title)
            <div class="wb-link-list-title">{{ $block->title }}</div>
        @endif

        @if ($block->subtitle)
            <div class="wb-link-list-meta">{{ $block->subtitle }}</div>
        @endif

        @if ($block->content)
            <div class="wb-link-list-desc">{{ $block->content }}</div>
        @endif
    </div>
</div>
