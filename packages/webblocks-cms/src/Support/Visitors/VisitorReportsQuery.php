<?php

namespace WebBlocks\Cms\Support\Visitors;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\VisitorEvent;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class VisitorReportsQuery
{
  private const DIRECT_LABEL = 'Direct / Unknown';

  private const FULL_TRACKING_MODE = 'full';

  public function __construct(private readonly AdminAuthorization $authorization) {}

  public function filters(Request $request, ?User $user = null): array
  {
    $dateRange = $this->normalizeDateRange($request);
    $site = $this->normalizeSite($request->query('site'), $user);
    $locale = $this->normalizeLocale($request->query('locale'));
    $traffic = $this->normalizeTraffic($request->query('traffic'));

    return [
      'date_range' => $dateRange['preset'],
      'from' => $dateRange['from']?->toDateString(),
      'to' => $dateRange['to']?->toDateString(),
      'site' => $site,
      'locale' => $locale,
      'traffic' => $traffic,
      'range_label' => $this->rangeLabel($dateRange['preset'], $dateRange['from'], $dateRange['to']),
      'user' => $user,
    ];
  }

  public function build(array $filters): array
  {
    $query = $this->filteredQuery($filters);
    $summary = $this->summary(clone $query);
    $supportsUtmBreakdowns = $this->supportsUtmBreakdowns();

    return [
      'summary' => $summary,
      'metric_states' => $this->metricStates($summary),
      'top_pages' => $this->topPages(clone $query),
      'top_entry_pages' => $this->topEntryPages(clone $query),
      'top_referrers' => $this->topReferrers(clone $query),
      'top_campaigns' => $supportsUtmBreakdowns ? $this->topCampaigns(clone $query) : collect(),
      'source_breakdown' => $supportsUtmBreakdowns ? $this->sourceBreakdown(clone $query) : collect(),
      'medium_breakdown' => $supportsUtmBreakdowns ? $this->mediumBreakdown(clone $query) : collect(),
      'locale_summary' => $this->localeSummary(clone $query),
      'device_summary' => $this->deviceSummary(clone $query),
      'bot_summary' => $this->botSummary(clone $query, $summary['total_page_views']),
    ];
  }

  public function hasEventsTable(): bool
  {
    return Schema::hasTable('wbcms_visitor_events');
  }

  public function utmTrackingEnabled(): bool
  {
    return (bool) config('cms.visitor_reports.utm_enabled', true);
  }

  public function supportsUtmBreakdowns(): bool
  {
    return $this->hasEventsTable() && Schema::hasColumns('visitor_events', ['utm_source', 'utm_medium', 'utm_campaign']);
  }

  public function supportsTrackingMode(): bool
  {
    return $this->hasEventsTable() && Schema::hasColumn('wbcms_visitor_events', 'tracking_mode');
  }

  public function supportsBotBreakdowns(): bool
  {
    return $this->supportsColumn('is_bot');
  }

  public function dashboardSummary(?User $user = null): array
  {
    $summary = [
      'is_enabled' => (bool) config('cms.visitor_reports.enabled', true),
      'table_exists' => $this->hasEventsTable(),
      'range_label' => 'Last 7 days',
      'total_page_views' => 0,
      'unique_visitors' => 0,
      'top_page_path' => null,
      'top_page_views' => 0,
    ];

    if (! $summary['is_enabled'] || ! $summary['table_exists']) {
      return $summary;
    }

    $from = CarbonImmutable::today()->subDays(6)->startOfDay();
    $to = CarbonImmutable::today()->endOfDay();
    $query = $this->filteredVisitorEvents($user)
      ->whereBetween('visited_at', [$from, $to]);
    $totals = $this->summary(clone $query);
    $topPage = $this->topPages(clone $query, 1)->first();

    return [
      ...$summary,
      'total_page_views' => $totals['total_page_views'],
      'unique_visitors' => $totals['unique_visitors'] ?? 0,
      'top_page_path' => $topPage['path'] ?? null,
      'top_page_views' => $topPage['page_views'] ?? 0,
    ];
  }

  private function filteredQuery(array $filters): Builder
  {
    return $this->filteredVisitorEvents($filters['user'] ?? null)
      ->with(['site', 'locale'])
      ->when($filters['site'] !== 'all', fn (Builder $query) => $query->where('site_id', (int) $filters['site']))
      ->when($filters['locale'] !== 'all', fn (Builder $query) => $query->where('locale_id', (int) $filters['locale']))
      ->when($this->supportsBotBreakdowns() && ($filters['traffic'] ?? 'all') === 'human', fn (Builder $query) => $query->where(function (Builder $query) {
        $query->where('is_bot', false)->orWhereNull('is_bot');
      }))
      ->when($this->supportsBotBreakdowns() && ($filters['traffic'] ?? 'all') === 'bots', fn (Builder $query) => $query->where('is_bot', true))
      ->whereBetween('visited_at', [
        CarbonImmutable::parse($filters['from'])->startOfDay(),
        CarbonImmutable::parse($filters['to'])->endOfDay(),
      ]);
  }

  private function summary(Builder $query): array
  {
    $summary = $query
      ->selectRaw('COUNT(*) as total_page_views')
      ->selectRaw($this->totalSessionsExpression().' as total_sessions')
      ->selectRaw($this->uniqueVisitorsExpression().' as unique_visitors')
      ->selectRaw($this->trackedPageViewsExpression().' as tracked_page_views')
      ->selectRaw($this->humanPageViewsExpression().' as human_page_views')
      ->selectRaw($this->botPageViewsExpression().' as bot_page_views')
      ->first();

    $totalPageViews = (int) ($summary?->total_page_views ?? 0);
    $totalSessions = (int) ($summary?->total_sessions ?? 0);
    $trackedPageViews = (int) ($summary?->tracked_page_views ?? 0);

    return [
      'total_page_views' => $totalPageViews,
      'human_page_views' => (int) ($summary?->human_page_views ?? $totalPageViews),
      'bot_page_views' => (int) ($summary?->bot_page_views ?? 0),
      'tracked_page_views' => $trackedPageViews,
      'unique_visitors' => $trackedPageViews > 0 ? (int) ($summary?->unique_visitors ?? 0) : null,
      'total_sessions' => $trackedPageViews > 0 ? $totalSessions : null,
      'average_pages_per_session' => $trackedPageViews > 0 && $totalSessions > 0 ? round($trackedPageViews / $totalSessions, 1) : null,
    ];
  }

  private function metricStates(array $summary): array
  {
    $trackingState = $summary['total_page_views'] > 0 && $summary['tracked_page_views'] === 0
      ? 'not_tracked'
      : ($summary['total_page_views'] === 0 ? 'no_data' : 'tracked');

    return [
      'unique_visitors' => $trackingState,
      'total_sessions' => $trackingState,
      'average_pages_per_session' => $trackingState,
    ];
  }

  private function topPages(Builder $query, int $limit = 15): Collection
  {
    return $query
      ->select('site_id', 'locale_id', 'path')
      ->selectRaw('COUNT(*) as page_views')
      ->selectRaw($this->uniqueVisitorsExpression().' as unique_visitors')
      ->selectRaw($this->trackedPageViewsExpression().' as tracked_page_views')
      ->groupBy('site_id', 'locale_id', 'path')
      ->orderByDesc('page_views')
      ->orderBy('path')
      ->limit($limit)
      ->get()
      ->map(fn (VisitorEvent $event) => [
        'site_name' => $event->site?->name ?? 'Unknown site',
        'locale_code' => $event->locale?->code ?? 'default',
        'path' => $event->path,
        'page_views' => (int) $event->page_views,
        'unique_visitors' => (int) $event->tracked_page_views > 0 ? (int) $event->unique_visitors : null,
        'unique_visitors_state' => (int) $event->tracked_page_views > 0 ? 'tracked' : 'not_tracked',
      ]);
  }

  private function topEntryPages(Builder $query): Collection
  {
    $entries = $this->fullTrackingQuery($query)
      ->whereNotNull('session_key')
      ->select(['id', 'site_id', 'locale_id', 'path', 'session_key', 'visited_at'])
      ->orderBy('session_key')
      ->orderBy('visited_at')
      ->orderBy('id')
      ->get()
      ->unique('session_key')
      ->groupBy(fn (VisitorEvent $event) => implode('|', [
        $event->site_id,
        $event->locale_id ?: 'none',
        $event->path,
      ]))
      ->map(function (Collection $group) {
        /** @var VisitorEvent $first */
        $first = $group->first();

        return [
          'site_name' => $first->site?->name ?? 'Unknown site',
          'locale_code' => $first->locale?->code ?? 'default',
          'path' => $first->path,
          'sessions' => $group->count(),
        ];
      })
      ->sortByDesc('sessions')
      ->take(10)
      ->values();

    return $entries;
  }

  private function topReferrers(Builder $query): Collection
  {
    $columns = array_values(array_filter([
      'referrer',
      $this->supportsColumn('referrer_host') ? 'referrer_host' : null,
      $this->supportsColumn('referrer_type') ? 'referrer_type' : null,
      'site_id',
    ]));

    return $query
      ->with('site.siteDomains')
      ->get($columns)
      ->map(fn (VisitorEvent $event) => $this->referrerLabel($event))
      ->filter()
      ->countBy()
      ->map(fn (int $visits, string $label) => [
        'label' => $label,
        'visits' => $visits,
      ])
      ->sortByDesc('visits')
      ->take(10)
      ->values();
  }

  private function topCampaigns(Builder $query): Collection
  {
    return $this->utmBreakdown($query, 'utm_campaign', 10);
  }

  private function sourceBreakdown(Builder $query): Collection
  {
    return $this->utmBreakdown($query, 'utm_source', 10);
  }

  private function mediumBreakdown(Builder $query): Collection
  {
    return $this->utmBreakdown($query, 'utm_medium', 10);
  }

  private function localeSummary(Builder $query): Collection
  {
    $localeNames = Locale::query()->pluck('name', 'id');

    return $query
      ->select('locale_id')
      ->selectRaw('COUNT(*) as page_views')
      ->selectRaw($this->uniqueVisitorsExpression().' as unique_visitors')
      ->selectRaw($this->trackedPageViewsExpression().' as tracked_page_views')
      ->groupBy('locale_id')
      ->orderByDesc('page_views')
      ->get()
      ->map(function (VisitorEvent $event) use ($localeNames) {
        $locale = Locale::query()->find($event->locale_id);

        return [
          'label' => $locale?->code ? strtoupper($locale->code) : 'Default',
          'name' => $locale?->name ?? ($event->locale_id ? ($localeNames[$event->locale_id] ?? 'Unknown locale') : 'Default locale'),
          'page_views' => (int) $event->page_views,
          'unique_visitors' => (int) $event->tracked_page_views > 0 ? (int) $event->unique_visitors : null,
          'unique_visitors_state' => (int) $event->tracked_page_views > 0 ? 'tracked' : 'not_tracked',
        ];
      });
  }

  private function deviceSummary(Builder $query): Collection
  {
    $total = (int) (clone $query)->count();

    return $query
      ->select('device_type')
      ->selectRaw('COUNT(*) as page_views')
      ->selectRaw($this->totalSessionsExpression().' as sessions')
      ->groupBy('device_type')
      ->orderByDesc('page_views')
      ->get()
      ->map(fn (VisitorEvent $event) => [
        'label' => match ($event->device_type) {
          'desktop' => 'Desktop',
          'mobile' => 'Mobile',
          'tablet' => 'Tablet',
          'bot' => 'Bot',
          default => 'Unknown',
        },
        'page_views' => (int) $event->page_views,
        'sessions' => (int) $event->sessions > 0 ? (int) $event->sessions : null,
        'share' => $total > 0 ? round(((int) $event->page_views / $total) * 100, 1) : 0.0,
      ]);
  }

  private function utmBreakdown(Builder $query, string $column, int $limit): Collection
  {
    return $query
      ->select($column)
      ->selectRaw('COUNT(*) as page_views')
      ->selectRaw($this->uniqueVisitorsExpression().' as unique_visitors')
      ->selectRaw($this->totalSessionsExpression().' as sessions')
      ->selectRaw($this->trackedPageViewsExpression().' as tracked_page_views')
      ->groupBy($column)
      ->orderByDesc('page_views')
      ->orderBy($column)
      ->limit($limit)
      ->get()
      ->map(fn (VisitorEvent $event) => [
        'label' => $this->utmLabel($event->{$column}),
        'page_views' => (int) $event->page_views,
        'unique_visitors' => (int) $event->tracked_page_views > 0 ? (int) $event->unique_visitors : null,
        'sessions' => (int) $event->tracked_page_views > 0 ? (int) $event->sessions : null,
        'tracking_state' => (int) $event->tracked_page_views > 0 ? 'tracked' : 'not_tracked',
      ]);
  }

  private function normalizeDateRange(Request $request): array
  {
    $preset = (string) $request->query('date_range', 'last_30_days');
    $allowed = ['today', 'last_7_days', 'last_30_days', 'this_month', 'custom'];
    $preset = in_array($preset, $allowed, true) ? $preset : 'last_30_days';
    $today = CarbonImmutable::today();

    $range = match ($preset) {
      'today' => ['from' => $today, 'to' => $today],
      'last_7_days' => ['from' => $today->subDays(6), 'to' => $today],
      'this_month' => ['from' => $today->startOfMonth(), 'to' => $today],
      'custom' => [
        'from' => $this->parseDate($request->query('from')) ?? $today->subDays(29),
        'to' => $this->parseDate($request->query('to')) ?? $today,
      ],
      default => ['from' => $today->subDays(29), 'to' => $today],
    };

    if ($range['from']->greaterThan($range['to'])) {
      [$range['from'], $range['to']] = [$range['to'], $range['from']];
    }

    return [
      'preset' => $preset,
      'from' => $range['from'],
      'to' => $range['to'],
    ];
  }

  private function normalizeSite(mixed $site, ?User $user = null): string
  {
    $normalized = is_string($site) ? trim($site) : (string) $site;

    if ($normalized === '' || $normalized === 'all') {
      return 'all';
    }

    if (! ctype_digit($normalized) || ! $this->allowedSitesQuery($user)->whereKey((int) $normalized)->exists()) {
      return 'all';
    }

    return $normalized;
  }

  private function filteredVisitorEvents(?User $user = null): Builder
  {
    $query = VisitorEvent::query();

    if ($user) {
      $siteIds = $user->accessibleSiteIds();

      if (! $user->isSuperAdmin()) {
        $query->whereIn('site_id', $siteIds);
      }
    }

    return $query;
  }

  private function allowedSitesQuery(?User $user): Builder
  {
    $query = Site::query();

    if (! $user) {
      return $query;
    }

    return $this->authorization->scopeSitesForUser($query, $user);
  }

  private function normalizeLocale(mixed $locale): string
  {
    $normalized = is_string($locale) ? trim($locale) : (string) $locale;

    if ($normalized === '' || $normalized === 'all') {
      return 'all';
    }

    if (! ctype_digit($normalized) || ! Locale::query()->whereKey((int) $normalized)->exists()) {
      return 'all';
    }

    return $normalized;
  }

  private function normalizeTraffic(mixed $traffic): string
  {
    $normalized = is_string($traffic) ? trim($traffic) : (string) $traffic;

    return in_array($normalized, ['all', 'human', 'bots'], true) ? $normalized : 'all';
  }

  private function parseDate(mixed $value): ?CarbonImmutable
  {
    $normalized = trim((string) $value);

    if ($normalized === '') {
      return null;
    }

    try {
      return CarbonImmutable::parse($normalized);
    } catch (\Throwable) {
      return null;
    }
  }

  private function rangeLabel(string $preset, ?CarbonImmutable $from, ?CarbonImmutable $to): string
  {
    return match ($preset) {
      'today' => 'Today',
      'last_7_days' => 'Last 7 days',
      'last_30_days' => 'Last 30 days',
      'this_month' => 'This month',
      default => ($from?->format('Y-m-d') ?? '?').' to '.($to?->format('Y-m-d') ?? '?'),
    };
  }

  private function referrerLabel(VisitorEvent $event): ?string
  {
    $type = trim((string) ($event->referrer_type ?? ''));

    if ($type === 'direct') {
      return self::DIRECT_LABEL;
    }

    if ($type === 'internal') {
      return 'Internal';
    }

    $host = trim((string) ($event->referrer_host ?? ''));

    if ($host !== '') {
      return $host;
    }

    $legacyHost = $this->normalizedHost($event->referrer);

    if ($legacyHost !== null) {
      return $this->isInternalReferrer($legacyHost, $event) ? 'Internal' : $legacyHost;
    }

    return self::DIRECT_LABEL;
  }

  private function utmLabel(?string $value): string
  {
    $normalized = trim((string) $value);

    return $normalized !== '' ? $normalized : self::DIRECT_LABEL;
  }

  private function fullTrackingQuery(Builder $query): Builder
  {
    if (! $this->supportsTrackingMode()) {
      return $query;
    }

    return $query->where('tracking_mode', self::FULL_TRACKING_MODE);
  }

  private function botSummary(Builder $query, int $totalPageViews): Collection
  {
    if (! $this->supportsBotBreakdowns()) {
      return collect([
        ['label' => 'Human / unknown', 'page_views' => $totalPageViews, 'share' => $totalPageViews > 0 ? 100.0 : 0.0],
        ['label' => 'Bots', 'page_views' => 0, 'share' => 0.0],
      ]);
    }

    return $query
      ->select('is_bot')
      ->selectRaw('COUNT(*) as page_views')
      ->groupBy('is_bot')
      ->orderByDesc('page_views')
      ->get()
      ->map(fn (VisitorEvent $event) => [
        'label' => $event->is_bot ? 'Bots' : 'Human / unknown',
        'page_views' => (int) $event->page_views,
        'share' => $totalPageViews > 0 ? round(((int) $event->page_views / $totalPageViews) * 100, 1) : 0.0,
      ]);
  }

  private function totalSessionsExpression(): string
  {
    if (! $this->supportsTrackingMode()) {
      return 'COUNT(DISTINCT session_key)';
    }

    return "COUNT(DISTINCT CASE WHEN tracking_mode = '".self::FULL_TRACKING_MODE."' THEN session_key END)";
  }

  private function uniqueVisitorsExpression(): string
  {
    if (! $this->supportsTrackingMode()) {
      return 'COUNT(DISTINCT COALESCE(ip_hash, session_key))';
    }

    return "COUNT(DISTINCT CASE WHEN tracking_mode = '".self::FULL_TRACKING_MODE."' THEN COALESCE(ip_hash, session_key) END)";
  }

  private function trackedPageViewsExpression(): string
  {
    if (! $this->supportsTrackingMode()) {
      return 'COUNT(*)';
    }

    return "SUM(CASE WHEN tracking_mode = '".self::FULL_TRACKING_MODE."' AND COALESCE(ip_hash, session_key) IS NOT NULL THEN 1 ELSE 0 END)";
  }

  private function humanPageViewsExpression(): string
  {
    if (! $this->supportsBotBreakdowns()) {
      return 'COUNT(*)';
    }

    return 'SUM(CASE WHEN is_bot = 1 THEN 0 ELSE 1 END)';
  }

  private function botPageViewsExpression(): string
  {
    if (! $this->supportsBotBreakdowns()) {
      return '0';
    }

    return 'SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END)';
  }

  private function normalizedHost(?string $referrer): ?string
  {
    $normalized = trim((string) $referrer);

    if ($normalized === '') {
      return null;
    }

    $host = parse_url($normalized, PHP_URL_HOST);
    $host = is_string($host) && $host !== '' ? $host : $normalized;
    $host = strtolower($host);
    $host = preg_replace('/^www\./', '', $host);

    return is_string($host) && $host !== '' ? $host : null;
  }

  private function isInternalReferrer(string $host, VisitorEvent $event): bool
  {
    $site = $event->site;

    if (! $site) {
      return false;
    }

    $canonical = $this->normalizedHost($site->canonicalDomain());

    if ($canonical !== null && $host === $canonical) {
      return true;
    }

    if ($site->relationLoaded('siteDomains')) {
      return $site->siteDomains->contains(fn ($domain) => $domain->domain === $host);
    }

    return $site->siteDomains()->where('domain', $host)->exists();
  }

  private function supportsColumn(string $column): bool
  {
    return $this->hasEventsTable() && Schema::hasColumn('wbcms_visitor_events', $column);
  }
}
