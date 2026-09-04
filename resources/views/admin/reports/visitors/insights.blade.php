@php
    $insights = $report['insights'];
@endphp
<section class="wb-card">
    <div class="wb-card-header"><h2 class="wb-card-title">{{ $insightText('trend') }}</h2></div>
    <div class="wb-card-body wb-stack wb-gap-3">
        <p>{{ $insightText('comparison', ['from' => $insights['previous_from'], 'to' => $insights['previous_to'], 'views' => number_format($insights['previous_views'])]) }}
            @if ($insights['change'] !== null)
                <strong>{{ $insightText('change', ['value' => ($insights['change'] > 0 ? '+' : '').number_format($insights['change'], 1)]) }}</strong>
            @else
                {{ $insightText('no_baseline') }}
            @endif
        </p>
        @if ($insights['includes_today'])<p class="wb-text-muted">{{ $insightText('partial_day') }}</p>@endif
        <figure class="wb-visitor-trend">
            <svg viewBox="0 0 900 210" role="img" aria-labelledby="visitor-trend-title visitor-trend-description">
                <title id="visitor-trend-title">{{ $insightText('trend') }}</title>
                <desc id="visitor-trend-description">{{ $filters['from'] }} – {{ $filters['to'] }}. {{ $insightText('table') }}</desc>
                <text x="20" y="14">{{ number_format($insights['maximum']) }}</text>
                <line class="wb-visitor-trend-axis" x1="20" y1="180" x2="880" y2="180" />
                <polyline class="wb-visitor-trend-line" points="{{ $insights['points'] }}" />
                @foreach ($insights['buckets'] as $bucket)
                    <circle class="wb-visitor-trend-point" cx="{{ $bucket['x'] }}" cy="{{ $bucket['y'] }}" r="3">
                        <title>{{ $bucket['from'] }} – {{ $bucket['to'] }}: {{ $bucket['views'] }}</title>
                    </circle>
                @endforeach
                <text x="20" y="205">{{ $filters['from'] }}</text>
                <text x="880" y="205" text-anchor="end">{{ $filters['to'] }}</text>
            </svg>
            <figcaption>{{ $insightText('bucket', ['days' => $insights['bucket_days']]) }}</figcaption>
        </figure>
        <details>
            <summary>{{ $insightText('table') }}</summary>
            <div class="wb-table-wrap"><table class="wb-table">
                <thead><tr><th scope="col">{{ $insightText('date') }}</th><th scope="col">{{ $insightText('views') }}</th></tr></thead>
                <tbody>@foreach ($insights['buckets'] as $bucket)
                    <tr><td>{{ $bucket['from'] }}@if ($bucket['to'] !== $bucket['from']) – {{ $bucket['to'] }}@endif</td><td>{{ number_format($bucket['views']) }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </details>
    </div>
</section>
<div class="wb-grid wb-grid-2">
    <section class="wb-card"><div class="wb-card-body wb-stack wb-gap-2">
        <h2 class="wb-card-title">{{ $insightText('coverage') }}</h2>
        <p>{{ $insights['coverage'] === null ? $insightText('no_data') : $insightText('coverage_value', ['tracked' => number_format($summary['tracked_page_views']), 'total' => number_format($summary['total_page_views']), 'percent' => number_format($insights['coverage'], 1)]) }}</p>
        <p class="wb-text-muted">{{ $insightText('coverage_help') }}</p>
    </div></section>
    <section class="wb-card"><div class="wb-card-body wb-stack wb-gap-2">
        <h2 class="wb-card-title">{{ $insightText('health') }}</h2>
        <strong>{{ $insightText('enabled') }}</strong>
        <p>{{ $insights['last_record'] ? $insightText('last_record', ['time' => $insights['last_record']]) : $insightText('no_record') }}</p>
        <p class="wb-text-muted">{{ $insightText('health_help') }}</p>
    </div></section>
</div>
<section class="wb-card wb-card-muted"><div class="wb-card-body wb-stack wb-gap-2">
    <h2 class="wb-card-title">{{ $insightText('retention') }}</h2>
    <p>{{ $insightText('retention_policy', ['detail' => $insights['retention']['detail_days'], 'total' => $insights['retention']['total_days']]) }}</p>
    <p>{{ $insightText(! $insights['retention']['ready'] ? 'cleanup_not_ready' : (! $insights['retention']['enabled'] ? 'cleanup_disabled' : 'retention_help')) }}</p>
    @if ($insights['archived']['total_page_views'] > 0)
        <p role="status">{{ $insightText('archived', ['views' => number_format($insights['archived']['total_page_views'])]) }}</p>
    @endif
</div></section>
