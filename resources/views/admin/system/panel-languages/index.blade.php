@php
    $text = static fn (string $key) => __('webblocks-cms::panel-languages.'.$key);
    $uploadError = $errors->has('package');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('language_packs'), 'heading' => $text('language_packs')])

@section('content')
    @component('webblocks-cms::admin.partials.page-header', ['title' => $text('language_packs')])
        @slot('actions')
            @if ($extensionsAvailable)
                <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.panel-languages.extensions.index') }}">
                    <i class="wb-icon wb-icon-search" aria-hidden="true"></i> {{ $text('extensions_browse') }}
                </a>
            @endif
        @endslot
    @endcomponent
    @include('webblocks-cms::admin.system.panel-languages.feedback', ['inlineErrors' => $uploadError && $manualInstallAvailable ? ['package'] : []])

    @unless ($manualInstallAvailable)
        <p class="wb-alert wb-alert-warning" role="status">{{ $text('storage_unavailable') }}</p>
    @endunless
    @include('webblocks-cms::admin.partials.extension-list-filter', [
        'id' => 'panel-language-search',
        'indexUrl' => route('admin.system.panel-languages.index'),
        'term' => $search,
    ])

    <section class="wb-card" aria-labelledby="installed-language-packs-title">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div>
                <h2 id="installed-language-packs-title" class="wb-card-title">{{ $text('installed_packs') }}</h2>
                <p class="wb-card-description">{{ __('webblocks-cms::admin_lists.languages') }}</p>
            </div>
            @if ($manualInstallAvailable)
                @include('webblocks-cms::admin.partials.extension-upload-button', ['target' => 'panel-language-install'])
            @endif
        </div>
        <div class="wb-card-body">
            @if ($packages->isEmpty())
                <div class="wb-empty">
                    <p class="wb-empty-title">{{ $search === '' ? $text('empty') : __('webblocks-cms::admin.extensions_ui.no_matches') }}</p>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('webblocks-cms::admin.extensions_ui.name') }}</th>
                                <th scope="col">{{ $text('status') }}</th>
                                <th scope="col">{{ __('webblocks-cms::admin.system_plugins_index.version') }}</th>
                                <th scope="col">{{ $text('source_version') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($packages as $pack)
                                @php
                                    $current = $pack['source_version'] === \WebBlocks\Cms\Support\WebBlocks::VERSION;
                                    $status = $text($current ? 'extensions_installed' : 'different_cms');
                                    $detailsId = 'language-pack-details-'.$pack['code'];
                                @endphp
                                <tr>
                                    <td class="wb-table-break">
                                        @include('webblocks-cms::admin.partials.extension-row-name', [
                                            'name' => $pack['name'],
                                            'identifier' => $pack['code'],
                                            'target' => $detailsId,
                                            'language' => $pack['code'],
                                        ])
                                    </td>
                                    <td><span class="wb-status-pill {{ $current ? 'wb-status-active' : 'wb-status-pending' }}">{{ $status }}</span></td>
                                    <td>{{ $pack['release_version'] ?? '—' }}</td>
                                    <td>{{ $pack['source_version'] }}</td>
                                </tr>
                                @push('overlays')
                                    @include('webblocks-cms::admin.partials.extension-details-modal', [
                                        'id' => $detailsId,
                                        'name' => $pack['name'],
                                        'language' => $pack['code'],
                                        'details' => [
                                            __('webblocks-cms::admin.extensions_ui.identifier') => $pack['code'],
                                            __('webblocks-cms::admin.system_plugins_index.version') => $pack['release_version'] ?? '—',
                                            $text('source_version') => $pack['source_version'],
                                            $text('status') => $status,
                                        ],
                                    ])
                                @endpush
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection

@if ($manualInstallAvailable)
    @push('overlays')
        @include('webblocks-cms::admin.partials.extension-upload-modal', [
            'id' => 'panel-language-install',
            'title' => $text('manual_install'),
            'action' => route('admin.system.panel-languages.store'),
            'fieldId' => 'panel-language-package',
            'field' => 'package',
            'fileLabel' => $text('package').' (.zip)',
            'help' => $text('package_help'),
            'error' => $errors->first('package'),
        ])
    @endpush
@endif
