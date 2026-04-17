@extends('layouts.admin', ['title' => 'System Updates', 'heading' => 'System Updates'])

@section('content')
    @php
        $updateStatus = $report['version'];
        $eligibility = $report['eligibility'];
        $diagnostics = $report['diagnostics'];
        $latestSuccessfulBackup = $backupFreshness['latest_successful'];
    @endphp

    @include('admin.partials.page-header', [
        'title' => 'System Updates',
        'description' => 'Review the installed version, available update, and safe update steps before applying a system update.',
        'actions' => '<div class="wb-stack wb-gap-1 wb-text-right"><div class="wb-text-sm wb-text-muted">Last checked at '.$checkedAt->format('Y-m-d H:i:s').'</div><a href="'.route('admin.system.updates.check').'" class="wb-btn wb-btn-secondary">Check again</a></div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Current Status</strong>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <span class="wb-status-pill {{ $eligibility['badge_class'] }}">{{ $eligibility['label'] }}</span>
                    <div class="wb-text-sm wb-text-muted">{{ $eligibility['message'] }}</div>

                    @if ($updateStatus['fallback_warning'])
                        <div class="wb-alert wb-alert-warning">
                            <div>
                                <div class="wb-alert-title">Remote check warning</div>
                                <div>{{ $updateStatus['fallback_warning'] }}</div>
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
                            <strong>{{ $updateStatus['current_version'] }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Available version</div>
                            <strong>{{ $updateStatus['latest_version'] }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Source</div>
                            <strong>{{ ucfirst($updateStatus['source']) }}</strong>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Channel</div>
                            <strong>{{ $updateStatus['channel'] }}</strong>
                        </div>
                    </div>

                    @if ($updateStatus['published_at'])
                        <div class="wb-text-sm wb-text-muted">Published at {{ \Carbon\Carbon::parse($updateStatus['published_at'])->format('Y-m-d H:i:s') }}</div>
                    @endif
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
                <strong>Release Notes</strong>
            </div>

            <div class="wb-card-body">
                @if ($updateStatus['release_notes'] === [])
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">No release notes available</div>
                    </div>
                @else
                    <ul class="wb-stack wb-gap-1">
                        @foreach ($updateStatus['release_notes'] as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                <strong>Recent Backup</strong>
                <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">Open Backups</a>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                @if ($backupFreshness['has_recent_successful_backup'])
                    <div class="wb-alert wb-alert-success">
                        <div>
                            <div class="wb-alert-title">Recent backup found</div>
                            <div>The latest successful backup finished at {{ $latestSuccessfulBackup?->finished_at?->format('Y-m-d H:i:s') }}. This counts as recent for {{ $backupFreshness['hours'] }} hours.</div>
                        </div>
                    </div>
                @else
                    <div class="wb-alert wb-alert-warning">
                        <div>
                            <div class="wb-alert-title">No recent successful backup</div>
                            <div>Updates do not create a backup automatically. Create one from the Backups screen before proceeding if you want a current database and uploads snapshot.</div>
                        </div>
                    </div>
                @endif

                <ul class="wb-stack wb-gap-1 wb-text-sm wb-text-muted">
                    <li>Backup Manager V1 stores a local zip archive in app-managed storage.</li>
                    <li>Each archive includes a database dump, `storage/app/public`, and a manifest.</li>
                    <li>Restore tooling is not included yet, so verify the archive before risky maintenance.</li>
                </ul>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <strong>Actions</strong>
            </div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-text-sm wb-text-muted">The update will run migrations and clear framework caches in maintenance mode.</div>

                <form method="POST" action="{{ route('admin.system.updates.run') }}" class="wb-stack wb-gap-3">
                    @csrf

                    <label class="wb-checkbox">
                        <input type="checkbox" name="confirm_backup" value="1" @checked(old('confirm_backup'))>
                        <span>I understand that WebBlocks CMS will not create a backup automatically during this update, and I have reviewed backup status or accept the risk.</span>
                    </label>

                    <div class="wb-cluster wb-cluster-between wb-cluster-2">
                        <div class="wb-text-sm wb-text-muted">Run this only when the diagnostics above match your deployment state.</div>

                        @if (! $eligibility['can_update'])
                            <button type="button" class="wb-btn wb-btn-secondary" disabled>{{ $eligibility['label'] }}</button>
                        @elseif ($updateStatus['up_to_date'])
                            <button type="button" class="wb-btn wb-btn-secondary" disabled>Up to date</button>
                        @else
                            <button type="submit" class="wb-btn wb-btn-primary">Update now</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if (session('update_output'))
            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Update Output</strong>
                </div>

                <div class="wb-card-body">
                    <pre class="wb-code-block">{{ implode(PHP_EOL.PHP_EOL, session('update_output')) }}</pre>
                </div>
            </div>
        @endif

        @if (session('update_error_output'))
            <div class="wb-card wb-card-danger">
                <div class="wb-card-header">
                    <strong>Last Error Output</strong>
                </div>

                <div class="wb-card-body">
                    <pre class="wb-code-block">{{ session('update_error_output') }}</pre>
                </div>
            </div>
        @endif

        @if ($latestRun)
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>Latest Update Run</strong>
                    <span class="wb-status-pill {{ in_array($latestRun->status, ['success', 'success_with_warnings'], true) ? ($latestRun->status === 'success' ? 'wb-status-active' : 'wb-status-pending') : 'wb-status-danger' }}">{{ str_replace('_', ' ', $latestRun->status) }}</span>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div class="wb-text-sm wb-text-muted">{{ $latestRun->from_version }} to {{ $latestRun->to_version }}</div>
                    <div class="wb-text-sm wb-text-muted">Started {{ $latestRun->started_at?->format('Y-m-d H:i:s') ?: $latestRun->created_at?->format('Y-m-d H:i:s') }} | Finished {{ $latestRun->finished_at?->format('Y-m-d H:i:s') ?: '-' }} | Duration {{ $latestRun->duration_ms ? number_format($latestRun->duration_ms) .' ms' : '-' }}</div>

                    @if ($latestRun->triggeredBy)
                        <div class="wb-text-sm wb-text-muted">Triggered by {{ $latestRun->triggeredBy->name }}</div>
                    @endif

                    @if ($latestRun->summary)
                        <div><strong>{{ $latestRun->summary }}</strong></div>
                    @endif

                    @if ($latestRun->warning_count > 0)
                        <div class="wb-text-sm wb-text-muted">Warnings: {{ $latestRun->warning_count }}</div>
                    @endif

                    @if ($latestRun->output)
                        <pre class="wb-code-block">{{ $latestRun->output }}</pre>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
