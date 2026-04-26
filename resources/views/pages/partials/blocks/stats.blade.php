@php
    $delegate = $block->replicate();
    $delegate->setRelation('children', $block->children);
    $delegate->variant = 'stats';
@endphp

@include('pages.partials.blocks.columns', ['block' => $delegate])
