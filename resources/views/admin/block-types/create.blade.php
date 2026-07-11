@extends('webblocks-cms::layouts.admin', ['title' => __('webblocks-cms::admin.block_type_form.create_title'), 'heading' => __('webblocks-cms::admin.block_type_form.create_title')])

@php
    $blockTypeFormText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.block_type_form.'.$key, $replace);
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $blockTypeFormText('create_title'),
        'description' => $blockTypeFormText('create_description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.block-types.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('webblocks-cms::admin.block-types._form')
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.block-types.index')" :submit-label="$blockTypeFormText('create')" />
            </div>
        </form>
    </div>
@endsection
