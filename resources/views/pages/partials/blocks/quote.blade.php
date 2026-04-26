@php
    $quoteVariant = $block->variant === 'testimonial' ? 'testimonial' : 'default';
@endphp

@if ($quoteVariant === 'testimonial')
    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            <blockquote class="wb-stack wb-gap-2">
                <p class="wb-m-0">{{ $block->content }}</p>
                @if ($block->title || $block->subtitle)
                    <footer>{{ $block->title }}{{ $block->subtitle ? ' | '.$block->subtitle : '' }}</footer>
                @endif
            </blockquote>
        </div>
    </div>
@else
    <blockquote class="wb-stack wb-gap-2">
        <p class="wb-m-0">{{ $block->content }}</p>
        @if ($block->title || $block->subtitle)
            <footer>{{ $block->title }}{{ $block->subtitle ? ' | '.$block->subtitle : '' }}</footer>
        @endif
    </blockquote>
@endif

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
