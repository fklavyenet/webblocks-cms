@php
    use WebBlocks\Cms\Support\Sites\ExportImport\SiteTransferLog;
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
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ $adminText('site_transfers.run_status') }}">
                            <tbody>
                                <tr><th scope="row">{{ $adminText('site_transfers.site') }}</th><td>{{ $siteExport->site?->name ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('site_transfers.includes_media') }}</th><td>{{ $yesNoLabel((bool) $siteExport->includes_media) }}</td></tr>
                                <tr><th scope="row">{{ $adminText('site_transfers.archive') }}</th><td>{{ $siteExport->archive_name ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('site_transfers.size') }}</th><td>{{ $siteExport->humanArchiveSize() }}</td></tr>
                                <tr><th scope="row">{{ $adminText('site_transfers.triggered_by') }}</th><td>{{ $siteExport->user?->name ?? '-' }}</td></tr>
                            </tbody>
                        </table>
                    </div>

                    @if ($siteExport->failure_message)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('site_transfers.export_failed') }}</div>
                                <div>{{ SiteTransferLog::display($siteExport->failure_message, $adminLocale) }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>{{ $adminText('site_transfers.manifest_metadata') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-2">
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ $adminText('site_transfers.manifest_metadata') }}">
                            <tbody>
                                <tr><th scope="row">{{ $adminText('site_transfers.product') }}</th><td>{{ $manifest['product'] ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('site_transfers.feature_version') }}</th><td>{{ $manifest['feature_version'] ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('site_transfers.format_version') }}</th><td>{{ $manifest['format_version'] ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('site_transfers.exported_at') }}</th><td>{{ $manifest['exported_at'] ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('site_transfers.source_app_version') }}</th><td>{{ $manifest['source_app_version'] ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('site_transfers.source_handle') }}</th><td>{{ $manifest['source_site_handle'] ?? '-' }}</td></tr>
                                <tr><th scope="row">{{ $adminText('site_transfers.source_domain') }}</th><td>{{ $manifest['source_site_domain'] ?? '-' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('site_transfers.counts') }}</strong></div>

            <div class="wb-card-body">
                @if (count($counts) > 0)
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ $adminText('site_transfers.counts') }}">
                            <tbody>
                                @foreach ($counts as $label => $value)
                                    <tr><th scope="row">{{ str($label)->replace('_', ' ')->title() }}</th><td>{{ $value }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">{{ $adminText('site_transfers.no_count_summary') }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('site_transfers.output_log') }}</strong></div>

            <div class="wb-card-body">
                @if ($siteExport->output_log)
                    <pre class="wb-code-block">{{ SiteTransferLog::display($siteExport->output_log, $adminLocale) }}</pre>
                @else
                    <div class="wb-empty wb-empty-sm">
                        <div class="wb-empty-title">{{ $adminText('site_transfers.no_output_log') }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
