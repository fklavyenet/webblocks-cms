@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('visitor_reports.'.$key, $adminLocale, $replace);
    $insightText = static fn (string $key, array $replace = []) => $adminTranslator->get('visitor_insights.'.$key, $adminLocale, $replace);
    $summary = $report['summary'] ?? [
        'total_page_views' => 0,
        'human_page_views' => 0,
        'bot_page_views' => 0,
        'tracked_page_views' => 0,
        'unique_visitors' => 0,
        'total_sessions' => 0,
        'average_pages_per_session' => 0,
    ];
    $metricStates = $report['metric_states'] ?? [
        'unique_visitors' => 'no_data',
        'total_sessions' => 'no_data',
        'average_pages_per_session' => 'no_data',
    ];
    $supportsCampaignReports = ($supportsUtmBreakdowns ?? false) && ($utmEnabled ?? true);
    $hasFilters = $filters['date_range'] !== 'last_30_days' || $filters['site'] !== 'all' || $filters['locale'] !== 'all' || ($filters['traffic'] ?? 'all') !== 'all';
    $reportTabs = [
        'acquisition' => $adminText('acquisition'),
        'journeys' => $adminText('journeys'),
        'audience' => $adminText('audience'),
        'traffic' => $adminText('traffic'),
        'content' => $adminText('content'),
    ];
    $reportTab = preg_replace('/^visitor-reports-|\-panel$/', '', (string) request()->query('tab', 'acquisition'));

    if (! array_key_exists($reportTab, $reportTabs)) {
        $reportTab = 'acquisition';
    }

    $trackedMetric = function ($value, string $state, int $decimals = 0) use ($adminText): string {
        if ($state === 'not_tracked') {
            return $adminText('not_tracked');
        }

        if ($state === 'no_data') {
            return $adminText('no_data');
        }

        if ($value === null) {
            return '—';
        }

        return number_format((float) $value, $decimals);
    };
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('title'), 'heading' => $adminText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('title'),
        'description' => $adminText('description'),
        'context' => '<span>'.e($adminText('range_context', ['range' => $filters['range_label']])).'</span>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if (! $isEnabled)
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('disabled_title') }}</div>
                    <div class="wb-empty-text">{!! $adminText('disabled_text') !!}</div>
                </div>
            </div>
        </div>
    @elseif (! $visitorEventsTableExists)
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('migration_missing_title') }}</div>
                    <div class="wb-empty-text">{!! $adminText('migration_missing_text') !!}</div>
                </div>
            </div>
        </div>
    @else
        <form method="GET" action="{{ route('admin.reports.visitors.index') }}" class="wb-filter-bar wb-filter-bar--fields" data-admin-listing-filters>
            <input type="hidden" name="tab" value="{{ $reportTab }}" data-wb-visitor-reports-tab-input>

            <div class="wb-filter-bar-fields" data-admin-listing-filters-fields>
                <div class="wb-field">
                    <label class="wb-label" for="visitor_reports_date_range">{{ $adminText('date_range') }}</label>
                    <select id="visitor_reports_date_range" name="date_range" class="wb-filter-select">
                        <option value="today" @selected($filters['date_range'] === 'today')>{{ $adminText('today') }}</option>
                        <option value="last_7_days" @selected($filters['date_range'] === 'last_7_days')>{{ $adminText('last_7_days') }}</option>
                        <option value="last_30_days" @selected($filters['date_range'] === 'last_30_days')>{{ $adminText('last_30_days') }}</option>
                        <option value="this_month" @selected($filters['date_range'] === 'this_month')>{{ $adminText('this_month') }}</option>
                        <option value="custom" @selected($filters['date_range'] === 'custom')>{{ $adminText('custom') }}</option>
                    </select>
                </div>

                <div class="wb-field">
                    <label class="wb-label" for="visitor_reports_from">{{ $adminText('from') }}</label>
                    <input id="visitor_reports_from" name="from" type="date" class="wb-input" value="{{ $filters['from'] }}">
                </div>

                <div class="wb-field">
                    <label class="wb-label" for="visitor_reports_to">{{ $adminText('to') }}</label>
                    <input id="visitor_reports_to" name="to" type="date" class="wb-input" value="{{ $filters['to'] }}">
                </div>

                <div class="wb-field">
                    <label class="wb-label" for="visitor_reports_site">{{ $adminText('site') }}</label>
                    <select id="visitor_reports_site" name="site" class="wb-filter-select">
                        <option value="all" @selected($filters['site'] === 'all')>{{ $adminText('all_sites') }}</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected($filters['site'] === (string) $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="wb-field">
                    <label class="wb-label" for="visitor_reports_locale">{{ $adminText('locale') }}</label>
                    <select id="visitor_reports_locale" name="locale" class="wb-filter-select">
                        <option value="all" @selected($filters['locale'] === 'all')>{{ $adminText('all_locales') }}</option>
                        @foreach ($locales as $locale)
                            <option value="{{ $locale->id }}" @selected($filters['locale'] === (string) $locale->id)>{{ $locale->name }} ({{ strtoupper($locale->code) }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="wb-field">
                    <label class="wb-label" for="visitor_reports_traffic">{{ $adminText('traffic') }}</label>
                    <select id="visitor_reports_traffic" name="traffic" class="wb-filter-select">
                        <option value="all" @selected(($filters['traffic'] ?? 'all') === 'all')>{{ $adminText('all_traffic') }}</option>
                        <option value="human" @selected(($filters['traffic'] ?? 'all') === 'human')>{{ $adminText('human_only') }}</option>
                        <option value="bots" @selected(($filters['traffic'] ?? 'all') === 'bots')>{{ $adminText('bots_only') }}</option>
                    </select>
                </div>
                <div class="wb-filter-bar-actions" data-admin-listing-filters-actions>
                    <div class="wb-action-group">
                        <button type="submit" class="wb-btn wb-btn-primary">{{ $adminText('apply') }}</button>
                        @if ($hasFilters)
                            <a href="{{ route('admin.reports.visitors.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('clear') }}</a>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        @if ($report['insights']['archived']['total_page_views'] > 0)
            <div class="wb-alert wb-alert-info" role="status">{{ $insightText('archived', ['views' => number_format($report['insights']['archived']['total_page_views'])]) }}</div>
        @endif
        @if (! $report['insights']['retention']['ready'])
            <div class="wb-alert wb-alert-warning" role="status">{{ $insightText('cleanup_not_ready') }}</div>
        @elseif (! $report['insights']['retention']['enabled'])
            <div class="wb-alert wb-alert-info" role="status">{{ $insightText('cleanup_disabled') }}</div>
        @endif

        <div class="wb-grid wb-grid-4">
            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-1">
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('total_page_views') }}</div>
                    <div class="wb-text-xs wb-text-muted">{{ $insightText('aggregate') }}</div>
                    <strong>{{ number_format($summary['total_page_views']) }}</strong>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-1">
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('human_page_views') }}</div>
                    <div class="wb-text-xs wb-text-muted">{{ $adminText('bot_flag_excluded') }}</div>
                    <strong>{{ number_format($summary['human_page_views']) }}</strong>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-1">
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('bot_page_views') }}</div>
                    <div class="wb-text-xs wb-text-muted">{{ $adminText('shown_separately') }}</div>
                    <strong>{{ number_format($summary['bot_page_views']) }}</strong>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-1">
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('unique_visitors') }}</div>
                    <div class="wb-text-xs wb-text-muted">{{ $adminText('requires_session_consent') }}</div>
                    <strong>{{ $trackedMetric($summary['unique_visitors'], $metricStates['unique_visitors']) }}</strong>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-1">
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('total_sessions') }}</div>
                    <div class="wb-text-xs wb-text-muted">{{ $adminText('requires_session_consent') }}</div>
                    <strong>{{ $trackedMetric($summary['total_sessions'], $metricStates['total_sessions']) }}</strong>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-1">
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('average_pages_per_session') }}</div>
                    <div class="wb-text-xs wb-text-muted">{{ $adminText('tracked_page_views_only') }}</div>
                    <strong>{{ $trackedMetric($summary['average_pages_per_session'], $metricStates['average_pages_per_session'], 1) }}</strong>
                </div>
            </div>
        </div>

        @include('webblocks-cms::admin.reports.visitors.insights')

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('detailed_reports') }}</strong></div>
            <div class="wb-card-body">
                <div class="wb-tabs" data-wb-tabs data-wb-tabs-field="[data-wb-visitor-reports-tab-input]">
                    <div class="wb-tabs-nav" role="tablist" aria-label="{{ $adminText('report_sections') }}">
                        @foreach ($reportTabs as $tabKey => $tabLabel)
                            <button
                                type="button"
                                class="wb-tabs-btn {{ $reportTab === $tabKey ? 'is-active' : '' }}"
                                data-wb-tab="visitor-reports-{{ $tabKey }}-panel"
                                aria-selected="{{ $reportTab === $tabKey ? 'true' : 'false' }}"
                                @if ($reportTab !== $tabKey) tabindex="-1" @endif
                            >{{ $tabLabel }}</button>
                        @endforeach
                    </div>

                    <div class="wb-tabs-panels">
                        <div class="wb-tabs-panel {{ $reportTab === 'acquisition' ? 'is-active' : '' }}" id="visitor-reports-acquisition-panel">
                            <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('top_campaigns') }}</strong></div>
                <div class="wb-card-body">
                    @if (! $supportsCampaignReports)
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('campaign_tracking_unavailable') }}</div>
                            <div class="wb-empty-text">
                                @if (! $utmEnabled)
                                    {!! $adminText('campaign_tracking_disabled_help') !!}
                                @else
                                    {!! $adminText('campaign_tracking_migration_help') !!}
                                @endif
                            </div>
                        </div>
                    @elseif ($report['top_campaigns']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('no_campaign_data') }}</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ $adminText('campaign') }}</th>
                                        <th>{{ $adminText('page_views') }}</th>
                                        <th>{{ $adminText('visitors') }}</th>
                                        <th>{{ $adminText('sessions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['top_campaigns'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ $trackedMetric($row['unique_visitors'], $row['tracking_state']) }}</td>
                                            <td>{{ $trackedMetric($row['sessions'], $row['tracking_state']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('source_breakdown') }}</strong></div>
                <div class="wb-card-body">
                    @if (! $supportsCampaignReports)
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('no_source_breakdown') }}</div>
                        </div>
                    @elseif ($report['source_breakdown']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('no_source_data') }}</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ $adminText('source') }}</th>
                                        <th>{{ $adminText('page_views') }}</th>
                                        <th>{{ $adminText('visitors') }}</th>
                                        <th>{{ $adminText('sessions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['source_breakdown'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ $trackedMetric($row['unique_visitors'], $row['tracking_state']) }}</td>
                                            <td>{{ $trackedMetric($row['sessions'], $row['tracking_state']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('medium_breakdown') }}</strong></div>
                <div class="wb-card-body">
                    @if (! $supportsCampaignReports)
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('no_medium_breakdown') }}</div>
                        </div>
                    @elseif ($report['medium_breakdown']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('no_medium_data') }}</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ $adminText('medium') }}</th>
                                        <th>{{ $adminText('page_views') }}</th>
                                        <th>{{ $adminText('visitors') }}</th>
                                        <th>{{ $adminText('sessions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['medium_breakdown'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ $trackedMetric($row['unique_visitors'], $row['tracking_state']) }}</td>
                                            <td>{{ $trackedMetric($row['sessions'], $row['tracking_state']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

                            </div>
                        </div>

                        <div class="wb-tabs-panel {{ $reportTab === 'journeys' ? 'is-active' : '' }}" id="visitor-reports-journeys-panel">
                            <div class="wb-grid wb-grid-2">

            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('top_entry_pages') }}</strong></div>
                <div class="wb-card-body">
                    @if ($report['top_entry_pages']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('no_entry_data') }}</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ $adminText('path') }}</th>
                                        <th>{{ $adminText('site') }}</th>
                                        <th>{{ $adminText('locale') }}</th>
                                        <th>{{ $adminText('sessions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['top_entry_pages'] as $row)
                                        <tr>
                                            <td><code>{{ $row['path'] }}</code></td>
                                            <td>{{ $row['site_name'] }}</td>
                                            <td>{{ strtoupper($row['locale_code']) }}</td>
                                            <td>{{ number_format($row['sessions']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('top_referrers') }}</strong></div>
                <div class="wb-card-body">
                    @if ($report['top_referrers']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('no_referrer_data') }}</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ $adminText('referrer') }}</th>
                                        <th>{{ $adminText('visits') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['top_referrers'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['visits']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

                            </div>
                        </div>

                        <div class="wb-tabs-panel {{ $reportTab === 'audience' ? 'is-active' : '' }}" id="visitor-reports-audience-panel">
                            <div class="wb-grid wb-grid-2">

            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('locale_summary') }}</strong></div>
                <div class="wb-card-body">
                    @if ($report['locale_summary']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('no_locale_data') }}</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ $adminText('locale') }}</th>
                                        <th>{{ $adminText('page_views') }}</th>
                                        <th>{{ $adminText('visitors') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['locale_summary'] as $row)
                                        <tr>
                                            <td>{{ $row['name'] }} <span class="wb-text-sm wb-text-muted">{{ $row['label'] }}</span></td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ $trackedMetric($row['unique_visitors'], $row['unique_visitors_state']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('device_summary') }}</strong></div>
                <div class="wb-card-body">
                    @if ($report['device_summary']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('no_device_data') }}</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ $adminText('device') }}</th>
                                        <th>{{ $adminText('page_views') }}</th>
                                        <th>{{ $adminText('share') }}</th>
                                        <th>{{ $adminText('sessions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['device_summary'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ number_format($row['share'], 1) }}%</td>
                                            <td>{{ $row['sessions'] === null ? $adminText('not_tracked') : number_format($row['sessions']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

                            </div>
                        </div>

                        <div class="wb-tabs-panel {{ $reportTab === 'traffic' ? 'is-active' : '' }}" id="visitor-reports-traffic-panel">

            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('bot_visibility') }}</strong></div>
                <div class="wb-card-body">
                    @if ($report['bot_summary']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('no_traffic_data') }}</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ $adminText('traffic') }}</th>
                                        <th>{{ $adminText('page_views') }}</th>
                                        <th>{{ $adminText('share') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['bot_summary'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ number_format($row['share'], 1) }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

                        </div>

                        <div class="wb-tabs-panel {{ $reportTab === 'content' ? 'is-active' : '' }}" id="visitor-reports-content-panel">

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('top_pages') }}</strong></div>
            <div class="wb-card-body">
                @if ($report['top_pages']->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('no_page_visits') }}</div>
                        <div class="wb-empty-text">{{ $adminText('no_page_visits_help') }}</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>{{ $adminText('path') }}</th>
                                    <th>{{ $adminText('site') }}</th>
                                    <th>{{ $adminText('locale') }}</th>
                                    <th>{{ $adminText('page_views') }}</th>
                                    <th>{{ $adminText('unique_visitors') }}</th>
                                    <th>{{ $insightText('actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['top_pages'] as $row)
                                    <tr>
                                        <td><code>{{ $row['path'] }}</code></td>
                                        <td>{{ $row['site_name'] }}</td>
                                        <td>{{ strtoupper($row['locale_code']) }}</td>
                                        <td>{{ number_format($row['page_views']) }}</td>
                                        <td>{{ $trackedMetric($row['unique_visitors'], $row['unique_visitors_state']) }}</td>
                                        <td class="wb-table-actions"><div class="wb-action-group">
                                            <button type="button" class="wb-icon-btn" data-wb-toggle="modal" data-wb-target="#visitor-page-{{ $loop->index }}" aria-label="{{ $insightText('details') }}">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </button>
                                        </div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @include('webblocks-cms::admin.reports.visitors.page-details')
    @endif
@endsection
