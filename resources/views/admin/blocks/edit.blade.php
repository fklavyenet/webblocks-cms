@php
    $blockPageName = $block->page?->title ?? 'Page';
    $blockSlotName = $block->slotType?->name ?? $block->slotName();
    $pageTitle = 'Edit Block: '.$block->typeName().' ('.$blockPageName.' / '.$blockSlotName.')';
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Update the block content, hierarchy, and publishing behavior together.',
    ])

    @if ($block->page)
        <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted">
            <span>{{ $block->page->title }}</span>
            <span>{{ $block->page->publicPath() }}</span>
            <span>{{ $block->page->slots->pluck('slotType.name')->filter()->implode(', ') ?: 'No slots yet' }}</span>
        </div>
    @endif

    @include('admin.partials.flash')

    @include('admin.blocks._type-picker', [
        'action' => route('admin.blocks.edit', $block),
        'selectedBlockType' => $selectedBlockType,
        'block' => $block,
        'blockTypes' => $blockTypes,
    ])

    <div class="wb-grid wb-grid-4">
        <div class="wb-card wb-card-muted"><div class="wb-card-body"><div class="wb-stat"><div class="wb-stat-label">Block Type</div><div class="wb-stat-value">{{ $block->typeName() }}</div></div></div></div>
        <div class="wb-card wb-card-muted"><div class="wb-card-body"><div class="wb-stat"><div class="wb-stat-label">Slot Type</div><div class="wb-stat-value">{{ $block->slotName() }}</div></div></div></div>
        <div class="wb-card wb-card-muted"><div class="wb-card-body"><div class="wb-stat"><div class="wb-stat-label">Page</div><div class="wb-stat-value">{{ $block->page?->title ?? '-' }}</div></div></div></div>
        <div class="wb-card wb-card-muted"><div class="wb-card-body"><div class="wb-stat"><div class="wb-stat-label">Status</div><div class="wb-stat-value">{{ $block->status }}</div></div></div></div>
    </div>

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.blocks.update', $block) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                @include('admin.blocks._form', [
                    'assetPickerAssets' => $assetPickerAssets ?? collect(),
                    'assetPickerFolders' => $assetPickerFolders ?? collect(),
                    'columnItemBlockType' => $columnItemBlockType ?? null,
                    'selectedAsset' => $selectedAsset ?? null,
                    'selectedGalleryAssets' => $selectedGalleryAssets ?? collect(),
                    'selectedAttachmentAsset' => $selectedAttachmentAsset ?? null,
                ])
            </form>
        </div>
    </div>
@endsection
