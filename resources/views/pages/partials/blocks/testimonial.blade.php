@php
    $delegate = $block->replicate();
    $delegate->setRelation('children', $block->children);
    $delegate->variant = 'testimonial';
@endphp

@include('webblocks-cms::pages.partials.blocks.quote', ['block' => $delegate])
