@extends('layouts.admin', ['title' => 'Export Details', 'heading' => 'Export Details'])

@section('content')
    @php($manifest = $siteExport->manifest_json ?? [])
    @php($counts = $siteExport->summary_json ?? [])

    @include('admin.partials.page-header', [
        'title' => $siteExport->archive_name ?? 'Site Export #'.$siteExport->id,
        'description' => 'Review export package metadata, counts, and output log for this site export run.',
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.site-transfers.exports.index').'" class="wb-btn wb-btn-secondary">Back to Exports</a>'.($siteExport->isCompleted() && $siteExport->archive_path ? '<a href="'.route('admin.site-transfers.exports.download', $siteExport).'" class="wb-btn wb-btn-primary">Download</a>' : '').'</div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>Run Status</strong>
                    <span class="wb-status-pill {{ $siteExport->statusBadgeClass() }}">{{ $siteExport->statusLabel() }}</span>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>Site:</strong> {{ $siteExport->site?->name ?? '-' }}</div>
                    <div><strong>Includes media:</strong> {{ $siteExport->includes_media ? 'Yes' : 'No' }}</div>
                    <div><strong>Archive:</strong> {{ $siteExport->archive_name ?? '-' }}</div>
                    <div><strong>Size:</strong> {{ $siteExport->humanArchiveSize() }}</div>
                    <div><strong>Triggered by:</strong> {{ $siteExport->user?->name ?? '-' }}</div>

                    @if ($siteExport->failure_message)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">Export failed</div>
                                <div>{{ $siteExport->failure_message }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>Manifest Metadata</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>Product:</strong> {{ $manifest['product'] ?? '-' }}</div>
                    <div><strong>Feature version:</strong> {{ $manifest['feature_version'] ?? '-' }}</div>
                    <div><strong>Format version:</strong> {{ $manifest['format_version'] ?? '-' }}</div>
                    <div><strong>Exported at:</strong> {{ $manifest['exported_at'] ?? '-' }}</div>
                    <div><strong>Source app version:</strong> {{ $manifest['source_app_version'] ?? '-' }}</div>
                    <div><strong>Source handle:</strong> {{ $manifest['source_site_handle'] ?? '-' }}</div>
                    <div><strong>Source domain:</strong> {{ $manifest['source_site_domain'] ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Counts</strong></div>

            <div class="wb-card-body">
                <div class="wb-grid wb-grid-3">
                    @forelse ($counts as $label => $value)
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-1">
                                <div class="wb-text-sm wb-text-muted">{{ str($label)->replace('_', ' ')->title() }}</div>
                                <strong>{{ $value }}</strong>
                            </div>
                        </div>
                    @empty
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No count summary recorded</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Output Log</strong></div>

            <div class="wb-card-body">
                @if ($siteExport->output_log)
                    <pre class="wb-code-block">{{ $siteExport->output_log }}</pre>
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">No output log captured</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
