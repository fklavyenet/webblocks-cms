@php
    $modalName = 'slot-block-editor';
    $isCreateMode = $slotModalMode === 'create';
    $isEditMode = $slotModalMode === 'edit';
    $showModal = $isCreateMode || $isEditMode;
    $slotBlockModalLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $slotBlockModalTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $blockFormText = fn (string $key, array $replace = []) => $slotBlockModalTranslator->admin('block_form.'.$key, $slotBlockModalLocale, $replace);
    $blockTypeFormText = fn (string $key, array $replace = []) => $slotBlockModalTranslator->admin('block_type_form.'.$key, $slotBlockModalLocale, $replace);
    $slotName = $slot->slotType?->name ?? $blockFormText('slot_fallback');
    $pageName = $page->title;
    $blockName = $isCreateMode ? ($slotModalSelectedBlockType?->name ?? $blockFormText('block_fallback')) : ($slotModalBlock?->typeName() ?? $blockFormText('block_fallback'));
    $modalTitle = $isCreateMode
        ? $blockFormText('add_modal_title', ['block' => $blockName, 'page' => $pageName, 'slot' => $slotName])
        : $blockFormText('edit_modal_title', ['block' => $blockName, 'page' => $pageName, 'slot' => $slotName]);
    $modalDescription = $isCreateMode
        ? $blockFormText('add_modal_description')
        : $blockFormText('edit_modal_description');
    $editorRouteName = $editorRouteName ?? 'admin.pages.slots.blocks';
    $editorRouteParameters = $editorRouteParameters ?? [$page, $slot];
    $closeUrl = route($editorRouteName, $editorRouteParameters + ['picker' => $isCreateMode ? 1 : null, 'parent_id' => $isCreateMode ? (request()->integer('parent_id') ?: null) : null, 'locale' => $activeLocale->is_default ? null : $activeLocale->code]);
    $activeTab = old('_slot_block_tab', 'block-fields');
@endphp

@if ($showModal && $slotModalBlock && $slotModalSelectedBlockType)
    <div class="wb-modal wb-modal-xl" id="slot-block-editor-modal" role="dialog" aria-modal="true" aria-labelledby="slot-block-editor-title" data-wb-admin-close-url="{{ $closeUrl }}" data-wb-slot-block-modal-autoload data-wb-admin-autoload-overlay hidden>
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="slot-block-editor-title">{{ $modalTitle }}</h2>
                    <span class="wb-text-sm wb-text-muted">{{ $modalDescription }}</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $blockFormText('close_modal') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <div class="wb-modal-body wb-stack wb-gap-4">
                @if ($errors->any())
                    <div class="wb-alert wb-alert-danger">
                        <div>
                            <div class="wb-alert-title">{{ $blockTypeFormText('validation_error') }}</div>
                            <div>{{ $errors->first() }}</div>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ $isCreateMode ? route('admin.blocks.store') : route('admin.blocks.update', $slotModalBlock) }}" class="wb-stack wb-gap-4" data-wb-admin-dirty-form data-wb-admin-dirty-close-confirm="{{ $blockFormText('discard_changes') }}">
                    @csrf
                    @if ($isEditMode)
                        @method('PUT')
                    @endif

                    <input type="hidden" name="_slot_block_mode" value="{{ $slotModalMode }}">
                    <input type="hidden" name="_slot_block_id" value="{{ $slotModalBlock->id }}">
                    <input type="hidden" name="return_url" value="{{ request('return_url') }}">
                    @if (! empty($sharedSlot ?? null))
                        <input type="hidden" name="shared_slot_id" value="{{ $sharedSlot->id }}">
                    @endif
                    @unless ($activeLocale->is_default)
                        <input type="hidden" name="locale" value="{{ $activeLocale->code }}">
                    @endunless

                    @include('webblocks-cms::admin.blocks._form', [
                        'block' => $slotModalBlock,
                        'selectedBlockType' => $slotModalSelectedBlockType,
                        'pages' => collect([$page]),
                        'parentBlocks' => $slotParentBlocks,
                        'blockTypes' => $blockTypes,
                        'columnItemBlockType' => $columnItemBlockType,
                        'featureItemBlockType' => $featureItemBlockType,
                        'linkListItemBlockType' => $linkListItemBlockType,
                        'slotTypes' => collect([$slot->slotType])->filter(),
                        'assetPickerAssets' => $assetPickerAssets,
                        'assetPickerFolders' => $assetPickerFolders,
                        'selectedAsset' => $slotModalSelectedAsset,
                        'selectedGalleryAssets' => $slotModalSelectedGalleryAssets,
                        'selectedAttachmentAsset' => $slotModalSelectedAttachmentAsset,
                        'lockPage' => true,
                        'lockSlot' => true,
                        'cancelUrl' => $closeUrl,
                        'actionsContainerClass' => 'wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap',
                        'submitLabel' => $isCreateMode ? $blockFormText('save_new_block') : $blockFormText('save_block'),
                        'modeLabel' => $isCreateMode ? $blockFormText('create') : $blockFormText('edit'),
                        'activeTab' => $activeTab,
                        'activeLocale' => $activeLocale,
                    ])
                </form>
            </div>
        </div>
    </div>
@endif
