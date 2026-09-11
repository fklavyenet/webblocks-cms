@php(
  $class = trim(implode(' ', array_filter([
    'wb-stack',
    $block->stackSpacingClass(),
  ])))
)
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
  @foreach (app(\WebBlocks\Cms\Support\ContentSources\ContentCollectionRenderer::class)->children($block) as $child)
    @include('webblocks-cms::pages.partials.block', ['block' => $child])
  @endforeach
</div>
@include('webblocks-cms::pages.partials.blocks.content-collection-pagination', ['block' => $block])
