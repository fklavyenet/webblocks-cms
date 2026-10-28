<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ConsentSetting;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Visitors\VisitorConsent;
use WebBlocks\Cms\Tests\TestCase;

/** Regression coverage for independent site consent and an always-reopenable preference center. */
class PublicCookieConsentBannerTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-cms.routes.public', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  /**
   * The layout only emits a published asset it can see on disk, which is how a
   * consumer that has not run the asset publish step avoids a 404 script tag.
   * Testbench has its own public directory, so the shipped file is placed there
   * to exercise the real guard rather than assert around it.
   */
  protected function setUp(): void
  {
    parent::setUp();

    $source = dirname(__DIR__, 2).'/public/cms/js/privacy-consent-sync.js';
    $target = public_path('cms/js/privacy-consent-sync.js');

    if (! is_dir(dirname($target))) {
      mkdir(dirname($target), 0o777, true);
    }

    copy($source, $target);
  }

  protected function tearDown(): void
  {
    @unlink(public_path('cms/js/privacy-consent-sync.js'));

    parent::tearDown();
  }

  #[Test]
  public function an_undecided_visitor_gets_the_banner_and_the_preference_center(): void
  {
    $html = $this->renderPage();

    $this->assertStringContainsString('wb-cookie-consent-banner', $html);
    $this->assertStringContainsString('wbCookiePreferences', $html, 'The banner\'s Customize button targets the preference modal by id.');
    $this->assertStringContainsString('data-cms-consent-root', $html, 'The CMS owns the service-level consent lifecycle.');
  }

  #[Test]
  public function the_banner_offers_every_category_the_sync_endpoint_validates(): void
  {
    $html = $this->renderPage();

    $this->assertStringContainsString('data-cms-consent-service="cms-analytics"', $html);
    $this->assertStringContainsString('Necessary', $html);
    $this->assertStringNotContainsString('data-cms-consent-service="youtube"', $html);

  }

  #[Test]
  public function the_bridge_script_is_published_with_the_config_it_reads(): void
  {
    $html = $this->renderPage();

    $this->assertStringContainsString('cms/js/privacy-consent-sync.js', $html);
    $this->assertStringNotContainsString('WebBlocksCmsPrivacyConsent', $html);

    $this->assertStringContainsString('data-consent-config=', $html);
    $this->assertStringContainsString('privacy-consent', $html);
    $this->assertStringContainsString('csrfToken', $html);

  }

  #[Test]
  public function a_visitor_who_already_decided_does_not_see_the_banner_again(): void
  {
    $consent = $this->app->make(VisitorConsent::class);

    $html = $this->renderPage([$consent->cookieName() => VisitorConsent::ACCEPTED]);

    $this->assertStringNotContainsString('wb-cookie-consent-banner', $html);
    $this->assertStringContainsString('id="wbCookiePreferences"', $html);
    $this->assertStringContainsString(
      'cms/js/privacy-consent-sync.js',
      $html,
      'The bridge must keep loading so a change made from the reopened preference center still reaches the server cookie.'
    );
  }

  #[Test]
  public function site_settings_can_suppress_the_whole_feature(): void
  {
    $html = $this->renderPage(enabled: false);
    $this->assertStringNotContainsString('wb-cookie-consent-banner', $html);
    $this->assertStringNotContainsString('id="wbCookiePreferences"', $html);
    $this->assertStringNotContainsString('cms/js/privacy-consent-sync.js', $html);
  }

  #[Test]
  public function consent_remains_available_when_visitor_reports_are_off(): void
  {
    config()->set('cms.visitor_reports.enabled', false);
    $html = $this->renderPage();
    $this->assertStringNotContainsString('wb-cookie-consent-banner', $html);
    $this->assertStringContainsString('id="wbCookiePreferences"', $html);
    $this->assertStringContainsString('cms/js/privacy-consent-sync.js', $html);
  }

  /**
   * @param  array<string, string>  $cookies
   */
  private function renderPage(array $cookies = [], bool $enabled = true): string
  {
    $page = $this->seedPage();
    ConsentSetting::query()->updateOrCreate(['site_id' => $page->site_id], ['enabled' => $enabled]);

    $request = Request::create('/about', 'GET', [], $cookies);

    // The layout reads the consent cookie through the request() helper, so the
    // container's request has to be the one carrying the cookie.
    $this->app->instance('request', $request);

    $page->load(['site', 'translations.locale', 'slots.slotType', 'pageAssets', 'blocks.blockType']);
    $page->setRelation('currentTranslation', $page->translations->first());
    $html = view('webblocks-cms::public.pages.show', app(PublicPagePresenter::class)->present($page) + ['previewMode' => false])->render();

    $this->assertStringContainsString('class="wb-public-body', $html);

    return $html;
  }

  private function seedPage(): Page
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'consent', 'is_primary' => true]);
    $locale = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'slug' => 'about',
      'status' => Page::STATUS_PUBLISHED,
    ]);
    $page->translations()->create([
      'locale_id' => $locale->id,
      'name' => 'About',
      'slug' => 'about',
      'path' => '/about',
    ]);

    $slotType = SlotType::query()->firstOrCreate(
      ['slug' => 'main'],
      ['name' => 'Main', 'status' => 'published', 'sort_order' => 0],
    );
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 0]);

    Block::create([
      'page_id' => $page->id,
      'type' => 'header',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Main heading',
      'variant' => 'h2',
      'status' => 'published',
    ]);

    return $page->fresh();
  }
}
