@php
    $href = $block->sidebarLinkUrl();
    $target = $block->sidebarLinkTarget() === '_blank';
    $title = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $subtitle = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
@endphp

@if ($href !== null && $title !== null)
    <a href="{{ $href }}" class="wb-sidebar-brand"@if ($target) target="_blank" rel="noopener noreferrer"@endif>
        <strong>{{ $title }}</strong>
        @if ($subtitle !== null)
            <span>{{ $subtitle }}</span>
        @endif
    </a>
@endif
