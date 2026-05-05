@php($class = collect(['wb-container', $block->containerWidthClass(), 'wb-stack'])->filter()->implode(' '))
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach ($block->children as $child)
        @include('pages.partials.block', ['block' => $child])
    @endforeach
</div>
