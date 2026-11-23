@php
    $text = static fn (string $key, array $replace = []) => __('webblocks-cms::panel-languages.'.$key, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('catalog_title'), 'heading' => $text('catalog_title')])

@section('content')
    @component('webblocks-cms::admin.partials.page-header', ['title' => $text('catalog_title'), 'description' => $text('extensions_for_cms', ['version' => $cmsVersion])])
        @slot('actions')
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.panel-languages.index') }}">
                <i class="wb-icon wb-icon-arrow-left" aria-hidden="true"></i> {{ $text('back') }}
            </a>
        @endslot
    @endcomponent
    @include('webblocks-cms::admin.system.panel-languages.feedback')

    @if ($catalogError)
        <div class="wb-alert wb-alert-danger" role="alert">{{ $catalogError }}</div>
    @elseif ($releases === [])
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty"><p class="wb-empty-title">{{ $text('extensions_empty') }}</p></div>
            </div>
        </div>
    @else
        <div class="wb-grid wb-cms-catalog-grid">
            @foreach ($releases as $release)
                @php
                    $state = $release['state'];
                    $statusClass = match ($state) {
                        'installed' => 'wb-status-active',
                        'update' => 'wb-status-pending',
                        default => 'wb-status-info',
                    };
                @endphp
                @component('webblocks-cms::admin.partials.extension-catalog-card', [
                    'name' => $release['name'],
                    'coverIcon' => 'languages',
                    'language' => $release['code'],
                    'titleBadge' => strtoupper($release['code']),
                ])
                    @slot('metadata')
                        <span>{{ __('webblocks-cms::admin.plugin_catalog.latest_release', ['version' => $release['version']]) }}</span>
                        <span class="wb-status-pill {{ $statusClass }}">{{ $text('state_'.$state) }}</span>
                    @endslot
                    @if ($state !== 'installed')
                        @slot('actions')
                            <form class="wb-w-full" method="POST" action="{{ route('admin.system.panel-languages.extensions.store') }}">
                                @csrf
                                <input type="hidden" name="code" value="{{ $release['code'] }}">
                                <input type="hidden" name="version" value="{{ $release['version'] }}">
                                <input type="hidden" name="format" value="native">
                                <input type="hidden" name="checksum" value="{{ $release['sha256'] }}">
                                <button type="submit" class="wb-btn wb-btn-secondary wb-w-full" data-wb-busy
                                    data-wb-busy-label="{{ __('webblocks-cms::themes.catalog_installing') }}"
                                    aria-label="{{ $text('extensions_'.$state) }} · {{ $release['name'] }}">
                                    <span data-wb-busy-text>{{ $text('extensions_'.$state) }}</span>
                                </button>
                            </form>
                        @endslot
                    @endif
                @endcomponent
            @endforeach
        </div>
    @endif
@endsection
