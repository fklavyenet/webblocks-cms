@php
    $text = static fn (string $key, array $replace = []) => __('webblocks-cms::themes.'.$key, $replace);
    $page = $themes->currentPage();
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('catalog_title'), 'heading' => $text('catalog_title')])

@section('content')
    @component('webblocks-cms::admin.partials.page-header', ['title' => $text('catalog_title')])
        @slot('actions')
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.themes.index') }}"><i class="wb-icon wb-icon-arrow-left" aria-hidden="true"></i> {{ $text('catalog_back') }}</a>
        @endslot
    @endcomponent
    @include('webblocks-cms::admin.system.themes.feedback')

    @if ($catalogError)
        <p class="wb-alert wb-alert-danger" role="alert">{{ $catalogError }}</p>
    @elseif ($themes->isEmpty())
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty"><p class="wb-empty-title">{{ $text('catalog_empty') }}</p></div>
            </div>
        </div>
    @else
        <div class="wb-grid wb-cms-catalog-grid">
            @foreach ($themes as $theme)
                @php
                    $state = $theme['state'];
                    $release = $theme['release'];
                    [$status, $statusClass] = match ($state) {
                        'incompatible' => ['catalog_incompatible', 'wb-status-danger'],
                        'installed' => ['catalog_installed', 'wb-status-active'],
                        'conflict' => ['catalog_conflict', 'wb-status-pending'],
                        'newer_installed' => ['catalog_newer_installed', 'wb-status-info'],
                        'update' => ['catalog_update_available', 'wb-status-pending'],
                        'install' => ['catalog_available', 'wb-status-info'],
                    };
                @endphp
                @component('webblocks-cms::admin.partials.extension-catalog-card', [
                    'name' => $theme['name'],
                    'coverIcon' => 'palette',
                ])
                    @if (! empty($theme['description']))
                        <p class="wb-m-0">{{ $theme['description'] }}</p>
                    @endif
                    @if (! empty($release['layouts']))
                        <div class="wb-cluster wb-cluster-2" role="group" aria-label="{{ $text('catalog_layouts') }}">
                            @foreach ($release['layouts'] as $layout)
                                <span class="wb-badge">{{ $layout }}</span>
                            @endforeach
                        </div>
                    @endif
                    @slot('metadata')
                        @if ($release)
                            <span>{{ __('webblocks-cms::admin.plugin_catalog.latest_release', ['version' => $release['version']]) }}</span>
                        @endif
                        <span class="wb-status-pill {{ $statusClass }}">{{ $text($status) }}</span>
                        @if ($state === 'conflict')
                            <p class="wb-m-0">{{ $text('catalog_conflict_help') }}</p>
                        @endif
                    @endslot
                    @if (in_array($state, ['install', 'update'], true))
                        @slot('actions')
                            <form class="wb-w-full" method="post" action="{{ route('admin.system.themes.catalog.store', $page > 1 ? ['page' => $page] : []) }}">
                                @csrf
                                <input type="hidden" name="handle" value="{{ $theme['handle'] }}">
                                <input type="hidden" name="version" value="{{ $release['version'] }}">
                                <button class="wb-btn wb-btn-secondary wb-w-full" type="submit" data-wb-busy
                                    data-wb-busy-label="{{ $text('catalog_installing') }}"
                                    aria-label="{{ $text('catalog_'.$state.'_named', ['name' => $theme['name']]) }}">
                                    <span data-wb-busy-text>{{ $text('catalog_'.$state) }}</span>
                                </button>
                            </form>
                        @endslot
                    @endif
                @endcomponent
            @endforeach
        </div>
    @endif
    @if (! $catalogError && $themes->hasPages())
        <div class="wb-card">
            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $themes, 'ariaLabel' => $text('catalog_title'), 'compact' => true])
        </div>
    @endif
@endsection
