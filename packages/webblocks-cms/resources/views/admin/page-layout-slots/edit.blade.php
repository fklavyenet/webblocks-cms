@php
    $pageLayoutUrl = route('admin.page-layouts.edit', $pageLayout);
    $pageLayoutsText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.page_layouts.'.$key, $replace);
    $pageTitle = $pageLayoutsText('edit_slot_title', ['name' => $pageLayout->name]);
    $slotContext = $pageLayoutSlot->label ?: $pageLayoutSlot->slot_name;
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => __('webblocks-cms::admin.page_layouts.title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="'.e($pageLayoutsText('breadcrumb')).'"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.page-layouts.index').'">'.e($pageLayoutsText('title')).'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pageLayoutUrl.'">'.e($pageLayout->name).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($slotContext).'</span></li></ol></nav>',
        'title' => $pageTitle,
        'context' => '<span class="wb-status-pill wb-status-info">'.e($pageLayoutsText('slot_context')).'</span> <code>'.e($pageLayoutSlot->slot_name).'</code>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.page-layouts.slots.update', [$pageLayout, $pageLayoutSlot]) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <div class="wb-card-body">
                @include('webblocks-cms::admin.page-layout-slots._form')
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="$pageLayoutUrl" :submit-label="$pageLayoutsText('save_slot')" />
            </div>
        </form>
    </div>
@endsection
