@php
    $level = in_array($block->variant, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $block->variant : 'h2';
    $anchor = trim((string) ($block->url ?? ''));
@endphp

<{{ $level }} @if($anchor !== '')id="{{ $anchor }}"@endif>{{ $block->title ?: $block->content }}</{{ $level }}>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
