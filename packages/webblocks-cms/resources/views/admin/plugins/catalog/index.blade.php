@extends('webblocks-cms::layouts.admin', ['title' => 'Plugin Catalog', 'heading' => 'Plugin Catalog'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Plugin Catalog',
        'description' => 'Browse public WebBlocks CMS-compatible plugins from the read-only Plugin Catalog.',
        'actions' => '<a href="'.e(route('admin.system.plugins.index')).'" class="wb-btn wb-btn-secondary">Back to Plugins</a>',
        'context' => '<span class="wb-text-sm wb-text-muted">Compatibility is checked against this CMS installation.</span>',
    ])

    @if (! $catalog->available)
        <div class="wb-alert wb-alert-danger wb-mb-4">
            {{ $catalog->message ?? 'The Plugin Catalog is currently unavailable.' }}
        </div>
    @endif

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>Catalog Plugins</strong>
        </div>
        <div class="wb-card-body">
            @if (count($catalog->plugins) === 0)
                <div class="wb-empty">
                    <div class="wb-empty-title">No catalog plugins found.</div>
                    <div class="wb-empty-text">Public WebBlocks CMS-compatible plugins will appear here when the catalog returns them.</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th>Plugin</th>
                                <th>Vendor</th>
                                <th>Latest Compatible Release</th>
                                <th>Compatibility</th>
                                <th>Channel / Status</th>
                                <th>Links</th>
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
                                    <td>{{ $plugin->vendor ?? 'Not listed' }}</td>
                                    <td>
                                        @if ($plugin->latestCompatibleRelease?->version)
                                            {{ $plugin->latestCompatibleRelease->version }}
                                        @else
                                            <span class="wb-text-muted">Not listed</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="wb-status {{ $compatibilityClass }}">{{ ucfirst($compatibility) }}</span>
                                        @if ($plugin->displayRequiredCmsVersion())
                                            <div class="wb-text-sm wb-text-muted">Requires {{ $plugin->displayRequiredCmsVersion() }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $plugin->displayChannel() ?? 'Not listed' }}
                                        @if ($plugin->displayStatus())
                                            <div class="wb-text-sm wb-text-muted">{{ $plugin->displayStatus() }}</div>
                                        @endif
                                    </td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group" aria-label="Catalog links for {{ $plugin->label }}">
                                            <a href="{{ route('admin.plugins.catalog.show', $plugin->handle) }}" class="wb-action-btn" title="View details" aria-label="View details">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>
                                            @if ($plugin->firstDetailsUrl())
                                                <a href="{{ $plugin->firstDetailsUrl() }}" class="wb-action-btn" title="Open details" aria-label="Open details" target="_blank" rel="noopener noreferrer">
                                                    <i class="wb-icon wb-icon-external-link" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                            @if ($plugin->firstDocumentationUrl())
                                                <a href="{{ $plugin->firstDocumentationUrl() }}" class="wb-action-btn" title="Open documentation" aria-label="Open documentation" target="_blank" rel="noopener noreferrer">
                                                    <i class="wb-icon wb-icon-book-open" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                            @if ($plugin->firstDownloadUrl())
                                                <a href="{{ $plugin->firstDownloadUrl() }}" class="wb-action-btn" title="Open download" aria-label="Open download" target="_blank" rel="noopener noreferrer">
                                                    <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                            @if (! $plugin->firstDetailsUrl() && ! $plugin->firstDocumentationUrl() && ! $plugin->firstDownloadUrl())
                                                <span class="wb-text-sm wb-text-muted">No links</span>
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
