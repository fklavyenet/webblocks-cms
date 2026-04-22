@extends('layouts.admin', ['title' => 'Visitor Reports', 'heading' => 'Visitor Reports'])

@php
    $summary = $report['summary'] ?? [
        'total_page_views' => 0,
        'unique_visitors' => 0,
        'total_sessions' => 0,
        'average_pages_per_session' => 0,
    ];
    $supportsCampaignReports = ($supportsUtmBreakdowns ?? false) && ($utmEnabled ?? true);
    $hasFilters = $filters['date_range'] !== 'last_30_days' || $filters['site'] !== 'all' || $filters['locale'] !== 'all';
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Visitor Reports',
        'description' => 'Review lightweight public visit activity across sites and locales without leaving the CMS.',
        'context' => '<span>Range: '.e($filters['range_label']).'</span>',
    ])

    @include('admin.partials.flash')

    @if (! $isEnabled)
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">Visitor reports are disabled</div>
                    <div class="wb-empty-text">Set <code>CMS_VISITOR_REPORTS_ENABLED=true</code> to resume public visit tracking and reporting.</div>
                </div>
            </div>
        </div>
    @elseif (! $visitorEventsTableExists)
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">Visitor reports migration is missing</div>
                    <div class="wb-empty-text">Run <code>php artisan migrate</code> to create the <code>visitor_events</code> table before opening this report.</div>
                </div>
            </div>
        </div>
    @else
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                <form method="GET" action="{{ route('admin.reports.visitors.index') }}" class="wb-cluster wb-cluster-2 wb-cluster-between">
                    <div class="wb-cluster wb-cluster-2">
                        <div class="wb-stack wb-gap-1">
                            <label for="visitor_reports_date_range">Date range</label>
                            <select id="visitor_reports_date_range" name="date_range" class="wb-select">
                                <option value="today" @selected($filters['date_range'] === 'today')>Today</option>
                                <option value="last_7_days" @selected($filters['date_range'] === 'last_7_days')>Last 7 days</option>
                                <option value="last_30_days" @selected($filters['date_range'] === 'last_30_days')>Last 30 days</option>
                                <option value="this_month" @selected($filters['date_range'] === 'this_month')>This month</option>
                                <option value="custom" @selected($filters['date_range'] === 'custom')>Custom</option>
                            </select>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="visitor_reports_from">From</label>
                            <input id="visitor_reports_from" name="from" type="date" class="wb-input" value="{{ $filters['from'] }}">
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="visitor_reports_to">To</label>
                            <input id="visitor_reports_to" name="to" type="date" class="wb-input" value="{{ $filters['to'] }}">
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="visitor_reports_site">Site</label>
                            <select id="visitor_reports_site" name="site" class="wb-select">
                                <option value="all" @selected($filters['site'] === 'all')>All sites</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}" @selected($filters['site'] === (string) $site->id)>{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="visitor_reports_locale">Locale</label>
                            <select id="visitor_reports_locale" name="locale" class="wb-select">
                                <option value="all" @selected($filters['locale'] === 'all')>All locales</option>
                                @foreach ($locales as $locale)
                                    <option value="{{ $locale->id }}" @selected($filters['locale'] === (string) $locale->id)>{{ $locale->name }} ({{ strtoupper($locale->code) }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="wb-cluster wb-cluster-2 wb-admin-filter-actions-end">
                        <button type="submit" class="wb-btn wb-btn-primary">Apply</button>
                        @if ($hasFilters)
                            <a href="{{ route('admin.reports.visitors.index') }}" class="wb-btn wb-btn-secondary">Clear</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="wb-grid wb-grid-4">
            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-1">
                    <div class="wb-text-sm wb-text-muted">Total page views</div>
                    <strong>{{ number_format($summary['total_page_views']) }}</strong>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-1">
                    <div class="wb-text-sm wb-text-muted">Unique visitors</div>
                    <strong>{{ number_format($summary['unique_visitors']) }}</strong>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-1">
                    <div class="wb-text-sm wb-text-muted">Total sessions</div>
                    <strong>{{ number_format($summary['total_sessions']) }}</strong>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-1">
                    <div class="wb-text-sm wb-text-muted">Average pages per session</div>
                    <strong>{{ number_format($summary['average_pages_per_session'], 1) }}</strong>
                </div>
            </div>
        </div>

        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header"><strong>Top Campaigns</strong></div>
                <div class="wb-card-body">
                    @if (! $supportsCampaignReports)
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">Campaign tracking is unavailable</div>
                            <div class="wb-empty-text">
                                @if (! $utmEnabled)
                                    Set <code>CMS_VISITOR_UTM_ENABLED=true</code> to capture UTM parameters for campaign reporting.
                                @else
                                    Run <code>php artisan migrate</code> so the UTM columns are available in <code>visitor_events</code>.
                                @endif
                            </div>
                        </div>
                    @elseif ($report['top_campaigns']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No campaign data yet</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>Campaign</th>
                                        <th>Page views</th>
                                        <th>Visitors</th>
                                        <th>Sessions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['top_campaigns'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ number_format($row['unique_visitors']) }}</td>
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
                <div class="wb-card-header"><strong>Source Breakdown</strong></div>
                <div class="wb-card-body">
                    @if (! $supportsCampaignReports)
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No source breakdown yet</div>
                        </div>
                    @elseif ($report['source_breakdown']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No source data yet</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th>Page views</th>
                                        <th>Visitors</th>
                                        <th>Sessions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['source_breakdown'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ number_format($row['unique_visitors']) }}</td>
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
                <div class="wb-card-header"><strong>Medium Breakdown</strong></div>
                <div class="wb-card-body">
                    @if (! $supportsCampaignReports)
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No medium breakdown yet</div>
                        </div>
                    @elseif ($report['medium_breakdown']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No medium data yet</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>Medium</th>
                                        <th>Page views</th>
                                        <th>Visitors</th>
                                        <th>Sessions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['medium_breakdown'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ number_format($row['unique_visitors']) }}</td>
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
                <div class="wb-card-header"><strong>Top Entry Pages</strong></div>
                <div class="wb-card-body">
                    @if ($report['top_entry_pages']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No entry data yet</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>Path</th>
                                        <th>Site</th>
                                        <th>Locale</th>
                                        <th>Sessions</th>
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
                <div class="wb-card-header"><strong>Top Referrers</strong></div>
                <div class="wb-card-body">
                    @if ($report['top_referrers']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No referrer data yet</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>Referrer</th>
                                        <th>Visits</th>
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

            <div class="wb-card">
                <div class="wb-card-header"><strong>Locale Summary</strong></div>
                <div class="wb-card-body">
                    @if ($report['locale_summary']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No locale data yet</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>Locale</th>
                                        <th>Page views</th>
                                        <th>Visitors</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['locale_summary'] as $row)
                                        <tr>
                                            <td>{{ $row['name'] }} <span class="wb-text-sm wb-text-muted">{{ $row['label'] }}</span></td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ number_format($row['unique_visitors']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>Device Summary</strong></div>
                <div class="wb-card-body">
                    @if ($report['device_summary']->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No device data yet</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead>
                                    <tr>
                                        <th>Device</th>
                                        <th>Page views</th>
                                        <th>Sessions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['device_summary'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ number_format($row['page_views']) }}</td>
                                            <td>{{ number_format($row['sessions']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Top Pages</strong></div>
            <div class="wb-card-body">
                @if ($report['top_pages']->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">No page visits yet</div>
                        <div class="wb-empty-text">Published public pages will begin appearing here after successful page renders.</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>Path</th>
                                    <th>Site</th>
                                    <th>Locale</th>
                                    <th>Page views</th>
                                    <th>Unique visitors</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['top_pages'] as $row)
                                    <tr>
                                        <td><code>{{ $row['path'] }}</code></td>
                                        <td>{{ $row['site_name'] }}</td>
                                        <td>{{ strtoupper($row['locale_code']) }}</td>
                                        <td>{{ number_format($row['page_views']) }}</td>
                                        <td>{{ number_format($row['unique_visitors']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
