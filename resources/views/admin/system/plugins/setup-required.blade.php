@php
    $pluginSetupLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $pluginSetupText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('plugin_setup.'.$key, $pluginSetupLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $pluginSetupText('heading')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pluginSetupText('title'),
        'description' => $pluginSetupText('description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $pluginSetupText('pending') }}</strong>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-alert wb-alert-warning">{{ $message }}</div>
            <p>{{ $pluginSetupText('help') }}</p>
        </div>
        <div class="wb-card-footer">
            <a href="{{ $pluginDetailUrl }}" class="wb-btn wb-btn-primary">
                <i class="wb-icon wb-icon-settings" aria-hidden="true"></i>
                {{ $pluginSetupText('open_setup') }}
            </a>
        </div>
    </div>
@endsection
