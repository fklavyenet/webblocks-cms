@php
    $blockFormText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.block_form.'.$key, $replace);
    $blockPageName = $block->page?->title ?? $blockFormText('page_fallback');
    $blockSlotName = $block->slotType?->name ?? $block->slotName();
    $pageTitle = $blockFormText('edit_title', ['type' => $block->typeName(), 'page' => $blockPageName, 'slot' => $blockSlotName]);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $blockFormText('edit_description'),
    ])

    @if ($block->page)
        <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted">
            <span>{{ $block->page->title }}</span>
            <span>{{ $block->page->publicPath() }}</span>
            <span>{{ $block->page->slots->pluck('slotType.name')->filter()->implode(', ') ?: $blockFormText('no_slots_yet') }}</span>
        </div>
    @endif

    @include('webblocks-cms::admin.partials.flash')

    @include('webblocks-cms::admin.blocks._type-picker', [
        'action' => route('admin.blocks.edit', $block),
        'selectedBlockType' => $selectedBlockType,
        'block' => $block,
        'blockTypes' => $blockTypes,
    ])

    <div class="wb-grid wb-grid-4">
        <div class="wb-card wb-card-muted"><div class="wb-card-body"><div class="wb-stat"><div class="wb-stat-label">{{ $blockFormText('block_type') }}</div><div class="wb-stat-value">{{ $block->typeName() }}</div></div></div></div>
        <div class="wb-card wb-card-muted"><div class="wb-card-body"><div class="wb-stat"><div class="wb-stat-label">{{ $blockFormText('slot_type') }}</div><div class="wb-stat-value">{{ $block->slotName() }}</div></div></div></div>
        <div class="wb-card wb-card-muted"><div class="wb-card-body"><div class="wb-stat"><div class="wb-stat-label">{{ $blockFormText('page') }}</div><div class="wb-stat-value">{{ $block->page?->title ?? '-' }}</div></div></div></div>
        <div class="wb-card wb-card-muted"><div class="wb-card-body"><div class="wb-stat"><div class="wb-stat-label">{{ $blockFormText('status') }}</div><div class="wb-stat-value">{{ $block->status }}</div></div></div></div>
    </div>

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.blocks.update', $block) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <div class="wb-card-body">
                @include('webblocks-cms::admin.blocks._form', [
                    'assetPickerAssets' => $assetPickerAssets ?? collect(),
                    'assetPickerFolders' => $assetPickerFolders ?? collect(),
                    'columnItemBlockType' => $columnItemBlockType ?? null,
                    'linkListItemBlockType' => $linkListItemBlockType ?? null,
                    'selectedAsset' => $selectedAsset ?? null,
                    'selectedGalleryAssets' => $selectedGalleryAssets ?? collect(),
                    'selectedAttachmentAsset' => $selectedAttachmentAsset ?? null,
                ])
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.blocks.index')" :submit-label="$blockFormText('save_changes')" />
            </div>
        </form>
    </div>
@endsection
