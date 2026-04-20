@extends('layouts.admin', ['title' => 'System Updates', 'heading' => 'System Updates'])

@section('content')
    @php
        $updateStatus = $report['version'];
        $diagnostics = $report['diagnostics'];
        $environment = $report['environment'];
        $release = $updateStatus['release'] ?? null;
        $installedVersion = $report['installed_version'] ?? $updateStatus['installed_version'];
        $latestUpdateRun = $latestUpdateRun ?? null;
        $latestSuccessfulBackup = $backupFreshness['latest_successful'] ?? null;
        $hasRecentBackup = $backupFreshness['has_recent_successful_backup'] ?? false;
        $compatibilityStatus = $updateStatus['compatibility']['status'] ?? 'unknown';
        $compatibilityBadgeClass = match ($compatibilityStatus) {
            'compatible' => 'wb-status-active',
            'incompatible' => 'wb-status-danger',
            default => 'wb-status-pending',
        };
        $releaseText = trim((string) ($release['description'] ?? ''));

        if ($releaseText === '') {
            $releaseText = trim((string) ($release['changelog'] ?? ''));
        }

        $releaseNotes = collect(preg_split('/\r\n|\r|\n/', $releaseText ?: ''))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values();
    @endphp

    @include('admin.partials.page-header', [
        'title' => 'System Updates',
        'description' => 'Review the installed version, check the latest published release, and download the package when an update is available.',
        'actions' => '<div class="wb-text-sm wb-text-muted">Last checked at '.$checkedAt->format('Y-m-d H:i:s').'</div>',
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
                            <strong>{{ $installedVersion }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Latest version</div>
                            <strong>{{ $updateStatus['latest_version'] ?? 'N/A' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Channel</div>
                            <strong>{{ $updateStatus['channel'] }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Published at</div>
                            <strong>{{ ($release['published_at'] ?? null) ? \Carbon\Carbon::parse($release['published_at'])->format('Y-m-d H:i:s') : 'N/A' }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Release Notes</strong>
            </div>

            <div class="wb-card-body">
                @if ($releaseNotes->isEmpty())
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">No release notes available</div>
                    </div>
                @else
                    <div class="wb-stack wb-gap-2">
                        @foreach ($releaseNotes as $note)
                            <div class="wb-text-sm wb-text-muted">{{ $note }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Actions</strong>
                </div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-text-sm wb-text-muted">Use this page to check the latest release and download the package when you are ready to update this install manually.</div>

                    <div class="wb-cluster wb-cluster-between wb-cluster-2">
                        <a href="{{ route('admin.system.updates.check') }}" class="wb-btn wb-btn-secondary">Check again</a>

                        @if ($release['download_url'] ?? null)
                            <a href="{{ $release['download_url'] }}" class="wb-btn wb-btn-primary" target="_blank" rel="noopener">Download package</a>
                        @else
                            <button type="button" class="wb-btn wb-btn-secondary" disabled>Download unavailable</button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>Recent Backup</strong>
                    <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">Open Backups</a>
                </div>

                <div class="wb-card-body">
                    @if ($hasRecentBackup)
                        <div class="wb-alert wb-alert-success">
                            <div>
                                <div class="wb-alert-title">Recent backup found</div>
                                <div>The latest successful backup finished at {{ $latestSuccessfulBackup?->finished_at?->format('Y-m-d H:i:s') }}.</div>
                            </div>
                        </div>
                    @else
                        <div class="wb-alert wb-alert-warning">
                            <div>
                                <div class="wb-alert-title">No recent successful backup</div>
                                <div>Create a fresh backup before applying package changes or maintenance work.</div>
                            </div>
                        </div>
                    @endif
                    <div class="wb-text-sm wb-text-muted">Backups remain manual in V1 and are stored locally in app-managed storage.</div>
                </div>
            </div>
        </div>

        @if ($latestUpdateRun)
            <div class="wb-card wb-card-muted">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>Latest Update Run</strong>
                    <span class="wb-status-pill {{ $latestUpdateRun->statusBadgeClass() }}">{{ $latestUpdateRun->statusLabel() }}</span>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>{{ $latestUpdateRun->summary ?: 'No summary recorded.' }}</strong></div>
                    <div class="wb-text-sm wb-text-muted">{{ $latestUpdateRun->from_version }} to {{ $latestUpdateRun->to_version }}</div>
                    <div class="wb-text-sm wb-text-muted">Started {{ $latestUpdateRun->started_at?->format('Y-m-d H:i:s') ?: $latestUpdateRun->created_at?->format('Y-m-d H:i:s') }} | Finished {{ $latestUpdateRun->finished_at?->format('Y-m-d H:i:s') ?: '-' }} | Duration {{ $latestUpdateRun->durationLabel() }}</div>

                    @if ($latestUpdateRun->triggeredBy)
                        <div class="wb-text-sm wb-text-muted">Triggered by {{ $latestUpdateRun->triggeredBy->name }}</div>
                    @endif

                    @if ($latestUpdateRun->warning_count > 0)
                        <div class="wb-text-sm wb-text-muted">Warnings: {{ $latestUpdateRun->warning_count }}</div>
                    @endif

                    @if ($latestUpdateRun->output)
                        <details>
                            <summary>Show output</summary>
                            <pre class="wb-code-block">{{ $latestUpdateRun->output }}</pre>
                        </details>
                    @endif
                </div>
            </div>
        @endif

        <details class="wb-card wb-card-muted">
            <summary class="wb-card-header" style="cursor: pointer;"><strong>Technical details</strong></summary>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-2">
                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <div class="wb-text-sm wb-text-muted">Compatibility</div>
                            <span class="wb-status-pill {{ $compatibilityBadgeClass }}">{{ $compatibilityStatus }}</span>
                        </div>

                        @if (($updateStatus['compatibility']['reasons'] ?? []) === [])
                            <div class="wb-text-sm wb-text-muted">No compatibility issues reported.</div>
                        @else
                            <ul class="wb-stack wb-gap-1">
                                @foreach ($updateStatus['compatibility']['reasons'] as $reason)
                                    <li>{{ $reason }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="wb-stack wb-gap-2">
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

                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">Update server</div>
                        <strong>{{ $updateStatus['server_url'] ?: 'not configured' }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">API version</div>
                        <strong>{{ $updateStatus['api_version'] ?? 'N/A' }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">Site URL</div>
                        <strong>{{ $environment['site_url'] }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">Runtime</div>
                        <strong>PHP {{ $environment['php_version'] }} | Laravel {{ $environment['laravel_version'] }}</strong>
                    </div>
                </div>

                @if ($release)
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Release source</div>
                            <strong>{{ $release['source_type'] ?? 'N/A' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Source reference</div>
                            <strong>{{ $release['source_reference'] ?? 'N/A' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Release date</div>
                            <strong>{{ $release['release_date'] ?? 'N/A' }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">SHA-256</div>
                            <strong>{{ $release['checksum_sha256'] ?? 'N/A' }}</strong>
                        </div>
                    </div>
                @endif
            </div>
        </details>
    </div>
@endsection
