@extends('webblocks-cms::layouts.admin', ['title' => 'System Updates', 'heading' => 'System Updates'])

@section('content')
    @php
        $updateStatus = $report['version'];
        $diagnostics = $report['diagnostics'];
        $environment = $report['environment'];
        $release = $updateStatus['release'] ?? null;
        $installedVersion = $report['installed_version'] ?? $updateStatus['installed_version'];
        $latestUpdateRun = $latestUpdateRun ?? null;
        $pendingUpdate = $pendingUpdate ?? null;
        $pendingBackup = $pendingBackup ?? null;
        $autoUpdate = $report['auto_update'] ?? ['allowed' => false, 'blockers' => [], 'busy' => false];
        $compatibilityStatus = $updateStatus['compatibility']['status'] ?? 'unknown';
        $showLatestVersion = ($updateStatus['latest_version'] ?? null) !== null
            && (string) $installedVersion !== (string) $updateStatus['latest_version'];
        $compatibilityBadgeClass = match ($compatibilityStatus) {
            'compatible' => 'wb-status-active',
            'incompatible' => 'wb-status-danger',
            default => 'wb-status-pending',
        };
        $releaseDetails = $release['release_details'] ?? null;

        if (! is_array($releaseDetails)) {
            $fallbackText = trim((string) (($release['description'] ?? '') ?: ($release['changelog'] ?? '')));
            $fallbackNotes = collect(preg_split('/\r\n|\r|\n/', $fallbackText ?: ''))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->values();
            $releaseDetails = [
                'title' => null,
                'summary' => $fallbackNotes->shift(),
                'groups' => [],
                'fallback_notes' => $fallbackNotes->all(),
                'has_notes' => $fallbackText !== '',
            ];
        }

        $releaseDetailGroups = collect($releaseDetails['groups'] ?? [])
            ->filter(fn ($group) => is_array($group) && collect($group['items'] ?? [])->filter()->isNotEmpty())
            ->values();
        $fallbackReleaseNotes = collect($releaseDetails['fallback_notes'] ?? [])->filter()->values();
        $hasReleaseDetails = (bool) ($releaseDetails['has_notes'] ?? false)
            || trim((string) ($releaseDetails['title'] ?? '')) !== ''
            || trim((string) ($releaseDetails['summary'] ?? '')) !== ''
            || $releaseDetailGroups->isNotEmpty()
            || $fallbackReleaseNotes->isNotEmpty();
        $showUpdateOptions = ($pendingUpdate && $pendingBackup)
            || in_array(($updateStatus['state'] ?? null), ['update_available', 'incompatible'], true)
            || ($updateStatus['update_available'] ?? false) === true;
        $headerActions = '<div class="wb-stack wb-gap-2">'
            .'<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.system.updates.check').'" class="wb-btn wb-btn-secondary">Check again</a></div>'
            .'<div class="wb-text-sm wb-text-muted">Last checked at '.$checkedAt->format('Y-m-d H:i:s').'</div>'
            .'</div>';
        $diagnosticItems = collect($diagnostics)->prepend([
            'label' => 'Compatibility',
            'status' => $compatibilityStatus,
            'message' => ($updateStatus['compatibility']['reasons'] ?? []) === []
                ? 'No compatibility issues reported.'
                : implode(' ', $updateStatus['compatibility']['reasons']),
            'badge_class' => $compatibilityBadgeClass,
        ]);
    @endphp

    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'System Updates',
        'description' => 'Review the installed version, check the latest published release, and run in-app automatic updates when a compatible release is available.',
        'actions' => $headerActions,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Update Summary</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <span class="wb-status-pill {{ $updateStatus['badge_class'] }}">{{ $updateStatus['label'] }}</span>
                @if (! (($updateStatus['state'] ?? null) === 'up_to_date'))
                    <div class="wb-text-sm wb-text-muted">{{ $updateStatus['message'] }}</div>
                @endif

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

                    @if ($showLatestVersion)
                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Latest version</div>
                            <strong>{{ $updateStatus['latest_version'] }}</strong>
                        </div>
                    @endif

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

        @if ($pendingUpdate && $pendingBackup)
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Update Options</strong>
                </div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-stack wb-gap-2">
                        <div class="wb-alert wb-alert-info">
                            <div>
                                <div class="wb-alert-title">Pre-update backup created.</div>
                                <div>A pre-update backup was created automatically. Download it before continuing the installation.</div>
                            </div>
                        </div>

                        <div class="wb-grid wb-grid-2">
                            <div class="wb-stack wb-gap-1">
                                <div class="wb-text-sm wb-text-muted">Backup name</div>
                                <strong>{{ $pendingBackup->archive_filename ?? ('Backup #'.$pendingBackup->id) }}</strong>
                            </div>

                            <div class="wb-stack wb-gap-1">
                                <div class="wb-text-sm wb-text-muted">Backup size</div>
                                <strong>{{ $pendingBackup->humanArchiveSize() }}</strong>
                            </div>

                            @if (! $showLatestVersion || (string) ($pendingUpdate['to_version'] ?? '') !== (string) ($updateStatus['latest_version'] ?? ''))
                                <div class="wb-stack wb-gap-1">
                                    <div class="wb-text-sm wb-text-muted">Pending target</div>
                                    <strong>{{ $pendingUpdate['to_version'] }}</strong>
                                </div>
                            @endif
                        </div>

                        <div class="wb-cluster wb-cluster-2">
                            <a href="{{ route('admin.system.backups.download', $pendingBackup) }}" class="wb-btn wb-btn-secondary">Download backup</a>

                            <form method="POST" action="{{ route('admin.system.updates.continue') }}" data-wb-update-form>
                                @csrf
                                <button type="submit" class="wb-btn wb-btn-primary" data-wb-update-submit data-default-label="Continue update" data-busy-label="Updating...">Continue update</button>
                            </form>

                            <form method="POST" action="{{ route('admin.system.updates.cancel') }}">
                                @csrf
                                <button type="submit" class="wb-btn wb-btn-secondary">Cancel</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($showUpdateOptions)
            <form method="POST" action="{{ route('admin.system.updates.store') }}" data-wb-update-form>
                @csrf

                <div class="wb-card">
                    <div class="wb-card-header">
                        <strong>Update Options</strong>
                    </div>

                    <div class="wb-card-body wb-stack wb-gap-3">
                        <div class="wb-stack wb-gap-2">
                            <label class="wb-checkbox">
                                <input type="checkbox" name="download_pre_update_backup" value="1" @checked(old('download_pre_update_backup'))>
                                <span>Download backup before update</span>
                            </label>

                            <div class="wb-text-sm wb-text-muted">A pre-update backup is always created automatically. Enable this option to also download the backup before installation starts.</div>

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
                        </div>

                        <div class="wb-text-sm wb-text-muted">
                            @if ($autoUpdate['allowed'])
                                If download is not selected, the CMS will create the pre-update backup, then download, verify, install, migrate, clear runtime caches, and bring the site back online automatically.
                            @else
                                {{ $autoUpdate['blockers'][0] ?? 'Automatic updates are not available right now.' }}
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        @endif

        @if ($release)
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Release Details</strong>
                </div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    @if ($hasReleaseDetails)
                        @if (trim((string) ($releaseDetails['title'] ?? '')) !== '')
                            <strong>{{ $releaseDetails['title'] }}</strong>
                        @endif

                        @if (trim((string) ($releaseDetails['summary'] ?? '')) !== '')
                            <div class="wb-text-sm">{{ $releaseDetails['summary'] }}</div>
                        @endif

                        @foreach ($releaseDetailGroups as $group)
                            <div class="wb-stack wb-gap-1">
                                <strong class="wb-text-sm">{{ $group['label'] ?? 'Release details' }}</strong>
                                <ul class="wb-m-0 wb-text-sm wb-text-muted">
                                    @foreach (collect($group['items'] ?? [])->filter() as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach

                        @if ($fallbackReleaseNotes->isNotEmpty())
                            <div class="wb-stack wb-gap-1">
                                <strong class="wb-text-sm">Release notes</strong>
                                <ul class="wb-m-0 wb-text-sm wb-text-muted">
                                    @foreach ($fallbackReleaseNotes as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @else
                        <div class="wb-text-sm wb-text-muted">No release notes were provided for this release.</div>
                    @endif
                </div>
            </div>
        @endif

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
            <summary class="wb-card-header"><strong>Technical details</strong></summary>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-grid wb-grid-2">
                    @foreach ($diagnosticItems->chunk((int) ceil($diagnosticItems->count() / 2)) as $diagnosticColumn)
                        <div class="wb-stack wb-gap-2">
                            @foreach ($diagnosticColumn as $diagnostic)
                            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                <div class="wb-stack wb-gap-1">
                                    <strong>{{ $diagnostic['label'] }}</strong>
                                    <div class="wb-text-sm wb-text-muted">{{ $diagnostic['message'] }}</div>
                                </div>

                                <span class="wb-status-pill {{ $diagnostic['badge_class'] }}">{{ $diagnostic['status'] }}</span>
                            </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">Update server</div>
                        <strong>{{ $updateStatus['server_url'] ?: 'not configured' }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">Site URL</div>
                        <strong>{{ $environment['site_url'] }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">API version</div>
                        <strong>{{ $updateStatus['api_version'] ?? 'N/A' }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <div class="wb-text-sm wb-text-muted">Runtime</div>
                        <strong>PHP {{ $environment['php_version'] }} | Laravel {{ $environment['laravel_version'] }}</strong>
                    </div>

                    @if ($release)
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
                    @endif
                </div>
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
