@php
    $slotTitle = 'Edit Shared Slot Blocks: '.$sharedSlot->name;
    $sharedSlotsIndexUrl = route('admin.shared-slots.index', ['site' => $sharedSlot->site_id]);
    $slotBlockTreeScriptPath = public_path('assets/webblocks-cms/js/admin/slot-block-tree.js');
@endphp

@extends('layouts.admin', ['title' => $slotTitle, 'heading' => $slotTitle])

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

    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$sharedSlotsIndexUrl.'">Shared Slots</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.shared-slots.edit', $sharedSlot).'">'.e($sharedSlot->name).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">Blocks</span></li></ol></nav>',
        'title' => $slotTitle,
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.shared-slots.edit', $sharedSlot).'" class="wb-btn wb-btn-secondary">Back to Shared Slot</a></div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Public Wrapper</strong>
            <span class="wb-text-sm wb-text-muted">Resolved automatically from the page shell and slot name.</span>
        </div>
        <div class="wb-card-body">
            <p class="wb-text-sm wb-text-muted">This Shared Slot provides only the inner block tree. Pages still own the public shell and wrapper for the matching slot.</p>
        </div>
    </div>

    <div class="wb-card" data-wb-cms-slot-block-tree data-wb-shared-slot-id="{{ $sharedSlot->id }}" data-page-id="{{ $sourcePage->id }}" data-slot-type-id="{{ $slot->slot_type_id }}">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-stack wb-gap-1">
                <strong>Blocks</strong>
                <span class="wb-text-sm wb-text-muted">Editing content for {{ strtoupper($activeLocale->code) }}. Structure and ordering remain canonical for this Shared Slot.</span>
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
                        <a href="{{ $slotBlockRoute(['locale' => $locale->code, 'edit' => request('edit'), 'picker' => request()->boolean('picker') ? 1 : null, 'block_type_id' => request('block_type_id'), 'block_type_search' => request('block_type_search'), 'block_type_category' => request('block_type_category'), 'block_type_sort' => request('block_type_sort')]) }}" class="wb-btn {{ $isActiveLocale ? 'wb-btn-primary' : 'wb-btn-secondary' }}">{{ strtoupper($locale->code) }}</a>
                    @endforeach
                </div>
                <span class="wb-text-sm wb-text-muted">Shared Slot translations use the same block translation behavior as page-owned slot content.</span>
            </div>
        </div>

        @if ($blocks->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No blocks in this Shared Slot yet</div>
                    <div class="wb-empty-text">Use Add Block to start populating this Shared Slot.</div>
                </div>
            </div>
        @else
            <div class="wb-card-body">
                <div class="wb-table-wrap wb-admin-slot-blocks-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover wb-admin-slot-blocks-table" data-wb-slot-block-table data-admin-sortable-list data-admin-sortable-mode="slot-blocks" data-admin-sortable-reorder-url="{{ route('admin.shared-slots.blocks.reorder', $sharedSlot) }}">
                        <thead>
                            <tr>
                                <th>Block Type</th>
                                <th>Summary</th>
                                <th>Children</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        @foreach ($blocks as $block)
                            @include('admin.pages.partials.slot-block-row', [
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
            <a href="{{ $slotBlockRoute(['picker' => 1]) }}" class="wb-btn wb-btn-primary" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['picker' => 1]) }}">Add Block</a>
        </div>
    </div>
@endsection

@push('overlays')
    @include('admin.pages.partials.slot-block-picker', [
        'page' => $sourcePage,
        'slot' => $slot,
        'blockTypes' => $blockTypes,
        'pickerSearch' => $pickerSearch,
        'pickerCategory' => $pickerCategory,
        'isPickerOpen' => $isPickerOpen,
        'slotModalMode' => $slotModalMode,
    ])

    @include('admin.pages.partials.slot-block-modal', [
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
@endpush

@push('scripts')
    @if (is_file($slotBlockTreeScriptPath))
        <script src="{{ asset('assets/webblocks-cms/js/admin/slot-block-tree.js') }}?v={{ filemtime($slotBlockTreeScriptPath) }}" defer></script>
    @endif
@endpush
