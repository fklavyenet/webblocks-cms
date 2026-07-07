@php
    $blockFormText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.block_form.'.$key, $replace);
    $blockPageName = ($block->page_id && ($contextPage = $pages->firstWhere('id', $block->page_id))) ? $contextPage->title : $blockFormText('page_fallback');
    $blockSlotName = $slotTypes->firstWhere('id', (int) old('slot_type_id', $block->slot_type_id))?->name ?? ($block->slot ? str($block->slot)->headline()->toString() : $blockFormText('slot_fallback'));
    $blockName = $selectedBlockType?->name ?? $blockFormText('block_fallback');
    $pageTitle = $blockFormText('add_modal_title', ['block' => $blockName, 'page' => $blockPageName, 'slot' => $blockSlotName]);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $selectedBlockType ? $blockFormText('create_selected_description') : $blockFormText('create_choose_description'),
    ])

    @if ($block->page_id && ($page = $pages->firstWhere('id', $block->page_id)))
        <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted">
            <span>{{ $page->title }}</span>
            <span>{{ $page->publicPath() }}</span>
            <span>{{ $page->slots->pluck('slotType.name')->filter()->implode(', ') ?: $blockFormText('no_slots_yet') }}</span>
        </div>
    @endif

    @include('webblocks-cms::admin.partials.flash')

    @include('webblocks-cms::admin.blocks._type-picker', [
        'action' => route('admin.blocks.create'),
        'selectedBlockType' => $selectedBlockType,
        'block' => $block,
        'blockTypes' => $blockTypes,
    ])

    @if ($selectedBlockType)
        <div class="wb-card">
            <form method="POST" action="{{ route('admin.blocks.store') }}" class="wb-stack wb-gap-0">
                @csrf

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
                    <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.blocks.index')" :submit-label="$blockFormText('create')" />
                </div>
            </form>
        </div>
    @endif
@endsection
