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
@endphp
@extends('webblocks-cms::layouts.admin', ['title' => $adminText('dashboard.title'), 'heading' => $adminText('dashboard.title')])
@section('content')
    <div class="wb-cms-dashboard">
        @include('webblocks-cms::admin.partials.page-header', ['title' => $adminText('dashboard.title'), 'description' => $adminText('dashboard.description')])
        @include('webblocks-cms::admin.partials.flash')
        <div class="wb-grid wb-grid-3 wb-cms-dashboard-grid" data-wb-dashboard-summary>
            @include('webblocks-cms::admin.partials.dashboard-health')
            <section class="wb-card" aria-labelledby="dashboard-overview-title">
                <div class="wb-card-header"><strong id="dashboard-overview-title">{{ $adminText('dashboard.overview') }}</strong></div>
                <div class="wb-card-body wb-stack wb-stack-2">
                    <table class="wb-table wb-table-sm wb-cms-dashboard-overview">
                        <tbody>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('dashboard.pages') }}</th><td>{{ number_format($stats['pages']) }}</td><td class="wb-text-xs wb-text-muted">{{ $adminText('dashboard.published_drafts', ['published' => $stats['publishedPages'], 'drafts' => $stats['draftPages']]) }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('dashboard.blocks') }}</th><td>{{ number_format($stats['blocks']) }}</td><td></td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('dashboard.media') }}</th><td>{{ number_format($stats['media']) }}</td><td></td></tr>
                            @can('access-system')
                                <tr><th scope="row" class="wb-table-key">{{ $adminText('dashboard.slot_types') }}</th><td>{{ number_format($stats['slotTypes']) }}</td><td></td></tr>
                                <tr><th scope="row" class="wb-table-key">{{ $adminText('dashboard.block_types') }}</th><td>{{ number_format($stats['blockTypes']) }}</td><td></td></tr>
                            @endcan
                        </tbody>
                    </table>
                    <div id="panel-notifications">@include('webblocks-cms::admin.partials.dashboard-panel-notifications', ['compact' => true])</div>
                </div>
            </section>
            @foreach ($pluginDashboardWidgets as $widget)
                @if ($widget->isSummary())@include('webblocks-cms::admin.partials.dashboard-plugin-widget')@endif
            @endforeach
        </div>
        <div class="wb-grid wb-grid-3 wb-cms-dashboard-grid" data-wb-dashboard-activity>
            @if ($canViewVisitorReports)
                <section class="wb-card">
                    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                        <strong>{{ $adminText('dashboard.page_views') }}</strong><span class="wb-text-xs wb-text-muted">{{ $adminText('dashboard.last_7_days') }}</span>
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
            <section class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('dashboard.recent_pages') }}</strong>
                    @if ($dashboardAccess['pages'])<a href="{{ route('admin.pages.index') }}" class="wb-btn wb-btn-ghost wb-btn-sm">{{ $adminText('dashboard.view_all') }}</a>@endif
                </div>
                <div class="wb-card-body">
                    @if ($recentPages->isEmpty())
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('dashboard.no_pages') }}</p>
                        @if ($dashboardAccess['create'])<a href="{{ route('admin.pages.create') }}" class="wb-btn wb-btn-primary wb-btn-sm">{{ $adminText('dashboard.create_page') }}</a>@endif
                    @else
                        <div class="wb-link-list">
                            @foreach ($recentPages as $page)
                                <a href="{{ route('admin.pages.edit', $page) }}" class="wb-link-list-item wb-cms-dashboard-recent-page">
                                    <div class="wb-link-list-main"><div class="wb-link-list-title" title="{{ $page->title }}">{{ $page->title }}</div><div class="wb-link-list-meta" title="{{ $page->slug }}">{{ $page->slug }}</div></div>
                                    <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
            <section class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('dashboard.recent_media') }}</strong>
                    @if ($dashboardAccess['media'])<a href="{{ route('admin.media.index') }}" class="wb-btn wb-btn-ghost wb-btn-sm">{{ $adminText('dashboard.manage') }}</a>@endif
                </div>
                <div class="wb-card-body">
                    @if ($recentAssets->isEmpty())
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('dashboard.no_media') }}</p>
                    @else
                        <div class="wb-link-list">
                            @foreach ($recentAssets as $asset)
                                <a href="{{ route('admin.media.edit', $asset) }}" class="wb-link-list-item"><div class="wb-link-list-main"><div class="wb-link-list-title" title="{{ $asset->title ?: $asset->original_name }}">{{ $asset->title ?: $asset->original_name }}</div><div class="wb-link-list-meta">{{ $asset->kind }} · {{ $asset->humanSize() }}</div></div></a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
        <section class="wb-card" aria-label="{{ $adminText('dashboard.actions_title') }}">
            <div class="wb-card-body wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>{{ $adminText('dashboard.quick_actions') }}</strong>
                @if ($dashboardAccess['create'])<a href="{{ route('admin.pages.create') }}" class="wb-btn wb-btn-primary wb-btn-sm">{{ $adminText('dashboard.new_page') }}</a>@endif
                @if ($dashboardAccess['pages'])<a href="{{ route('admin.pages.index') }}" class="wb-btn wb-btn-secondary wb-btn-sm">{{ $adminText('dashboard.pages') }}</a>@endif
                @if ($dashboardAccess['shared'])<a href="{{ route('admin.shared-slots.index') }}" class="wb-btn wb-btn-secondary wb-btn-sm">{{ $adminText('dashboard.shared_slots') }}</a>@endif
                @can('access-system')
                    <a href="{{ route('admin.sites.index') }}" class="wb-btn wb-btn-secondary wb-btn-sm">{{ $adminText('dashboard.sites') }}</a>
                    <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary wb-btn-sm">{{ $adminText('dashboard.backups') }}</a>
                    <a href="{{ route('admin.system.updates.index') }}" class="wb-btn wb-btn-secondary wb-btn-sm">{{ $adminText('dashboard.update') }}</a>
                @endcan
            </div>
        </section>
        @foreach ($pluginDashboardWidgets as $widget)
            @if (! $widget->isSummary())@include('webblocks-cms::admin.partials.dashboard-plugin-widget')@endif
        @endforeach
    </div>
@endsection
