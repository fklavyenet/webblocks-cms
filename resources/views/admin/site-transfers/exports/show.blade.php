@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
    $transferStatusLabel = static fn (?string $status) => $status ? $adminText('site_transfers.statuses.'.$status) : '-';
    $yesNoLabel = static fn (bool $value) => $value ? $adminText('common.yes') : $adminText('common.no');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('site_transfers.export_details'), 'heading' => $adminText('site_transfers.export_details')])

@section('content')
    @php($manifest = $siteExport->manifest_json ?? [])
    @php($counts = $siteExport->summary_json ?? [])

    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $siteExport->archive_name ?? $adminText('site_transfers.export_number', ['id' => $siteExport->id]),
        'description' => $adminText('site_transfers.export_details_description'),
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.site-transfers.exports.index').'" class="wb-btn wb-btn-secondary">'.$adminText('site_transfers.back_to_exports').'</a>'.($siteExport->isCompleted() && $siteExport->archive_path ? '<a href="'.route('admin.site-transfers.exports.download', $siteExport).'" class="wb-btn wb-btn-primary">'.$adminText('common.download').'</a>' : '').'</div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('site_transfers.run_status') }}</strong>
                    <span class="wb-status-pill {{ $siteExport->statusBadgeClass() }}">{{ $transferStatusLabel($siteExport->status) }}</span>
                </div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>{{ $adminText('site_transfers.site') }}:</strong> {{ $siteExport->site?->name ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.includes_media') }}:</strong> {{ $yesNoLabel((bool) $siteExport->includes_media) }}</div>
                    <div><strong>{{ $adminText('site_transfers.archive') }}:</strong> {{ $siteExport->archive_name ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.size') }}:</strong> {{ $siteExport->humanArchiveSize() }}</div>
                    <div><strong>{{ $adminText('site_transfers.triggered_by') }}:</strong> {{ $siteExport->user?->name ?? '-' }}</div>

                    @if ($siteExport->failure_message)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('site_transfers.export_failed') }}</div>
                                <div>{{ $siteExport->failure_message }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('site_transfers.manifest_metadata') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div><strong>{{ $adminText('site_transfers.product') }}:</strong> {{ $manifest['product'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.feature_version') }}:</strong> {{ $manifest['feature_version'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.format_version') }}:</strong> {{ $manifest['format_version'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.exported_at') }}:</strong> {{ $manifest['exported_at'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.source_app_version') }}:</strong> {{ $manifest['source_app_version'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.source_handle') }}:</strong> {{ $manifest['source_site_handle'] ?? '-' }}</div>
                    <div><strong>{{ $adminText('site_transfers.source_domain') }}:</strong> {{ $manifest['source_site_domain'] ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('site_transfers.counts') }}</strong></div>

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
                            <div class="wb-empty-title">{{ $adminText('site_transfers.no_count_summary') }}</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('site_transfers.output_log') }}</strong></div>

            <div class="wb-card-body">
                @if ($siteExport->output_log)
                    <pre class="wb-code-block">{{ $siteExport->output_log }}</pre>
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">{{ $adminText('site_transfers.no_output_log') }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
