@php
    $delegate = $block->replicate();
    $delegate->setRelation('children', $block->children);
    $delegate->variant = 'cards';
@endphp

@include('pages.partials.blocks.columns', ['block' => $delegate])
