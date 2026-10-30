@if ($dashboardSystemHealth !== null)
    <section class="wb-card" data-wb-system-health-summary aria-labelledby="dashboard-health-title">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong id="dashboard-health-title">{{ $adminText('dashboard.site_health') }}</strong>
            <a href="{{ route('admin.system.health.index') }}" class="wb-btn wb-btn-ghost wb-btn-sm">{{ $adminTranslator->get('system_health.details', $adminLocaleCode) }}</a>
        </div>
        <div class="wb-card-body wb-stack wb-stack-2">
            @if (($dashboardSystemHealth['status'] ?? 'fresh') === 'missing')
                <span class="wb-status-pill wb-status-inactive">{{ $adminTranslator->get('maintenance_reports.missing', $adminLocaleCode) }}</span>
            @else
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    @if (array_sum($dashboardSystemHealth['counts']) === 0)
                        <span class="wb-text-sm wb-text-muted">{{ $adminTranslator->get('system_health.no_attention', $adminLocaleCode) }}</span>
                    @else
                        @foreach (['critical' => 'wb-status-error', 'warning' => 'wb-status-pending', 'unknown' => 'wb-status-inactive'] as $status => $class)
                            @if ($dashboardSystemHealth['counts'][$status] > 0)
                                <span class="wb-status-pill {{ $class }}">{{ number_format($dashboardSystemHealth['counts'][$status]) }} {{ $adminTranslator->get('system_health.summary.'.$status, $adminLocaleCode) }}</span>
                            @endif
                        @endforeach
                    @endif
                </div>
            @endif
                <div class="wb-cms-health-badges">
                    @foreach ($dashboardSystemHealth['categories'] ?? [] as $category => $check)
                        <a class="wb-status-pill {{ match ($check['status']) { 'healthy' => 'wb-status-active', 'critical' => 'wb-status-error', 'warning' => 'wb-status-pending', default => 'wb-status-inactive' } }}" href="{{ route('admin.system.health.index') }}#health-{{ $category }}" title="{{ $adminTranslator->get('system_health.messages.'.$check['lead']['message'], $adminLocaleCode, $check['lead']['parameters'] ?? []) }}">
                            <i class="wb-icon {{ match ($check['status']) { 'healthy' => 'wb-icon-check', 'warning', 'critical' => 'wb-icon-triangle-alert', default => 'wb-icon-help-circle' } }}" aria-hidden="true"></i>
                            {{ $adminTranslator->get('system_health.categories.'.$category, $adminLocaleCode) }}
                            <span class="wb-sr-only">: {{ $adminTranslator->get('system_health.statuses.'.$check['status'], $adminLocaleCode) }}</span>
                        </a>
                    @endforeach
                </div>
                @if (($dashboardSystemHealth['status'] ?? 'fresh') === 'stale')
                    <p class="wb-text-xs wb-text-muted">{{ $adminTranslator->get('maintenance_reports.stale', $adminLocaleCode, ['time' => $dashboardSystemHealth['checked_at']]) }}</p>
                @elseif (! empty($dashboardSystemHealth['checked_at']))
                    <p class="wb-text-xs wb-text-muted">{{ $adminTranslator->get('system_health.checked_at', $adminLocaleCode, ['time' => $dashboardSystemHealth['checked_at']]) }}</p>
                @endif
        </div>
    </section>
@else
    @include('webblocks-cms::admin.partials.dashboard-scheduler-health')
@endif
