@php
    $href = $block->linkListItemUrl();
    $title = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $meta = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
    $description = $block->stringValueOrNull($block->content) ?? $block->translatedTextFieldValue('content');
@endphp

@if ($href !== null && $title !== null)
    <a href="{{ $href }}" class="wb-link-list-item">
        <div class="wb-link-list-main">
            <span class="wb-link-list-title">{{ $title }}</span>

            @if ($meta !== null)
                <span class="wb-link-list-meta">{{ $meta }}</span>
            @endif
        </div>

        @if ($description !== null)
            <span class="wb-link-list-desc">{{ $description }}</span>
        @endif
    </a>
@endif
