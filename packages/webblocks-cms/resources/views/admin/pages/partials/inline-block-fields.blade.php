@php
    $prefix = "blocks[{$index}]";
    $selectedBlockTypeId = old("{$prefix}.block_type_id", $block->block_type_id ?: $selectedBlockType?->id);
    $selectedSlotTypeId = old("{$prefix}.slot_type_id", $block->slot_type_id ?: $slotTypes->firstWhere('slug', $block->slot)?->id);
    $packageInlineView = 'webblocks-cms::admin.blocks.types.'.$block->typeSlug().'-inline';
    $legacyInlineView = 'admin.blocks.types.'.$block->typeSlug().'-inline';
    $fallbackInlineView = view()->exists('webblocks-cms::admin.blocks.types.fallback-inline')
        ? 'webblocks-cms::admin.blocks.types.fallback-inline'
        : 'admin.blocks.types.fallback-inline';
    $inlineView = view()->exists($packageInlineView)
        ? $packageInlineView
        : (view()->exists($legacyInlineView) ? $legacyInlineView : $fallbackInlineView);
    $inlineBlocksText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.inline_blocks.'.$key, $replace);
@endphp

<input type="hidden" name="{{ $prefix }}[id]" value="{{ $block->id }}">
<input type="hidden" name="{{ $prefix }}[_delete]" value="0" data-wb-inline-delete>
<input type="hidden" name="{{ $prefix }}[sort_order]" value="{{ $index }}" data-wb-inline-sort>

<div class="wb-grid wb-grid-4">
    <div class="wb-stack wb-gap-1">
        <label>{{ $inlineBlocksText('block_type') }}</label>
        <input type="hidden" name="{{ $prefix }}[block_type_id]" value="{{ $selectedBlockTypeId }}">
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                <strong>{{ $selectedBlockType?->name ?? $block->typeName() }}</strong>
                <div>{{ $selectedBlockType?->description ?: $inlineBlocksText('block_type_help') }}</div>
            </div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_slot_type_id">{{ $inlineBlocksText('slot_type') }}</label>
        <select id="block_{{ $index }}_slot_type_id" name="{{ $prefix }}[slot_type_id]" class="wb-select">
            @foreach ($slotTypes as $slotType)
                <option value="{{ $slotType->id }}" @selected((string) $selectedSlotTypeId === (string) $slotType->id)>{{ $slotType->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_status">{{ $inlineBlocksText('status') }}</label>
        <select id="block_{{ $index }}_status" name="{{ $prefix }}[status]" class="wb-select">
            <option value="draft" @selected(old("{$prefix}.status", $block->status ?: 'published') === 'draft')>{{ $inlineBlocksText('draft') }}</option>
            <option value="published" @selected(old("{$prefix}.status", $block->status ?: 'published') === 'published')>{{ $inlineBlocksText('published') }}</option>
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label>{{ $inlineBlocksText('kind') }}</label>
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                <strong>{{ $selectedBlockType?->kindLabel() ?? ($block->is_system ? $inlineBlocksText('system_block') : $inlineBlocksText('content_block')) }}</strong>
            </div>
        </div>
    </div>
</div>

<div class="wb-card wb-card-accent">
    <div class="wb-card-body">
        @include($inlineView, [
            'block' => $block,
            'selectedBlockType' => $selectedBlockType,
            'slotTypes' => $slotTypes,
            'index' => $index,
            'prefix' => $prefix,
        ])
    </div>
</div>
