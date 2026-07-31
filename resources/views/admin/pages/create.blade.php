@php
    $pageFormLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $pageFormText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('page_form.'.$key, $pageFormLocale, $replace);
    $pageTitle = $pageFormText('create_title');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $pageFormText('create_description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.pages.store') }}" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                @include('webblocks-cms::admin.pages._form')
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.pages.index')" :submit-label="$pageFormText('create')" />
            </div>
        </form>
    </div>
@endsection
