<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\VisitorEvent;
use WebBlocks\Cms\Support\Visitors\VisitorReportsQuery;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class VisitorCampaignSafetyTest extends TestCase
{
  private Site $site;

  private const PROBE = '</script><svg/onload=confirm("cms-campaign-probe")>';

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('webblocks-cms.routes.public', true);
    $app['config']->set('app.url', 'http://example.test');
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists('App\\Models\\User')) {
      class_alias(CmsSqlTestUser::class, 'App\\Models\\User');
    }
    $this->site = Site::query()->create(['name' => 'Synthetic studio', 'handle' => 'synthetic', 'domain' => 'example.test', 'is_primary' => true]);
  }

  private function event(array $attributes = []): VisitorEvent
  {
    return VisitorEvent::query()->create([
      'site_id' => $this->site->id, 'path' => '/page', 'visited_at' => '2026-10-26 12:00:00',
      'tracking_mode' => 'basic', 'is_bot' => false, ...$attributes,
    ]);
  }

  private function report(array $overrides = []): array
  {
    return app(VisitorReportsQuery::class)->build([
      'from' => '2026-10-26', 'to' => '2026-10-26', 'site' => 'all', 'locale' => 'all', 'traffic' => 'all', ...$overrides,
    ]);
  }

  #[Test]
  public function public_requests_keep_the_view_and_normalized_evidence_without_treating_markup_as_a_campaign(): void
  {
    $locale = Locale::query()->create(['name' => 'English', 'code' => 'en', 'is_enabled' => true, 'is_default' => true]);
    $this->site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $page = Page::query()->create(['site_id' => $this->site->id, 'status' => Page::STATUS_PUBLISHED]);
    $page->translations()->create(['locale_id' => $locale->id, 'name' => 'Synthetic page', 'slug' => 'page', 'path' => '/page']);
    $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));
    $query = ['utm_campaign' => self::PROBE, 'utm_source' => rawurlencode(self::PROBE), 'utm_medium' => "Email\n  & Partners"];
    $this->get('http://example.test/page?'.http_build_query($query))->assertOk();
    $event = VisitorEvent::query()->sole();
    $this->assertSame(self::PROBE, $event->utm_campaign);
    $this->assertSame($query['utm_source'], $event->utm_source);
    $this->assertSame('Email & Partners', $event->utm_medium);
    $report = $this->report();
    $this->assertSame(1, $report['summary']['total_page_views']);
    $this->assertTrue($report['top_campaigns']->isEmpty());
    $this->assertTrue($report['source_breakdown']->isEmpty());
    $this->assertSame('Email & Partners', $report['medium_breakdown']->sole()['label']);
    $this->assertSame(self::PROBE, $report['campaign_quality']['top_campaigns']['flagged_rows']->sole()['label']);
    $this->assertNull($report['campaign_quality']['top_campaigns']['flagged_rows']->sole()['unique_visitors']);
    $this->assertSame('not_tracked', $report['campaign_quality']['top_campaigns']['flagged_rows']->sole()['tracking_state']);
  }

  #[Test]
  public function classification_precedes_limits_preserves_totals_and_does_not_sum_distinct_identifiers(): void
  {
    for ($i = 0; $i < 12; $i++) {
      foreach (['Normal '.$i, '<svg/onload=probe'.$i.'>'] as $value) {
        for ($repeat = 0; $repeat < (str_starts_with($value, '<') ? 2 : 1); $repeat++) {
          $this->event([
            'utm_campaign' => $value, 'utm_source' => $value, 'utm_medium' => $value,
            'tracking_mode' => 'full', 'ip_hash' => 'same-visitor', 'session_key' => 'same-session',
          ]);
        }
      }
    }
    $before = DB::table('wbcms_visitor_events')->orderBy('id')->get()->toJson();
    $report = $this->report();
    $this->assertSame(36, $report['summary']['total_page_views']);
    $this->assertSame(1, $report['summary']['unique_visitors']);
    $this->assertSame(1, $report['summary']['total_sessions']);
    $this->assertSame([36], array_column($report['insights']['buckets'], 'views'));
    foreach (['top_campaigns', 'source_breakdown', 'medium_breakdown'] as $key) {
      $this->assertCount(10, $report[$key]);
      $this->assertCount(10, $report['campaign_quality'][$key]['flagged_rows']);
      $this->assertSame(24, $report['campaign_quality'][$key]['flagged_page_views']);
      foreach ($report[$key] as $row) {
        $this->assertStringStartsWith('Normal ', $row['label']);
        $this->assertSame(1, $row['unique_visitors']);
        $this->assertSame(1, $row['sessions']);
      }
      foreach ($report['campaign_quality'][$key]['flagged_rows'] as $row) {
        $this->assertSame(2, $row['page_views']);
        $this->assertSame(1, $row['unique_visitors']);
        $this->assertSame(1, $row['sessions']);
      }
    }
    $this->assertSame($before, DB::table('wbcms_visitor_events')->orderBy('id')->get()->toJson());
  }

  #[Test]
  public function quality_counts_obey_dates_sites_languages_traffic_and_operator_scope(): void
  {
    $locale = Locale::query()->create(['name' => 'English', 'code' => 'en']);
    $other = Site::query()->create(['name' => 'Other synthetic site', 'handle' => 'other']);
    $attributes = ['utm_campaign' => self::PROBE, 'utm_source' => self::PROBE, 'utm_medium' => self::PROBE];
    $this->event([...$attributes, 'locale_id' => $locale->id]);
    $this->event([...$attributes, 'site_id' => $other->id, 'locale_id' => $locale->id]);
    $this->event([...$attributes, 'visited_at' => '2026-10-25 12:00:00']);
    $this->event([...$attributes, 'is_bot' => true]);
    $this->event($attributes);
    $class = 'App\\Models\\User';
    $user = $this->cmsActor((new $class)->forceFill(['role' => 'site_admin', 'is_active' => true]), [$this->site->id]);
    $report = $this->report(['user' => $user, 'locale' => (string) $locale->id, 'traffic' => 'human']);
    foreach ($report['campaign_quality'] as $quality) {
      $this->assertSame(1, $quality['flagged_page_views']);
      $this->assertSame(1, $quality['flagged_rows']->sole()['page_views']);
    }
    $this->assertSame(0, $this->report(['user' => $user, 'site' => (string) $other->id])['campaign_quality']['top_campaigns']['flagged_page_views']);
    $this->assertSame(1, $this->report(['user' => $user, 'traffic' => 'bots'])['campaign_quality']['top_campaigns']['flagged_page_views']);
  }

  #[Test]
  public function the_real_report_escapes_legacy_and_new_labels_in_normal_tables_evidence_and_page_modals(): void
  {
    $normal = 'Spring "Partners" & We <3 customers';
    for ($i = 0; $i < 5; $i++) {
      $this->event(['utm_campaign' => self::PROBE, 'utm_source' => self::PROBE, 'utm_medium' => self::PROBE, 'path' => '/'.self::PROBE, 'referrer_host' => self::PROBE, 'referrer_type' => 'external']);
    }
    $this->event(['utm_campaign' => $normal, 'utm_source' => $normal, 'utm_medium' => $normal]);
    $this->event();
    $class = 'App\\Models\\User';
    $user = $this->cmsActor((new $class)->forceFill(['role' => 'super_admin', 'is_active' => true]));
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $response = $this->actingAs($user)->get('/webadmin/reports/visitors?date_range=custom&from=2026-10-26&to=2026-10-26');
    $response->assertOk()->assertSee('UTM values to review')->assertSee($normal)->assertSee(self::PROBE);
    $html = $response->getContent();
    $this->assertStringNotContainsString(self::PROBE, $html);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $this->assertSame(0, $xpath->query('//svg[@onload] | //script[contains(., "cms-campaign-probe")] | //*[@onload or @onerror]')->length);
    $this->assertSame(0, $xpath->query('//@*[contains(., "cms-campaign-probe")]')->length);
    $quality = $xpath->query('//*[@id="visitor-reports-acquisition-panel"]//section[@aria-labelledby="visitor-campaign-quality-title"]')->item(0);
    $this->assertNotNull($quality);
    $this->assertSame(3, $xpath->query('.//code', $quality)->length);
    foreach ($xpath->query('.//code', $quality) as $code) {
      $this->assertSame(self::PROBE, $code->textContent);
    }
    $this->assertStringNotContainsString('cms-campaign-probe', $xpath->query('//*[@id="visitor-chart-data"]')->item(0)->textContent);
    $this->assertSame(7, VisitorEvent::query()->count());

    // Escaping must also hold when classification misses a value or a report
    // caller supplies an unclassified historical row to any acquisition table.
    $data = $response->original->getData();
    foreach (['top_campaigns', 'source_breakdown', 'medium_breakdown'] as $key) {
      $data['report'][$key] = $data['report']['campaign_quality'][$key]['flagged_rows'];
      $data['report']['campaign_quality'][$key]['flagged_page_views'] = 0;
    }
    $html = view('webblocks-cms::admin.reports.visitors.index', $data)->render();
    $this->assertStringNotContainsString(self::PROBE, $html);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $this->assertSame(0, $xpath->query('//svg[@onload] | //script[contains(., "cms-campaign-probe")] | //*[@onload or @onerror]')->length);
    $this->assertSame(3, $xpath->query('//*[@id="visitor-reports-acquisition-panel"]//td[contains(., "cms-campaign-probe")]')->length);
  }

  #[Test]
  public function absent_utm_schema_returns_empty_quality_and_plain_values_remain_unflagged(): void
  {
    $this->event(['utm_campaign' => 'Normal', 'utm_source' => 'chatgpt.com']);
    $this->event();
    $report = $this->report();
    $this->assertSame(['Direct / Unknown', 'Normal'], $report['top_campaigns']->pluck('label')->all());
    foreach ($report['campaign_quality'] as $quality) {
      $this->assertSame(0, $quality['flagged_page_views']);
      $this->assertTrue($quality['flagged_rows']->isEmpty());
    }
    $evidence = '  '.self::PROBE.'  ';
    $this->event(['utm_campaign' => $evidence]);
    $this->assertSame($evidence, $this->report()['campaign_quality']['top_campaigns']['flagged_rows']->sole()['label']);
    Schema::table('wbcms_visitor_events', fn ($table) => $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign']));
    $report = $this->report();
    $this->assertSame(3, $report['summary']['total_page_views']);
    $this->assertTrue($report['top_campaigns']->isEmpty());
    foreach ($report['campaign_quality'] as $quality) {
      $this->assertSame(0, $quality['flagged_page_views']);
    }
  }
}
