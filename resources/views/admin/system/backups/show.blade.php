@extends('layouts.admin', ['title' => 'Backup Details', 'heading' => 'Backup Details'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $backup->archive_filename ?? 'Backup #'.$backup->id,
        'description' => 'Review backup results, operational logs, and archive metadata for this run.',
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
                    <div><strong>Archive disk:</strong> {{ $backup->archive_disk }}</div>
                    <div><strong>Archive file:</strong> {{ $backup->archive_filename ?? '-' }}</div>
                    <div><strong>Archive path:</strong> <code>{{ $backup->archive_path ?? '-' }}</code></div>
                    <div><strong>Archive size:</strong> {{ $backup->humanArchiveSize() }}</div>
                </div>
            </div>
        </div>

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
