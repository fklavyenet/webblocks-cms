@php
    $insights = $report['insights'];
@endphp
<section class="wb-card">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <h2 class="wb-card-title">{{ $insightText('trend') }}</h2>
        <div class="wb-action-group">
            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-toggle="modal" data-wb-target="#visitor-chart-values" aria-haspopup="dialog">{{ $insightText('table') }}</button>
            <button type="button" class="wb-btn wb-btn-ghost wb-btn-sm" data-wb-toggle="modal" data-wb-target="#visitor-report-information" aria-haspopup="dialog">{{ $insightText('information') }}</button>
        </div>
    </div>
    <div class="wb-card-body wb-stack wb-gap-3">
        <p>{{ $insightText('comparison', ['from' => $insights['previous_from'], 'to' => $insights['previous_to'], 'views' => number_format($insights['previous_views'])]) }}
            @if ($insights['change'] !== null)
                <strong>{{ $insightText('change', ['value' => ($insights['change'] > 0 ? '+' : '').number_format($insights['change'], 1)]) }}</strong>
            @else
                {{ $insightText('no_baseline') }}
            @endif
        </p>
        @if ($insights['includes_today'])<p class="wb-text-muted">{{ $insightText('partial_day') }}</p>@endif
        <div class="wb-chart" data-wb-chart="line" data-wb-chart-table="visitor-chart-data"
            aria-label="{{ $insightText('trend') }}" lang="{{ $adminLocale ?? 'en' }}"
            data-wb-chart-help="{{ $insightText('chart_help') }}"
            data-wb-chart-empty="{{ $insightText('no_data') }}"
            data-wb-chart-error="{{ $insightText('chart_error') }}">
            <p class="wb-chart-fallback wb-text-muted">{{ $insightText('chart_fallback') }}</p>
        </div>
        <p class="wb-text-sm wb-text-muted">{{ $insights['bucket_days'] === 1 ? $insightText('daily') : $insightText('bucket', ['days' => $insights['bucket_days']]) }}</p>
    </div>
</section>
<div id="visitor-chart-values" class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="visitor-chart-values-title">
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="visitor-chart-values-title">{{ $insightText('table') }}</h2>
            <button type="button" class="wb-icon-btn wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $insightText('close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <div class="wb-modal-body">
            <div class="wb-table-wrap"><table id="visitor-chart-data" class="wb-table wb-table-striped">
                <thead><tr><th scope="col">{{ $insightText('date') }}</th><th scope="col">{{ $insightText('views') }}</th></tr></thead>
                <tbody>@foreach ($insights['buckets'] as $bucket)
                    <tr><th scope="row">{{ $bucket['from'] }}@if ($bucket['to'] !== $bucket['from']) – {{ $bucket['to'] }}@endif</th><td data-wb-chart-value="{{ $bucket['views'] }}">{{ number_format($bucket['views']) }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </div>
        <div class="wb-modal-footer"><button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $insightText('close') }}</button></div>
    </div>
</div>
<div id="visitor-report-information" class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="visitor-report-information-title">
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="visitor-report-information-title">{{ $insightText('information') }}</h2>
            <button type="button" class="wb-icon-btn wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $insightText('close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <div class="wb-modal-body">
            <div class="wb-stack wb-gap-4">
                <p class="wb-text-muted">{{ $insightText('privacy') }}</p>
                <section class="wb-stack wb-gap-2">
                    <h3>{{ $insightText('coverage') }}</h3>
                    <p>{{ $insights['coverage'] === null ? $insightText('no_data') : $insightText('coverage_value', ['tracked' => number_format($summary['tracked_page_views']), 'total' => number_format($summary['total_page_views']), 'percent' => number_format($insights['coverage'], 1)]) }}</p>
                    <p class="wb-text-muted">{{ $insightText('coverage_help') }}</p>
                </section>
                <section class="wb-stack wb-gap-2">
                    <h3>{{ $insightText('health') }}</h3>
                    <strong>{{ $insightText('enabled') }}</strong>
                    <p>{{ $insights['last_record'] ? $insightText('last_record', ['time' => $insights['last_record']]) : $insightText('no_record') }}</p>
                    <p class="wb-text-muted">{{ $insightText('health_help') }}</p>
                </section>
                <section class="wb-stack wb-gap-2">
                    <h3>{{ $insightText('retention') }}</h3>
                    <p>{{ $insightText('retention_policy', ['detail' => $insights['retention']['detail_days'], 'total' => $insights['retention']['total_days']]) }}</p>
                    <p>{{ $insightText(! $insights['retention']['ready'] ? 'cleanup_not_ready' : (! $insights['retention']['enabled'] ? 'cleanup_disabled' : 'retention_help')) }}</p>
                </section>
            </div>
        </div>
        <div class="wb-modal-footer"><button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $insightText('close') }}</button></div>
    </div>
</div>
