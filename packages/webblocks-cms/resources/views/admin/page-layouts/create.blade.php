@extends('webblocks-cms::layouts.admin', ['title' => __('webblocks-cms::admin.page_layouts.create_title'), 'heading' => __('webblocks-cms::admin.page_layouts.title')])

@php
    $pageLayoutsText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.page_layouts.'.$key, $replace);
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageLayoutsText('create_title'),
        'description' => $pageLayoutsText('create_description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.page-layouts.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('webblocks-cms::admin.page-layouts._form', ['pageLayout' => $pageLayout])
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.page-layouts.index')" :submit-label="$pageLayoutsText('create')" />
            </div>
        </form>
    </div>
@endsection
