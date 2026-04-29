@php($class = trim('wb-container '.($block->containerWidthClass() ?? '')))
<div class="{{ $class }}">
    @foreach ($block->children as $child)
        @include('pages.partials.block', ['block' => $child])
    @endforeach
</div>
