@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.plugin_catalog.show.'.$key, $adminLocale, $replace);
    $plugin = $catalog->plugin;
    $release = $plugin?->latestCompatibleRelease;
    $notProvided = '<span class="wb-text-muted">'.e($adminText('not_provided')).'</span>';
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
    $downloadUrl = $plugin?->firstDownloadUrl();
    $checksum = $release?->checksumSha256;
    $canInstallFromCatalog = $plugin?->hasInstallableArtifact() ?? false;
    $hasArtifactMetadata = $downloadUrl || $checksum || $release?->artifactFilename || $release?->artifactSize || $release?->artifactStatus || $release?->scanStatus;
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $plugin?->label ?? $adminText('title'),
        'description' => $adminText('description'),
        'actions' => '<a href="'.e(route('admin.plugins.catalog.index')).'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_catalog')).'</a>',
        'context' => '<span class="wb-text-sm wb-text-muted">'.e($adminText('compatibility_context')).'</span>',
    ])

    @if (! $catalog->available || $plugin === null)
        <div class="wb-alert wb-alert-danger wb-mb-4">
            {{ $catalog->message ?? $adminText('unavailable_alert') }}
        </div>

        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('unavailable_title') }}</div>
                    <div class="wb-empty-text">{!! $adminText('unavailable_text', ['handle' => '<code>'.e($handle).'</code>']) !!}</div>
                </div>
            </div>
        </div>
    @else
        <div class="wb-grid wb-grid-2 wb-gap-4">
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>{{ $adminText('catalog_plugin') }}</strong>
                </div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <div>
                        <strong>{{ $plugin->label }}</strong>
                        <div class="wb-text-sm wb-text-muted"><code>{{ $plugin->handle }}</code></div>
                    </div>
                    <div>{{ $plugin->summary ?? $adminText('not_provided') }}</div>
                    <div class="wb-grid wb-grid-2">
                        <div>
                            <strong>{{ $adminText('vendor') }}</strong>
                            <div>{!! $value($plugin->vendor) !!}</div>
                        </div>
                        <div>
                            <strong>{{ $adminText('author') }}</strong>
                            <div>{!! $value($plugin->author) !!}</div>
                        </div>
                        <div>
                            <strong>{{ $adminText('status') }}</strong>
                            <div>{!! $value($plugin->status) !!}</div>
                        </div>
                        <div>
                            <strong>{{ $adminText('channel') }}</strong>
                            <div>{!! $value($plugin->displayChannel()) !!}</div>
                        </div>
                    </div>
                    <div>
                        <strong>{{ $adminText('plugin_description') }}</strong>
                        <div>{{ $plugin->description ?? $adminText('not_provided') }}</div>
                    </div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>{{ $adminText('compatibility') }}</strong>
                </div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <div>
                        <span class="wb-status {{ $compatibilityClass }}">{{ ucfirst($compatibility) }}</span>
                    </div>
                    <div class="wb-grid wb-grid-2">
                        <div>
                            <strong>{{ $adminText('required_cms') }}</strong>
                            <div>{!! $value($plugin->displayRequiredCmsVersion()) !!}</div>
                        </div>
                        <div>
                            <strong>{{ $adminText('latest_compatible_release') }}</strong>
                            <div>{!! $value($release?->version) !!}</div>
                        </div>
                        <div>
                            <strong>{{ $adminText('release_status') }}</strong>
                            <div>{!! $value($release?->status ?? $plugin->displayStatus()) !!}</div>
                        </div>
                        <div>
                            <strong>{{ $adminText('local_state') }}</strong>
                            <div>{{ $installedState['installed'] ? $adminText('installed') : $adminText('not_installed') }}</div>
                        </div>
                        @if ($installedState['installed'])
                            <div>
                                <strong>{{ $adminText('local_version') }}</strong>
                                <div>{!! $value($installedState['version']) !!}</div>
                            </div>
                            <div>
                                <strong>{{ $adminText('local_lifecycle') }}</strong>
                                <div>{{ $installedState['enabled'] ? $adminText('enabled') : $adminText('disabled') }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>{{ $adminText('zip_install') }}</strong>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                @if ($errors->has('catalog_install'))
                    <div class="wb-alert wb-alert-danger">
                        {{ $errors->first('catalog_install') }}
                    </div>
                @endif
                <div class="wb-alert wb-alert-info">
                    {{ $adminText('zip_install_info') }}
                </div>
                <ol>
                    <li>{{ $adminText('install_step_review') }}</li>
                    <li>{{ $adminText('install_step_download') }}</li>
                    <li>{{ $adminText('install_step_checksum') }}</li>
                    <li>{{ $adminText('install_step_validation') }}</li>
                    <li>{{ $adminText('install_step_disabled') }}</li>
                    <li>{{ $adminText('install_step_enable') }}</li>
                </ol>
                <p>{{ $adminText('manual_upload_note') }}</p>

                <div class="wb-grid wb-grid-2">
                    <div>
                        <strong>{{ $adminText('download_url') }}</strong>
                        @if ($downloadUrl)
                            <div><code class="wb-text-break">{{ $downloadUrl }}</code></div>
                        @else
                            <div>{!! $notProvided !!}</div>
                        @endif
                    </div>
                    <div>
                        <strong>SHA-256</strong>
                        @if ($checksum)
                            <div><code class="wb-text-break">{{ $checksum }}</code></div>
                        @else
                            <div>{!! $notProvided !!}</div>
                        @endif
                    </div>
                    <div>
                        <strong>{{ $adminText('filename') }}</strong>
                        <div>{!! $value($release?->artifactFilename) !!}</div>
                    </div>
                    <div>
                        <strong>{{ $adminText('size') }}</strong>
                        <div>{!! $value($release?->artifactSize) !!}</div>
                    </div>
                    <div>
                        <strong>{{ $adminText('release_status') }}</strong>
                        <div>{!! $value($release?->status ?? $plugin->displayStatus()) !!}</div>
                    </div>
                    <div>
                        <strong>{{ $adminText('artifact_validation_status') }}</strong>
                        <div>{!! $value($release?->artifactStatus) !!}</div>
                    </div>
                    <div>
                        <strong>{{ $adminText('scan_status') }}</strong>
                        <div>{!! $value($release?->scanStatus) !!}</div>
                    </div>
                </div>

                @if (! $hasArtifactMetadata)
                    <div class="wb-alert wb-alert-warning">
                        {{ $adminText('no_artifact') }}
                    </div>
                @endif

                @if ($downloadUrl || $checksum)
                    <div class="wb-text-sm wb-text-muted" data-wb-copy-feedback aria-live="polite"></div>
                @endif
            </div>
            <div class="wb-card-footer wb-cluster wb-cluster-2 wb-flex-wrap">
                @if ($canInstallFromCatalog)
                    <form method="POST" action="{{ route('admin.plugins.catalog.install', $plugin->handle) }}">
                        @csrf
                        <button type="submit" class="wb-btn wb-btn-primary">
                            <i class="wb-icon wb-icon-package" aria-hidden="true"></i>
                            {{ $adminText('install_from_catalog') }}
                        </button>
                    </form>
                @else
                    <button type="button" class="wb-btn wb-btn-primary" disabled>
                        <i class="wb-icon wb-icon-package" aria-hidden="true"></i>
                        {{ $adminText('install_from_catalog') }}
                    </button>
                @endif
                @if ($manualUploadUrl)
                    <a href="{{ $manualUploadUrl }}" class="wb-btn wb-btn-secondary">
                        <i class="wb-icon wb-icon-upload" aria-hidden="true"></i>
                        {{ $adminText('upload_plugin_zip') }}
                    </a>
                @endif
                @if ($downloadUrl)
                    <a href="{{ $downloadUrl }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">
                        <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                        {{ $adminText('download_zip') }}
                    </a>
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-copy-value="{{ $downloadUrl }}" data-wb-copy-label="{{ $adminText('download_url') }}">
                        <i class="wb-icon wb-icon-copy" aria-hidden="true"></i>
                        {{ $adminText('copy_download_url') }}
                    </button>
                @endif
                @if ($checksum)
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-copy-value="{{ $checksum }}" data-wb-copy-label="{{ $adminText('checksum') }}">
                        <i class="wb-icon wb-icon-copy" aria-hidden="true"></i>
                        {{ $adminText('copy_checksum') }}
                    </button>
                @endif
            </div>
        </div>

        <div class="wb-grid wb-grid-2 wb-gap-4">
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>{{ $adminText('links') }}</strong>
                </div>
                <div class="wb-card-body">
                    <div class="wb-grid wb-grid-2">
                        <div>
                            <strong>{{ $adminText('website') }}</strong>
                            <div>{!! $urlValue($plugin->firstWebsiteUrl(), $adminText('open_website')) !!}</div>
                        </div>
                        <div>
                            <strong>{{ $adminText('documentation') }}</strong>
                            <div>{!! $urlValue($plugin->firstDocumentationUrl(), $adminText('open_documentation')) !!}</div>
                        </div>
                        <div>
                            <strong>{{ $adminText('support') }}</strong>
                            <div>{!! $urlValue($plugin->firstSupportUrl(), $adminText('open_support')) !!}</div>
                        </div>
                        <div>
                            <strong>{{ $adminText('catalog_detail') }}</strong>
                            <div>{!! $urlValue($plugin->firstDetailsUrl(), $adminText('open_catalog_detail')) !!}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>{{ $adminText('artifact') }}</strong>
                </div>
                <div class="wb-card-body">
                    @if (! $hasArtifactMetadata)
                        <div class="wb-empty">
                            <div class="wb-empty-title">{{ $adminText('no_artifact') }}</div>
                            <div class="wb-empty-text">{{ $adminText('no_artifact_help') }}</div>
                        </div>
                    @else
                        <div class="wb-grid wb-grid-2">
                            <div>
                                <strong>{{ $adminText('download_url') }}</strong>
                                <div>{!! $urlValue($downloadUrl, $adminText('download_zip')) !!}</div>
                            </div>
                            <div>
                                <strong>{{ $adminText('filename') }}</strong>
                                <div>{!! $value($release?->artifactFilename) !!}</div>
                            </div>
                            <div>
                                <strong>{{ $adminText('size') }}</strong>
                                <div>{!! $value($release?->artifactSize) !!}</div>
                            </div>
                            <div>
                                <strong>SHA-256</strong>
                                <div>{!! $value($checksum) !!}</div>
                            </div>
                            <div>
                                <strong>{{ $adminText('release_status') }}</strong>
                                <div>{!! $value($release?->status ?? $plugin->displayStatus()) !!}</div>
                            </div>
                            <div>
                                <strong>{{ $adminText('artifact_validation_status') }}</strong>
                                <div>{!! $value($release?->artifactStatus) !!}</div>
                            </div>
                            <div>
                                <strong>{{ $adminText('scan_status') }}</strong>
                                <div>{!! $value($release?->scanStatus) !!}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>{{ $adminText('release_notes') }}</strong>
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
                    <div class="wb-text-muted">{{ $adminText('not_provided') }}</div>
                @endif
            </div>
        </div>

        <details class="wb-card">
            <summary class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                <strong>{{ $adminText('declared_metadata') }}</strong>
                <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
            </summary>
            <div class="wb-card-body">
                <div class="wb-grid wb-grid-2">
                    <div>
                        <strong>{{ $adminText('permissions') }}</strong>
                        <div>{!! $listValue($plugin->declaredPermissions) !!}</div>
                    </div>
                    <div>
                        <strong>{{ $adminText('routes') }}</strong>
                        <div>{!! $listValue($plugin->declaredRoutes) !!}</div>
                    </div>
                    <div>
                        <strong>{{ $adminText('migrations') }}</strong>
                        <div>{!! $listValue($plugin->declaredMigrations) !!}</div>
                    </div>
                    <div>
                        <strong>{{ $adminText('providers') }}</strong>
                        <div>{!! $listValue($plugin->declaredProviders) !!}</div>
                    </div>
                    <div>
                        <strong>{{ $adminText('commands') }}</strong>
                        <div>{!! $listValue($plugin->declaredCommands) !!}</div>
                    </div>
                </div>
            </div>
        </details>

        @php($copyScriptPath = public_path('cms/js/admin/plugin-catalog-copy.js'))
        @if (file_exists($copyScriptPath))
            <script src="{{ asset('cms/js/admin/plugin-catalog-copy.js') }}?v={{ filemtime($copyScriptPath) }}" defer></script>
        @endif
    @endif
@endsection
