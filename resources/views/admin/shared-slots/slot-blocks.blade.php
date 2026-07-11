@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('shared_slots.'.$key, $adminLocale, $replace);
  $slotTitle = $adminText('edit_blocks_title', ['name' => $sharedSlot->name]);
  $sharedSlotsIndexUrl = route('admin.shared-slots.index', ['site' => $sharedSlot->site_id]);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $slotTitle, 'heading' => $slotTitle])

@section('content')
    @php
        $slotBlockRoute = function (array $parameters = []) use ($sharedSlot, $activeLocale) {
            $resolved = $parameters;

            if (! array_key_exists('locale', $resolved) && ! $activeLocale->is_default) {
                $resolved['locale'] = $activeLocale->code;
            }

            return route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot] + $resolved);
        };

        $slotBlockBaseRoute = function (array $parameters = []) use ($sharedSlot, $activeLocale) {
            if (! array_key_exists('locale', $parameters) && ! $activeLocale->is_default) {
                $parameters['locale'] = $activeLocale->code;
            }

            return route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot] + $parameters);
        };
    @endphp

    @include('webblocks-cms::admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="'.e($adminText('breadcrumb')).'"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$sharedSlotsIndexUrl.'">'.e($adminText('title')).'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.shared-slots.edit', $sharedSlot).'">'.e($sharedSlot->name).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($adminText('blocks')).'</span></li></ol></nav>',
        'title' => $slotTitle,
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.shared-slots.edit', $sharedSlot).'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_shared_slot')).'</a></div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>{{ $adminText('public_wrapper') }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('public_wrapper_help') }}</span>
        </div>
        <div class="wb-card-body">
            <p class="wb-text-sm wb-text-muted">{{ $adminText('inner_block_tree_help') }}</p>
        </div>
    </div>

    <div class="wb-card" data-wb-cms-slot-block-tree data-wb-shared-slot-id="{{ $sharedSlot->id }}" data-page-id="{{ $sourcePage->id }}" data-slot-type-id="{{ $slot->slot_type_id }}">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-stack wb-gap-1">
                <strong>{{ $adminText('blocks') }}</strong>
                <span class="wb-text-sm wb-text-muted">{{ $adminText('editing_locale_help', ['locale' => strtoupper($activeLocale->code)]) }}</span>
            </div>
            <div class="wb-cluster wb-cluster-2">
                @if (! $blocks->isEmpty())
                    <a href="{{ $slotBlockRoute(['delete_all' => 1]) }}" class="wb-btn wb-btn-ghost wb-text-danger" aria-haspopup="dialog">{{ $adminText('delete_all_blocks') }}</a>
                @endif
                <a href="{{ $slotBlockRoute(['picker' => 1]) }}" class="wb-btn wb-btn-secondary" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['picker' => 1]) }}">{{ $adminText('add_block') }}</a>
            </div>
        </div>

        <div class="wb-card-body wb-border-b">
            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <div class="wb-cluster wb-cluster-2">
                    @foreach ($availableLocales as $translationStatus)
                        @php
                            $locale = $translationStatus['locale'];
                            $isActiveLocale = $locale->id === $activeLocale->id;
                        @endphp
                        <a href="{{ $slotBlockRoute(['locale' => $locale->code, 'edit' => request('edit'), 'picker' => request()->boolean('picker') ? 1 : null, 'block_type_id' => request('block_type_id'), 'block_type_tab' => request('block_type_tab'), 'block_type_search' => request('block_type_search'), 'block_type_category' => request('block_type_category'), 'block_type_sort' => request('block_type_sort')]) }}" class="wb-btn {{ $isActiveLocale ? 'wb-btn-primary' : 'wb-btn-secondary' }}">{{ strtoupper($locale->code) }}</a>
                    @endforeach
                </div>
                <span class="wb-text-sm wb-text-muted">{{ $adminText('translations_help') }}</span>
            </div>
        </div>

        @if ($blocks->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('empty_blocks_title') }}</div>
                    <div class="wb-empty-text">{{ $adminText('empty_blocks_help') }}</div>
                </div>
            </div>
        @else
            <div class="wb-card-body">
                <div class="wb-table-wrap wb-admin-slot-blocks-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover wb-admin-slot-blocks-table" data-wb-slot-block-table data-admin-sortable-list data-admin-sortable-mode="slot-blocks" data-admin-sortable-reorder-url="{{ route('admin.shared-slots.blocks.reorder', $sharedSlot) }}">
                        <thead>
                            <tr>
                                <th>{{ $adminText('block_type') }}</th>
                                <th>{{ $adminText('summary') }}</th>
                                <th>{{ $adminText('children') }}</th>
                                <th>{{ $adminText('status') }}</th>
                                <th>{{ $adminText('actions') }}</th>
                            </tr>
                        </thead>

                        @foreach ($blocks as $block)
                            @include('webblocks-cms::admin.pages.partials.slot-block-row', [
                                'block' => $block,
                                'depth' => 0,
                                'parentBlock' => null,
                                'page' => $sourcePage,
                                'slot' => $slot,
                                'slotBlockRoute' => $slotBlockRoute,
                                'slotBlockBaseRoute' => $slotBlockBaseRoute,
                                'activeLocale' => $activeLocale,
                                'expandedBlockIds' => $expandedBlockIds,
                                'sharedSlot' => $sharedSlot,
                            ])
                        @endforeach
                    </table>
                </div>
            </div>
        @endif

        <div class="wb-card-footer">
            <a href="{{ $slotBlockRoute(['picker' => 1]) }}" class="wb-btn wb-btn-primary" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['picker' => 1]) }}">{{ $adminText('add_block') }}</a>
        </div>
    </div>
@endsection

@push('overlays')
    @include('webblocks-cms::admin.pages.partials.slot-block-picker', [
        'page' => $sourcePage,
        'slot' => $slot,
        'blockTypes' => $blockTypes,
        'slotBlockRoute' => $slotBlockRoute,
        'slotBlockBaseRoute' => $slotBlockBaseRoute,
        'pickerSearch' => $pickerSearch,
        'pickerCategory' => $pickerCategory,
        'isPickerOpen' => $isPickerOpen,
        'slotModalMode' => $slotModalMode,
    ])

    @include('webblocks-cms::admin.pages.partials.slot-block-modal', [
        'page' => $sourcePage,
        'slot' => $slot,
        'sharedSlot' => $sharedSlot,
        'editorRouteName' => 'admin.shared-slots.blocks.edit',
        'editorRouteParameters' => ['shared_slot' => $sharedSlot],
        'blockTypes' => $blockTypes,
        'slotModalMode' => $slotModalMode,
        'slotModalBlock' => $slotModalBlock,
        'slotModalSelectedBlockType' => $slotModalSelectedBlockType,
        'assetPickerAssets' => $assetPickerAssets,
        'assetPickerFolders' => $assetPickerFolders,
        'slotModalSelectedAsset' => $slotModalSelectedAsset,
        'slotModalSelectedGalleryAssets' => $slotModalSelectedGalleryAssets,
        'slotModalSelectedAttachmentAsset' => $slotModalSelectedAttachmentAsset,
        'slotParentBlocks' => $slotParentBlocks,
    ])

    @include('webblocks-cms::admin.pages.partials.slot-block-delete-modal', [
        'page' => $sourcePage,
        'slot' => $slot,
        'sharedSlot' => $sharedSlot,
        'slotBlockRoute' => $slotBlockRoute,
        'slotDeleteModalBlock' => $slotDeleteModalBlock,
        'slotDeleteModalMeta' => $slotDeleteModalMeta,
        'slotDeleteAllModalMeta' => $slotDeleteAllModalMeta,
        'activeLocale' => $activeLocale,
    ])
@endpush

@push('admin-scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin-sortable-list.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/inline-block-builder.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/builder-items.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/page-builder-modals.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/slot-block-delete-modal.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/slot-block-tree.js'])
@endpush
