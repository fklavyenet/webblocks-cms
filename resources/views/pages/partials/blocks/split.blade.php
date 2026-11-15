@php
  $reverseOnMobile = $block->splitResponsiveClass() !== null && $block->children->count() === 2
    && \WebBlocks\Cms\Support\Blocks\MobileContentOrder::reversesPair($block->appearanceSetting('mobile_order'), $block->children->first(), $block->children->last());
  $class = trim(implode(' ', array_filter([
    'wb-split',
    $block->splitGapClass(),
    $block->splitAlignClass(),
    $block->splitWidthClass(),
    $block->splitResponsiveClass(),
    $reverseOnMobile ? 'wb-public-split--reverse-mobile' : null,
  ])));
@endphp
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
  @foreach ($block->children as $child)
    @include('webblocks-cms::pages.partials.block', ['block' => $child])
  @endforeach
</div>
