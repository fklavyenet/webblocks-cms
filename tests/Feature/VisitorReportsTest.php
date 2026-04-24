<?php

namespace Tests\Feature;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\User;
use App\Models\VisitorEvent;
use App\Support\Visitors\VisitorConsent;
use App\Support\Visitors\VisitorReportsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisitorReportsTest extends TestCase
{
    use RefreshDatabase;

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function createPublishedPage(?Site $site = null, string $title = 'About', string $slug = 'about'): Page
    {
        return Page::query()->create([
            'site_id' => $site?->id ?? $this->defaultSite()->id,
            'title' => $title,
            'slug' => $slug,
            'status' => 'published',
        ]);
    }

    private function todayFilters(): array
    {
        $request = Request::create(route('admin.reports.visitors.index', ['date_range' => 'today'], false), 'GET', [
            'date_range' => 'today',
        ]);

        return app(VisitorReportsQuery::class)->filters($request);
    }

    private function consentCookieName(): string
    {
        return app(VisitorConsent::class)->cookieName();
    }

    private function dropTrackingModeColumnForLegacySchema(): void
    {
        Schema::table('visitor_events', function (Blueprint $table) {
            $table->dropIndex(['tracking_mode', 'visited_at']);
        });

        Schema::table('visitor_events', function (Blueprint $table) {
            $table->dropColumn('tracking_mode');
        });
    }

    #[Test]
    public function public_page_with_no_consent_cookie_creates_a_basic_tracking_row_only(): void
    {
        $page = $this->createPublishedPage();

        $this->withHeader('referer', 'https://example.test/campaign')
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit Safari')
            ->get(route('pages.show', ['slug' => $page->slug, 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring'], false))
            ->assertOk();

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertSame($page->site_id, $event->site_id);
        $this->assertSame($page->id, $event->page_id);
        $this->assertSame($this->defaultLocale()->id, $event->locale_id);
        $this->assertSame('/p/about', $event->path);
        $this->assertSame(VisitorEvent::TRACKING_MODE_BASIC, $event->tracking_mode);
        $this->assertNull($event->referrer);
        $this->assertNull($event->utm_source);
        $this->assertNull($event->utm_medium);
        $this->assertNull($event->utm_campaign);
        $this->assertNull($event->device_type);
        $this->assertNull($event->browser_family);
        $this->assertNull($event->os_family);
        $this->assertNull($event->session_key);
        $this->assertNull($event->ip_hash);
    }

    #[Test]
    public function public_page_with_declined_consent_creates_a_basic_tracking_row_only(): void
    {
        $page = $this->createPublishedPage();

        $this->withCookie($this->consentCookieName(), VisitorConsent::DECLINED)
            ->withHeader('referer', 'https://example.test/campaign')
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit Chrome')
            ->get(route('pages.show', ['slug' => $page->slug, 'utm_source' => 'newsletter'], false))
            ->assertOk();

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertSame(VisitorEvent::TRACKING_MODE_BASIC, $event->tracking_mode);
        $this->assertNull($event->session_key);
        $this->assertNull($event->ip_hash);
        $this->assertNull($event->referrer);
        $this->assertNull($event->utm_source);
        $this->assertNull($event->device_type);
        $this->assertNull($event->browser_family);
        $this->assertNull($event->os_family);
    }

    #[Test]
    public function public_page_with_accepted_consent_creates_a_full_tracking_row_with_current_rich_fields(): void
    {
        $page = $this->createPublishedPage();

        $this->withCookie($this->consentCookieName(), VisitorConsent::ACCEPTED)
            ->withHeader('referer', 'https://example.test/campaign')
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit Safari')
            ->get(route('pages.show', ['slug' => $page->slug, 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring'], false))
            ->assertOk();

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertSame(VisitorEvent::TRACKING_MODE_FULL, $event->tracking_mode);
        $this->assertSame('https://example.test/campaign', $event->referrer);
        $this->assertSame('newsletter', $event->utm_source);
        $this->assertSame('email', $event->utm_medium);
        $this->assertSame('spring', $event->utm_campaign);
        $this->assertSame('mobile', $event->device_type);
        $this->assertSame('Safari', $event->browser_family);
        $this->assertSame('iOS', $event->os_family);
        $this->assertNotEmpty($event->session_key);
        $this->assertNotEmpty($event->ip_hash);
        $this->assertNotSame('127.0.0.1', $event->ip_hash);
    }

    #[Test]
    public function accepted_consent_preserves_utm_sanitization_behavior(): void
    {
        $page = $this->createPublishedPage();

        $this->withCookie($this->consentCookieName(), VisitorConsent::ACCEPTED)
            ->get(route('pages.show', [
                'slug' => $page->slug,
                'utm_source' => "  Newsletter\nLaunch  ",
                'utm_medium' => '   ',
                'utm_campaign' => str_repeat('A', 300),
            ], false))->assertOk();

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertSame('Newsletter Launch', $event->utm_source);
        $this->assertNull($event->utm_medium);
        $this->assertSame(str_repeat('A', 255), $event->utm_campaign);
    }

    #[Test]
    public function obvious_bot_requests_are_not_tracked(): void
    {
        $page = $this->createPublishedPage();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->get(route('pages.show', $page->slug, false))
            ->assertOk();

        $this->assertDatabaseCount('visitor_events', 0);
    }

    #[Test]
    public function admin_visitor_reports_screen_opens_for_authenticated_users(): void
    {
        $user = User::factory()->editor()->create();

        $response = $this->actingAs($user)->get(route('admin.reports.visitors.index'));

        $response->assertOk();
        $response->assertSee('Visitor Reports');
        $response->assertSee('Total page views');
        $response->assertSee('Top Pages');
        $response->assertSee('Page views include privacy-safe anonymous views. Unique visitors, sessions, referrers, campaigns, and device summaries require analytics consent.');
    }

    #[Test]
    public function admin_visitor_reports_screen_handles_missing_table_gracefully(): void
    {
        $user = User::factory()->editor()->create();

        Schema::dropIfExists('visitor_events');

        $response = $this->actingAs($user)->get(route('admin.reports.visitors.index'));

        $response->assertOk();
        $response->assertSee('Visitor reports migration is missing');
        $response->assertSee('php artisan migrate');
    }

    #[Test]
    public function admin_visitor_reports_screen_handles_legacy_schema_without_tracking_mode(): void
    {
        $user = User::factory()->editor()->create();

        $this->dropTrackingModeColumnForLegacySchema();

        VisitorEvent::query()->create([
            'site_id' => $this->defaultSite()->id,
            'path' => '/p/about',
            'session_key' => 'legacy-session',
            'ip_hash' => 'legacy-hash',
            'visited_at' => CarbonImmutable::today()->setTime(10, 0),
        ]);

        $response = $this->actingAs($user)->get(route('admin.reports.visitors.index'));

        $response->assertOk();
        $response->assertSee('Visitor Reports');
        $response->assertSee('1');
    }

    #[Test]
    public function public_tracking_falls_back_to_legacy_full_row_when_tracking_mode_column_is_missing(): void
    {
        $page = $this->createPublishedPage();

        $this->dropTrackingModeColumnForLegacySchema();

        $this->get(route('pages.show', $page->slug, false))->assertOk();

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertNotEmpty($event->session_key);
        $this->assertNotEmpty($event->ip_hash);
    }

    #[Test]
    public function reports_respect_site_and_date_range_filters(): void
    {
        $user = User::factory()->editor()->create();
        $primarySite = $this->defaultSite();
        $campaignSite = Site::query()->create([
            'name' => 'Campaign',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);

        $defaultLocale = $this->defaultLocale();
        $campaignSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);
        $user->sites()->sync([$primarySite->id, $campaignSite->id]);

        $primaryPage = $this->createPublishedPage($primarySite, 'Primary About', 'primary-about');
        $campaignPage = $this->createPublishedPage($campaignSite, 'Campaign Launch', 'campaign-launch');

        VisitorEvent::query()->create([
            'site_id' => $primarySite->id,
            'page_id' => $primaryPage->id,
            'locale_id' => $defaultLocale->id,
            'path' => '/p/primary-about',
            'session_key' => 'primary-session',
            'ip_hash' => 'primary-hash',
            'visited_at' => CarbonImmutable::today()->subDays(15)->setTime(10, 0),
        ]);

        VisitorEvent::query()->create([
            'site_id' => $campaignSite->id,
            'page_id' => $campaignPage->id,
            'locale_id' => $defaultLocale->id,
            'path' => '/p/campaign-launch',
            'session_key' => 'campaign-session',
            'ip_hash' => 'campaign-hash',
            'visited_at' => CarbonImmutable::today()->setTime(9, 30),
        ]);

        $response = $this->actingAs($user)->get(route('admin.reports.visitors.index', [
            'site' => $campaignSite->id,
            'date_range' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('/p/campaign-launch');
        $response->assertDontSee('/p/primary-about');
        $response->assertSee('Campaign');
        $response->assertSee('1');
    }

    #[Test]
    public function campaign_source_and_medium_reports_respect_filters(): void
    {
        $user = User::factory()->editor()->create();
        $primarySite = $this->defaultSite();
        $campaignSite = Site::query()->create([
            'name' => 'Campaign Site',
            'handle' => 'campaign-site',
            'domain' => 'campaign-site.example.test',
            'is_primary' => false,
        ]);

        $defaultLocale = $this->defaultLocale();
        $trLocale = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $campaignSite->locales()->syncWithoutDetaching([
            $defaultLocale->id => ['is_enabled' => true],
            $trLocale->id => ['is_enabled' => true],
        ]);
        $user->sites()->sync([$primarySite->id, $campaignSite->id]);

        $primaryPage = $this->createPublishedPage($primarySite, 'Primary About', 'primary-about');
        $campaignPage = $this->createPublishedPage($campaignSite, 'Campaign Launch', 'campaign-launch');

        VisitorEvent::query()->create([
            'site_id' => $campaignSite->id,
            'page_id' => $campaignPage->id,
            'locale_id' => $defaultLocale->id,
            'path' => '/p/campaign-launch',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring-launch',
            'session_key' => 'session-1',
            'ip_hash' => 'hash-1',
            'visited_at' => CarbonImmutable::today()->setTime(9, 0),
        ]);

        VisitorEvent::query()->create([
            'site_id' => $campaignSite->id,
            'page_id' => $campaignPage->id,
            'locale_id' => $defaultLocale->id,
            'path' => '/p/campaign-launch',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'spring-launch',
            'session_key' => 'session-2',
            'ip_hash' => 'hash-2',
            'visited_at' => CarbonImmutable::today()->setTime(10, 0),
        ]);

        VisitorEvent::query()->create([
            'site_id' => $campaignSite->id,
            'page_id' => $campaignPage->id,
            'locale_id' => $trLocale->id,
            'path' => '/tr/p/campaign-launch',
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'session_key' => 'session-3',
            'ip_hash' => 'hash-3',
            'visited_at' => CarbonImmutable::today()->setTime(11, 0),
        ]);

        VisitorEvent::query()->create([
            'site_id' => $primarySite->id,
            'page_id' => $primaryPage->id,
            'locale_id' => $defaultLocale->id,
            'path' => '/p/primary-about',
            'utm_source' => 'ads',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'other-campaign',
            'session_key' => 'session-4',
            'ip_hash' => 'hash-4',
            'visited_at' => CarbonImmutable::today()->setTime(12, 0),
        ]);

        $response = $this->actingAs($user)->get(route('admin.reports.visitors.index', [
            'site' => $campaignSite->id,
            'locale' => $defaultLocale->id,
            'date_range' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Top Campaigns');
        $response->assertSee('Source Breakdown');
        $response->assertSee('Medium Breakdown');
        $response->assertSee('spring-launch');
        $response->assertSee('newsletter');
        $response->assertSee('email');
        $response->assertDontSee('other-campaign');
        $response->assertDontSee('Direct / None');
    }

    #[Test]
    public function reports_group_null_utm_values_without_breaking(): void
    {
        $user = User::factory()->editor()->create();
        $site = $this->defaultSite();
        $page = $this->createPublishedPage($site, 'Landing', 'landing');

        VisitorEvent::query()->create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'locale_id' => $this->defaultLocale()->id,
            'path' => '/p/landing',
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'session_key' => 'direct-session',
            'ip_hash' => 'direct-hash',
            'visited_at' => CarbonImmutable::today()->setTime(8, 0),
        ]);

        $response = $this->actingAs($user)->get(route('admin.reports.visitors.index', [
            'date_range' => 'today',
        ]));

        $response->assertOk();
        $response->assertSee('Direct / None');
    }

    #[Test]
    public function accept_route_sets_the_consent_cookie_and_redirects_back(): void
    {
        $page = $this->createPublishedPage();
        $target = route('pages.show', $page->slug, false);

        $response = $this->post(route('public.privacy-consent.accept'), [
            'redirect_to' => $target,
        ]);

        $response->assertRedirect($target);
        $response->assertCookie($this->consentCookieName(), VisitorConsent::ACCEPTED);
    }

    #[Test]
    public function decline_route_sets_the_consent_cookie_and_redirects_back(): void
    {
        $page = $this->createPublishedPage();
        $target = route('pages.show', $page->slug, false);

        $response = $this->post(route('public.privacy-consent.decline'), [
            'redirect_to' => $target,
        ]);

        $response->assertRedirect($target);
        $response->assertCookie($this->consentCookieName(), VisitorConsent::DECLINED);
    }

    #[Test]
    public function consent_routes_do_not_require_login(): void
    {
        $response = $this->post(route('public.privacy-consent.accept'), [
            'redirect_to' => '/p/example',
        ]);

        $response->assertRedirect('/p/example');
    }

    #[Test]
    public function public_page_renders_a_cookie_settings_footer_link(): void
    {
        $page = $this->createPublishedPage();

        $response = $this->get(route('pages.show', $page->slug, false));

        $response->assertSee('wb-footer-cookie-settings-link', false);
        $response->assertSee('Cookie settings');
    }

    #[Test]
    public function with_no_consent_cookie_the_cookie_panel_is_visible_by_default(): void
    {
        $page = $this->createPublishedPage();

        $response = $this->get(route('pages.show', $page->slug, false));

        $response->assertSee('Cookie settings');
        $response->assertSee('wb-cookie-settings-shell is-open', false);
        $response->assertSee('id="wb-cookie-settings-panel"', false);
        $response->assertSee('Necessary:');
        $response->assertSee('Analytics:');
        $response->assertSee('Accept');
        $response->assertSee('Decline');
    }

    #[Test]
    public function with_accepted_consent_cookie_the_panel_is_closed_by_default(): void
    {
        $page = $this->createPublishedPage();

        $response = $this->withCookie($this->consentCookieName(), VisitorConsent::ACCEPTED)
            ->get(route('pages.show', $page->slug, false));

        $response->assertSee('Cookie settings');
        $response->assertDontSee('wb-cookie-settings-shell is-open', false);
        $response->assertSee('id="wb-cookie-settings-panel"', false);
        $response->assertSee('hidden', false);
    }

    #[Test]
    public function with_declined_consent_cookie_the_panel_is_closed_by_default(): void
    {
        $page = $this->createPublishedPage();

        $this->withCookie($this->consentCookieName(), VisitorConsent::DECLINED)
            ->get(route('pages.show', $page->slug, false))
            ->assertOk()
            ->assertSee('Cookie settings')
            ->assertDontSee('wb-cookie-settings-shell is-open', false);
    }

    #[Test]
    public function footer_cookie_settings_control_can_target_and_reopen_the_panel(): void
    {
        $page = $this->createPublishedPage();

        $this->withCookie($this->consentCookieName(), VisitorConsent::ACCEPTED)
            ->get(route('pages.show', $page->slug, false))
            ->assertOk()
            ->assertSee('wb-footer-cookie-settings-link', false)
            ->assertSee('data-wb-cookie-settings-open', false)
            ->assertSee('data-wb-target="#wb-cookie-settings-panel', false)
            ->assertSee('aria-controls="wb-cookie-settings-panel"', false)
            ->assertSee('aria-expanded="false"', false);
    }

    #[Test]
    public function close_x_control_exists_and_does_not_submit_accept_or_decline(): void
    {
        $page = $this->createPublishedPage();

        $this->get(route('pages.show', $page->slug, false))
            ->assertOk()
            ->assertSee('wb-cookie-settings-header', false)
            ->assertSee('wb-cookie-settings-close', false)
            ->assertSee('data-wb-cookie-settings-close', false)
            ->assertSee('aria-label="Close cookie settings"', false)
            ->assertDontSee('action="'.route('public.privacy-consent.accept').'" data-wb-cookie-settings-close', false)
            ->assertDontSee('action="'.route('public.privacy-consent.decline').'" data-wb-cookie-settings-close', false);
    }

    #[Test]
    public function admin_report_page_views_include_basic_and_full_rows(): void
    {
        $page = $this->createPublishedPage();

        VisitorEvent::query()->create([
            'site_id' => $page->site_id,
            'page_id' => $page->id,
            'locale_id' => $this->defaultLocale()->id,
            'path' => '/p/about',
            'tracking_mode' => VisitorEvent::TRACKING_MODE_BASIC,
            'visited_at' => CarbonImmutable::today()->setTime(9, 0),
        ]);

        VisitorEvent::query()->create([
            'site_id' => $page->site_id,
            'page_id' => $page->id,
            'locale_id' => $this->defaultLocale()->id,
            'path' => '/p/about',
            'tracking_mode' => VisitorEvent::TRACKING_MODE_FULL,
            'session_key' => 'session-1',
            'ip_hash' => 'hash-1',
            'visited_at' => CarbonImmutable::today()->setTime(10, 0),
        ]);

        $report = app(VisitorReportsQuery::class)->build($this->todayFilters());

        $this->assertSame(2, $report['summary']['total_page_views']);
        $this->assertSame(2, $report['top_pages']->first()['page_views']);
    }

    #[Test]
    public function admin_unique_visitors_and_sessions_only_count_full_rows(): void
    {
        $page = $this->createPublishedPage();

        VisitorEvent::query()->create([
            'site_id' => $page->site_id,
            'page_id' => $page->id,
            'locale_id' => $this->defaultLocale()->id,
            'path' => '/p/about',
            'tracking_mode' => VisitorEvent::TRACKING_MODE_BASIC,
            'visited_at' => CarbonImmutable::today()->setTime(9, 0),
        ]);

        VisitorEvent::query()->create([
            'site_id' => $page->site_id,
            'page_id' => $page->id,
            'locale_id' => $this->defaultLocale()->id,
            'path' => '/p/about',
            'tracking_mode' => VisitorEvent::TRACKING_MODE_FULL,
            'session_key' => 'session-1',
            'ip_hash' => 'hash-1',
            'visited_at' => CarbonImmutable::today()->setTime(10, 0),
        ]);

        $report = app(VisitorReportsQuery::class)->build($this->todayFilters());

        $this->assertSame(1, $report['summary']['unique_visitors']);
        $this->assertSame(1, $report['summary']['total_sessions']);
        $this->assertSame(1, $report['top_pages']->first()['unique_visitors']);
    }

    #[Test]
    public function campaign_source_and_medium_summaries_do_not_include_basic_rows_as_direct_none(): void
    {
        $page = $this->createPublishedPage();

        VisitorEvent::query()->create([
            'site_id' => $page->site_id,
            'page_id' => $page->id,
            'locale_id' => $this->defaultLocale()->id,
            'path' => '/p/about',
            'tracking_mode' => VisitorEvent::TRACKING_MODE_BASIC,
            'visited_at' => CarbonImmutable::today()->setTime(9, 0),
        ]);

        VisitorEvent::query()->create([
            'site_id' => $page->site_id,
            'page_id' => $page->id,
            'locale_id' => $this->defaultLocale()->id,
            'path' => '/p/about',
            'tracking_mode' => VisitorEvent::TRACKING_MODE_FULL,
            'session_key' => 'session-1',
            'ip_hash' => 'hash-1',
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'visited_at' => CarbonImmutable::today()->setTime(10, 0),
        ]);

        $report = app(VisitorReportsQuery::class)->build($this->todayFilters());

        $this->assertCount(1, $report['top_campaigns']);
        $this->assertSame('Direct / None', $report['top_campaigns']->first()['label']);
        $this->assertSame(1, $report['top_campaigns']->first()['page_views']);
        $this->assertSame(1, $report['source_breakdown']->first()['page_views']);
        $this->assertSame(1, $report['medium_breakdown']->first()['page_views']);
    }

    #[Test]
    public function localized_public_pages_store_the_resolved_locale(): void
    {
        $site = $this->defaultSite();
        $page = $this->createPublishedPage($site, 'About', 'about');
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        $this->get('/tr/p/hakkinda')->assertOk();

        $this->assertDatabaseHas('visitor_events', [
            'page_id' => $page->id,
            'locale_id' => $turkish->id,
            'path' => '/tr/p/hakkinda',
        ]);
    }
}
