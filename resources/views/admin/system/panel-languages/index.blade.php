@php
    $text = static fn (string $key) => __('webblocks-cms::panel-languages.'.$key);
    $defaultErrors = $errors->getBag('panelLanguageDefault');
    $uploadError = $errors->has('package');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('language_packs'), 'heading' => $text('language_packs')])

@section('content')
    @component('webblocks-cms::admin.partials.page-header', ['title' => $text('language_packs')])
        @slot('actions')
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="modal" data-wb-target="#panel-language-default-modal"
                    aria-controls="panel-language-default-modal" aria-haspopup="dialog">
                <i class="wb-icon wb-icon-settings" aria-hidden="true"></i> {{ $text('default') }}
            </button>
            @if ($extensionsAvailable)
                <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.panel-languages.extensions.index') }}">
                    <i class="wb-icon wb-icon-search" aria-hidden="true"></i> {{ $text('extensions_browse') }}
                </a>
            @endif
        @endslot
    @endcomponent
    @include('webblocks-cms::admin.system.panel-languages.feedback', ['inlineErrors' => $uploadError && $manualInstallAvailable ? ['package'] : []])

    @if ($manualInstallAvailable)
        @include('webblocks-cms::admin.partials.extension-upload-card', [
            'id' => 'panel-language-install',
            'title' => $text('manual_install'),
            'action' => route('admin.system.panel-languages.store'),
            'fieldId' => 'panel-language-package',
            'field' => 'package',
            'fileLabel' => $text('package').' (.zip)',
            'help' => $text('package_help'),
            'error' => $errors->first('package'),
        ])
    @else
        <p class="wb-alert wb-alert-warning" role="status">{{ $text('storage_unavailable') }}</p>
    @endif

    <section class="wb-card" aria-labelledby="installed-language-packs-title">
        <div class="wb-card-header">
            <h2 id="installed-language-packs-title" class="wb-card-title">{{ $text('installed_packs') }}</h2>
        </div>
        <div class="wb-card-body">
            @if ($packages->isEmpty())
                <div class="wb-empty"><div class="wb-empty-title">{{ $text('empty') }}</div></div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('webblocks-cms::admin.extensions_ui.name') }}</th>
                                <th scope="col">{{ __('webblocks-cms::admin.system_plugins_index.version') }}</th>
                                <th scope="col">{{ $text('status') }}</th>
                                <th scope="col">{{ $text('source_version') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($packages as $pack)
                                @php($current = $pack['source_version'] === \WebBlocks\Cms\Support\WebBlocks::VERSION)
                                <tr>
                                    <th scope="row" class="wb-table-break"><span lang="{{ $pack['code'] }}">{{ $pack['name'] }}</span> <code>{{ $pack['code'] }}</code></th>
                                    <td>{{ $pack['release_version'] ?? '—' }}</td>
                                    <td><span class="wb-status-pill {{ $current ? 'wb-status-active' : 'wb-status-pending' }}">{{ $text($current ? 'extensions_installed' : 'different_cms') }}</span></td>
                                    <td>{{ $pack['source_version'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('overlays')
    <dialog class="wb-modal" id="panel-language-default-modal" aria-labelledby="panel-language-default-title"
            @if ($defaultErrors->any() || request()->query('tab') === 'settings') data-wb-admin-autoload-overlay @endif>
        <form method="POST" action="{{ route('admin.system.panel-languages.default') }}" class="wb-modal-dialog">
            @csrf
            @method('PUT')
            <div class="wb-modal-header">
                <h2 id="panel-language-default-title" class="wb-modal-title">{{ $text('default') }}</h2>
                <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ __('webblocks-cms::admin.common.close') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </button>
            </div>
            <div class="wb-modal-body">
                <div class="wb-field">
                    <label class="wb-label" for="panel-language-default">{{ $text('language') }}</label>
                    <select class="wb-select" id="panel-language-default" name="code"
                            aria-describedby="panel-language-default-help{{ $defaultErrors->any() ? ' panel-language-default-error' : '' }}"
                            @if ($defaultErrors->any()) aria-invalid="true" @endif required>
                        @foreach ($options as $code => $label)
                            <option value="{{ $code }}" @selected($code === $defaultLocale)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="wb-text-sm wb-text-muted wb-m-0" id="panel-language-default-help">{{ $text('default_help') }}</p>
                    @if ($defaultErrors->any())
                        <p class="wb-alert wb-alert-danger" id="panel-language-default-error" role="alert">{{ $defaultErrors->first() }}</p>
                    @endif
                </div>
            </div>
            <div class="wb-modal-footer">
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ __('webblocks-cms::admin.common.cancel') }}</button>
                <button type="submit" class="wb-btn wb-btn-primary">{{ $text('save_default') }}</button>
            </div>
        </form>
    </dialog>

@endpush
