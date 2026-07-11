@php($class = collect(['wb-container', $block->containerWidthClass(), $block->containerFlowClass()])->filter()->implode(' '))
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach ($block->children as $child)
        @include('webblocks-cms::pages.partials.block', ['block' => $child])
    @endforeach
</div>
