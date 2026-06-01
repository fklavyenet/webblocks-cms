@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $plugin = $catalog->plugin;
    $release = $plugin?->latestCompatibleRelease;
    $notProvided = '<span class="wb-text-muted">Not provided</span>';
    $value = fn (?string $text): string => $text !== null && trim($text) !== '' ? e($text) : $notProvided;
    $urlValue = function (?string $url, string $label) use ($notProvided): string {
        return $url !== null
            ? '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($label).'</a>'
            : $notProvided;
    };
    $listValue = function (array $items) use ($notProvided): string {
        if (count($items) === 0) {
            return $notProvided;
        }

        return '<ul class="wb-list wb-list-flush">'.collect($items)
            ->map(fn (string $item): string => '<li><code>'.e($item).'</code></li>')
            ->implode('').'</ul>';
    };
    $compatibility = $plugin?->compatibilityStatus ?? ($release ? 'compatible' : 'unknown');
    $compatibilityClass = match ($compatibility) {
        'compatible', 'supported' => 'wb-status-active',
        'incompatible', 'unsupported' => 'wb-status-danger',
        default => 'wb-status-pending',
    };
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $plugin?->label ?? 'Plugin Catalog Detail',
        'description' => 'Review public catalog metadata for a WebBlocks CMS-compatible plugin.',
        'actions' => '<a href="'.e(route('admin.plugins.catalog.index')).'" class="wb-btn wb-btn-secondary">Back to Catalog</a>',
        'context' => '<span class="wb-text-sm wb-text-muted">Catalog: '.e($catalog->baseUrl).' - CMS: '.e($catalog->cmsVersion).'</span>',
    ])

    @if (! $catalog->available || $plugin === null)
        <div class="wb-alert wb-alert-danger wb-mb-4">
            {{ $catalog->message ?? 'The Plugin Catalog detail is currently unavailable.' }}
        </div>

        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">Catalog plugin unavailable.</div>
                    <div class="wb-empty-text">The requested plugin <code>{{ $handle }}</code> could not be loaded from the configured read-only catalog.</div>
                </div>
            </div>
        </div>
    @else
        <div class="wb-grid wb-grid-2 wb-gap-4">
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Catalog Plugin</strong>
                </div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <div>
                        <strong>{{ $plugin->label }}</strong>
                        <div class="wb-text-sm wb-text-muted"><code>{{ $plugin->handle }}</code></div>
                    </div>
                    <div>{{ $plugin->summary ?? 'Not provided' }}</div>
                    <div class="wb-grid wb-grid-2">
                        <div>
                            <strong>Vendor</strong>
                            <div>{!! $value($plugin->vendor) !!}</div>
                        </div>
                        <div>
                            <strong>Author</strong>
                            <div>{!! $value($plugin->author) !!}</div>
                        </div>
                        <div>
                            <strong>Status</strong>
                            <div>{!! $value($plugin->status) !!}</div>
                        </div>
                        <div>
                            <strong>Channel</strong>
                            <div>{!! $value($plugin->displayChannel()) !!}</div>
                        </div>
                    </div>
                    <div>
                        <strong>Description</strong>
                        <div>{{ $plugin->description ?? 'Not provided' }}</div>
                    </div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Compatibility</strong>
                </div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <div>
                        <span class="wb-status {{ $compatibilityClass }}">{{ ucfirst($compatibility) }}</span>
                    </div>
                    <div class="wb-grid wb-grid-2">
                        <div>
                            <strong>Running CMS</strong>
                            <div>{{ $catalog->cmsVersion }}</div>
                        </div>
                        <div>
                            <strong>Required CMS</strong>
                            <div>{!! $value($plugin->displayRequiredCmsVersion()) !!}</div>
                        </div>
                        <div>
                            <strong>Latest Compatible Release</strong>
                            <div>{!! $value($release?->version) !!}</div>
                        </div>
                        <div>
                            <strong>Release Status</strong>
                            <div>{!! $value($release?->status ?? $plugin->displayStatus()) !!}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Manual Install Guidance</strong>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-alert wb-alert-info">
                    Catalog data is informational only. Plugin installation still happens through the existing manual ZIP upload flow.
                </div>
                <p>Downloaded plugins remain subject to CMS ZIP validation, compatibility checks, disabled-by-default lifecycle review, and explicit enable/setup steps after upload.</p>
                @if ($release?->checksumSha256)
                    <div><strong>SHA-256:</strong> <code>{{ $release->checksumSha256 }}</code></div>
                @endif
            </div>
            <div class="wb-card-footer wb-cluster wb-cluster-2 wb-flex-wrap">
                <a href="{{ route('admin.system.plugins.index') }}" class="wb-btn wb-btn-secondary">
                    <i class="wb-icon wb-icon-upload" aria-hidden="true"></i>
                    Manual ZIP Upload
                </a>
                @if ($plugin->firstDownloadUrl())
                    <a href="{{ $plugin->firstDownloadUrl() }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">
                        <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                        Download Externally
                    </a>
                @endif
            </div>
        </div>

        <div class="wb-grid wb-grid-2 wb-gap-4">
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Links</strong>
                </div>
                <div class="wb-card-body">
                    <div class="wb-grid wb-grid-2">
                        <div>
                            <strong>Website</strong>
                            <div>{!! $urlValue($plugin->firstWebsiteUrl(), 'Open website') !!}</div>
                        </div>
                        <div>
                            <strong>Documentation</strong>
                            <div>{!! $urlValue($plugin->firstDocumentationUrl(), 'Open documentation') !!}</div>
                        </div>
                        <div>
                            <strong>Support</strong>
                            <div>{!! $urlValue($plugin->firstSupportUrl(), 'Open support') !!}</div>
                        </div>
                        <div>
                            <strong>Catalog Detail</strong>
                            <div>{!! $urlValue($plugin->firstDetailsUrl(), 'Open catalog detail') !!}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Artifact</strong>
                </div>
                <div class="wb-card-body">
                    <div class="wb-grid wb-grid-2">
                        <div>
                            <strong>Download URL</strong>
                            <div>{!! $urlValue($plugin->firstDownloadUrl(), 'Open download') !!}</div>
                        </div>
                        <div>
                            <strong>Filename</strong>
                            <div>{!! $value($release?->artifactFilename) !!}</div>
                        </div>
                        <div>
                            <strong>Size</strong>
                            <div>{!! $value($release?->artifactSize) !!}</div>
                        </div>
                        <div>
                            <strong>SHA-256</strong>
                            <div>{!! $value($release?->checksumSha256) !!}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Release Notes</strong>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div>{!! $value($release?->displaySummary()) !!}</div>
                @if ($release && count($release->highlights) > 0)
                    <ul>
                        @foreach ($release->highlights as $highlight)
                            <li>{{ $highlight }}</li>
                        @endforeach
                    </ul>
                @else
                    <div class="wb-text-muted">Not provided</div>
                @endif
            </div>
        </div>

        <details class="wb-card">
            <summary class="wb-card-header">
                <strong>Declared Catalog Metadata</strong>
            </summary>
            <div class="wb-card-body">
                <div class="wb-grid wb-grid-2">
                    <div>
                        <strong>Permissions</strong>
                        <div>{!! $listValue($plugin->declaredPermissions) !!}</div>
                    </div>
                    <div>
                        <strong>Routes</strong>
                        <div>{!! $listValue($plugin->declaredRoutes) !!}</div>
                    </div>
                    <div>
                        <strong>Migrations</strong>
                        <div>{!! $listValue($plugin->declaredMigrations) !!}</div>
                    </div>
                    <div>
                        <strong>Providers</strong>
                        <div>{!! $listValue($plugin->declaredProviders) !!}</div>
                    </div>
                    <div>
                        <strong>Commands</strong>
                        <div>{!! $listValue($plugin->declaredCommands) !!}</div>
                    </div>
                </div>
            </div>
        </details>
    @endif
@endsection
