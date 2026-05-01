@extends('layouts.admin', ['title' => 'Backup Details', 'heading' => 'Backup Details'])

@section('content')
    @php($canRestore = $backup->isSuccessful() && filled($backup->archive_path))
    @php($manifest = $inspection?->manifest ?? [])

    @include('admin.partials.page-header', [
        'title' => $backup->archive_filename ?? 'Backup #'.$backup->id,
        'description' => 'Review backup results, uploaded archive metadata, operational logs, and restore history for this run.',
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.system.backups.index').'" class="wb-btn wb-btn-secondary">Back to Backups</a>'.($backup->isSuccessful() && $backup->archive_path ? '<a href="'.route('admin.system.backups.download', $backup).'" class="wb-btn wb-btn-primary">Download</a>' : '').'</div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>Run Status</strong>
                    <span class="wb-status-pill {{ $backup->statusBadgeClass() }}">{{ $backup->statusLabel() }}</span>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>{{ $backup->summary ?? 'No summary available.' }}</strong></div>
                    <div class="wb-text-sm wb-text-muted">Started {{ $backup->started_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                    <div class="wb-text-sm wb-text-muted">Finished {{ $backup->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                    <div class="wb-text-sm wb-text-muted">Duration {{ $backup->durationLabel() }}</div>
                    <div class="wb-text-sm wb-text-muted">Triggered by {{ $backup->triggeredBy?->name ?? '-' }}</div>

                    @if ($backup->error_message)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">Backup error</div>
                                <div>{{ $backup->error_message }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>Archive Metadata</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>Type:</strong> {{ $backup->type }}</div>
                    <div><strong>Contents:</strong> {{ $backup->contentsLabel() ?: '-' }}</div>
                    <div><strong>Source filename:</strong> {{ $backup->label ?? $backup->archive_filename ?? '-' }}</div>
                    <div><strong>Archive disk:</strong> {{ $backup->archive_disk }}</div>
                    <div><strong>Archive file:</strong> {{ $backup->archive_filename ?? '-' }}</div>
                    <div><strong>Archive path:</strong> <code>{{ $backup->archive_path ?? '-' }}</code></div>
                    <div><strong>Archive size:</strong> {{ $backup->humanArchiveSize() }}</div>
                    <div><strong>Manifest app:</strong> {{ $manifest['app_name'] ?? '-' }}</div>
                    <div><strong>Manifest version:</strong> {{ $manifest['app_version'] ?? '-' }}</div>
                    <div><strong>Manifest created at:</strong> {{ $manifest['created_at'] ?? '-' }}</div>
                </div>
            </div>
        </div>

        @if ($inspection)
            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>Manifest Preview</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>Product:</strong> {{ $manifest['product'] ?? 'Legacy backup manifest' }}</div>
                    <div><strong>Package type:</strong> {{ $manifest['package_type'] ?? 'legacy_backup' }}</div>
                    <div><strong>Format version:</strong> {{ $manifest['format_version'] ?? '-' }}</div>
                    <div><strong>Contents:</strong> DB{{ $inspection->includesUploads ? ' + uploads' : '' }}</div>
                </div>
            </div>
        @endif

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>Danger Zone</strong></div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-alert wb-alert-danger">
                    <div>
                        <div class="wb-alert-title">Restore backup</div>
                        <div>This restores a full system backup. It will overwrite the current database and uploaded files. It is different from Export/Import, which creates a new site from a site package. WebBlocks CMS will create a fresh safety backup first and will not delete the source backup archive.</div>
                    </div>
                </div>

                <div class="wb-text-sm wb-text-muted">Selected backup: <strong>{{ $backup->archive_filename ?? 'Backup #'.$backup->id }}</strong> at <code>{{ $backup->archive_path ?? 'archive unavailable' }}</code></div>

                @if ($canRestore)
                    <form method="POST" action="{{ route('admin.system.backups.restore', $backup) }}" class="wb-stack wb-gap-3" onsubmit="return confirm('Restore this backup? This will replace the current database and uploads.');" data-wb-restore-form>
                        @csrf

                        <label class="wb-checkbox" for="acknowledge_restore_risk">
                            <input id="acknowledge_restore_risk" type="checkbox" name="acknowledge_restore_risk" value="1" required {{ old('acknowledge_restore_risk') ? 'checked' : '' }} data-wb-restore-ack>
                            <span>I understand this will overwrite current data.</span>
                        </label>

                        <div class="wb-text-sm wb-text-muted">This restore imports <code>database/database.sql</code>, restores <code>uploads/public/...</code> when present, reruns <code>storage:link</code>, and clears framework caches after the restore.</div>

                        <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                            <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                                <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">Cancel</a>
                                <button type="submit" class="wb-btn wb-btn-danger" data-wb-restore-submit @disabled(! old('acknowledge_restore_risk'))>Restore backup</button>
                            </div>
                        </div>
                    </form>
                @else
                    <div class="wb-alert wb-alert-warning">
                        <div>
                            <div class="wb-alert-title">Restore is unavailable for this backup</div>
                            <div>Only completed backups with a stored archive can be restored from the admin panel.</div>
                        </div>
                    </div>

                    <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                        <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                            <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">Cancel</a>
                            <button type="button" class="wb-btn wb-btn-danger" disabled>Restore backup</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($restoreRuns->isNotEmpty())
            <div class="wb-card">
                <div class="wb-card-header"><strong>Restore History</strong></div>

                <div class="wb-card-body">
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>Started at</th>
                                    <th>Status</th>
                                    <th>Parts</th>
                                    <th>Safety backup</th>
                                    <th>Triggered by</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($restoreRuns as $restoreRun)
                                    <tr>
                                        <td>{{ $restoreRun->started_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td><span class="wb-status-pill {{ $restoreRun->statusBadgeClass() }}">{{ $restoreRun->statusLabel() }}</span></td>
                                        <td>{{ $restoreRun->restoredPartsLabel() ?: '-' }}</td>
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
                                            <div class="wb-backup-actions wb-cluster wb-gap-1 wb-items-center wb-justify-end wb-nowrap">
                                                <form method="POST" action="{{ route('admin.system.backups.restores.destroy', [$backup, $restoreRun]) }}" onsubmit="return confirm('Delete this restore history entry? This will not delete any backup archive.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete restore history entry" aria-label="Delete restore history entry">
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
            </div>
        @endif

        <div class="wb-card">
            <div class="wb-card-header"><strong>Operational Log</strong></div>

            <div class="wb-card-body">
                @if ($backup->output)
                    <pre class="wb-code-block">{{ $backup->output }}</pre>
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">No log output captured</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-wb-restore-form]').forEach(function (form) {
            var acknowledgement = form.querySelector('[data-wb-restore-ack]');
            var submitButton = form.querySelector('[data-wb-restore-submit]');

            if (!acknowledgement || !submitButton) {
                return;
            }

            var syncRestoreSubmitState = function () {
                submitButton.disabled = !acknowledgement.checked;
            };

            syncRestoreSubmitState();
            acknowledgement.addEventListener('change', syncRestoreSubmitState);
        });
    </script>
@endpush
