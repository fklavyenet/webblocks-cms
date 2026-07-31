@php
    $pluginSetupLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $pluginSetupText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('plugin_setup.'.$key, $pluginSetupLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $settings->labelText(),
        'description' => $settings->descriptionText() ?? $pluginSetupText('settings_description'),
    ])

    <p><a href="{{ route('admin.system.plugins.show', $plugin->handle()) }}">{{ $pluginSetupText('plugin_detail') }}</a></p>

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $plugin->labelText() }}</strong>
        </div>

        <div class="wb-card-body">
            <div class="wb-empty">
                <div class="wb-empty-title">{{ $pluginSetupText('settings_registered') }}</div>
                <div class="wb-empty-text">{{ $pluginSetupText('settings_foundation') }}</div>
            </div>
        </div>
    </div>
@endsection
