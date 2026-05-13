@php
    $pageLayoutUrl = route('admin.page-layouts.edit', $pageLayout);
@endphp

@extends('layouts.admin', ['title' => 'Add Page Layout Slot', 'heading' => 'Page Layouts'])

@section('content')
    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.page-layouts.index').'">Page Layouts</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pageLayoutUrl.'">'.e($pageLayout->name).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">Add Slot</span></li></ol></nav>',
        'title' => 'Add Page Layout Slot',
        'context' => '<span><code>'.e($pageLayout->handle).'</code></span>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.page-layouts.slots.store', $pageLayout) }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('admin.page-layout-slots._form')
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="$pageLayoutUrl" submit-label="Create Slot" />
            </div>
        </form>
    </div>
@endsection
