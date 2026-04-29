@php($class = trim('wb-grid '.$block->gridColumnsClass().' '.($block->gridGapClass() ?? '')))
<div class="{{ $class }}">
    @foreach ($block->children as $child)
        @include('pages.partials.block', ['block' => $child])
    @endforeach
</div>
