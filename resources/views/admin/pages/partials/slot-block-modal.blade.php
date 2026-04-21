@php
    $modalName = 'slot-block-editor';
    $isCreateMode = $slotModalMode === 'create';
    $isEditMode = $slotModalMode === 'edit';
    $showModal = $isCreateMode || $isEditMode;
    $slotName = $slot->slotType?->name ?? 'Slot';
    $pageName = $page->title;
    $blockName = $isCreateMode ? ($slotModalSelectedBlockType?->name ?? 'Block') : ($slotModalBlock?->typeName() ?? 'Block');
    $modalTitle = $isCreateMode
        ? 'Add Block: '.$blockName.' ('.$pageName.' / '.$slotName.')'
        : 'Edit Block: '.$blockName.' ('.$pageName.' / '.$slotName.')';
    $modalDescription = $isCreateMode
        ? 'Configure the new block, then save it back into the list.'
        : 'Update this block without leaving the slot management screen.';
    $expandedBlockQuery = trim((string) request('expanded'));
    $closeUrl = route('admin.pages.slots.blocks', [$page, $slot, 'picker' => $isCreateMode ? 1 : null, 'expanded' => $expandedBlockQuery !== '' ? $expandedBlockQuery : null, 'locale' => $activeLocale->is_default ? null : $activeLocale->code]);
    $activeTab = old('_slot_block_tab', 'block-fields');
@endphp

@if ($showModal && $slotModalBlock && $slotModalSelectedBlockType)
    <div class="wb-overlay-layer wb-overlay-layer--dialog">
        <div class="wb-overlay-backdrop"></div>

        <div class="wb-modal wb-modal-xl is-open" id="slot-block-editor-modal" role="dialog" aria-modal="true" aria-labelledby="slot-block-editor-title">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div class="wb-stack wb-gap-1">
                        <h2 class="wb-modal-title" id="slot-block-editor-title">{{ $modalTitle }}</h2>
                        <span class="wb-text-sm wb-text-muted">{{ $modalDescription }}</span>
                    </div>

                    <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </a>
                </div>

                <div class="wb-modal-body wb-stack wb-gap-4">
                    <form method="POST" action="{{ $isCreateMode ? route('admin.blocks.store') : route('admin.blocks.update', $slotModalBlock) }}" class="wb-stack wb-gap-4">
                        @csrf
                        @if ($isEditMode)
                            @method('PUT')
                        @endif

                        <input type="hidden" name="_slot_block_mode" value="{{ $slotModalMode }}">
                        <input type="hidden" name="_slot_block_id" value="{{ $slotModalBlock->id }}">
                        @unless ($activeLocale->is_default)
                            <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                        @endunless

                        @include('admin.blocks._form', [
                            'block' => $slotModalBlock,
                            'selectedBlockType' => $slotModalSelectedBlockType,
                            'pages' => collect([$page]),
                            'parentBlocks' => $slotParentBlocks,
                            'blockTypes' => $blockTypes,
                            'columnItemBlockType' => $columnItemBlockType,
                            'slotTypes' => collect([$slot->slotType])->filter(),
                            'assetPickerAssets' => $assetPickerAssets,
                            'assetPickerFolders' => $assetPickerFolders,
                            'selectedAsset' => $slotModalSelectedAsset,
                            'selectedGalleryAssets' => $slotModalSelectedGalleryAssets,
                            'selectedAttachmentAsset' => $slotModalSelectedAttachmentAsset,
                            'lockPage' => true,
                            'lockSlot' => true,
                            'cancelUrl' => $closeUrl,
                            'submitLabel' => $isCreateMode ? 'Save New Block' : 'Save Changes',
                            'activeTab' => $activeTab,
                            'activeLocale' => $activeLocale,
                        ])

                        <div class="wb-modal-footer">
                            <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Cancel</a>
                            <button type="submit" class="wb-btn wb-btn-primary">{{ $isCreateMode ? 'Save New Block' : 'Save Changes' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
