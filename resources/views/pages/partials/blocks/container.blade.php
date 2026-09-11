@php($class = collect(['wb-container', $block->containerWidthClass(), $block->containerFlowClass()])->filter()->implode(' '))
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach (app(\WebBlocks\Cms\Support\ContentSources\ContentCollectionRenderer::class)->children($block) as $child)
        @include('webblocks-cms::pages.partials.block', ['block' => $child])
    @endforeach
</div>
@include('webblocks-cms::pages.partials.blocks.content-collection-pagination', ['block' => $block])
