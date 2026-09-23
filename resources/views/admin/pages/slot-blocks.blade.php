@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('page_slot_blocks.'.$key, $adminLocale, $replace);
  $slotName = $slot->slotType?->name ?? $adminText('fallback_slot');
  $slotTitle = $adminText('title', ['slot' => $slotName, 'page' => $page->title]);
  $activePreviewUrl = $page->isPublished() ? $page->publicUrl($activeLocale->code) : null;
  $pagesIndexUrl = $pagesIndexUrl ?? session('page_return_url') ?? route('admin.pages.index', ['site' => $page->site_id]);
  $pageReturnUrl = $pageReturnUrl ?? $pagesIndexUrl;
  $siteName = $page->site?->name ?? $adminText('fallback_site');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $slotTitle, 'heading' => $slotTitle])

@section('content')
    @include('webblocks-cms::admin.blocks.partials.block-editor-assets')

    @php
        $slotBlockRoute = function (array $parameters = []) use ($page, $slot, $activeLocale, $pageReturnUrl) {
            $resolved = $parameters;

            if (! array_key_exists('return_url', $resolved)) {
                $resolved['return_url'] = $pageReturnUrl;
            }

            if (! array_key_exists('locale', $resolved) && ! $activeLocale->is_default) {
                $resolved['locale'] = $activeLocale->code;
            }

            return route('admin.pages.slots.blocks', [$page, $slot] + $resolved);
        };

        $slotBlockBaseRoute = function (array $parameters = []) use ($page, $slot, $activeLocale, $pageReturnUrl) {
            if (! array_key_exists('return_url', $parameters)) {
                $parameters['return_url'] = $pageReturnUrl;
            }

            if (! array_key_exists('locale', $parameters) && ! $activeLocale->is_default) {
                $parameters['locale'] = $activeLocale->code;
            }

            return route('admin.pages.slots.blocks', [$page, $slot] + $parameters);
        };
        $hasExpandableBlocks = $blocks->contains(fn ($block) => $block->children->isNotEmpty());
    @endphp

    @include('webblocks-cms::admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="'.e($adminText('breadcrumb')).'"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.e($adminText('pages')).'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.e($siteName).'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]).'">'.e($page->title).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($slotName).'</span></li></ol></nav>',
        'title' => $slotTitle,
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]).'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_page_slots')).'</a>'.($activePreviewUrl ? '<a href="'.$activePreviewUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>'.e($adminText('view_page')).'</span></a>' : '').'</div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-gap-4" data-wb-cms-slot-block-tree data-wb-slot-id="{{ $slot->id }}" data-page-id="{{ $page->id }}" data-slot-type-id="{{ $slot->slot_type_id }}">
        @unless ($blocks->isEmpty())
            <div class="wb-card wb-card-muted wb-admin-slot-block-search-card">
                <div class="wb-card-body">
                    @include('webblocks-cms::admin.partials.listing-filters', [
                        'action' => $slotBlockRoute(),
                        'search' => [
                            'id' => 'slot_block_search',
                            'name' => 'search',
                            'label' => $adminTranslator->admin('common.search', $adminLocale),
                            'value' => '',
                            'placeholder' => $adminText('search_placeholder'),
                        ],
                        'showActions' => false,
                        'liveSearch' => [
                            'clearLabel' => $adminText('clear_search'),
                            'emptyLabel' => $adminText('no_search_results'),
                        ],
                    ])
                </div>
            </div>
        @endunless

        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-admin-slot-block-toolbar">
            <div class="wb-cluster wb-cluster-2">
                <strong>{{ $adminText('blocks') }}</strong>
                @foreach ($availableLocales as $translationStatus)
                    @php
                        $locale = $translationStatus['locale'];
                        $isActiveLocale = $locale->id === $activeLocale->id;
                    @endphp
                    <a href="{{ $slotBlockRoute(['locale' => $locale->code, 'edit' => request('edit'), 'picker' => request()->boolean('picker') ? 1 : null, 'block_type_id' => request('block_type_id'), 'block_type_tab' => request('block_type_tab'), 'block_type_search' => request('block_type_search'), 'block_type_category' => request('block_type_category'), 'block_type_sort' => request('block_type_sort')]) }}" class="wb-btn wb-btn-sm {{ $isActiveLocale ? 'wb-btn-primary' : 'wb-btn-secondary' }}">{{ strtoupper($locale->code) }}</a>
                @endforeach
                <span class="wb-action-btn" role="img" tabindex="0" aria-label="{{ $adminText('editing_locale_help', ['locale' => strtoupper($activeLocale->code)]) }} {{ $adminText('translations_help') }}" title="{{ $adminText('editing_locale_help', ['locale' => strtoupper($activeLocale->code)]) }} {{ $adminText('translations_help') }}"><i class="wb-icon wb-icon-info" aria-hidden="true"></i></span>
            </div>
            <div class="wb-cluster wb-cluster-2">
                @if ($hasExpandableBlocks)
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-slot-block-expand-all data-expand-label="{{ $adminText('expand_all_blocks') }}" data-collapse-label="{{ $adminText('collapse_all_blocks') }}" aria-pressed="false">
                        <i class="wb-icon wb-icon-maximize2" aria-hidden="true"></i>
                        <span data-wb-slot-block-expand-all-label>{{ $adminText('expand_all_blocks') }}</span>
                    </button>
                @endif
                @if (! $blocks->isEmpty())
                    <a href="{{ $slotBlockRoute(['delete_all' => 1]) }}" class="wb-btn wb-btn-ghost wb-text-danger" aria-haspopup="dialog">{{ $adminText('delete_all_blocks') }}</a>
                @endif
                <a href="{{ $slotBlockRoute(['picker' => 1]) }}" class="wb-btn wb-btn-secondary" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['picker' => 1]) }}">{{ $adminText('add_block') }}</a>
            </div>
            </div>

        @if ($blocks->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('empty_title') }}</div>
                    <div class="wb-empty-text">{{ $adminText('empty_help') }}</div>
                </div>
            </div>
        @else
            <div class="wb-card-body">
                <div class="wb-table-wrap wb-admin-slot-blocks-table-wrap">
                    <table class="wb-table wb-table-sm wb-table-striped wb-table-hover wb-admin-slot-blocks-table" data-wb-slot-block-table data-admin-sortable-list data-admin-sortable-mode="slot-blocks" data-admin-sortable-reorder-url="{{ route('admin.pages.slots.blocks.reorder', [$page, $slot]) }}">
                        <thead>
                            <tr>
                                <th class="wb-admin-slot-block-id-cell">{{ $adminText('block_id') }}</th>
                                <th class="wb-admin-slot-block-type-cell">{{ $adminText('block_type') }}</th>
                                <th class="wb-admin-slot-block-summary-cell">{{ $adminText('summary') }}</th>
                                <th class="wb-cms-block-children-cell">{{ $adminText('children') }}</th>
                                <th class="wb-admin-slot-block-status-cell">{{ $adminText('status') }}</th>
                                <th class="wb-admin-slot-block-actions-cell">{{ $adminText('actions') }}</th>
                            </tr>
                        </thead>

                        @foreach ($blocks as $block)
                            @include('webblocks-cms::admin.pages.partials.slot-block-row', [
                                'block' => $block,
                                'depth' => 0,
                                'parentBlock' => null,
                                'page' => $page,
                                'slot' => $slot,
                                'slotBlockRoute' => $slotBlockRoute,
                                'slotBlockBaseRoute' => $slotBlockBaseRoute,
                                'activeLocale' => $activeLocale,
                                'expandedBlockIds' => $expandedBlockIds,
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
    </div>

@endsection

@push('overlays')
    @include('webblocks-cms::admin.pages.partials.slot-block-picker', [
        'page' => $page,
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
        'page' => $page,
        'slot' => $slot,
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
        'page' => $page,
        'slot' => $slot,
        'slotBlockRoute' => $slotBlockRoute,
        'slotDeleteModalBlock' => $slotDeleteModalBlock,
        'slotDeleteModalMeta' => $slotDeleteModalMeta,
        'slotDeleteAllModalMeta' => $slotDeleteAllModalMeta,
        'activeLocale' => $activeLocale,
    ])
@endpush

@push('admin-scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin-sortable-list.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/block-list-actions.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/page-builder-modals.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/slot-block-delete-modal.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/slot-block-tree.js'])
@endpush
