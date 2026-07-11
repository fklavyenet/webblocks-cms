@php
    $title = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $content = $block->stringValueOrNull($block->content) ?? $block->translatedTextFieldValue('content');
    $subtitle = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
@endphp

@if ($title !== null || $content !== null || $subtitle !== null)
    <div class="wb-sidebar-footer">
        @if ($title !== null || $content !== null)
            <div class="wb-callout {{ $block->sidebarFooterVariantClass() }}">
                @if ($title !== null)
                    <div class="wb-callout-title">{{ $title }}</div>
                @endif
                @if ($content !== null)
                    <p>{{ $content }}</p>
                @endif
            </div>
        @endif

        @if ($subtitle !== null)
            <p class="wb-text-xs wb-text-muted wb-mt-3 wb-mb-0">{{ $subtitle }}</p>
        @endif
    </div>
@endif
