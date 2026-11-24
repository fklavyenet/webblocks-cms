@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
    $backupStatusLabel = static fn (?string $status) => $status ? $adminText('backups.statuses.'.$status) : '-';
    $restoreStatusLabel = static fn (?string $status) => $status ? $adminText('backups.restore_statuses.'.$status) : '-';
    $backupContentsLabel = static fn ($backup) => collect([
        $backup->includes_database ? $adminText('backups.contents.database') : null,
        $backup->includes_uploads ? $adminText('backups.contents.uploads') : null,
    ])->filter()->implode(' + ') ?: '-';
    $restorePartsLabel = static fn ($restoreRun) => collect($restoreRun->restored_parts ?? [])->map(
        fn (string $part) => $adminText('backups.restore_parts.'.$part)
    )->implode(' + ') ?: '-';
    $archiveStatusLabel = static fn ($resolution) => $resolution ? $adminText('backups.archive_statuses.'.$resolution->status) : '';
    $archiveFeedback = static fn ($resolution) => $resolution ? $adminText('backups.archive_feedback.'.$resolution->status) : $adminText('backups.archive_unavailable');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('backups.details'), 'heading' => $adminText('backups.details')])

@section('content')
    @php($archiveResolution = $archiveResolution ?? null)
    @php($canDownload = $archiveResolution?->isAvailable() ?? false)
    @php($canRestore = $canDownload)
    @php($backupInterrupted = $backup->isStaleRunning())
    @php($archivePathValue = (string) $backup->archive_path)
    @php($isAbsoluteArchivePath = str_starts_with($archivePathValue, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $archivePathValue) === 1)
    @php($displayArchivePath = $isAbsoluteArchivePath ? $adminText('backups.absolute_path_hidden') : ($backup->archive_path ?? '-'))
    @php($manifest = $inspection?->manifest ?? [])

    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $backup->archive_filename ?? $adminText('backups.backup_number', ['id' => $backup->id]),
        'description' => $adminText('backups.details_description'),
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.system.backups.index').'" class="wb-btn wb-btn-secondary">'.$adminText('backups.back_to_backups').'</a>'.($canDownload ? '<a href="'.route('admin.system.backups.download', $backup).'" class="wb-btn wb-btn-primary">'.$adminText('common.download').'</a>' : '').'</div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if ($backup->isRunning())
        <div class="wb-alert {{ $backupInterrupted ? 'wb-alert-danger' : 'wb-alert-info' }}" role="status"
            @unless ($backupInterrupted)
                data-wb-backup-progress="{{ route('admin.system.backups.progress', $backup) }}"
                data-completed="{{ $adminText('backups.worker_completed') }}"
                data-failed="{{ $adminText('backups.worker_failed') }}"
                data-interrupted="{{ __('webblocks-cms::backup-retention.worker_interrupted') }}"
                data-unavailable="{{ $adminText('backups.worker_progress_unavailable') }}"
            @endunless
        >
            <div>
                <span data-wb-backup-message>{{ $backupInterrupted ? __('webblocks-cms::backup-retention.worker_interrupted') : $adminText('backups.worker_running') }}</span>
                <a class="wb-link" href="{{ route('admin.system.backups.show', $backup) }}">{{ $adminText('backups.refresh_details') }}</a>
            </div>
            @unless ($backupInterrupted)
                <div class="wb-progress-bar wb-progress-bar-indeterminate" role="progressbar" aria-label="{{ $adminText('backups.run_status') }}" data-wb-backup-bar>
                    <div class="wb-progress-bar-fill"></div>
                </div>
            @endunless
        </div>
    @endif

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('backups.run_status') }}</strong>
                    <span class="wb-status-pill {{ $backupInterrupted ? 'wb-status-danger' : $backup->statusBadgeClass() }}" data-wb-backup-badge
                        data-completed="{{ $backupStatusLabel('completed') }}" data-failed="{{ $backupStatusLabel('failed') }}"
                        data-interrupted="{{ $adminText('backups.worker_interrupted_status') }}">{{ $backupInterrupted ? $adminText('backups.worker_interrupted_status') : $backupStatusLabel($backup->status) }}</span>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    @if ($summary = $backup->panelSummary($adminLocale))
                        <div><strong>{{ $summary }}</strong></div>
                    @endif
                    <div class="wb-table-wrap"><table class="wb-table"><tbody>
                        <tr><th scope="row">{{ $adminText('backups.started') }}</th><td>{{ $backup->started_at?->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                        <tr><th scope="row">{{ $adminText('backups.finished') }}</th><td>{{ $backup->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                        <tr><th scope="row">{{ $adminText('backups.duration') }}</th><td>{{ $backup->durationLabel() }}</td></tr>
                        <tr><th scope="row">{{ $adminText('backups.triggered_by') }}</th><td>{{ $backup->triggeredBy?->name ?? '-' }}</td></tr>
                    </tbody></table></div>

                    @if ($error = $backup->panelError($adminLocale))
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('backups.error') }}</div>
                                <div>{{ $error }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('backups.archive_metadata') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ $adminText('backups.archive_metadata') }}">
                            <tbody>
                                <tr><th scope="row">{{ $adminText('backups.type') }}</th><td>{{ $adminText('backups.types.'.$backup->type) }}</td></tr>
                                <tr><th scope="row">{{ rtrim($adminText('backups.contents_label'), ': ') }}</th><td>{{ $backupContentsLabel($backup) }}</td></tr>
                                <tr><th scope="row">{{ $adminText('backups.source_filename') }}</th><td>{{ $backup->label ?? $backup->archive_filename ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('backups.archive_disk') }}</th><td>{{ $backup->archive_disk }}</td></tr>
                                <tr><th scope="row">{{ $adminText('backups.archive_file') }}</th><td>{{ $backup->archive_filename ?? '-' }}</td></tr>
                                <tr>
                                    <th scope="row">{{ $adminText('backups.archive_path') }}</th>
                                    <td>
                                        @if ($isAbsoluteArchivePath || $displayArchivePath === '-')
                                            {{ $displayArchivePath }}
                                        @else
                                            <details class="wb-collapse-panel">
                                                <summary class="wb-collapse-trigger">{{ $adminTranslator->admin('record_tables.full_value', $adminLocale) }}</summary>
                                                <div class="wb-collapse-body"><code>{{ $displayArchivePath }}</code></div>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                                @if ($archiveResolution && ! $archiveResolution->isAvailable())
                                    <tr><th scope="row">{{ $adminText('backups.archive_status') }}</th><td><span class="wb-status-pill {{ $archiveResolution->uiBadgeClass() }}">{{ $archiveStatusLabel($archiveResolution) }}</span></td></tr>
                                @endif
                                <tr><th scope="row">{{ $adminText('backups.archive_size') }}</th><td>{{ $backup->humanArchiveSize() }}</td></tr>
                                <tr><th scope="row">{{ $adminText('backups.manifest_app') }}</th><td>{{ $manifest['app_name'] ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('backups.manifest_version') }}</th><td>{{ $manifest['app_version'] ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('backups.manifest_created_at') }}</th><td>{{ $manifest['created_at'] ?? '-' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if ($inspection)
            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('backups.manifest_preview') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ $adminText('backups.manifest_preview') }}">
                            <tbody>
                                <tr><th scope="row">{{ $adminText('updates.product') }}</th><td>{{ $manifest['product'] ?? $adminText('backups.legacy_manifest') }}</td></tr>
                                <tr><th scope="row">{{ $adminText('backups.package_type') }}</th><td>{{ $manifest['package_type'] ?? 'legacy_backup' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('backups.format_version') }}</th><td>{{ $manifest['format_version'] ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ rtrim($adminText('backups.contents_label'), ': ') }}</th><td>{{ $adminText('backups.contents.database') }}{{ $inspection->includesUploads ? ' + '.$adminText('backups.contents.uploads') : '' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $adminText('backups.danger_zone') }}</strong></div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-alert wb-alert-danger">
                    <div>
                        <div class="wb-alert-title">{{ $adminText('backups.restore_backup') }}</div>
                        <div>{{ $adminText('backups.restore_backup_warning') }}</div>
                    </div>
                </div>

                <div class="wb-text-sm wb-text-muted">{{ $adminText('backups.selected_backup') }}: <strong>{{ $backup->archive_filename ?? $adminText('backups.backup_number', ['id' => $backup->id]) }}</strong> {{ $adminText('backups.at') }} <code>{{ $displayArchivePath !== '-' ? $displayArchivePath : $adminText('backups.archive_unavailable') }}</code></div>

                @if ($canRestore)
                    <div class="wb-stack wb-gap-3">
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('backups.restore_process_help') }}</div>

                        <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                            <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                                <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('common.cancel') }}</a>
                                <button
                                    type="button"
                                    class="wb-btn wb-btn-danger"
                                    data-wb-toggle="modal"
                                    data-wb-target="#restore-backup-{{ $backup->id }}"
                                    aria-haspopup="dialog"
                                >{{ $adminText('backups.restore_backup') }}</button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="wb-alert wb-alert-warning">
                        <div>
                            <div class="wb-alert-title">{{ $adminText('backups.restore_unavailable') }}</div>
                            <div>{{ $archiveResolution ? $archiveFeedback($archiveResolution) : $adminText('backups.restore_unavailable_help') }}</div>
                        </div>
                    </div>

                    <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                        <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                            <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('common.cancel') }}</a>
                            <button type="button" class="wb-btn wb-btn-danger" disabled>{{ $adminText('backups.restore_backup') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($restoreRuns->isNotEmpty())
            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('backups.restore_history') }}</strong></div>

                <div class="wb-card-body">
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>{{ $adminText('backups.started_at') }}</th>
                                    <th>{{ $adminText('common.status') }}</th>
                                    <th>{{ $adminText('backups.parts') }}</th>
                                    <th>{{ $adminText('backups.safety_backup') }}</th>
                                    <th>{{ $adminText('backups.triggered_by') }}</th>
                                    <th>{{ $adminText('backups.duration') }}</th>
                                    <th>{{ $adminText('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($restoreRuns as $restoreRun)
                                    <tr>
                                        <td>{{ $restoreRun->started_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td><span class="wb-status-pill {{ $restoreRun->statusBadgeClass() }}">{{ $restoreStatusLabel($restoreRun->status) }}</span></td>
                                        <td>{{ $restorePartsLabel($restoreRun) }}</td>
                                        <td>
                                            @if ($restoreRun->safetyBackup)
                                                <a href="{{ route('admin.system.backups.show', $restoreRun->safetyBackup) }}">#{{ $restoreRun->safetyBackup->id }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $restoreRun->triggeredBy?->name ?? '-' }}</td>
                                        <td>{{ $restoreRun->durationLabel() }}</td>
                                        <td>
                                            <div class="wb-action-group">
                                                <button
                                                    type="button"
                                                    class="wb-action-btn wb-action-btn-delete"
                                                    data-wb-toggle="modal"
                                                    data-wb-target="#delete-restore-run-{{ $restoreRun->id }}"
                                                    data-wb-tooltip="{{ $adminText('backups.delete_restore_history') }}"
                                                    aria-label="{{ $adminText('backups.delete_restore_history') }}"
                                                    aria-haspopup="dialog"
                                                >
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('backups.operational_log') }}</strong></div>

            <div class="wb-card-body">
                @if ($output = $backup->panelOutput($adminLocale))
                    <pre class="wb-code-block">{{ $output }}</pre>
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">{{ $adminText('backups.no_log_output') }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="wb-card wb-mt-4" id="backup-retention">
        <div class="wb-card-header"><strong>{{ __('webblocks-cms::backup-retention.title') }}</strong></div>
        @if ($retentionReady)
            <form method="POST" action="{{ route('admin.system.backups.retention.update', $backup) }}">
                @csrf @method('PUT')
                <div class="wb-card-body">@include('webblocks-cms::admin.system.backups.retention-fields', ['retentionBackup' => $backup])</div>
                <div class="wb-card-footer"><button class="wb-btn wb-btn-primary" type="submit">{{ __('webblocks-cms::backup-retention.save') }}</button></div>
            </form>
        @else
            <div class="wb-card-body">{{ __('webblocks-cms::backup-retention.schema_missing') }}</div>
        @endif
    </div>
@endsection

@push('overlays')
    @if ($canRestore)
        {{-- The acknowledgement moved into the modal so it stays the real guard the
             server checks, instead of a checkbox on the page plus a browser confirm. --}}
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'restore-backup-'.$backup->id,
            'title' => $adminText('backups.restore_title'),
            'description' => $adminText('backups.restore_description'),
            'pendingOperation' => 'restoring',
            'action' => route('admin.system.backups.restore', $backup),
            'method' => 'POST',
            'submitLabel' => $adminText('backups.restore_backup'),
            'formAttributes' => ['data-wb-restore-form' => true],
            'submitAttributes' => ['data-wb-restore-submit' => true, 'disabled' => true],
        ])
            <p>{{ $adminText('backups.restore_confirm_prefix') }} <strong>{{ $backup->archive_filename ?? $adminText('backups.backup_number', ['id' => $backup->id]) }}</strong>?</p>

            <div class="wb-alert wb-alert-danger">
                {{ $adminText('backups.restore_backup_warning') }}
            </div>

            <label class="wb-check" for="acknowledge_restore_risk">
                <input id="acknowledge_restore_risk" type="checkbox" name="acknowledge_restore_risk" value="1" required data-wb-restore-ack>
                <span>{{ $adminText('backups.acknowledge_restore_risk') }}</span>
            </label>
        @endcomponent
    @endif

    @foreach ($restoreRuns as $restoreRun)
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'delete-restore-run-'.$restoreRun->id,
            'title' => $adminText('backups.delete_restore_history_title'),
            'description' => $adminText('backups.delete_restore_history_description'),
            'action' => route('admin.system.backups.restores.destroy', [$backup, $restoreRun]),
            'method' => 'DELETE',
            'submitLabel' => $adminText('backups.delete_restore_history'),
        ])
            <p>{{ $adminText('backups.delete_restore_history_confirm_prefix') }} <strong>#{{ $restoreRun->id }}</strong> ({{ $restoreRun->started_at?->format('Y-m-d H:i:s') ?? '-' }})? {{ $adminText('backups.delete_restore_history_note') }}</p>
        @endcomponent
    @endforeach
@endpush

@push('admin-scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/backup-restore.js'])
    @if ($backup->isRunning() && ! $backupInterrupted)
        @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/backup-progress.js'])
    @endif
@endpush
