@extends('webblocks-cms::layouts.admin', ['title' => __('webblocks-cms::admin.plugin_catalog.title'), 'heading' => __('webblocks-cms::admin.plugin_catalog.title')])

@php
    $pluginCatalogText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.plugin_catalog.'.$key, $replace);
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pluginCatalogText('title'),
        'description' => $pluginCatalogText('description'),
        'actions' => '<a href="'.e(route('admin.system.plugins.index')).'" class="wb-btn wb-btn-secondary">'.e($pluginCatalogText('back_to_plugins')).'</a>',
        'context' => '<span class="wb-text-sm wb-text-muted">'.e($pluginCatalogText('context')).'</span>',
    ])

    @if (! $catalog->available)
        <div class="wb-alert wb-alert-danger wb-mb-4">
            {{ $catalog->message ?? $pluginCatalogText('unavailable') }}
        </div>
    @endif

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $pluginCatalogText('catalog_plugins') }}</strong>
        </div>
        <div class="wb-card-body">
            @if (count($catalog->plugins) === 0)
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $pluginCatalogText('empty_title') }}</div>
                    <div class="wb-empty-text">{{ $pluginCatalogText('empty_text') }}</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th>{{ $pluginCatalogText('plugin') }}</th>
                                <th>{{ $pluginCatalogText('vendor') }}</th>
                                <th>{{ $pluginCatalogText('latest_compatible_release') }}</th>
                                <th>{{ $pluginCatalogText('compatibility') }}</th>
                                <th>{{ $pluginCatalogText('channel_status') }}</th>
                                <th>{{ $pluginCatalogText('actions_links') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($catalog->plugins as $plugin)
                                @php
                                    $compatibility = $plugin->compatibilityStatus ?? ($plugin->latestCompatibleRelease ? 'compatible' : 'unknown');
                                    $compatibilityClass = match ($compatibility) {
                                        'compatible', 'supported' => 'wb-status-active',
                                        'incompatible', 'unsupported' => 'wb-status-danger',
                                        default => 'wb-status-pending',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.plugins.catalog.show', $plugin->handle) }}"><strong>{{ $plugin->label }}</strong></a>
                                        <div class="wb-text-sm wb-text-muted"><code>{{ $plugin->handle }}</code></div>
                                        @if ($plugin->summary)
                                            <div class="wb-text-sm">{{ $plugin->summary }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $plugin->vendor ?? $pluginCatalogText('not_listed') }}</td>
                                    <td>
                                        @if ($plugin->latestCompatibleRelease?->version)
                                            {{ $plugin->latestCompatibleRelease->version }}
                                        @else
                                            <span class="wb-text-muted">{{ $pluginCatalogText('not_listed') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="wb-status {{ $compatibilityClass }}">{{ ucfirst($compatibility) }}</span>
                                        @if ($plugin->displayRequiredCmsVersion())
                                            <div class="wb-text-sm wb-text-muted">{{ $pluginCatalogText('requires', ['version' => $plugin->displayRequiredCmsVersion()]) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $plugin->displayChannel() ?? $pluginCatalogText('not_listed') }}
                                        @if ($plugin->displayStatus())
                                            <div class="wb-text-sm wb-text-muted">{{ $plugin->displayStatus() }}</div>
                                        @endif
                                    </td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group" aria-label="{{ $pluginCatalogText('catalog_links_for', ['label' => $plugin->label]) }}">
                                            <a href="{{ route('admin.plugins.catalog.show', $plugin->handle) }}" class="wb-action-btn" title="{{ $pluginCatalogText('view_details') }}" aria-label="{{ $pluginCatalogText('view_details') }}">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>
                                            @if ($plugin->firstDetailsUrl())
                                                <a href="{{ $plugin->firstDetailsUrl() }}" class="wb-action-btn" title="{{ $pluginCatalogText('open_details') }}" aria-label="{{ $pluginCatalogText('open_details') }}" target="_blank" rel="noopener noreferrer">
                                                    <i class="wb-icon wb-icon-external-link" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                            @if ($plugin->firstDocumentationUrl())
                                                <a href="{{ $plugin->firstDocumentationUrl() }}" class="wb-action-btn" title="{{ $pluginCatalogText('open_documentation') }}" aria-label="{{ $pluginCatalogText('open_documentation') }}" target="_blank" rel="noopener noreferrer">
                                                    <i class="wb-icon wb-icon-book-open" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                            @if ($plugin->firstDownloadUrl())
                                                <a href="{{ $plugin->firstDownloadUrl() }}" class="wb-action-btn" title="{{ $pluginCatalogText('open_download') }}" aria-label="{{ $pluginCatalogText('open_download') }}" target="_blank" rel="noopener noreferrer">
                                                    <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
