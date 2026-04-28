@php
    $href = $block->url ?: '#';
    $title = $block->title ?: $block->url ?: 'Open link';
    $meta = trim((string) $block->subtitle);
    $description = trim((string) $block->content);
@endphp

<a class="wb-link-list-item" href="{{ $href }}">
    <div class="wb-link-list-main">
        <span class="wb-link-list-title">{{ $title }}</span>

        @if ($meta !== '')
            <span class="wb-link-list-meta">{{ $meta }}</span>
        @endif
    </div>

    <div class="wb-link-list-desc">{{ $description !== '' ? $description : $title }}</div>
</a>
