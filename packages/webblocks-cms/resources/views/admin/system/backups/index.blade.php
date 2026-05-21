@extends('webblocks-cms::layouts.admin', ['title' => 'Backups', 'heading' => 'Backups'])

@php
    $filters = $filters ?? ['search' => '', 'type' => '', 'status' => ''];
    $hasActiveFilters = $filters['search'] !== '' || $filters['type'] !== '' || $filters['status'] !== '';
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Backups',
        'description' => 'Create a local backup before updates or other risky maintenance, then review history, upload a downloaded backup archive, and restore through the normal backup flow.',
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        @if (! $backupTableExists)
            <div class="wb-alert wb-alert-warning">
                <div>
                    <div class="wb-alert-title">Backup storage is not ready yet</div>
                    <div>The `system_backups` table is missing in this environment. Run the latest migrations before using Backup Manager V1.</div>
                </div>
            </div>
        @endif

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                @include('webblocks-cms::admin.partials.listing-filters', [
                    'action' => route('admin.system.backups.index'),
                    'search' => [
                        'id' => 'backups_search',
                        'name' => 'search',
                        'label' => 'Search',
                        'value' => $filters['search'],
                        'placeholder' => 'Search archive, source, summary, type, or status',
                    ],
                    'selects' => [
                        [
                            'id' => 'backups_type',
                            'name' => 'type',
                            'label' => 'Type',
                            'selected' => $filters['type'],
                            'placeholder' => 'All types',
                            'options' => [
                                \WebBlocks\Cms\Models\SystemBackup::TYPE_MANUAL => 'Manual',
                                \WebBlocks\Cms\Models\SystemBackup::TYPE_UPLOADED => 'Uploaded',
                                \WebBlocks\Cms\Models\SystemBackup::TYPE_RESTORE_SAFETY => 'Restore safety',
                                \WebBlocks\Cms\Models\SystemBackup::TYPE_PRE_UPDATE => 'Pre update',
                            ],
                        ],
                        [
                            'id' => 'backups_status',
                            'name' => 'status',
                            'label' => 'Status',
                            'selected' => $filters['status'],
                            'placeholder' => 'All statuses',
                            'options' => [
                                \WebBlocks\Cms\Models\SystemBackup::STATUS_COMPLETED => 'Completed',
                                \WebBlocks\Cms\Models\SystemBackup::STATUS_RUNNING => 'Running',
                                \WebBlocks\Cms\Models\SystemBackup::STATUS_FAILED => 'Failed',
                            ],
                        ],
                    ],
                    'showReset' => $hasActiveFilters,
                    'resetUrl' => route('admin.system.backups.index'),
                    'applyLabel' => 'Apply',
                ])
            </div>
        </div>

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

                    @if (! $freshness['has_recent_successful_backup'])
                        <form method="POST" action="{{ route('admin.system.backups.store') }}" class="wb-stack wb-gap-3">
                            @csrf
                            <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                                <button type="submit" class="wb-btn wb-btn-primary" @disabled(! $backupTableExists)>Create backup</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        @if ($backups->isEmpty())
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                        <strong>Backups</strong>
                        <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                    </div>

                    <div class="wb-cluster wb-cluster-2">
                        <form method="POST" action="{{ route('admin.system.backups.store') }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-primary" @disabled(! $backupTableExists)>Create backup</button>
                        </form>
                        <a href="{{ route('admin.system.backups.upload') }}" class="wb-btn wb-btn-secondary">Upload backup</a>
                    </div>
                </div>

                <div class="wb-card-body">
                    <div class="wb-empty">
                        <div class="wb-empty-title">No backup history yet</div>
                        <div class="wb-empty-text">The first completed backup will appear here with size, status, and download actions.</div>
                    </div>
                </div>
            </div>
        @else
            <div class="wb-card" data-wb-admin-bulk-listing>
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                        <strong>Backups</strong>
                        <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                    </div>

                    <div class="wb-cluster wb-cluster-2">
                        <form method="POST" action="{{ route('admin.system.backups.store') }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-primary" @disabled(! $backupTableExists)>Create backup</button>
                        </form>
                        <a href="{{ route('admin.system.backups.upload') }}" class="wb-btn wb-btn-secondary">Upload backup</a>
                    </div>
                </div>

                <div class="wb-card-body">
                    @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                        'label' => 'selected',
                        'deleteTarget' => '#bulk-delete-backups-modal',
                        'deleteLabel' => 'Delete selected',
                    ])

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="wb-checkbox" for="select_all_visible_backups">
                                            <input id="select_all_visible_backups" type="checkbox" data-wb-admin-select-all-visible aria-label="Select all visible backups">
                                            <span class="wb-sr-only">Select all visible backups</span>
                                        </label>
                                    </th>
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
                                        <td>
                                            <label class="wb-checkbox" for="backup_select_{{ $backup->id }}">
                                                <input id="backup_select_{{ $backup->id }}" type="checkbox" value="{{ $backup->id }}" data-wb-admin-row-select aria-label="Select backup {{ $backup->archive_filename ?? '#'.$backup->id }}">
                                                <span class="wb-sr-only">Select backup {{ $backup->archive_filename ?? '#'.$backup->id }}</span>
                                            </label>
                                        </td>
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

                                                <button
                                                    type="button"
                                                    class="wb-action-btn wb-action-btn-delete"
                                                    title="{{ $backup->isRunning() ? 'Delete stuck running backup' : 'Delete backup' }}"
                                                    aria-label="{{ $backup->isRunning() ? 'Delete stuck running backup' : 'Delete backup' }}"
                                                    data-wb-toggle="modal"
                                                    data-wb-target="#delete-backup-{{ $backup->id }}-modal"
                                                >
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    @push('overlays')
                                        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                                            'id' => 'delete-backup-'.$backup->id.'-modal',
                                            'title' => $backup->isRunning() ? 'Delete Stuck Running Backup' : 'Delete Backup',
                                            'description' => $backup->isRunning()
                                                ? 'Only delete a running backup record when you are sure no backup process is still active.'
                                                : 'This deletes the backup record and archive file when present.',
                                            'action' => route('admin.system.backups.destroy', $backup),
                                            'method' => 'DELETE',
                                            'submitLabel' => $backup->isRunning() ? 'Delete stuck backup' : 'Delete backup',
                                        ])
                                            @if ($backup->isRunning())
                                                <input type="hidden" name="force_running" value="1">
                                            @endif

                                            <div class="wb-card wb-card-muted">
                                                <div class="wb-card-body wb-stack wb-gap-2">
                                                    <div><strong>{{ $backup->archive_filename ?? 'Backup #'.$backup->id }}</strong></div>
                                                    <div class="wb-text-sm wb-text-muted">Status {{ $backup->statusLabel() }} | Created {{ $backup->created_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                                                </div>
                                            </div>

                                            <p class="wb-text-sm wb-text-muted">This cannot be undone from the admin UI. Recovery requires another backup archive.</p>
                                        @endcomponent
                                    @endpush
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @include('webblocks-cms::admin.partials.pagination', ['paginator' => $backups])
            </div>

            @push('overlays')
                @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                    'id' => 'bulk-delete-backups-modal',
                    'title' => 'Delete Selected Backups',
                    'description' => 'This deletes the selected backup records and archive files when present.',
                    'action' => route('admin.system.backups.bulk-destroy'),
                    'method' => 'DELETE',
                    'submitLabel' => 'Delete selected',
                    'formAttributes' => [
                        'data-wb-admin-bulk-delete-form' => true,
                    ],
                    'submitAttributes' => [
                        'data-wb-admin-bulk-delete-submit' => true,
                        'disabled' => true,
                    ],
                ])
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <strong><span data-wb-admin-bulk-modal-count>0</span> selected backups will be deleted.</strong>
                            <p class="wb-text-sm wb-text-muted">This first bulk action applies only to backups visible on this page. Active running backups are re-checked on the server and will be skipped unless they are stale.</p>
                        </div>
                    </div>

                    <div data-wb-admin-bulk-inputs></div>
                    <input type="hidden" name="backup_ids[]" value="" disabled data-wb-admin-bulk-empty-input>
                @endcomponent
            @endpush

            @push('scripts')
                @php($bulkActionsJsPath = public_path('cms/js/admin/listing-bulk-actions.js'))
                @if (is_file($bulkActionsJsPath))
                    <script src="{{ asset('cms/js/admin/listing-bulk-actions.js') }}?v={{ filemtime($bulkActionsJsPath) }}" defer></script>
                @endif
            @endpush
        @endif
    </div>
@endsection
