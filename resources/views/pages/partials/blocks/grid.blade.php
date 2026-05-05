@php($class = trim('wb-grid '.$block->gridColumnsClass().' '.($block->gridGapClass() ?? '')))
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach ($block->children as $child)
        @include('pages.partials.block', ['block' => $child])
    @endforeach
</div>
