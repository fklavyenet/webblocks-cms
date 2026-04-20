@extends('layouts.admin', ['title' => 'System Updates', 'heading' => 'System Updates'])

@section('content')
    @php
        $updateStatus = $report['version'];
        $diagnostics = $report['diagnostics'];
        $environment = $report['environment'];
        $release = $updateStatus['release'] ?? null;
        $installedVersion = $report['installed_version'] ?? $updateStatus['installed_version'];
        $latestUpdateRun = $latestUpdateRun ?? null;
        $autoUpdate = $report['auto_update'] ?? ['allowed' => false, 'blockers' => [], 'busy' => false];
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
        $releasePreview = $releaseNotes->take(3);
        $hasMoreReleaseNotes = $releaseNotes->count() > $releasePreview->count();
    @endphp

    @include('admin.partials.page-header', [
        'title' => 'System Updates',
        'description' => 'Review the installed version, check the latest published release, and run in-app automatic updates when a compatible release is available.',
        'actions' => '<div class="wb-text-sm wb-text-muted">Last checked at '.$checkedAt->format('Y-m-d H:i:s').'</div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Update Summary</strong>
                </div>

                <div class="wb-card-body wb-stack wb-gap-3">
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

                    <div class="wb-stack wb-gap-2">
                        <div class="wb-text-sm wb-text-muted">Release notes</div>

                        @if ($releasePreview->isEmpty())
                            <div class="wb-empty wb-empty-sm">
                                <div class="wb-empty-title">No release notes available</div>
                            </div>
                        @else
                            <div class="wb-stack wb-gap-2">
                                @foreach ($releasePreview as $note)
                                    <div class="wb-text-sm wb-text-muted">{{ $note }}</div>
                                @endforeach
                            </div>

                            @if ($hasMoreReleaseNotes)
                                <div class="wb-text-sm wb-text-muted">Additional release notes are available in the published release details.</div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header">
                    <strong>Actions</strong>
                </div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    <a href="{{ route('admin.system.updates.check') }}" class="wb-btn wb-btn-secondary">Check again</a>

                    <form method="POST" action="{{ route('admin.system.updates.store') }}" class="wb-stack wb-gap-2" data-wb-update-form>
                        @csrf

                        <label class="wb-checkbox">
                            <input type="checkbox" name="acknowledge_backup_risk" value="1" @checked(old('acknowledge_backup_risk'))>
                            <span>Automatic backup is not created before update in this version.</span>
                        </label>

                        <button
                            type="submit"
                            class="wb-btn wb-btn-primary"
                            data-wb-update-submit
                            data-default-label="Update now"
                            data-busy-label="Updating..."
                            @disabled(! $autoUpdate['allowed'])
                        >
                            {{ $autoUpdate['busy'] ? 'Updating...' : 'Update now' }}
                        </button>
                    </form>

                    @if ($autoUpdate['allowed'])
                        <div class="wb-text-sm wb-text-muted">The system will download, verify, install, migrate, clear runtime caches, and bring the site back online automatically.</div>
                    @else
                        <div class="wb-text-sm wb-text-muted">{{ $autoUpdate['blockers'][0] ?? 'Automatic updates are not available right now.' }}</div>
                    @endif
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

@push('scripts')
    <script>
        document.addEventListener('submit', function (event) {
            var form = event.target.closest('[data-wb-update-form]');

            if (!form) {
                return;
            }

            var button = form.querySelector('[data-wb-update-submit]');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.textContent = button.getAttribute('data-busy-label') || 'Updating...';
        });
    </script>
@endpush
