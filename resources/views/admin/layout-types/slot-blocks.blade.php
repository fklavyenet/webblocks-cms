@php
    $slotTitle = 'Edit Layout Slot: '.$layoutType->name.' / '.($slot->slotType?->name ?? 'Slot');
    $layoutTypesIndexUrl = route('admin.layout-types.index');
    $slotBlockTreeScriptPath = public_path('assets/webblocks-cms/js/admin/slot-block-tree.js');
@endphp

@extends('layouts.admin', ['title' => $slotTitle, 'heading' => $slotTitle])

@section('content')
    @php
        $slotBlockRoute = function (array $parameters = []) use ($layoutType, $slot) {
            return route('admin.layout-types.slots.blocks', [$layoutType, $slot] + $parameters);
        };

        $slotBlockBaseRoute = function (array $parameters = []) use ($layoutType, $slot) {
            return route('admin.layout-types.slots.blocks', [$layoutType, $slot] + $parameters);
        };

        $wrapperPreset = old('wrapper_preset', $slot->wrapperPreset());
        $wrapperElement = old('wrapper_element', $slot->wrapperElement());
    @endphp

    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$layoutTypesIndexUrl.'">Layout Types</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.layout-types.edit', $layoutType).'">'.$layoutType->name.'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.($slot->slotType?->name ?? 'Slot').'</span></li></ol></nav>',
        'title' => $slotTitle,
        'actions' => '<a href="'.route('admin.layout-types.edit', $layoutType).'" class="wb-btn wb-btn-secondary">Back to Layout Type</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Slot Settings</strong>
            <span class="wb-text-sm wb-text-muted">Shared structural wrapper settings for public rendering.</span>
        </div>
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.layout-types.slots.settings.update', [$layoutType, $slot]) }}" class="wb-grid wb-grid-3">
                @csrf
                @method('PUT')
                <div class="wb-stack wb-gap-1">
                    <label for="wrapper_element">Wrapper element</label>
                    <select id="wrapper_element" name="wrapper_element" class="wb-select">
                        @foreach (\App\Models\PageSlot::allowedWrapperElements() as $element)
                            <option value="{{ $element }}" @selected($wrapperElement === $element)>{{ $element }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="wrapper_preset">Wrapper preset</label>
                    <select id="wrapper_preset" name="wrapper_preset" class="wb-select">
                        <option value="default" @selected($wrapperPreset === 'default')>Default</option>
                        <option value="docs-navbar" @selected($wrapperPreset === 'docs-navbar')>Docs Navbar</option>
                        <option value="docs-sidebar" @selected($wrapperPreset === 'docs-sidebar')>Docs Sidebar</option>
                        <option value="docs-main" @selected($wrapperPreset === 'docs-main')>Docs Main</option>
                        <option value="plain" @selected($wrapperPreset === 'plain')>Plain</option>
                    </select>
                </div>
                <div class="wb-stack wb-gap-1 wb-justify-end">
                    <label class="wb-text-sm wb-text-muted">Apply</label>
                    <button type="submit" class="wb-btn wb-btn-secondary">Save Slot Settings</button>
                </div>
            </form>
        </div>
    </div>

    <div class="wb-card" data-wb-cms-slot-block-tree data-wb-slot-id="{{ $slot->id }}" data-slot-type-id="{{ $slot->slot_type_id }}">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-stack wb-gap-1">
                <strong>Blocks</strong>
                <span class="wb-text-sm wb-text-muted">Manage shared blocks for this layout-owned slot.</span>
            </div>
            <a href="{{ $slotBlockRoute(['picker' => 1]) }}" class="wb-btn wb-btn-secondary" data-wb-slot-block-link data-base-url="{{ $slotBlockBaseRoute(['picker' => 1]) }}">Add Block</a>
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
                    <table class="wb-table wb-table-striped wb-table-hover" data-wb-slot-block-table data-admin-sortable-list data-admin-sortable-mode="slot-blocks" data-admin-sortable-reorder-url="{{ route('admin.layout-types.slots.blocks.reorder', [$layoutType, $slot]) }}">
                        <thead>
                            <tr>
                                <th>Block Type</th>
                                <th>Summary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        @foreach ($blocks as $block)
                            @include('admin.layout-types.partials.slot-block-row', [
                                'block' => $block,
                                'depth' => 0,
                                'parentBlock' => null,
                                'layoutType' => $layoutType,
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
    @include('admin.layout-types.partials.slot-block-picker', [
        'layoutType' => $layoutType,
        'slot' => $slot,
        'blockTypes' => $blockTypes,
        'pickerSearch' => $pickerSearch,
        'isPickerOpen' => $isPickerOpen,
        'slotModalMode' => $slotModalMode,
        'pickerBlockTypes' => $pickerBlockTypes,
        'pickerParentBlock' => $pickerParentBlock,
        'activeLocale' => $activeLocale,
    ])

    @include('admin.layout-types.partials.slot-block-modal', [
        'layoutType' => $layoutType,
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
        'columnItemBlockType' => $columnItemBlockType,
        'featureItemBlockType' => $featureItemBlockType,
        'linkListItemBlockType' => $linkListItemBlockType,
        'activeLocale' => $activeLocale,
    ])
@endpush

@push('scripts')
    @if (is_file($slotBlockTreeScriptPath))
        <script src="{{ asset('assets/webblocks-cms/js/admin/slot-block-tree.js') }}?v={{ filemtime($slotBlockTreeScriptPath) }}" defer></script>
    @endif
@endpush
