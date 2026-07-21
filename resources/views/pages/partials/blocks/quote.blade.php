@php
    $quoteVariant = $block->variant === 'testimonial' ? 'testimonial' : 'default';
@endphp

@if ($quoteVariant === 'testimonial')
    <blockquote class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-2">
            <p class="wb-m-0">{{ $block->content }}</p>
            @if ($block->title || $block->subtitle)
                <footer class="wb-text-sm wb-text-muted">{{ $block->title }}{{ $block->subtitle ? ' | '.$block->subtitle : '' }}</footer>
            @endif
        </div>
    </blockquote>
@else
    <blockquote class="wb-stack wb-gap-2">
        <p class="wb-m-0">{{ $block->content }}</p>
        @if ($block->title || $block->subtitle)
            <footer>{{ $block->title }}{{ $block->subtitle ? ' | '.$block->subtitle : '' }}</footer>
        @endif
    </blockquote>
@endif
