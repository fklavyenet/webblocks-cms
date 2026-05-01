@php
    $slotTitle = 'Edit Slot: '.($slot->slotType?->name ?? 'Slot').' ('.$page->title.')';
    $activePreviewUrl = $page->isPublished() ? $page->publicUrl($activeLocale->code) : null;
    $pagesIndexUrl = route('admin.pages.index', ['site' => $page->site_id]);
    $siteName = $page->site?->name ?? 'Site';
    $slotBlockTreeScriptPath = public_path('assets/webblocks-cms/js/admin/slot-block-tree.js');
@endphp

@extends('layouts.admin', ['title' => $slotTitle, 'heading' => $slotTitle])

@section('content')
    @php
        $slotBlockRoute = function (array $parameters = []) use ($page, $slot, $activeLocale) {
            $resolved = $parameters;

            if (! array_key_exists('locale', $resolved) && ! $activeLocale->is_default) {
                $resolved['locale'] = $activeLocale->code;
            }

            return route('admin.pages.slots.blocks', [$page, $slot] + $resolved);
        };

        $slotBlockBaseRoute = function (array $parameters = []) use ($page, $slot, $activeLocale) {
            if (! array_key_exists('locale', $parameters) && ! $activeLocale->is_default) {
                $parameters['locale'] = $activeLocale->code;
            }

            return route('admin.pages.slots.blocks', [$page, $slot] + $parameters);
        };
    @endphp

    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">Pages</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.$siteName.'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.pages.edit', $page).'">'.$page->title.'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.($slot->slotType?->name ?? 'Slot').'</span></li></ol></nav>',
        'title' => $slotTitle,
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.pages.edit', $page).'" class="wb-btn wb-btn-secondary">Back to Page Slots</a>'.($activePreviewUrl ? '<a href="'.$activePreviewUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>View Page</span></a>' : '').'</div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card" data-wb-cms-slot-block-tree data-wb-slot-id="{{ $slot->id }}" data-page-id="{{ $page->id }}" data-slot-type-id="{{ $slot->slot_type_id }}">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-stack wb-gap-1">
                <strong>Blocks</strong>
                <span class="wb-text-sm wb-text-muted">Editing content for {{ strtoupper($activeLocale->code) }}. Structure, ordering, and shared block config remain canonical.</span>
            </div>
            <a href="{{ $slotBlockRoute(['picker' => 1]) }}" class="wb-btn wb-btn-secondary" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['picker' => 1]) }}">Add Block</a>
        </div>

        <div class="wb-card-body wb-border-b">
            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <div class="wb-cluster wb-cluster-2">
                    @foreach ($availableLocales as $translationStatus)
                        @php
                            $locale = $translationStatus['locale'];
                            $isActiveLocale = $locale->id === $activeLocale->id;
                        @endphp
                        <a href="{{ $slotBlockRoute(['locale' => $locale->code, 'edit' => request('edit'), 'picker' => request()->boolean('picker') ? 1 : null, 'block_type_id' => request('block_type_id'), 'block_type_search' => request('block_type_search')]) }}" class="wb-btn {{ $isActiveLocale ? 'wb-btn-primary' : 'wb-btn-secondary' }}">
                            {{ strtoupper($locale->code) }}
                        </a>
                    @endforeach
                </div>
                <span class="wb-text-sm wb-text-muted">Page route translation and block content translation are edited separately.</span>
            </div>
        </div>

        @if ($blocks->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No blocks in this slot yet</div>
                    <div class="wb-empty-text">Use Add Block to start populating this slot.</div>
                </div>
            </div>
        @else
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover" data-wb-slot-block-table data-admin-sortable-list data-admin-sortable-mode="slot-blocks" data-admin-sortable-reorder-url="{{ route('admin.pages.slots.blocks.reorder', [$page, $slot]) }}">
                        <thead>
                            <tr>
                                <th>Block Type</th>
                                <th>Summary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        @foreach ($blocks as $block)
                            @include('admin.pages.partials.slot-block-row', [
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
            <a href="{{ $slotBlockRoute(['picker' => 1]) }}" class="wb-btn wb-btn-primary" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['picker' => 1]) }}">Add Block</a>
        </div>
    </div>

@endsection

@push('overlays')
    @include('admin.pages.partials.slot-block-picker', [
        'page' => $page,
        'slot' => $slot,
        'blockTypes' => $blockTypes,
        'pickerSearch' => $pickerSearch,
        'isPickerOpen' => $isPickerOpen,
        'slotModalMode' => $slotModalMode,
    ])

    @include('admin.pages.partials.slot-block-modal', [
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
@endpush

@push('scripts')
    @if (is_file($slotBlockTreeScriptPath))
        <script src="{{ asset('assets/webblocks-cms/js/admin/slot-block-tree.js') }}?v={{ filemtime($slotBlockTreeScriptPath) }}" defer></script>
    @endif
@endpush
