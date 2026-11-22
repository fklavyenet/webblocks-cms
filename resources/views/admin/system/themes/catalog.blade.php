@php
    $text = static fn (string $key, array $replace = []) => __('webblocks-cms::themes.'.$key, $replace);
    $page = $themes->currentPage();
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('catalog_title'), 'heading' => $text('catalog_title')])

@section('content')
    @component('webblocks-cms::admin.partials.page-header', ['title' => $text('catalog_title')])
        @slot('actions')
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.themes.index') }}">{{ $text('catalog_back') }}</a>
        @endslot
    @endcomponent
    @include('webblocks-cms::admin.system.themes.feedback')

    @if ($catalogError)
        <p class="wb-alert wb-alert-danger" role="alert">{{ $catalogError }}</p>
    @endif

    @if (! $catalogError)
        <section class="wb-card" aria-label="{{ $text('catalog_title') }}">
            <div class="wb-card-body">
                @if ($themes->isEmpty())
                    <div class="wb-empty">
                        <p class="wb-empty-title">{{ $text('catalog_empty') }}</p>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $text('theme') }}</th>
                                    <th scope="col">{{ $text('catalog_version') }}</th>
                                    <th scope="col">{{ $text('layouts') }}</th>
                                    <th scope="col">{{ $text('status') }}</th>
                                    <th scope="col" class="wb-table-actions">{{ $text('actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($themes as $theme)
                                    @php
                                        [$status, $statusClass] = match (true) {
                                            $theme['release'] === null => ['catalog_incompatible', 'wb-status-danger'],
                                            $theme['install_action'] === null => ['catalog_installed', 'wb-status-active'],
                                            $theme['install_action'] === 'update' => ['catalog_update_available', 'wb-status-pending'],
                                            default => ['catalog_available', 'wb-status-info'],
                                        };
                                    @endphp
                                    <tr>
                                        <th scope="row" class="wb-table-break">
                                            {{ $theme['name'] }}
                                            @if (isset($theme['description']))
                                                <p class="wb-text-sm wb-text-muted wb-m-0">{{ $theme['description'] }}</p>
                                            @endif
                                        </th>
                                        <td>{{ $theme['release']['version'] ?? '—' }}</td>
                                        <td>{{ $theme['release'] ? implode(', ', $theme['release']['layouts']) : '—' }}</td>
                                        <td><span class="wb-status-pill {{ $statusClass }}">{{ $text($status) }}</span></td>
                                        <td class="wb-table-actions">
                                            @if ($theme['install_action'])
                                                <div class="wb-action-group">
                                                    <form method="post" action="{{ route('admin.system.themes.catalog.store', $page > 1 ? ['page' => $page] : []) }}">
                                                        @csrf
                                                        <input type="hidden" name="handle" value="{{ $theme['handle'] }}">
                                                        <input type="hidden" name="version" value="{{ $theme['release']['version'] }}">
                                                        <button class="wb-btn wb-btn-secondary wb-btn-sm" type="submit" data-wb-busy
                                                            data-wb-busy-label="{{ $text('catalog_installing') }}"
                                                            aria-label="{{ $text('catalog_'.$theme['install_action'].'_named', ['name' => $theme['name']]) }}">
                                                            <span data-wb-busy-text>{{ $text('catalog_'.$theme['install_action']) }}</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $themes, 'ariaLabel' => $text('catalog_title'), 'compact' => true])
        </section>
    @endif
@endsection
