<?php

namespace App\Support\Visitors;

use App\Models\Locale;
use App\Models\Site;
use App\Models\VisitorEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitorReportsQuery
{
    public function filters(Request $request): array
    {
        $dateRange = $this->normalizeDateRange($request);
        $site = $this->normalizeSite($request->query('site'));
        $locale = $this->normalizeLocale($request->query('locale'));

        return [
            'date_range' => $dateRange['preset'],
            'from' => $dateRange['from']?->toDateString(),
            'to' => $dateRange['to']?->toDateString(),
            'site' => $site,
            'locale' => $locale,
            'range_label' => $this->rangeLabel($dateRange['preset'], $dateRange['from'], $dateRange['to']),
        ];
    }

    public function build(array $filters): array
    {
        $query = $this->filteredQuery($filters);
        $summary = $this->summary(clone $query);

        return [
            'summary' => $summary,
            'top_pages' => $this->topPages(clone $query),
            'top_entry_pages' => $this->topEntryPages(clone $query),
            'top_referrers' => $this->topReferrers(clone $query),
            'locale_summary' => $this->localeSummary(clone $query),
            'device_summary' => $this->deviceSummary(clone $query),
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        return VisitorEvent::query()
            ->with(['site', 'locale'])
            ->when($filters['site'] !== 'all', fn (Builder $query) => $query->where('site_id', (int) $filters['site']))
            ->when($filters['locale'] !== 'all', fn (Builder $query) => $query->where('locale_id', (int) $filters['locale']))
            ->whereBetween('visited_at', [
                CarbonImmutable::parse($filters['from'])->startOfDay(),
                CarbonImmutable::parse($filters['to'])->endOfDay(),
            ]);
    }

    private function summary(Builder $query): array
    {
        $summary = $query
            ->selectRaw('COUNT(*) as total_page_views')
            ->selectRaw('COUNT(DISTINCT session_key) as total_sessions')
            ->selectRaw('COUNT(DISTINCT COALESCE(ip_hash, session_key)) as unique_visitors')
            ->first();

        $totalPageViews = (int) ($summary?->total_page_views ?? 0);
        $totalSessions = (int) ($summary?->total_sessions ?? 0);

        return [
            'total_page_views' => $totalPageViews,
            'unique_visitors' => (int) ($summary?->unique_visitors ?? 0),
            'total_sessions' => $totalSessions,
            'average_pages_per_session' => $totalSessions > 0 ? round($totalPageViews / $totalSessions, 1) : 0.0,
        ];
    }

    private function topPages(Builder $query): Collection
    {
        return $query
            ->select('site_id', 'locale_id', 'path')
            ->selectRaw('COUNT(*) as page_views')
            ->selectRaw('COUNT(DISTINCT COALESCE(ip_hash, session_key)) as unique_visitors')
            ->groupBy('site_id', 'locale_id', 'path')
            ->orderByDesc('page_views')
            ->orderBy('path')
            ->limit(15)
            ->get()
            ->map(fn (VisitorEvent $event) => [
                'site_name' => $event->site?->name ?? 'Unknown site',
                'locale_code' => $event->locale?->code ?? 'default',
                'path' => $event->path,
                'page_views' => (int) $event->page_views,
                'unique_visitors' => (int) $event->unique_visitors,
            ]);
    }

    private function topEntryPages(Builder $query): Collection
    {
        $entries = $query
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
        return $query
            ->whereNotNull('referrer')
            ->get(['referrer'])
            ->map(fn (VisitorEvent $event) => $this->referrerLabel($event->referrer))
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

    private function localeSummary(Builder $query): Collection
    {
        $localeNames = Locale::query()->pluck('name', 'id');

        return $query
            ->select('locale_id')
            ->selectRaw('COUNT(*) as page_views')
            ->selectRaw('COUNT(DISTINCT COALESCE(ip_hash, session_key)) as unique_visitors')
            ->groupBy('locale_id')
            ->orderByDesc('page_views')
            ->get()
            ->map(function (VisitorEvent $event) use ($localeNames) {
                $locale = Locale::query()->find($event->locale_id);

                return [
                    'label' => $locale?->code ? strtoupper($locale->code) : 'Default',
                    'name' => $locale?->name ?? ($event->locale_id ? ($localeNames[$event->locale_id] ?? 'Unknown locale') : 'Default locale'),
                    'page_views' => (int) $event->page_views,
                    'unique_visitors' => (int) $event->unique_visitors,
                ];
            });
    }

    private function deviceSummary(Builder $query): Collection
    {
        return $query
            ->select('device_type')
            ->selectRaw('COUNT(*) as page_views')
            ->selectRaw('COUNT(DISTINCT session_key) as sessions')
            ->groupBy('device_type')
            ->orderByDesc('page_views')
            ->get()
            ->map(fn (VisitorEvent $event) => [
                'label' => match ($event->device_type) {
                    'desktop' => 'Desktop',
                    'mobile' => 'Mobile',
                    'tablet' => 'Tablet',
                    default => 'Unknown',
                },
                'page_views' => (int) $event->page_views,
                'sessions' => (int) $event->sessions,
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

    private function normalizeSite(mixed $site): string
    {
        $normalized = is_string($site) ? trim($site) : (string) $site;

        if ($normalized === '' || $normalized === 'all') {
            return 'all';
        }

        if (! ctype_digit($normalized) || ! Site::query()->whereKey((int) $normalized)->exists()) {
            return 'all';
        }

        return $normalized;
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

    private function referrerLabel(?string $referrer): ?string
    {
        $normalized = trim((string) $referrer);

        if ($normalized === '') {
            return null;
        }

        $host = parse_url($normalized, PHP_URL_HOST);
        $path = parse_url($normalized, PHP_URL_PATH);

        if (is_string($host) && $host !== '') {
            return $host.($path && $path !== '/' ? $path : '');
        }

        return $normalized;
    }
}
