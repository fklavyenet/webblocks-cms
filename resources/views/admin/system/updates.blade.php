@extends('layouts.admin', ['title' => 'System Updates', 'heading' => 'System Updates'])

@section('content')
    @php
        $updateStatus = $report['version'];
        $diagnostics = $report['diagnostics'];
        $environment = $report['environment'];
        $release = $updateStatus['release'] ?? null;
        $publishResult = session('publish_result');
        $compatibilityStatus = $updateStatus['compatibility']['status'] ?? 'unknown';
        $compatibilityBadgeClass = match ($compatibilityStatus) {
            'compatible' => 'wb-status-active',
            'incompatible' => 'wb-status-danger',
            default => 'wb-status-pending',
        };
    @endphp

    @include('admin.partials.page-header', [
        'title' => 'System Updates',
        'description' => 'Check the configured update server, review release metadata, and confirm compatibility before downloading a package.',
        'actions' => '<div class="wb-stack wb-gap-1 wb-text-right"><div class="wb-text-sm wb-text-muted">Last checked at '.$checkedAt->format('Y-m-d H:i:s').'</div><a href="'.route('admin.system.updates.check').'" class="wb-btn wb-btn-secondary">Check for updates</a></div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Current Status</strong>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <span class="wb-status-pill {{ $updateStatus['badge_class'] }}">{{ $updateStatus['label'] }}</span>
                    <div class="wb-text-sm wb-text-muted">{{ $updateStatus['message'] }}</div>

                    @if ($updateStatus['error_message'])
                        <div class="wb-alert wb-alert-warning">
                            <div>
                                <div class="wb-alert-title">Server detail</div>
                                <div>{{ $updateStatus['error_message'] }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header">
                    <strong>Versions</strong>
                </div>

                <div class="wb-card-body">
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Installed version</div>
                            <strong>{{ $updateStatus['installed_version'] }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Latest version</div>
                            <strong>{{ $updateStatus['latest_version'] ?? 'N/A' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Product</div>
                            <strong>{{ $updateStatus['product'] }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Channel</div>
                            <strong>{{ $updateStatus['channel'] }}</strong>
                        </div>
                    </div>

                    <div class="wb-text-sm wb-text-muted">Update server {{ $updateStatus['server_url'] ?: 'not configured' }}</div>
                    <div class="wb-text-sm wb-text-muted">API version {{ $updateStatus['api_version'] ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header">
                <strong>Diagnostics</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-2">
                @foreach ($diagnostics as $diagnostic)
                    <div class="wb-cluster wb-cluster-between wb-cluster-2">
                        <div class="wb-stack wb-gap-1">
                            <strong>{{ $diagnostic['label'] }}</strong>
                            <div class="wb-text-sm wb-text-muted">{{ $diagnostic['message'] }}</div>
                        </div>

                        <span class="wb-status-pill {{ $diagnostic['badge_class'] }}">{{ $diagnostic['status'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header">
                <strong>Compatibility</strong>
            </div>

            <div class="wb-card-body">
                <div class="wb-cluster wb-cluster-between wb-cluster-2" style="margin-bottom: 1rem;">
                    <div class="wb-text-sm wb-text-muted">Compatibility status</div>
                    <span class="wb-status-pill {{ $compatibilityBadgeClass }}">{{ $compatibilityStatus }}</span>
                </div>

                @if (($updateStatus['compatibility']['reasons'] ?? []) === [])
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">No compatibility issues reported</div>
                    </div>
                @else
                    <ul class="wb-stack wb-gap-1">
                        @foreach ($updateStatus['compatibility']['reasons'] as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Release Metadata</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                @if (! $release)
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">No release metadata available</div>
                    </div>
                @else
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Release name</div>
                            <strong>{{ $release['name'] ?: 'Unnamed release' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Published at</div>
                            <strong>{{ $release['published_at'] ? \Carbon\Carbon::parse($release['published_at'])->format('Y-m-d H:i:s') : 'N/A' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Critical</div>
                            <strong>{{ $release['is_critical'] ? 'Yes' : 'No' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Security</div>
                            <strong>{{ $release['is_security'] ? 'Yes' : 'No' }}</strong>
                        </div>
                    </div>

                    @if ($release['description'])
                        <div class="wb-text-sm wb-text-muted">{{ $release['description'] }}</div>
                    @endif

                    @if ($release['changelog'])
                        <pre class="wb-code-block">{{ $release['changelog'] }}</pre>
                    @endif

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Minimum PHP</div>
                            <strong>{{ $release['requirements']['min_php_version'] ?: 'N/A' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Minimum Laravel</div>
                            <strong>{{ $release['requirements']['min_laravel_version'] ?: 'N/A' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Supported from version</div>
                            <strong>{{ $release['requirements']['supported_from_version'] ?: 'N/A' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Supported until version</div>
                            <strong>{{ $release['requirements']['supported_until_version'] ?: 'N/A' }}</strong>
                        </div>
                    </div>

                    @if ($release['checksum_sha256'])
                        <div class="wb-text-sm wb-text-muted">SHA-256 {{ $release['checksum_sha256'] }}</div>
                    @endif

                    <div class="wb-cluster wb-cluster-between wb-cluster-2">
                        <div class="wb-text-sm wb-text-muted">V1 provides check-only behavior. Download and installation remain manual.</div>

                        @if ($release['download_url'])
                            <a href="{{ $release['download_url'] }}" class="wb-btn wb-btn-primary" target="_blank" rel="noopener">Download package</a>
                        @else
                            <button type="button" class="wb-btn wb-btn-secondary" disabled>Download unavailable</button>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header">
                <strong>Client Environment</strong>
            </div>

            <div class="wb-card-body">
                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">Site URL</div>
                        <strong>{{ $environment['site_url'] }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">Configured server</div>
                        <strong>{{ $environment['server_url'] ?: 'N/A' }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">PHP version</div>
                        <strong>{{ $environment['php_version'] }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">Laravel version</div>
                        <strong>{{ $environment['laravel_version'] }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Publish to Updates Server</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-text-sm wb-text-muted">Publish release metadata from this CMS install to the central Updates Server API.</div>

                @if ($errors->has('release_publish'))
                    <div class="wb-alert wb-alert-danger">
                        <div>
                            <div class="wb-alert-title">Publish failed</div>
                            <div>{{ $errors->first('release_publish') }}</div>
                        </div>
                    </div>
                @endif

                @if ($publishResult)
                    <div class="wb-alert wb-alert-success">
                        <div>
                            <div class="wb-alert-title">Publish response</div>
                            <div class="wb-text-sm wb-text-muted">Endpoint {{ $publishResult['endpoint'] ?? 'N/A' }}</div>
                            @if (! empty($publishResult['download_url']))
                                <div><a href="{{ $publishResult['download_url'] }}" target="_blank" rel="noopener">{{ $publishResult['download_url'] }}</a></div>
                            @endif
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.system.updates.publish') }}" class="wb-stack wb-gap-3">
                    @csrf

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <label for="publish-version" class="wb-text-sm wb-text-muted">Version</label>
                            <input id="publish-version" type="text" name="version" value="{{ old('version', $publishDefaults['version']) }}" class="wb-input">
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="publish-channel" class="wb-text-sm wb-text-muted">Channel</label>
                            <input id="publish-channel" type="text" name="channel" value="{{ old('channel', $publishDefaults['channel']) }}" class="wb-input">
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="publish-source-url" class="wb-text-sm wb-text-muted">Source URL</label>
                            <input id="publish-source-url" type="url" name="source_url" value="{{ old('source_url') }}" class="wb-input">
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="publish-tag" class="wb-text-sm wb-text-muted">Tag</label>
                            <input id="publish-tag" type="text" name="tag" value="{{ old('tag') }}" class="wb-input">
                        </div>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="publish-notes" class="wb-text-sm wb-text-muted">Notes</label>
                        <textarea id="publish-notes" name="notes" rows="6" class="wb-input">{{ old('notes') }}</textarea>
                    </div>

                    <div class="wb-cluster wb-cluster-between wb-cluster-2">
                        <div class="wb-text-sm wb-text-muted">The package release flow remains separate. This action publishes release metadata to the Updates Server API.</div>
                        <button type="submit" class="wb-btn wb-btn-primary">Publish to Updates Server</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
