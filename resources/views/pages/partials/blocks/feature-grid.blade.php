@php
    $delegate = $block->replicate();
    $delegate->setRelation(
        'children',
        $block->children
            ->filter(fn ($child) => in_array($child->typeSlug(), ['feature-item', 'column_item'], true))
            ->values()
    );
    $delegate->variant = 'cards';
@endphp

@include('pages.partials.blocks.columns', ['block' => $delegate])
