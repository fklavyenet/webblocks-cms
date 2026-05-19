@php
    $pageLayoutUrl = route('admin.page-layouts.edit', $pageLayout);
    $pageTitle = 'Edit Page Layout Slot: '.$pageLayout->name;
    $slotContext = $pageLayoutSlot->label ?: $pageLayoutSlot->slot_name;
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => 'Page Layouts'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.page-layouts.index').'">Page Layouts</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pageLayoutUrl.'">'.e($pageLayout->name).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($slotContext).'</span></li></ol></nav>',
        'title' => $pageTitle,
        'context' => '<span class="wb-status-pill wb-status-info">Slot</span> <code>'.e($pageLayoutSlot->slot_name).'</code>',
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
                <x-webblocks-cms::admin.form-actions :cancel-url="$pageLayoutUrl" submit-label="Save Slot" />
            </div>
        </form>
    </div>
@endsection
