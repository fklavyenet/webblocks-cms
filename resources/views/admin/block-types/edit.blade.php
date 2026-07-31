@php
    $blockTypeFormLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $blockTypeFormText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('block_type_form.'.$key, $blockTypeFormLocale, $replace);
    $pageTitle = $blockTypeFormText('edit_title', ['name' => $blockType->name]);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $blockTypeFormText('edit_description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.block-types.update', $blockType) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_url" value="{{ $blockTypesReturnUrl }}">

            <div class="wb-card-body">
                @include('webblocks-cms::admin.block-types._form')
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="$blockTypesReturnUrl" :submit-label="$blockTypeFormText('save_changes')" />
            </div>
        </form>
    </div>
@endsection
