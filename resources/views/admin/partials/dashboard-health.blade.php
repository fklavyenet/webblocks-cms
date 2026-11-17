@if ($dashboardSystemHealth !== null)
    @php
        $healthText = static fn (string $key, array $replace = []) => $adminTranslator->get('system_health.'.$key, $adminLocaleCode, $replace);
        $healthUrl = route('admin.system.health.index');
        $reportStatus = $dashboardSystemHealth['status'] ?? 'fresh';
        $categories = $dashboardSystemHealth['categories'] ?? [];
        $categoryCounts = array_count_values(array_column($categories, 'status'));
        $detailsOpen = (bool) session('dashboard_health_open');
        $checkedAt = $dashboardSystemHealth['checked_at'] ?? null;
        $statusClasses = [
            'critical' => 'wb-status-error',
            'warning' => 'wb-status-pending',
            'unknown' => 'wb-status-info',
            'healthy' => 'wb-status-active',
            'not_applicable' => 'wb-status-inactive',
        ];
    @endphp
    <section class="wb-card" data-wb-system-health-summary aria-labelledby="dashboard-health-title">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <h2 class="wb-card-title" id="dashboard-health-title">
                <i class="wb-icon wb-icon-activity" aria-hidden="true"></i>
                {{ $adminText('dashboard.site_health') }}
            </h2>
            @if ($reportStatus === 'missing')
                <form method="POST" action="{{ route('admin.dashboard.health.check') }}">
                    @csrf
                    <button type="submit" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-busy data-wb-busy-label="{{ $adminTranslator->get('maintenance_reports.refreshing', $adminLocaleCode) }}">
                        <span data-wb-busy-text>{{ $healthText('details') }}</span>
                        <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
                    </button>
                </form>
            @else
                <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm wb-cms-health-toggle"
                    data-wb-collapse="dashboard-health-details" aria-controls="dashboard-health-details" aria-expanded="{{ $detailsOpen ? 'true' : 'false' }}">
                    <span class="wb-cms-health-show">{{ $healthText('details') }}</span>
                    <span class="wb-cms-health-hide">{{ $healthText('dashboard_hide_checks') }}</span>
                    <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
                </button>
                <noscript><a href="{{ $healthUrl }}">{{ $healthText('title') }}</a></noscript>
            @endif
        </div>
        <div class="wb-card-body wb-stack wb-stack-2">
            @if (session('dashboard_health_failed'))
                <div class="wb-alert wb-alert-danger" role="alert">{{ $healthText('dashboard_check_failed') }}</div>
            @endif
            @if ($reportStatus === 'missing')
                <p class="wb-text-sm wb-text-muted">
                    <strong>{{ $adminTranslator->get('maintenance_reports.missing', $adminLocaleCode) }}</strong>
                    {{ $healthText('dashboard_missing_help') }}
                </p>
            @else
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    @foreach (['healthy', 'critical', 'warning', 'unknown'] as $status)
                        @if (! empty($categoryCounts[$status]))
                            <span class="wb-status-pill {{ $statusClasses[$status] }}">{{ number_format($categoryCounts[$status]) }} {{ $healthText('summary.'.$status) }}</span>
                        @endif
                    @endforeach
                    @if (empty($categoryCounts['critical']) && empty($categoryCounts['warning']) && empty($categoryCounts['unknown']) && empty($categoryCounts['healthy']))
                        <span class="wb-text-sm">{{ $healthText('no_attention') }}</span>
                    @endif
                </div>
                @if ($checkedAt !== null)
                    @php
                        $time = \Carbon\CarbonImmutable::parse($checkedAt)->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i');
                    @endphp
                    <p class="wb-text-xs wb-text-muted">
                        {{ $reportStatus === 'stale' ? $adminTranslator->get('maintenance_reports.stale', $adminLocaleCode, ['time' => $time]) : $healthText('checked_at', ['time' => $time]) }}
                    </p>
                @endif
                <div id="dashboard-health-details" class="wb-collapse {{ $detailsOpen ? 'is-open' : '' }}">
                    <div class="wb-stack wb-stack-2">
                        <table class="wb-table wb-table-sm wb-cms-health-table">
                            <tbody>
                                @foreach ($categories as $category => $check)
                                    <tr>
                                        <th scope="row"><a href="{{ $healthUrl }}#health-{{ $category }}" data-wb-tooltip="{{ $healthText('messages.'.$check['lead']['message'], $check['lead']['parameters'] ?? []) }}" data-wb-tooltip-wrap>{{ $healthText('categories.'.$category) }}</a></th>
                                        <td><span class="wb-status-pill {{ $statusClasses[$check['status']] }}">{{ $healthText('statuses.'.$check['status']) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div><a href="{{ $healthUrl }}" class="wb-btn wb-btn-secondary wb-btn-sm">{{ $healthText('title') }} <i class="wb-icon wb-icon-arrow-right" aria-hidden="true"></i></a></div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@else
    @include('webblocks-cms::admin.partials.dashboard-scheduler-health')
@endif
