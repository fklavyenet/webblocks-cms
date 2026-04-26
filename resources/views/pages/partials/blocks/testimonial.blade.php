@php
    $delegate = $block->replicate();
    $delegate->setRelation('children', $block->children);
    $delegate->variant = 'testimonial';
@endphp

@include('pages.partials.blocks.quote', ['block' => $delegate])
