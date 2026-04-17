@php
    $imageSource = $block->asset?->url();
@endphp

@if ($imageSource)
    <figure class="wb-stack wb-gap-2">
        <img src="{{ $imageSource }}" alt="{{ $block->asset?->alt_text ?: $block->subtitle ?: $block->title ?: 'Image block' }}">

        @if ($block->title)
            <figcaption>{{ $block->title }}</figcaption>
        @endif
    </figure>
@endif

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
