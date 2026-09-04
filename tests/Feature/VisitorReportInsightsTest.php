<?php

namespace WebBlocks\Cms\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\VisitorEvent;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Visitors\VisitorConsent;
use WebBlocks\Cms\Support\Visitors\VisitorEventLogger;
use WebBlocks\Cms\Support\Visitors\VisitorReportRetention;
use WebBlocks\Cms\Support\Visitors\VisitorReportsQuery;
use WebBlocks\Cms\Tests\TestCase;

class VisitorReportInsightsTest extends TestCase
{
  private Site $site;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    CarbonImmutable::setTestNow('2026-09-04 12:00:00');
    $this->site = Site::query()->create(['name' => 'Example', 'handle' => 'example']);
  }

  protected function tearDown(): void
  {
    CarbonImmutable::setTestNow();
    parent::tearDown();
  }

  private function event(string $at, array $attributes = []): VisitorEvent
  {
    return VisitorEvent::query()->create([...[
      'site_id' => $this->site->id, 'path' => '/page', 'visited_at' => $at,
      'tracking_mode' => 'basic', 'device_type' => 'desktop', 'is_bot' => false,
    ], ...$attributes]);
  }

  private function report(array $overrides = []): array
  {
    return app(VisitorReportsQuery::class)->build([...[
      'from' => '2026-09-01', 'to' => '2026-09-03',
      'site' => 'all', 'locale' => 'all', 'traffic' => 'all',
    ], ...$overrides]);
  }

  #[Test]
  public function chart_zero_fills_days_and_compares_equal_previous_period_without_new_identifiers(): void
  {
    $this->event('2026-08-29 12:00:00');
    $this->event('2026-09-01 12:00:00');
    $this->event('2026-09-03 12:00:00');
    $report = $this->report();
    $this->assertSame([1, 0, 1], array_column($report['insights']['buckets'], 'views'));
    $this->assertSame('2026-08-29', $report['insights']['previous_from']);
    $this->assertSame('2026-08-31', $report['insights']['previous_to']);
    $this->assertSame(100.0, $report['insights']['change']);
    $this->assertSame(0.0, $report['insights']['coverage']);
    $this->assertSame('not_tracked', $report['metric_states']['unique_visitors']);
    $this->assertSame(0, VisitorEvent::query()->whereNotNull('session_key')->count());
    $this->assertSame(3, VisitorEvent::query()->count());
    $this->assertSame(3, app(VisitorReportsQuery::class)->dashboardSummary()['total_page_views']);
  }

  #[Test]
  public function empty_zero_baseline_and_large_date_ranges_are_honest_and_bounded(): void
  {
    $report = $this->report();
    $this->assertNull($report['insights']['change']);
    $this->assertNull($report['insights']['coverage']);
    $this->assertNull($report['insights']['last_record']);
    $this->assertCount(3, $report['insights']['buckets']);
    $report = $this->report(['from' => '2000-01-01', 'to' => '2026-09-04']);
    $this->assertLessThanOrEqual(90, count($report['insights']['buckets']));
    $this->assertTrue($report['insights']['includes_today']);
  }

  #[Test]
  public function summaries_charts_comparisons_health_and_page_details_respect_site_access(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(AuthenticatableUser::class, 'App\\Models\\User');
    }
    $user = Mockery::mock('App\\Models\\User');
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('accessibleSiteIds')->andReturn([$this->site->id]);
    $other = Site::query()->create(['name' => 'Other', 'handle' => 'other']);
    $this->event('2026-09-01 12:00:00');
    $this->event('2026-09-03 23:00:00', ['site_id' => $other->id, 'path' => '/private-other']);
    $this->event('2026-08-30 12:00:00', ['site_id' => $other->id]);
    DB::table('wbcms_visitor_daily_totals')->insert([
      'site_id' => $other->id, 'day' => '2026-09-02', 'locale_id' => 0, 'page_views' => 100, 'bot_page_views' => 0,
    ]);
    $report = $this->report(['user' => $user]);
    $this->assertSame(1, $report['summary']['total_page_views']);
    $this->assertSame(0, $report['insights']['previous_views']);
    $this->assertSame('2026-09-01 12:00:00', $report['insights']['last_record']);
    $this->assertSame(['/page'], $report['top_pages']->pluck('path')->all());
    $this->assertSame(0, $report['insights']['archived']['total_page_views']);
    $this->assertSame(0, $this->report(['user' => $user, 'site' => (string) $other->id])['summary']['total_page_views']);
  }

  #[Test]
  public function page_breakdowns_hide_the_entire_dimension_when_one_cell_is_small(): void
  {
    for ($i = 0; $i < 5; $i++) {
      $this->event('2026-09-01 12:00:00', ['referrer_type' => 'external', 'referrer_host' => 'example.org']);
    }
    $details = $this->report()['top_pages']->first()['details'];
    $this->assertSame([['label' => 'desktop', 'views' => 5]], $details['devices']);
    $this->assertSame([['label' => 'example.org', 'views' => 5]], $details['referrers']);
    $this->event('2026-09-01 12:00:00', ['device_type' => 'mobile', 'referrer_type' => 'external', 'referrer_host' => 'small.example']);
    $details = $this->report()['top_pages']->first()['details'];
    $this->assertSame([], $details['devices']);
    $this->assertSame([], $details['referrers']);
  }

  #[Test]
  public function cleanup_preserves_filtered_totals_but_removes_expired_identifiers_and_paths(): void
  {
    $locale = Locale::query()->create(['name' => 'English', 'code' => 'en']);
    $old = ['locale_id' => $locale->id, 'tracking_mode' => 'full', 'ip_hash' => 'old-hash', 'session_key' => 'old-session'];
    $this->event('2026-05-01 12:00:00', $old);
    $this->event('2026-05-01 14:00:00', [...$old, 'is_bot' => true]);
    $this->event('2026-09-01 12:00:00');
    $filters = ['from' => '2026-05-01', 'to' => '2026-05-01', 'locale' => (string) $locale->id];
    $before = $this->report($filters);
    $retention = app(VisitorReportRetention::class);
    $this->assertSame(2, $retention->run(true)['events']);
    $this->assertSame(3, VisitorEvent::query()->count());
    $this->assertSame(2, $retention->run()['events']);
    $this->assertSame(0, $retention->run()['events']);
    $this->assertSame(1, DB::table('wbcms_visitor_daily_totals')->count());
    $this->assertSame(0, VisitorEvent::query()->whereNotNull('ip_hash')->count());
    $after = $this->report($filters);
    $this->assertSame($before['summary']['total_page_views'], $after['summary']['total_page_views']);
    $this->assertSame($before['insights']['buckets'], $after['insights']['buckets']);
    $this->assertSame(2, $after['insights']['archived']['total_page_views']);
    $this->assertNull($after['summary']['unique_visitors']);
    $this->assertTrue($after['top_pages']->isEmpty());
    $this->assertSame(1, $this->report([...$filters, 'traffic' => 'human'])['summary']['total_page_views']);
    $this->assertSame(1, $this->report([...$filters, 'traffic' => 'bots'])['summary']['total_page_views']);
    $this->assertSame(['id', 'site_id', 'locale_id', 'day', 'page_views', 'bot_page_views'], Schema::getColumnListing('wbcms_visitor_daily_totals'));
    // A late record is merged once, never overwriting or double-counting the existing rollup.
    $this->event('2026-05-01 15:00:00', $old);
    $retention->run();
    $this->assertSame(3, $this->report($filters)['summary']['total_page_views']);
  }

  #[Test]
  public function retention_honors_boundaries_disabled_mode_and_total_expiry(): void
  {
    $cutoff = CarbonImmutable::today()->subDays(90);
    $this->event($cutoff->toDateTimeString());
    $this->event($cutoff->subSecond()->toDateTimeString());
    $this->event('2020-01-01 00:00:00');
    DB::table('wbcms_visitor_daily_totals')->insert([
      'site_id' => $this->site->id, 'day' => '2020-01-01', 'locale_id' => 0, 'page_views' => 20, 'bot_page_views' => 0,
    ]);
    config()->set('cms.visitor_reports.cleanup_enabled', false);
    $this->assertSame(0, app(VisitorReportRetention::class)->run()['events']);
    config()->set('cms.visitor_reports.cleanup_enabled', true);
    $result = app(VisitorReportRetention::class)->run();
    $this->assertSame(['events' => 2, 'totals' => 1], $result);
    $this->assertSame(1, VisitorEvent::query()->count());
    $this->assertSame(1, DB::table('wbcms_visitor_daily_totals')->count());
  }

  #[Test]
  public function failed_deletion_rolls_back_the_totals_and_lock_prevents_concurrent_cleanup(): void
  {
    $this->event('2026-05-01 12:00:00');
    DB::unprepared("CREATE TRIGGER prevent_visitor_delete BEFORE DELETE ON wbcms_visitor_events BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
    try {
      app(VisitorReportRetention::class)->run();
      $this->fail('Cleanup should have failed.');
    } catch (QueryException $exception) {
      $this->assertStringContainsString('test rollback', $exception->getMessage());
    }
    $this->assertSame(1, VisitorEvent::query()->count());
    $this->assertSame(0, DB::table('wbcms_visitor_daily_totals')->count());
    $lock = Cache::lock('cms:visitor-reports:retention', 60);
    $this->assertTrue($lock->get());
    try {
      $this->expectException(\RuntimeException::class);
      app(VisitorReportRetention::class)->run();
    } finally {
      $lock->release();
    }
  }

  #[Test]
  public function missing_summary_migration_keeps_reports_working_and_blocks_cleanup(): void
  {
    Schema::drop('wbcms_visitor_daily_totals');
    $this->event('2026-09-01 12:00:00');
    $this->assertSame(1, $this->report()['summary']['total_page_views']);
    $this->assertFalse($this->report()['insights']['retention']['ready']);
    $this->artisan('visitors:cleanup')->assertExitCode(1);
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_09_04_120000_create_visitor_daily_totals.php';
    $migration->up();
    $migration->up();
    $this->assertTrue(app(VisitorReportRetention::class)->policy()['ready']);
  }

  #[Test]
  public function undecided_and_declined_visits_still_have_no_identifiers(): void
  {
    $page = Page::query()->create(['site_id' => $this->site->id, 'title' => 'Page', 'slug' => 'page']);
    $page->setRelation('currentTranslation', null);
    foreach ([null, VisitorConsent::DECLINED] as $choice) {
      $request = Request::create('/page', 'GET', [], $choice ? ['webblocks_visitor_consent' => $choice] : [], [], ['REMOTE_ADDR' => '192.0.2.1', 'HTTP_USER_AGENT' => 'Test']);
      app(VisitorEventLogger::class)->logPageView($request, $page);
    }
    $this->assertSame(2, VisitorEvent::query()->where('tracking_mode', 'basic')->count());
    $this->assertSame(0, VisitorEvent::query()->whereNotNull('session_key')->orWhereNotNull('ip_hash')->count());
  }

  #[Test]
  public function empty_identifiers_do_not_inflate_coverage_in_current_or_legacy_schemas(): void
  {
    $this->event('2026-09-01 12:00:00', ['tracking_mode' => 'full', 'session_key' => '', 'ip_hash' => '']);
    $this->event('2026-09-01 13:00:00', ['tracking_mode' => 'full', 'session_key' => 'valid', 'ip_hash' => '']);
    $this->event('2026-09-01 14:00:00');
    $report = $this->report();
    $this->assertSame(33.3, $report['insights']['coverage']);
    $this->assertSame(1, $report['summary']['unique_visitors']);
    $this->assertSame(1, $report['summary']['total_sessions']);
    Schema::table('wbcms_visitor_events', fn ($table) => $table->dropColumn('tracking_mode'));
    $report = $this->report();
    $this->assertSame(33.3, $report['insights']['coverage']);
    $this->assertSame(1, $report['summary']['unique_visitors']);
  }

  #[Test]
  public function cleanup_discards_undated_and_deleted_site_records_and_site_deletion_cascades_totals(): void
  {
    $this->event('2026-05-01 12:00:00', ['site_id' => null]);
    $this->event('2026-05-01 12:00:00', ['visited_at' => null]);
    $this->event('2026-05-01 12:00:00');
    $retention = app(VisitorReportRetention::class);
    $this->assertSame(3, $retention->run(true)['events']);
    $this->assertSame(3, $retention->run()['events']);
    $this->assertSame(1, DB::table('wbcms_visitor_daily_totals')->sum('page_views'));
    $this->site->delete();
    $this->assertSame(0, DB::table('wbcms_visitor_daily_totals')->count());
  }

  #[Test]
  public function previous_period_and_dashboard_include_rollups_when_detail_retention_is_short(): void
  {
    config()->set('cms.visitor_reports.detail_retention_days', 1);
    $this->event('2026-08-29 12:00:00');
    $this->event('2026-09-01 12:00:00');
    app(VisitorReportRetention::class)->run();
    $this->assertSame(0, VisitorEvent::query()->count());
    $this->assertSame(1, $this->report()['insights']['previous_views']);
    $this->assertSame(0.0, $this->report()['insights']['change']);
    $this->assertSame(2, app(VisitorReportsQuery::class)->dashboardSummary()['total_page_views']);
  }

  #[Test]
  public function native_ui_chart_uses_the_modal_table_without_a_second_renderer(): void
  {
    $this->event('2026-09-01 12:00:00');
    $report = $this->report();
    $text = fn ($key, $replace = []) => app(CmsTranslator::class)->get('visitor_insights.'.$key, 'en', $replace);
    $html = view('webblocks-cms::admin.reports.visitors.insights', [
      'report' => $report, 'summary' => $report['summary'], 'insightText' => $text,
      'filters' => ['from' => '2026-09-01', 'to' => '2026-09-03'],
    ])->render();
    $this->assertStringContainsString('data-wb-chart="line"', $html);
    $this->assertStringContainsString('data-wb-chart-table="visitor-chart-data"', $html);
    $this->assertStringContainsString('id="visitor-chart-data"', $html);
    $this->assertStringContainsString('data-wb-chart-value="0"', $html);
    $this->assertStringContainsString('data-wb-chart-value="1"', $html);
    $this->assertStringNotContainsString('<svg', $html);
    $this->assertStringNotContainsString('<script', $html);
  }

  #[Test]
  public function insight_and_page_modal_views_render_translated_accessible_escaped_content(): void
  {
    $this->event('2026-09-01 12:00:00', ['path' => '/<script>']);
    $report = $this->report();
    $text = fn ($key, $replace = []) => app(CmsTranslator::class)->get('visitor_insights.'.$key, 'tr', $replace);
    $data = ['report' => $report, 'summary' => $report['summary'], 'insightText' => $text, 'filters' => ['from' => '2026-09-01', 'to' => '2026-09-03']];
    $html = view('webblocks-cms::admin.reports.visitors.insights', $data)->render();
    $this->assertStringContainsString('Ölçüm kapsamı', $html);
    $this->assertStringContainsString('aria-label="Zaman içinde sayfa görüntülemeleri"', $html);
    $this->assertStringContainsString('data-wb-chart="line"', $html);
    $this->assertStringNotContainsString('style=', $html);
    $html = view('webblocks-cms::admin.reports.visitors.page-details', $data)->render();
    $this->assertStringContainsString('wb-modal', $html);
    $this->assertStringContainsString('/&lt;script&gt;', $html);
    $this->assertStringNotContainsString('<script>', $html);
  }
}
