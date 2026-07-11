@php
    $pageLayoutUrl = route('admin.page-layouts.edit', $pageLayout);
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $pageLayoutsText = fn (string $key, array $replace = []) => $adminTranslator->admin('page_layouts.'.$key, $adminLocale, $replace);
    $pageTitle = $pageLayoutsText('add_slot_title', ['name' => $pageLayout->name]);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageLayoutsText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="'.e($pageLayoutsText('breadcrumb')).'"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.page-layouts.index').'">'.e($pageLayoutsText('title')).'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pageLayoutUrl.'">'.e($pageLayout->name).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($pageLayoutsText('add_slot_current')).'</span></li></ol></nav>',
        'title' => $pageTitle,
        'context' => '<span class="wb-status-pill wb-status-info">'.e($pageLayoutsText('layout')).'</span> <code>'.e($pageLayout->handle).'</code>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.page-layouts.slots.store', $pageLayout) }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('webblocks-cms::admin.page-layout-slots._form')
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="$pageLayoutUrl" :submit-label="$pageLayoutsText('create_slot')" />
            </div>
        </form>
    </div>
@endsection
