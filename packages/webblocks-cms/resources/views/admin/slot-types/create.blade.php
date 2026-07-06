@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('slot_types.'.$key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('create_title'), 'heading' => $adminText('create_title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('create_title'),
        'description' => $adminText('create_description'),
    ])

    <div class="wb-card">
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-alert wb-alert-info">
                <div>
                    <div class="wb-alert-title">{{ $adminText('read_only') }}</div>
                    <div>{{ $adminText('catalog_help') }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
