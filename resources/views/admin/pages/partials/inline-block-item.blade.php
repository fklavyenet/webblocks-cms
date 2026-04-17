@php
    $selectedType = $blockTypes->firstWhere('id', $block->block_type_id) ?? $blockTypes->firstWhere('slug', $block->type);
@endphp

<div class="wb-card wb-card-muted" data-wb-inline-block>
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-stack wb-gap-1">
            <strong>{{ $selectedType?->name ?? $block->typeName() }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $block->title ?: 'No title yet' }}</span>
        </div>

        <div class="wb-action-group">
            <button type="button" class="wb-action-btn" data-wb-inline-move="up" title="Move block up" aria-label="Move block up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn" data-wb-inline-move="down" title="Move block down" aria-label="Move block down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn" data-wb-inline-toggle title="Collapse block" aria-label="Collapse block"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-inline-remove title="Remove block" aria-label="Remove block"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="wb-card-body" data-wb-inline-body>
        @include('admin.pages.partials.inline-block-fields', [
            'block' => $block,
            'index' => $index,
            'blockTypes' => $blockTypes,
            'slotTypes' => $slotTypes,
            'selectedBlockType' => $selectedType,
        ])
    </div>
</div>
