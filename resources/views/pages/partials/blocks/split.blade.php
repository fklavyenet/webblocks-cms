@php(
  $class = trim(implode(' ', array_filter([
    'wb-split',
    $block->splitGapClass(),
    $block->splitAlignClass(),
    $block->splitWidthClass(),
  ])))
)
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
  @foreach ($block->children as $child)
    @include('webblocks-cms::pages.partials.block', ['block' => $child])
  @endforeach
</div>
