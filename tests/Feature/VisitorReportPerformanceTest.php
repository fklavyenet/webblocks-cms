<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\VisitorEvent;
use WebBlocks\Cms\Support\Visitors\VisitorReportsQuery;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class VisitorReportPerformanceTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function a_large_report_aggregates_events_without_hydrating_the_traffic_history(): void
  {
    $site = Site::query()->create(['name' => 'Synthetic studio', 'handle' => 'report-benchmark']);
    $locale = Locale::query()->create(['code' => 'en', 'name' => 'English']);
    for ($offset = 0; $offset < 20000; $offset += 500) {
      $rows = [];
      for ($i = $offset; $i < $offset + 500; $i++) {
        $rows[] = [
          'site_id' => $site->id, 'locale_id' => $locale->id,
          'path' => '/page-'.($i % 20), 'visited_at' => '2026-10-26 12:00:00',
          'tracking_mode' => $i % 5 === 0 ? 'full' : 'basic',
          'session_key' => $i % 5 === 0 ? 'session-'.intdiv($i, 20) : null,
          'ip_hash' => $i % 5 === 0 ? 'visitor-'.($i % 200) : null,
          'referrer' => $i % 2 === 0 ? 'https://www.partner.example/path-'.($i % 30) : 'search.example',
          'referrer_host' => $i % 2 === 0 ? null : 'search.example',
          'referrer_type' => $i % 2 === 0 ? null : 'external',
          'device_type' => $i % 3 === 0 ? 'mobile' : 'desktop',
          'is_bot' => false, 'utm_campaign' => 'Summer 2026',
          'utm_source' => $i % 97 === 0 ? '<svg/onload=probe>' : 'source-'.($i % 9),
          'utm_medium' => 'email',
        ];
      }
      DB::table('wbcms_visitor_events')->insert($rows);
    }
    $retrieved = 0;
    Event::listen('eloquent.retrieved: '.VisitorEvent::class, function () use (&$retrieved): void {
      $retrieved++;
    });
    DB::flushQueryLog();
    DB::enableQueryLog();
    $started = hrtime(true);
    try {
      $report = app(VisitorReportsQuery::class)->build([
        'from' => '2026-10-26', 'to' => '2026-10-26',
        'site' => 'all', 'locale' => 'all', 'traffic' => 'all',
      ]);
      $queries = DB::getQueryLog();
    } finally {
      DB::disableQueryLog();
    }
    $profile = [
      'events' => 20000, 'queries' => count($queries),
      'visitor_models_retrieved' => $retrieved,
      'schema_queries' => count(array_filter($queries, fn ($q) => str_contains($q['query'], 'pragma_') || str_contains($q['query'], 'sqlite_master'))),
      'sql_ms' => round(array_sum(array_column($queries, 'time')), 3),
      'report_ms' => round((hrtime(true) - $started) / 1e6, 3),
    ];
    if ($output = getenv('WEBBLOCKS_VISITOR_BENCHMARK_OUTPUT')) {
      file_put_contents($output, json_encode($profile, JSON_PRETTY_PRINT).PHP_EOL);
    }
    $this->assertSame(20000, $report['summary']['total_page_views']);
    $this->assertSame(1000, $report['summary']['total_sessions']);
    $this->assertSame(40, $report['summary']['unique_visitors']);
    $this->assertSame(['/page-0'], $report['top_entry_pages']->pluck('path')->all());
    $this->assertSame(1000, $report['top_entry_pages']->sole()['sessions']);
    $this->assertSame(['partner.example' => 10000, 'search.example' => 10000], $report['top_referrers']->pluck('visits', 'label')->sortKeys()->all());
    $this->assertSame(207, $report['campaign_quality']['source_breakdown']['flagged_page_views']);
    $this->assertCount(15, $report['top_pages']);
    $this->assertLessThan(150, $retrieved, json_encode($profile));
    $this->assertLessThan(100, count($queries), json_encode($profile));
  }

  #[Test]
  public function sql_session_entries_preserve_filtered_first_visit_and_id_tie_breaking(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(CmsSqlTestUser::class, 'App\\Models\\User');
    }
    $a = Site::query()->create(['name' => 'Studio A', 'handle' => 'studio-a']);
    $b = Site::query()->create(['name' => 'Studio B', 'handle' => 'studio-b']);
    $en = Locale::query()->create(['code' => 'en', 'name' => 'English']);
    $tr = Locale::query()->create(['code' => 'tr', 'name' => 'Turkish']);
    $class = 'App\\Models\\User';
    $user = $this->cmsActor((new $class)->forceFill(['role' => 'site_admin', 'is_active' => true]), [$a->id]);
    for ($i = 0; $i < 12; $i++) {
      $base = ['site_id' => $a->id, 'locale_id' => $i % 2 ? $en->id : $tr->id, 'session_key' => 'session-'.$i, 'ip_hash' => 'visitor-'.$i, 'tracking_mode' => 'full', 'is_bot' => $i % 3 === 0];
      foreach ([
        ['path' => '/outside-period', 'visited_at' => '2026-10-25 12:00:00'],
        ['path' => '/basic', 'visited_at' => '2026-10-26 08:00:00', 'tracking_mode' => 'basic'],
        ['path' => '/first', 'visited_at' => '2026-10-26 09:00:00', 'site_id' => $b->id],
        ['path' => '/tie-winner', 'visited_at' => '2026-10-26 10:00:00'],
        ['path' => '/tie-loser', 'visited_at' => '2026-10-26 10:00:00'],
        ['path' => '/earlier-in-time', 'visited_at' => '2026-10-26 08:30:00', 'is_bot' => true],
      ] as $attributes) {
        VisitorEvent::query()->create([...$base, ...$attributes]);
      }
    }
    foreach ([
      [], ['traffic' => 'human'], ['traffic' => 'bots'],
      ['locale' => (string) $en->id], ['site' => (string) $a->id],
      ['user' => $user], ['user' => $user, 'traffic' => 'human', 'locale' => (string) $tr->id],
    ] as $overrides) {
      $filters = ['from' => '2026-10-26', 'to' => '2026-10-26', 'site' => 'all', 'locale' => 'all', 'traffic' => 'all', ...$overrides];
      $expected = VisitorEvent::query()->with(['site', 'locale'])
        ->whereBetween('visited_at', ['2026-10-26 00:00:00', '2026-10-26 23:59:59'])
        ->where('tracking_mode', 'full')->whereNotNull('session_key')->where('session_key', '!=', '')
        ->when(isset($filters['user']), fn ($q) => $q->where('site_id', $a->id))
        ->when($filters['site'] !== 'all', fn ($q) => $q->where('site_id', (int) $filters['site']))
        ->when($filters['locale'] !== 'all', fn ($q) => $q->where('locale_id', (int) $filters['locale']))
        ->when($filters['traffic'] !== 'all', fn ($q) => $q->where('is_bot', $filters['traffic'] === 'bots'))
        ->orderBy('session_key')->orderBy('visited_at')->orderBy('id')->get()->unique('session_key')
        ->groupBy(fn ($e) => $e->site->name.'|'.$e->locale->code.'|'.$e->path)
        ->map(fn ($rows) => $rows->count())->sortKeys()->all();
      $actual = app(VisitorReportsQuery::class)->build($filters)['top_entry_pages']
        ->mapWithKeys(fn ($row) => [$row['site_name'].'|'.$row['locale_code'].'|'.$row['path'] => $row['sessions']])->sortKeys()->all();
      $this->assertSame($expected, $actual);
    }
  }

  #[Test]
  public function batched_page_details_keep_site_locale_null_scope_and_small_cells_separate(): void
  {
    $a = Site::query()->create(['name' => 'Studio A', 'handle' => 'studio-a']);
    $b = Site::query()->create(['name' => 'Studio B', 'handle' => 'studio-b']);
    $en = Locale::query()->create(['code' => 'en', 'name' => 'English']);
    $tr = Locale::query()->create(['code' => 'tr', 'name' => 'Turkish']);
    foreach ([
      [$a->id, $en->id, 'desktop'], [$a->id, $tr->id, 'mobile'],
      [$a->id, null, 'bot'], [$b->id, $en->id, 'tablet'],
    ] as [$site, $locale, $device]) {
      for ($i = 0; $i < 5; $i++) {
        VisitorEvent::query()->create(['site_id' => $site, 'locale_id' => $locale, 'path' => '/same',
          'device_type' => $device, 'referrer_type' => 'external', 'referrer_host' => 'partner.example',
          'tracking_mode' => 'basic', 'visited_at' => '2026-10-26 12:00:00']);
      }
    }
    // More than ten large groups must not hide a smaller privacy cell.
    for ($i = 0; $i < 12; $i++) {
      for ($repeat = 0; $repeat < ($i === 11 ? 1 : 5); $repeat++) {
        VisitorEvent::query()->create(['site_id' => $b->id, 'locale_id' => $en->id, 'path' => '/many-referrers',
          'device_type' => 'desktop', 'referrer_type' => 'external', 'referrer_host' => 'source-'.$i.'.example',
          'tracking_mode' => 'basic', 'visited_at' => '2026-10-26 12:00:00']);
      }
    }
    $rows = app(VisitorReportsQuery::class)->build(['from' => '2026-10-26', 'to' => '2026-10-26', 'site' => 'all', 'locale' => 'all', 'traffic' => 'all'])['top_pages'];
    $this->assertSame([], $rows->firstWhere('path', '/many-referrers')['details']['referrers']);
    $same = $rows->where('path', '/same')->mapWithKeys(fn ($row) => [$row['site_name'].'|'.$row['locale_code'] => $row['details']]);
    foreach (['Studio A|en' => 'desktop', 'Studio A|tr' => 'mobile', 'Studio A|default' => 'bot', 'Studio B|en' => 'tablet'] as $key => $device) {
      $this->assertSame([['label' => $device, 'views' => 5]], $same[$key]['devices']);
      $this->assertSame([['label' => 'partner.example', 'views' => 5]], $same[$key]['referrers']);
    }
  }
}
