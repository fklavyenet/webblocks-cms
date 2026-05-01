@extends('layouts.admin', ['title' => 'Backups', 'heading' => 'Backups'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Backups',
        'description' => 'Create a local backup before updates or other risky maintenance, then review history, upload a downloaded backup archive, and restore through the normal backup flow.',
        'count' => $backups->total(),
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.system.updates.index').'" class="wb-btn wb-btn-secondary">System Updates</a><a href="'.route('admin.system.backups.upload').'" class="wb-btn wb-btn-secondary">Upload backup</a></div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        @if (! $backupTableExists)
            <div class="wb-alert wb-alert-warning">
                <div>
                    <div class="wb-alert-title">Backup storage is not ready yet</div>
                    <div>The `system_backups` table is missing in this environment. Run the latest migrations before using Backup Manager V1.</div>
                </div>
            </div>
        @endif

        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>Latest Backup Status</strong>
                    @if ($latestBackup)
                        <span class="wb-status-pill {{ $latestBackup->statusBadgeClass() }}">{{ $latestBackup->statusLabel() }}</span>
                    @endif
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    @if ($latestBackup)
                        <div><strong>{{ $latestBackup->summary ?? 'Backup record available.' }}</strong></div>
                        <div class="wb-text-sm wb-text-muted">Started {{ $latestBackup->started_at?->format('Y-m-d H:i:s') ?? '-' }} | Finished {{ $latestBackup->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                        <div class="wb-text-sm wb-text-muted">Contents {{ $latestBackup->contentsLabel() ?: '-' }} | Size {{ $latestBackup->humanArchiveSize() }} | Duration {{ $latestBackup->durationLabel() }}</div>

                        @if ($latestBackup->triggeredBy)
                            <div class="wb-text-sm wb-text-muted">Triggered by {{ $latestBackup->triggeredBy->name }}</div>
                        @endif

                        @if ($latestBackup->error_message)
                            <div class="wb-alert wb-alert-danger">
                                <div>
                                    <div class="wb-alert-title">Latest failure</div>
                                    <div>{{ $latestBackup->error_message }}</div>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No backups yet</div>
                            <div class="wb-empty-text">Create the first backup before running updates or other maintenance changes.</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>Backup Recommendation</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    @if ($freshness['has_recent_successful_backup'])
                        <div class="wb-alert wb-alert-success">
                            <div>
                                <div class="wb-alert-title">Recent backup available</div>
                                <div>The latest successful backup finished at {{ $freshness['latest_successful']?->finished_at?->format('Y-m-d H:i:s') }}. System Updates will treat this as recent for {{ $freshness['hours'] }} hours.</div>
                            </div>
                        </div>
                    @else
                        <div class="wb-alert wb-alert-warning">
                            <div>
                                <div class="wb-alert-title">Create a backup before risky changes</div>
                                <div>No successful backup has finished within the last {{ $freshness['hours'] }} hours. Run one now before updates, package changes, or other maintenance.</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-text-sm wb-text-muted">Each backup archive includes a database dump, the current `storage/app/public` uploads snapshot, and a manifest. Uploaded backup archives are validated before they are registered. This full-system restore flow overwrites the current database and uploaded files, is different from Export / Import, and reuses the same restore path that creates a fresh safety backup first.</div>

                    <form method="POST" action="{{ route('admin.system.backups.store') }}" class="wb-stack wb-gap-3">
                        @csrf
                        <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                            <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                                <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">Cancel</a>
                                <button type="submit" class="wb-btn wb-btn-primary" @disabled(! $backupTableExists)>Create backup</button>
                                <a href="{{ route('admin.system.backups.upload') }}" class="wb-btn wb-btn-secondary">Upload backup</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($backups->isEmpty())
            <div class="wb-card">
                <div class="wb-card-body">
                    <div class="wb-empty">
                        <div class="wb-empty-title">No backup history yet</div>
                        <div class="wb-empty-text">The first completed backup will appear here with size, status, and download actions.</div>
                    </div>
                </div>
            </div>
        @else
            <div class="wb-card">
                <div class="wb-card-body">
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>Created at</th>
                                    <th>Archive</th>
                                    <th>Status</th>
                                    <th>Contents</th>
                                    <th>Size</th>
                                    <th>Triggered by</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($backups as $backup)
                                    <tr>
                                        <td>{{ $backup->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td>
                                            <div>{{ $backup->archive_filename ?? '-' }}</div>
                                            @if ($backup->label)
                                                <div class="wb-text-sm wb-text-muted">Source {{ $backup->label }}</div>
                                            @endif
                                        </td>
                                        <td><span class="wb-status-pill {{ $backup->statusBadgeClass() }}">{{ $backup->statusLabel() }}</span></td>
                                        <td>{{ $backup->contentsLabel() ?: '-' }}</td>
                                        <td>{{ $backup->humanArchiveSize() }}</td>
                                        <td>{{ $backup->triggeredBy?->name ?? '-' }}</td>
                                        <td>
                                            <div class="wb-action-group">
                                                <a href="{{ route('admin.system.backups.show', $backup) }}" class="wb-action-btn wb-action-btn-view" title="Backup details" aria-label="Backup details">
                                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                </a>

                                                @if ($backup->isSuccessful() && $backup->archive_path)
                                                    <a href="{{ route('admin.system.backups.download', $backup) }}" class="wb-action-btn wb-action-btn-download" title="Download backup" aria-label="Download backup">
                                                        <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                                                    </a>
                                                @endif

                                                <form method="POST" action="{{ route('admin.system.backups.destroy', $backup) }}" onsubmit="return confirm('{{ $backup->isRunning() ? 'This backup is marked as running. Delete this stuck backup record anyway? Only do this if no backup process is still active.' : 'Delete this backup record and archive file? This cannot be undone.' }}');">
                                                    @csrf
                                                    @method('DELETE')

                                                    @if ($backup->isRunning())
                                                        <input type="hidden" name="force_running" value="1">
                                                    @endif

                                                    <button
                                                        type="submit"
                                                        class="wb-action-btn wb-action-btn-delete"
                                                        title="{{ $backup->isRunning() ? 'Delete stuck running backup' : 'Delete backup' }}"
                                                        aria-label="{{ $backup->isRunning() ? 'Delete stuck running backup' : 'Delete backup' }}"
                                                    >
                                                        <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @include('admin.partials.pagination', ['paginator' => $backups])
            </div>
        @endif
    </div>
@endsection
