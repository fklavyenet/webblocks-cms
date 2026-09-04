<?php

namespace WebBlocks\Cms\Support\Visitors;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class VisitorReportRetention
{
  public function policy(): array
  {
    $detailDays = max(1, (int) config('cms.visitor_reports.detail_retention_days', 90));

    return [
      'enabled' => (bool) config('cms.visitor_reports.cleanup_enabled', true),
      'detail_days' => $detailDays,
      'total_days' => max($detailDays, (int) config('cms.visitor_reports.total_retention_days', 400)),
      'ready' => Schema::hasTable('wbcms_visitor_daily_totals') && Schema::hasTable('wbcms_visitor_events'),
    ];
  }

  public function run(bool $dryRun = false): array
  {
    $policy = $this->policy();
    $result = ['events' => 0, 'totals' => 0];
    if (! $policy['enabled'] || ! $policy['ready']) {
      return $result;
    }

    $detailBefore = CarbonImmutable::today()->subDays($policy['detail_days']);
    $totalBefore = CarbonImmutable::today()->subDays($policy['total_days'])->toDateString();
    $eligible = DB::table('wbcms_visitor_events')->where('visited_at', '<', $detailBefore);
    if ($dryRun) {
      return ['events' => (clone $eligible)->count() + DB::table('wbcms_visitor_events')->whereNull('visited_at')->count(), 'totals' => DB::table('wbcms_visitor_daily_totals')->where('day', '<', $totalBefore)->count()];
    }

    // The command and scheduler share this lock. Transactions keep rollup and deletion atomic.
    $lock = Cache::lock('cms:visitor-reports:retention', 86400);
    if (! $lock->get()) {
      throw new RuntimeException(__('webblocks-cms::visitor_insights.cleanup_busy'));
    }

    try {
      $result['events'] += DB::table('wbcms_visitor_events')->whereNull('visited_at')->delete();
      $hasBots = Schema::hasColumn('wbcms_visitor_events', 'is_bot');
      while ($first = (clone $eligible)->orderBy('visited_at')->first(['visited_at'])) {
        $day = CarbonImmutable::parse($first->visited_at)->toDateString();
        $result['events'] += DB::transaction(function () use ($eligible, $day, $totalBefore, $hasBots): int {
          $dayQuery = (clone $eligible)->where('visited_at', '>=', $day.' 00:00:00')
            ->where('visited_at', '<', CarbonImmutable::parse($day)->addDay());
          $maxId = (clone $dayQuery)->max('id');
          $dayQuery->where('id', '<=', $maxId);
          if ($day >= $totalBefore) {
            $groups = (clone $dayQuery)->whereNotNull('site_id')->select('site_id')->selectRaw('COALESCE(locale_id, 0) as locale_id')
              ->selectRaw('COUNT(*) as page_views')
              ->selectRaw(($hasBots ? 'SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END)' : '0').' as bot_page_views')
              ->groupBy('site_id')->groupByRaw('COALESCE(locale_id, 0)')->get();
            foreach ($groups as $group) {
              $key = ['site_id' => $group->site_id, 'locale_id' => $group->locale_id, 'day' => $day];
              $existing = DB::table('wbcms_visitor_daily_totals')->where($key)->first();
              DB::table('wbcms_visitor_daily_totals')->updateOrInsert($key, [
                'page_views' => (int) $group->page_views + (int) ($existing?->page_views ?? 0),
                'bot_page_views' => (int) $group->bot_page_views + (int) ($existing?->bot_page_views ?? 0),
              ]);
            }
          }

          return $dayQuery->delete();
        });
      }
      $result['totals'] = DB::table('wbcms_visitor_daily_totals')->where('day', '<', $totalBefore)->delete();
    } finally {
      $lock->release();
    }

    return $result;
  }
}
