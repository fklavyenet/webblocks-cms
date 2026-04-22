<?php

namespace Tests\Feature;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\User;
use App\Models\VisitorEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    #[Test]
    public function public_page_render_creates_a_visitor_event(): void
    {
        $page = $this->createPublishedPage();

        $this->withHeader('referer', 'https://example.test/campaign')
            ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit Safari')
            ->get(route('pages.show', ['slug' => $page->slug, 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring'], false))
            ->assertOk();

        $event = VisitorEvent::query()->first();

        $this->assertNotNull($event);
        $this->assertSame($page->site_id, $event->site_id);
        $this->assertSame($page->id, $event->page_id);
        $this->assertSame($this->defaultLocale()->id, $event->locale_id);
        $this->assertSame('/p/about', $event->path);
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.reports.visitors.index'));

        $response->assertOk();
        $response->assertSee('Visitor Reports');
        $response->assertSee('Total page views');
        $response->assertSee('Top Pages');
    }

    #[Test]
    public function admin_visitor_reports_screen_handles_missing_table_gracefully(): void
    {
        $user = User::factory()->create();

        Schema::dropIfExists('visitor_events');

        $response = $this->actingAs($user)->get(route('admin.reports.visitors.index'));

        $response->assertOk();
        $response->assertSee('Visitor reports migration is missing');
        $response->assertSee('php artisan migrate');
    }

    #[Test]
    public function reports_respect_site_and_date_range_filters(): void
    {
        $user = User::factory()->create();
        $primarySite = $this->defaultSite();
        $campaignSite = Site::query()->create([
            'name' => 'Campaign',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);

        $defaultLocale = $this->defaultLocale();
        $campaignSite->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);

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
