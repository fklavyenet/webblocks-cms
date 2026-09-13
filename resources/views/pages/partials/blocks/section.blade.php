@php
    $class = collect(['wb-section', $block->sectionSpacingClass(), $block->sectionFlowClass(), 'wb-stack', $block->publicBackgroundMediaClass()])->filter()->implode(' ');
    $backgroundStyle = $block->publicBackgroundMediaStyle();
@endphp

<section class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"@if ($backgroundStyle !== null) style="{{ $backgroundStyle }}"@endif>
    @foreach (app(\WebBlocks\Cms\Support\ContentSources\ContentCollectionRenderer::class)->children($block) as $child)
        @include('webblocks-cms::pages.partials.block', ['block' => $child])
    @endforeach
</section>
@include('webblocks-cms::pages.partials.blocks.content-collection-pagination', ['block' => $block])
