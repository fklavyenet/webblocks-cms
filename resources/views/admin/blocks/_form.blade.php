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
    $activeLocale = $activeLocale ?? null;
    $translationStatus = $activeLocale ? $block->translationStatus($activeLocale) : null;
    $isDefaultLocale = $activeLocale?->is_default ?? false;
    $isTranslatableBlock = $block->supportsTranslations();
@endphp

<div class="wb-stack wb-gap-4">
    <input type="hidden" name="block_type_id" value="{{ $selectedBlockTypeId }}">
    <input type="hidden" name="source_type" value="{{ $selectedBlockType?->source_type ?? $block->source_type ?? 'static' }}">
    <input type="hidden" name="_slot_block_tab" value="{{ $activeTab }}" data-wb-slot-block-tab-input>

    <div class="wb-tabs" data-wb-tabs data-wb-slot-block-tabs>
        <div class="wb-tabs-nav" role="tablist" aria-label="Block editor sections">
            <button type="button" class="wb-tabs-btn {{ $activeTab === 'block-info' ? 'is-active' : '' }}" data-wb-tab="slot-block-info-panel" aria-selected="{{ $activeTab === 'block-info' ? 'true' : 'false' }}" @if ($activeTab !== 'block-info') tabindex="-1" @endif>Block Info</button>
            <button type="button" class="wb-tabs-btn {{ $activeTab === 'block-fields' ? 'is-active' : '' }}" data-wb-tab="slot-block-fields-panel" aria-selected="{{ $activeTab === 'block-fields' ? 'true' : 'false' }}" @if ($activeTab !== 'block-fields') tabindex="-1" @endif>Block Fields</button>
        </div>

        <div class="wb-tabs-panels">
            <div class="wb-tabs-panel {{ $activeTab === 'block-info' ? 'is-active' : '' }}" id="slot-block-info-panel">
                <div class="wb-stack wb-gap-4">
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <label for="page_id">Page</label>
                            @if ($lockPage)
                                <input type="hidden" name="page_id" value="{{ $selectedPageId }}">
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body">
                                        <strong>{{ $pages->firstWhere('id', $selectedPageId)?->title ?? 'Selected page' }}</strong>
                                    </div>
                                </div>
                            @else
                                <select id="page_id" name="page_id" class="wb-select" required>
                                    <option value="">Select page</option>
                                    @foreach ($pages as $pageOption)
                                        <option value="{{ $pageOption->id }}" @selected($selectedPageId === $pageOption->id)>{{ $pageOption->title }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

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
                    </div>

                    <div class="wb-grid wb-grid-4">
                        <div class="wb-stack wb-gap-1">
                            <label>Block Type</label>
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-body">
                                    <strong>{{ $selectedBlockType?->name ?? $block->typeName() }}</strong>
                                    <div>{{ $selectedBlockType?->description ?: 'This block type defines the current builder behavior.' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="slot_type_id">Slot Type</label>
                            @if ($lockSlot)
                                <input type="hidden" name="slot_type_id" value="{{ $selectedSlotTypeId }}">
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body">
                                        <strong>{{ $slotTypes->firstWhere('id', $selectedSlotTypeId)?->name ?? 'Selected slot' }}</strong>
                                    </div>
                                </div>
                            @else
                                <select id="slot_type_id" name="slot_type_id" class="wb-select" required>
                                    @foreach ($slotTypes as $slotType)
                                        <option value="{{ $slotType->id }}" @selected((string) $selectedSlotTypeId === (string) $slotType->id)>{{ $slotType->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="sort_order">Sort Order</label>
                            <input id="sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $block->sort_order ?? 0) }}" required>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label>Mode</label>
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-body">
                                    <strong>{{ $modeLabel }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="wb-select">
                                <option value="draft" @selected(old('status', $block->status ?: 'published') === 'draft')>draft</option>
                                <option value="published" @selected(old('status', $block->status ?: 'published') === 'published')>published</option>
                            </select>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label>Kind</label>
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-body">
                                    <strong>{{ $selectedBlockType?->kindLabel() ?? ($block->is_system ? 'System Block' : 'Content Block') }}</strong>
                                    <div>{{ ($selectedBlockType?->is_system ?? $block->is_system) ? 'Runtime output comes from application data and compact block config.' : 'Runtime output comes from editorial fields authored on this block.' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($activeLocale)
                        <div class="wb-grid wb-grid-2">
                            <div class="wb-stack wb-gap-1">
                                <label>Locale</label>
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body">
                                        <strong>{{ strtoupper($activeLocale->code) }}{{ $activeLocale->is_default ? ' (Default)' : '' }}</strong>
                                        <div>{{ $activeLocale->name }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="wb-stack wb-gap-1">
                                <label>Translation Status</label>
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body">
                                        <strong>{{ $translationStatus['label'] ?? 'Shared' }}</strong>
                                        <div>
                                            @if (! $isTranslatableBlock)
                                                This block uses shared canonical fields across all locales.
                                            @elseif ($isDefaultLocale)
                                                Default locale edits update the default translation row.
                                            @elseif (($translationStatus['state'] ?? null) === 'fallback')
                                                This locale is currently falling back to {{ strtoupper($translationStatus['resolved_locale']->code) }} until you save translated content.
                                            @elseif (($translationStatus['state'] ?? null) === 'missing')
                                                This locale does not have translated content yet.
                                            @else
                                                This locale has its own translated content.
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
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
                            ])
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
