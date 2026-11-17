@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;
    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);
    $insightText = static fn (string $key, array $replace = []) => $adminTranslator->get('visitor_insights.'.$key, $adminLocaleCode, $replace);
    $chartDate = static fn (string $date) => \Carbon\CarbonImmutable::parse($date)->locale($adminLocaleCode)->isoFormat('D MMM');
    $canViewVisitorReports = $canViewVisitorReports ?? false;
    $visitorSummary = $visitorSummary ?? ['is_enabled' => false, 'table_exists' => false, 'buckets' => [], 'total_page_views' => 0];
    $summaryWidgets = array_filter($pluginDashboardWidgets, fn ($widget) => $widget->isSummary());
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $adminText('dashboard.title'), 'heading' => $adminText('dashboard.title')])
@section('content')
    <div class="wb-cms-dashboard">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => $adminText('dashboard.title'),
            'actions' => view('webblocks-cms::admin.partials.dashboard-actions', [
                'dashboardAccess' => $dashboardAccess,
                'adminText' => $adminText,
                'canAccessSystem' => $canAccessDashboardSystem,
            ]),
        ])
        @include('webblocks-cms::admin.partials.flash')

        <div class="wb-grid wb-grid-2 wb-cms-dashboard-grid">
            <div class="wb-stack wb-stack-3">
                <section class="wb-card" aria-labelledby="dashboard-overview-title">
                    <div class="wb-card-header"><h2 class="wb-card-title" id="dashboard-overview-title">{{ $adminText('dashboard.overview') }}</h2></div>
                    <div class="wb-card-body wb-stack wb-stack-2">
                        <dl class="wb-cms-dashboard-metrics">
                            <div>
                                <dt class="wb-text-xs wb-text-muted">{{ $adminText('dashboard.pages') }}</dt>
                                <dd><strong>{{ number_format($stats['pages']) }}</strong></dd>
                                <dd class="wb-text-xs wb-text-muted">{{ $adminText('dashboard.published_drafts', ['published' => $stats['publishedPages'], 'drafts' => $stats['draftPages']]) }}</dd>
                            </div>
                            <div><dt class="wb-text-xs wb-text-muted">{{ $adminText('dashboard.blocks') }}</dt><dd><strong>{{ number_format($stats['blocks']) }}</strong></dd></div>
                            <div><dt class="wb-text-xs wb-text-muted">{{ $adminText('dashboard.media') }}</dt><dd><strong>{{ number_format($stats['media']) }}</strong></dd></div>
                        </dl>
                        @if ($canAccessDashboardSystem)
                            <dl class="wb-cluster wb-cluster-2 wb-flex-wrap wb-text-xs wb-cms-dashboard-catalog">
                                <div class="wb-cluster wb-cluster-2"><dt class="wb-text-muted">{{ $adminText('dashboard.slot_types') }}</dt><dd>{{ number_format($stats['slotTypes']) }}</dd></div>
                                <div class="wb-cluster wb-cluster-2"><dt class="wb-text-muted">{{ $adminText('dashboard.block_types') }}</dt><dd>{{ number_format($stats['blockTypes']) }}</dd></div>
                            </dl>
                        @endif
                    </div>
                </section>
                @if ($canViewVisitorReports)
                    <section class="wb-card" aria-labelledby="dashboard-views-title">
                        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                            <h2 class="wb-card-title" id="dashboard-views-title">{{ $adminText('dashboard.page_views') }}</h2><span class="wb-text-xs wb-text-muted">{{ $adminText('dashboard.last_7_days') }}</span>
                        </div>
                        <div class="wb-card-body">
                            @if (! $visitorSummary['is_enabled'])
                                <p class="wb-text-sm wb-text-muted">{{ $adminText('dashboard.visitor_disabled') }}</p>
                            @elseif (! $visitorSummary['table_exists'])
                                <p class="wb-text-sm wb-text-muted">{{ $adminText('dashboard.visitor_missing') }}</p>
                            @else
                                <strong class="wb-cms-dashboard-views">{{ number_format($visitorSummary['total_page_views'] ?? array_sum(array_column($visitorSummary['buckets'], 'views'))) }}</strong>
                                <div class="wb-chart" data-wb-chart="line" data-wb-chart-table="dashboard-page-views-chart-data" aria-label="{{ $insightText('trend') }}" lang="{{ $adminLocaleCode }}" data-wb-chart-help="{{ $insightText('chart_help') }}" data-wb-chart-empty="{{ $insightText('no_data') }}" data-wb-chart-error="{{ $insightText('chart_error') }}">
                                    <p class="wb-chart-fallback wb-text-muted">{{ $insightText('chart_fallback') }}</p>
                                </div>
                                <table id="dashboard-page-views-chart-data" hidden>
                                    <thead><tr><th scope="col">{{ $insightText('date') }}</th><th scope="col">{{ $insightText('views') }}</th></tr></thead>
                                    <tbody>
                                        @foreach ($visitorSummary['buckets'] as $bucket)
                                            <tr><th scope="row" data-wb-chart-label="{{ $chartDate($bucket['from']) }}">{{ $bucket['from'] }}</th><td data-wb-chart-value="{{ $bucket['views'] }}">{{ $bucket['views'] }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </section>
                @endif
            </div>
            @if ($dashboardSystemHealth !== null || collect($notificationHealthSites)->isNotEmpty() || $dashboardAccess['pages'] || $dashboardAccess['media'])
                <div class="wb-stack wb-stack-3">
                    @include('webblocks-cms::admin.partials.dashboard-health')
                    @include('webblocks-cms::admin.partials.dashboard-recent-content')
                </div>
            @endif
        </div>

        @if ($panelNotifications !== null)
            <div id="panel-notifications" class="wb-card">
                <div class="wb-card-body">
                    @include('webblocks-cms::admin.partials.dashboard-panel-notifications', ['compact' => true])
                </div>
            </div>
        @endif
        @if ($summaryWidgets !== [])
            <div class="wb-grid wb-grid-2 wb-cms-dashboard-grid">
                @foreach ($summaryWidgets as $widget)
                    @include('webblocks-cms::admin.partials.dashboard-plugin-widget')
                @endforeach
            </div>
        @endif
        @foreach ($pluginDashboardWidgets as $widget)
            @if (! $widget->isSummary())@include('webblocks-cms::admin.partials.dashboard-plugin-widget')@endif
        @endforeach
    </div>
@endsection
