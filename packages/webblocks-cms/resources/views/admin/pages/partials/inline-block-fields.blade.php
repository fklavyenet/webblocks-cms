@php
    $prefix = "blocks[{$index}]";
    $selectedBlockTypeId = old("{$prefix}.block_type_id", $block->block_type_id ?: $selectedBlockType?->id);
    $selectedSlotTypeId = old("{$prefix}.slot_type_id", $block->slot_type_id ?: $slotTypes->firstWhere('slug', $block->slot)?->id);
    $inlineView = 'admin.blocks.types.'.$block->typeSlug().'-inline';
@endphp

<input type="hidden" name="{{ $prefix }}[id]" value="{{ $block->id }}">
<input type="hidden" name="{{ $prefix }}[_delete]" value="0" data-wb-inline-delete>
<input type="hidden" name="{{ $prefix }}[sort_order]" value="{{ $index }}" data-wb-inline-sort>

<div class="wb-grid wb-grid-4">
    <div class="wb-stack wb-gap-1">
        <label>Block Type</label>
        <input type="hidden" name="{{ $prefix }}[block_type_id]" value="{{ $selectedBlockTypeId }}">
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                <strong>{{ $selectedBlockType?->name ?? $block->typeName() }}</strong>
                <div>{{ $selectedBlockType?->description ?: 'This block type defines the current inline block behavior.' }}</div>
            </div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_slot_type_id">Slot Type</label>
        <select id="block_{{ $index }}_slot_type_id" name="{{ $prefix }}[slot_type_id]" class="wb-select">
            @foreach ($slotTypes as $slotType)
                <option value="{{ $slotType->id }}" @selected((string) $selectedSlotTypeId === (string) $slotType->id)>{{ $slotType->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_status">Status</label>
        <select id="block_{{ $index }}_status" name="{{ $prefix }}[status]" class="wb-select">
            <option value="draft" @selected(old("{$prefix}.status", $block->status ?: 'published') === 'draft')>draft</option>
            <option value="published" @selected(old("{$prefix}.status", $block->status ?: 'published') === 'published')>published</option>
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label>Kind</label>
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                <strong>{{ $selectedBlockType?->kindLabel() ?? ($block->is_system ? 'System Block' : 'Content Block') }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="wb-card wb-card-accent">
    <div class="wb-card-body">
        @include(view()->exists($inlineView) ? $inlineView : 'admin.blocks.types.fallback-inline', [
            'block' => $block,
            'selectedBlockType' => $selectedBlockType,
            'slotTypes' => $slotTypes,
            'index' => $index,
            'prefix' => $prefix,
        ])
    </div>
</div>
