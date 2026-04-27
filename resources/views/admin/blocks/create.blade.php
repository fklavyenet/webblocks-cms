@php
    $blockPageName = ($block->page_id && ($contextPage = $pages->firstWhere('id', $block->page_id))) ? $contextPage->title : 'Page';
    $blockSlotName = $slotTypes->firstWhere('id', (int) old('slot_type_id', $block->slot_type_id))?->name ?? ($block->slot ? str($block->slot)->headline()->toString() : 'Slot');
    $blockName = $selectedBlockType?->name ?? 'Block';
    $pageTitle = 'Add Block: '.$blockName.' ('.$blockPageName.' / '.$blockSlotName.')';
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $selectedBlockType ? 'Fill out the editor for the selected block type.' : 'Choose the kind of block you want to add to the page.',
    ])

    @if ($block->page_id && ($page = $pages->firstWhere('id', $block->page_id)))
        <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted">
            <span>{{ $page->title }}</span>
            <span>{{ $page->publicPath() }}</span>
            <span>{{ $page->slots->pluck('slotType.name')->filter()->implode(', ') ?: 'No slots yet' }}</span>
        </div>
    @endif

    @include('admin.partials.flash')

    @include('admin.blocks._type-picker', [
        'action' => route('admin.blocks.create'),
        'selectedBlockType' => $selectedBlockType,
        'block' => $block,
        'blockTypes' => $blockTypes,
    ])

    @if ($selectedBlockType)
        <div class="wb-card">
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.blocks.store') }}" class="wb-stack wb-gap-4">
                    @csrf

                    @include('admin.blocks._form', [
                        'assetPickerAssets' => $assetPickerAssets ?? collect(),
                        'assetPickerFolders' => $assetPickerFolders ?? collect(),
                        'columnItemBlockType' => $columnItemBlockType ?? null,
                        'linkListItemBlockType' => $linkListItemBlockType ?? null,
                        'selectedAsset' => $selectedAsset ?? null,
                        'selectedGalleryAssets' => $selectedGalleryAssets ?? collect(),
                        'selectedAttachmentAsset' => $selectedAttachmentAsset ?? null,
                    ])
                </form>
            </div>
        </div>
    @endif
@endsection
