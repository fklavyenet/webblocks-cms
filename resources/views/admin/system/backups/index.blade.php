@extends('layouts.admin', ['title' => 'Backups', 'heading' => 'Backups'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Backups',
        'description' => 'Create a local backup before updates or other risky maintenance, then review history and download the resulting archive.',
        'count' => $backups->total(),
        'actions' => '<a href="'.route('admin.system.updates.index').'" class="wb-btn wb-btn-secondary">System Updates</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
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

                    <div class="wb-text-sm wb-text-muted">Backup Manager V1 creates a local zip archive with a database dump, the current `storage/app/public` uploads snapshot, and a manifest. Restore tooling is intentionally not included yet.</div>

                    <form method="POST" action="{{ route('admin.system.backups.store') }}">
                        @csrf
                        <button type="submit" class="wb-btn wb-btn-primary">Create backup</button>
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
                                    <th>Status</th>
                                    <th>Type</th>
                                    <th>Contents</th>
                                    <th>Size</th>
                                    <th>Triggered by</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($backups as $backup)
                                    <tr>
                                        <td>{{ $backup->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td><span class="wb-status-pill {{ $backup->statusBadgeClass() }}">{{ $backup->statusLabel() }}</span></td>
                                        <td>{{ $backup->type }}</td>
                                        <td>{{ $backup->contentsLabel() ?: '-' }}</td>
                                        <td>{{ $backup->humanArchiveSize() }}</td>
                                        <td>{{ $backup->triggeredBy?->name ?? '-' }}</td>
                                        <td>{{ $backup->durationLabel() }}</td>
                                        <td>
                                            <div class="wb-cluster wb-cluster-2 wb-row-end">
                                                <a href="{{ route('admin.system.backups.show', $backup) }}" class="wb-btn wb-btn-secondary">Details</a>

                                                @if ($backup->isSuccessful() && $backup->archive_path)
                                                    <a href="{{ route('admin.system.backups.download', $backup) }}" class="wb-btn wb-btn-primary">Download</a>
                                                @endif
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
