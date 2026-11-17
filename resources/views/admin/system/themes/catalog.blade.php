@php($text = static fn (string $key) => __('webblocks-cms::themes.'.$key))

@extends('webblocks-cms::layouts.admin', ['title' => $text('browse_catalog'), 'heading' => $text('browse_catalog')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('browse_catalog')])
    <div class="wb-stack wb-gap-4">
        <div class="wb-cluster">
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.themes.index') }}">{{ $text('versions') }}</a>
        </div>
        @if ($catalogError)
            <p class="wb-alert wb-alert-danger" role="alert">{{ $catalogError }}</p>
        @endif
        @foreach (['catalog', 'package', 'handle', 'version'] as $field)
            @error($field)
                <p class="wb-alert wb-alert-danger" role="alert">{{ $message }}</p>
            @enderror
        @endforeach
        <section class="wb-card">
            <div class="wb-card-body">
                @if ($themes === [] && ! $catalogError)
                    <p>{{ $text('catalog_empty') }}</p>
                @elseif ($themes !== [])
                    <div class="wb-table-wrap">
                        <table class="wb-table">
                            <thead><tr>
                                <th scope="col">{{ $text('theme') }}</th>
                                <th scope="col">{{ $text('catalog_version') }}</th>
                                <th scope="col">{{ $text('layouts') }}</th>
                                <th scope="col" class="wb-table-actions">{{ $text('actions') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($themes as $theme)
                                    <tr>
                                        <th scope="row">
                                            {{ $theme['name'] }}
                                            @if (isset($theme['description']))
                                                <p class="wb-text-sm wb-text-muted">{{ $theme['description'] }}</p>
                                            @endif
                                        </th>
                                        <td>{{ $theme['release']['version'] ?? $text('catalog_incompatible') }}</td>
                                        <td>{{ implode(', ', $theme['release']['layouts'] ?? []) }}</td>
                                        <td class="wb-table-actions"><div class="wb-action-group">
                                            @if ($theme['install_action'])
                                                <form method="post" action="{{ route('admin.system.themes.catalog.store') }}">
                                                    @csrf
                                                    <input type="hidden" name="handle" value="{{ $theme['handle'] }}">
                                                    <input type="hidden" name="version" value="{{ $theme['release']['version'] }}">
                                                    <button class="wb-btn wb-btn-secondary wb-btn-sm" type="submit">
                                                        {{ $text('catalog_'.$theme['install_action']) }}
                                                    </button>
                                                </form>
                                            @elseif ($theme['release'])
                                                {{ $text('catalog_installed') }}
                                            @endif
                                        </div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            @if ($page > 1 || $hasMore)
                <div class="wb-card-footer wb-cluster wb-gap-2">
                    @if ($page > 1)
                        <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.themes.catalog.index', ['page' => $page - 1]) }}">{{ $text('catalog_previous') }}</a>
                    @endif
                    @if ($hasMore)
                        <a class="wb-btn wb-btn-secondary" href="{{ route('admin.system.themes.catalog.index', ['page' => $page + 1]) }}">{{ $text('catalog_next') }}</a>
                    @endif
                </div>
            @endif
        </section>
    </div>
@endsection
