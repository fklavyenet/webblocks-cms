@php
  use WebBlocks\Cms\Models\SystemBackup;
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $adminLocale = app(AdminLocaleResolver::class)->locale();
  $adminTranslator = app(CmsTranslator::class);
  $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
  $backupStatusLabel = static fn (?string $status) => $status ? $adminText('backups.statuses.'.$status) : '-';
  $backupTypeLabel = static fn (?string $type) => $type ? $adminText('backups.types.'.$type) : '-';
  $backupContentsLabel = static function (SystemBackup $backup) use ($adminText): string {
    return collect([
      $backup->includes_database ? $adminText('backups.contents.database') : null,
      $backup->includes_uploads ? $adminText('backups.contents.uploads') : null,
    ])->filter()->implode(' + ') ?: '-';
  };
  $archiveStatusLabel = static fn ($resolution) => $resolution ? $adminText('backups.archive_statuses.'.$resolution->status) : '';
  $archiveFeedback = static fn ($resolution) => $resolution ? $adminText('backups.archive_feedback.'.$resolution->status) : $adminText('backups.archive_unavailable');
  $filters = $filters ?? ['search' => '', 'type' => '', 'status' => ''];
  $hasActiveFilters = $filters['search'] !== '' || $filters['type'] !== '' || $filters['status'] !== '';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('backups.title'), 'heading' => $adminText('backups.title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('backups.title'),
        'description' => $adminText('backups.description'),
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        @if (! $backupTableExists)
            <div class="wb-alert wb-alert-warning">
                <div>
                    <div class="wb-alert-title">{{ $adminText('backups.storage_not_ready') }}</div>
                    <div>{{ $adminText('backups.storage_not_ready_help') }}</div>
                </div>
            </div>
        @endif

        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('backups.latest_status') }}</strong>
                    @if ($latestBackup)
                        <span class="wb-status-pill {{ $latestBackup->statusBadgeClass() }}">{{ $backupStatusLabel($latestBackup->status) }}</span>
                    @endif
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    @if ($latestBackup)
                        <div><strong>{{ $latestBackup->summary ?? $adminText('backups.record_available') }}</strong></div>
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('backups.started') }} {{ $latestBackup->started_at?->format('Y-m-d H:i:s') ?? '-' }} | {{ $adminText('backups.finished') }} {{ $latestBackup->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('backups.contents_label') }} {{ $backupContentsLabel($latestBackup) }} | {{ $adminText('backups.size') }} {{ $latestBackup->humanArchiveSize() }} | {{ $adminText('backups.duration') }} {{ $latestBackup->durationLabel() }}</div>

                        @if ($latestBackup->triggeredBy)
                            <div class="wb-text-sm wb-text-muted">{{ $adminText('backups.triggered_by') }} {{ $latestBackup->triggeredBy->name }}</div>
                        @endif

                        @if ($latestBackup->error_message)
                            <div class="wb-alert wb-alert-danger">
                                <div>
                                    <div class="wb-alert-title">{{ $adminText('backups.latest_failure') }}</div>
                                    <div>{{ $latestBackup->error_message }}</div>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('backups.no_backups') }}</div>
                            <div class="wb-empty-text">{{ $adminText('backups.no_backups_help') }}</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('backups.recommendation') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    @if ($freshness['has_recent_successful_backup'])
                        <div class="wb-alert wb-alert-success">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('backups.recent_available') }}</div>
                                <div>{{ $adminText('backups.recent_available_help', ['date' => $freshness['latest_successful']?->finished_at?->format('Y-m-d H:i:s'), 'hours' => $freshness['hours']]) }}</div>
                            </div>
                        </div>
                    @else
                        <div class="wb-alert wb-alert-warning">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('backups.create_before_risky_changes') }}</div>
                                <div>{{ $adminText('backups.create_before_risky_changes_help', ['hours' => $freshness['hours']]) }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-text-sm wb-text-muted">{{ $adminText('backups.recommendation_detail') }}</div>
                </div>
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                @include('webblocks-cms::admin.partials.listing-filters', [
                    'action' => route('admin.system.backups.index'),
                    'search' => [
                        'id' => 'backups_search',
                        'name' => 'search',
                        'label' => $adminText('common.search'),
                        'value' => $filters['search'],
                        'placeholder' => $adminText('backups.search_placeholder'),
                    ],
                    'selects' => [
                        [
                            'id' => 'backups_type',
                            'name' => 'type',
                            'label' => $adminText('backups.type'),
                            'selected' => $filters['type'],
                            'placeholder' => $adminText('backups.all_types'),
                            'options' => [
                                SystemBackup::TYPE_MANUAL => $adminText('backups.types.manual'),
                                SystemBackup::TYPE_UPLOADED => $adminText('backups.types.uploaded'),
                                SystemBackup::TYPE_RESTORE_SAFETY => $adminText('backups.types.restore_safety'),
                                SystemBackup::TYPE_PRE_UPDATE => $adminText('backups.types.pre_update'),
                                SystemBackup::TYPE_CONTENT_APPLY => $adminText('backups.types.content_apply'),
                            ],
                        ],
                        [
                            'id' => 'backups_status',
                            'name' => 'status',
                            'label' => $adminText('common.status'),
                            'selected' => $filters['status'],
                            'placeholder' => $adminText('common.all_statuses'),
                            'options' => [
                                SystemBackup::STATUS_COMPLETED => $adminText('backups.statuses.completed'),
                                SystemBackup::STATUS_RUNNING => $adminText('backups.statuses.running'),
                                SystemBackup::STATUS_FAILED => $adminText('backups.statuses.failed'),
                            ],
                        ],
                    ],
                    'showReset' => $hasActiveFilters,
                    'resetUrl' => route('admin.system.backups.index'),
                    'applyLabel' => $adminText('common.apply'),
                ])
            </div>
        </div>

        @if ($backups->isEmpty())
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                        <strong>{{ $adminText('backups.title') }}</strong>
                        <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                    </div>

                    <div class="wb-cluster wb-cluster-2">
                        <form method="POST" action="{{ route('admin.system.backups.store') }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-primary" @disabled(! $backupTableExists)>{{ $adminText('backups.create_backup') }}</button>
                        </form>
                        <a href="{{ route('admin.system.backups.upload') }}" class="wb-btn wb-btn-secondary">{{ $adminText('backups.upload_backup') }}</a>
                    </div>
                </div>

                <div class="wb-card-body">
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('backups.no_history') }}</div>
                        <div class="wb-empty-text">{{ $adminText('backups.no_history_help') }}</div>
                    </div>
                </div>
            </div>
        @else
            <div class="wb-card" data-wb-admin-bulk-listing>
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                        <strong>{{ $adminText('backups.title') }}</strong>
                        <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                    </div>

                    <div class="wb-cluster wb-cluster-2">
                        <form method="POST" action="{{ route('admin.system.backups.store') }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-primary" @disabled(! $backupTableExists)>{{ $adminText('backups.create_backup') }}</button>
                        </form>
                        <a href="{{ route('admin.system.backups.upload') }}" class="wb-btn wb-btn-secondary">{{ $adminText('backups.upload_backup') }}</a>
                    </div>
                </div>

                <div class="wb-card-body">
                    @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                        'label' => $adminText('common.selected'),
                        'deleteTarget' => '#bulk-delete-backups-modal',
                        'deleteLabel' => $adminText('common.delete_selected'),
                    ])

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="wb-checkbox" for="select_all_visible_backups">
                                            <input id="select_all_visible_backups" type="checkbox" data-wb-admin-select-all-visible aria-label="{{ $adminText('backups.select_all_visible') }}">
                                            <span class="wb-sr-only">{{ $adminText('backups.select_all_visible') }}</span>
                                        </label>
                                    </th>
                                    <th>{{ $adminText('backups.created_at') }}</th>
                                    <th>{{ $adminText('backups.archive') }}</th>
                                    <th>{{ $adminText('common.status') }}</th>
                                    <th>{{ $adminText('backups.contents_label') }}</th>
                                    <th>{{ $adminText('backups.size') }}</th>
                                    <th>{{ $adminText('backups.triggered_by') }}</th>
                                    <th>{{ $adminText('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($backups as $backup)
                                    @php($archiveResolution = $backupArchiveStatuses[$backup->id] ?? null)
                                    <tr>
                                        <td>
                                            <label class="wb-checkbox" for="backup_select_{{ $backup->id }}">
                                                <input id="backup_select_{{ $backup->id }}" type="checkbox" value="{{ $backup->id }}" data-wb-admin-row-select aria-label="{{ $adminText('backups.select_backup', ['name' => $backup->archive_filename ?? '#'.$backup->id]) }}">
                                                <span class="wb-sr-only">{{ $adminText('backups.select_backup', ['name' => $backup->archive_filename ?? '#'.$backup->id]) }}</span>
                                            </label>
                                        </td>
                                        <td>{{ $backup->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td>
                                            <div>{{ $backup->archive_filename ?? '-' }}</div>
                                            @if ($backup->label)
                                                <div class="wb-text-sm wb-text-muted">{{ $adminText('backups.source') }} {{ $backup->label }}</div>
                                            @endif
                                            @if ($archiveResolution && ! $archiveResolution->isAvailable())
                                                <div class="wb-mt-1">
                                                    <span class="wb-status-pill {{ $archiveResolution->uiBadgeClass() }}">{{ $archiveStatusLabel($archiveResolution) }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td><span class="wb-status-pill {{ $backup->statusBadgeClass() }}">{{ $backupStatusLabel($backup->status) }}</span></td>
                                        <td>{{ $backupContentsLabel($backup) }}</td>
                                        <td>{{ $backup->humanArchiveSize() }}</td>
                                        <td>{{ $backup->triggeredBy?->name ?? '-' }}</td>
                                        <td class="wb-table-actions">
                                            <div class="wb-action-group">
                                                <a href="{{ route('admin.system.backups.show', $backup) }}" class="wb-action-btn wb-action-btn-view" title="{{ $adminText('backups.details') }}" aria-label="{{ $adminText('backups.details') }}">
                                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                </a>

                                                @if ($archiveResolution?->isAvailable())
                                                    <a href="{{ route('admin.system.backups.download', $backup) }}" class="wb-action-btn wb-action-btn-download" title="{{ $adminText('backups.download_backup') }}" aria-label="{{ $adminText('backups.download_backup') }}">
                                                        <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                                                    </a>
                                                @elseif ($backup->isSuccessful() && $backup->archive_path)
                                                    <button type="button" class="wb-action-btn wb-action-btn-download" title="{{ $archiveFeedback($archiveResolution) }}" aria-label="{{ $archiveFeedback($archiveResolution) }}" disabled>
                                                        <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                                                    </button>
                                                @endif

                                                <button
                                                    type="button"
                                                    class="wb-action-btn wb-action-btn-delete"
                                                    title="{{ $backup->isRunning() ? $adminText('backups.delete_stuck_running') : $adminText('backups.delete_backup') }}"
                                                    aria-label="{{ $backup->isRunning() ? $adminText('backups.delete_stuck_running') : $adminText('backups.delete_backup') }}"
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
                                            'title' => $backup->isRunning() ? $adminText('backups.delete_stuck_running_title') : $adminText('backups.delete_backup_title'),
                                            'description' => $backup->isRunning()
                                                ? $adminText('backups.delete_stuck_running_description')
                                                : $adminText('backups.delete_backup_description'),
                                            'action' => route('admin.system.backups.destroy', $backup),
                                            'method' => 'DELETE',
                                            'submitLabel' => $backup->isRunning() ? $adminText('backups.delete_stuck_backup') : $adminText('backups.delete_backup'),
                                        ])
                                            @if ($backup->isRunning())
                                                <input type="hidden" name="force_running" value="1">
                                            @endif

                                            <div class="wb-card wb-card-muted">
                                                <div class="wb-card-body wb-stack wb-gap-2">
                                                    <div><strong>{{ $backup->archive_filename ?? $adminText('backups.backup_number', ['id' => $backup->id]) }}</strong></div>
                                                    <div class="wb-text-sm wb-text-muted">{{ $adminText('common.status') }} {{ $backupStatusLabel($backup->status) }} | {{ $adminText('backups.created') }} {{ $backup->created_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                                                </div>
                                            </div>

                                            <p class="wb-text-sm wb-text-muted">{{ $adminText('backups.delete_warning') }}</p>
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
                    'title' => $adminText('backups.delete_selected_title'),
                    'description' => $adminText('backups.delete_selected_description'),
                    'action' => route('admin.system.backups.bulk-destroy'),
                    'method' => 'DELETE',
                    'submitLabel' => $adminText('common.delete_selected'),
                    'formAttributes' => [
                        'data-wb-admin-bulk-delete-form' => true,
                        'data-wb-admin-bulk-input-name' => 'backup_ids[]',
                    ],
                    'submitAttributes' => [
                        'data-wb-admin-bulk-delete-submit' => true,
                        'disabled' => true,
                    ],
                ])
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <strong><span data-wb-admin-bulk-modal-count>0</span> {{ $adminText('backups.selected_backups_will_be_deleted') }}</strong>
                            <p class="wb-text-sm wb-text-muted">{{ $adminText('backups.bulk_delete_help') }}</p>
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
