@php
    $href = $block->sidebarLinkUrl() ?? '/';
    $target = $block->sidebarLinkTarget() === '_blank';
    $title = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $subtitle = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
    $logoUrl = $block->asset?->isImage() ? $block->asset?->url() : null;
    $hasCopy = $title !== null || $subtitle !== null;
@endphp

@if ($logoUrl !== null || $hasCopy)
    <a href="{{ $href }}" class="wb-sidebar-brand"@if ($target) target="_blank" rel="noopener noreferrer"@endif>
        @if ($logoUrl !== null)
            <img src="{{ $logoUrl }}" alt="" class="wb-sidebar-brand-logo">
        @endif

        @if ($hasCopy)
            <span class="wb-sidebar-brand-copy">
                @if ($title !== null)
                    <strong>{{ $title }}</strong>
                @endif
                @if ($subtitle !== null)
                    <span>{{ $subtitle }}</span>
                @endif
            </span>
        @endif
    </a>
@endif
