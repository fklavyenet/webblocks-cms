@php
    $selectedPageId = (int) old('page_id', $block->page_id);
    $availableParents = $parentBlocks ?? collect();
    $selectedBlockTypeId = old('block_type_id', $selectedBlockType?->id ?? $block->block_type_id ?: $blockTypes->firstWhere('slug', $block->type)?->id);
    $selectedSlotTypeId = old('slot_type_id', $block->slot_type_id ?: $slotTypes->firstWhere('slug', $block->slot)?->id);
    $lockPage = $lockPage ?? false;
    $lockSlot = $lockSlot ?? false;
    $cancelUrl = $cancelUrl ?? (($selectedPageId && $selectedSlotTypeId) ? route('admin.pages.slots.blocks', ['page' => $selectedPageId, 'slot' => $selectedSlotTypeId]) : ($selectedPageId ? route('admin.pages.edit', $selectedPageId) : route('admin.blocks.index')));
    $submitLabel = $submitLabel ?? 'Save';
    $modeLabel = $modeLabel ?? ($block->exists ? 'Edit' : 'Create');
    $actionsContainerClass = $actionsContainerClass ?? 'wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap';
    $activeTab = $activeTab ?? old('_slot_block_tab', 'block-fields');
    $assetPickerAssets = $assetPickerAssets ?? collect();
    $assetPickerFolders = $assetPickerFolders ?? collect();
    $columnItemBlockType = $columnItemBlockType ?? null;
    $featureItemBlockType = $featureItemBlockType ?? null;
    $linkListItemBlockType = $linkListItemBlockType ?? null;
    $activeLocale = $activeLocale ?? null;
    $statusValue = old('status', $block->exists ? $block->status : ($block->status ?: 'published'));
@endphp

<div class="wb-stack wb-gap-4">
    <input type="hidden" name="block_type_id" value="{{ $selectedBlockTypeId }}">
    <input type="hidden" name="source_type" value="{{ $selectedBlockType?->source_type ?? $block->source_type ?? 'static' }}">
    <input type="hidden" name="_slot_block_tab" value="{{ $activeTab }}" data-wb-slot-block-tab-input>

    <div class="wb-tabs" data-wb-tabs data-wb-slot-block-tabs>
        <div class="wb-tabs-nav" role="tablist" aria-label="Block editor sections">
            <button type="button" class="wb-tabs-btn {{ $activeTab === 'block-info' ? 'is-active' : '' }}" data-wb-tab="slot-block-info-panel" aria-selected="{{ $activeTab === 'block-info' ? 'true' : 'false' }}" @if ($activeTab !== 'block-info') tabindex="-1" @endif>Block Info</button>
            <button type="button" class="wb-tabs-btn {{ $activeTab === 'block-fields' ? 'is-active' : '' }}" data-wb-tab="slot-block-fields-panel" aria-selected="{{ $activeTab === 'block-fields' ? 'true' : 'false' }}" @if ($activeTab !== 'block-fields') tabindex="-1" @endif>Block Fields</button>
            <button type="button" class="wb-tabs-btn {{ $activeTab === 'settings' ? 'is-active' : '' }}" data-wb-tab="slot-block-settings-panel" aria-selected="{{ $activeTab === 'settings' ? 'true' : 'false' }}" @if ($activeTab !== 'settings') tabindex="-1" @endif>Settings</button>
        </div>

        <div class="wb-tabs-panels">
            <div class="wb-tabs-panel {{ $activeTab === 'block-info' ? 'is-active' : '' }}" id="slot-block-info-panel">
                <input type="hidden" name="page_id" value="{{ $selectedPageId }}">
                <input type="hidden" name="slot_type_id" value="{{ $selectedSlotTypeId }}">

                <div class="wb-grid wb-grid-3">
                    <div class="wb-stack wb-gap-1">
                        <label for="parent_id">Parent Block</label>
                        <select id="parent_id" name="parent_id" class="wb-select">
                            <option value="">No parent</option>
                            @foreach ($availableParents as $parent)
                                <option value="{{ $parent['id'] }}" @selected((string) old('parent_id', $block->parent_id) === (string) $parent['id'])>
                                    {{ $parent['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="sort_order">Sort Order</label>
                        <input id="sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $block->sort_order ?? 0) }}" required>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="wb-select">
                            <option value="draft" @selected($statusValue === 'draft')>draft</option>
                            <option value="published" @selected($statusValue === 'published')>published</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="wb-tabs-panel {{ $activeTab === 'block-fields' ? 'is-active' : '' }}" id="slot-block-fields-panel">
                <div class="wb-card wb-card-accent">
                    <div class="wb-card-header">
                        <strong>{{ ($selectedBlockType?->is_system ?? $block->is_system) ? 'System Block Config' : 'Content Fields' }} for {{ $selectedBlockType?->name ?? $block->typeName() }}</strong>
                    </div>
                    <div class="wb-card-body">
                        @include($block->adminFormView(), [
                            'block' => $block,
                            'selectedBlockType' => $selectedBlockType,
                            'assetPickerAssets' => $assetPickerAssets,
                            'assetPickerFolders' => $assetPickerFolders,
                            'columnItemBlockType' => $columnItemBlockType,
                            'featureItemBlockType' => $featureItemBlockType,
                            'linkListItemBlockType' => $linkListItemBlockType,
                        ])
                    </div>
                </div>
            </div>

            <div class="wb-tabs-panel {{ $activeTab === 'settings' ? 'is-active' : '' }}" id="slot-block-settings-panel">
                <div class="wb-card wb-card-accent">
                    <div class="wb-card-header">
                        <strong>Settings for {{ $selectedBlockType?->name ?? $block->typeName() }}</strong>
                    </div>
                    <div class="wb-card-body">
                        @includeIf('admin.blocks.settings.'.($selectedBlockType?->slug ?? $block->typeSlug()), [
                            'block' => $block,
                            'selectedBlockType' => $selectedBlockType,
                        ])

                        @unless (view()->exists('admin.blocks.settings.'.($selectedBlockType?->slug ?? $block->typeSlug())))
                            @include('admin.blocks.settings.fallback')
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-admin.form-actions
        :cancel-url="$cancelUrl"
        :submit-label="$submitLabel"
        :container-class="$actionsContainerClass"
    />
</div>
