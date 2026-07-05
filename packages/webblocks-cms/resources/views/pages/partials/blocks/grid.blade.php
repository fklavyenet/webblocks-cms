@php
    $alternateSections = $block->gridAlternatesMediaTextSections();
    $class = $alternateSections
        ? trim('wb-stack '.($block->gridGapClass() ?? ''))
        : trim('wb-grid '.$block->gridColumnsClass().' '.($block->gridGapClass() ?? ''));
    $sectionIndex = 0;
@endphp
<div class="{{ $class }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">
    @foreach ($block->children as $child)
        @if ($alternateSections && $child->typeSlug() === 'section')
            @include('webblocks-cms::pages.partials.blocks.grid-alternating-section', [
                'block' => $child,
                'parentGrid' => $block,
                'sectionIndex' => $sectionIndex,
            ])
            @php($sectionIndex++)
        @else
            @include('webblocks-cms::pages.partials.block', ['block' => $child])
        @endif
    @endforeach
</div>
