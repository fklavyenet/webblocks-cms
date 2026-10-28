<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Consent\SaveConsentService;
use WebBlocks\Cms\Actions\Consent\UpdateConsentSettings;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ConsentSetting;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Consent\ConsentIntegrations;
use WebBlocks\Cms\Support\Consent\ConsentPresenter;
use WebBlocks\Cms\Support\Consent\ConsentPresets;
use WebBlocks\Cms\Support\Consent\ConsentSchema;
use WebBlocks\Cms\Support\Consent\ConsentState;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class ConsentManagementTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('webblocks-cms.routes.public', true);
    $app['config']->set('cms.multisite.unknown_host_fallback', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutMiddleware(EnsureInstalled::class);
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
  }

  private function site(): Site
  {
    return Site::create(['name' => 'Synthetic site', 'handle' => 'synthetic-'.(Site::count() + 1), 'is_primary' => Site::count() === 0]);
  }

  private function token(array $capabilities, ?array $sites = null): string
  {
    return app(CmsApiTokenIssuer::class)->issue('Synthetic consent test', capabilities: $capabilities, allowedSiteIds: $sites)->plainToken;
  }

  #[Test]
  public function repeated_embeds_reuse_configuration_and_mutations_invalidate_the_request_cache(): void
  {
    $site = $this->site();
    app(SaveConsentService::class)->execute($site, 'custom', ['name' => 'Synthetic service']);
    $config = app(ConsentConfiguration::class);
    $config->services($site, 'en');
    $config->settings($site);
    DB::enableQueryLog();
    for ($i = 0; $i < 20; $i++) {
      $config->services($site, 'en');
      $config->settings($site);
    }
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $this->assertSame([], $queries);
    app(SaveConsentService::class)->execute($site, 'custom', ['enabled' => false]);
    $this->assertFalse($config->services($site, 'en')['custom']['enabled']);
    app(UpdateConsentSettings::class)->settings($site, ['enabled' => false]);
    $this->assertFalse($config->settings($site)['enabled']);
  }

  #[Test]
  public function migrations_are_idempotent_and_configuration_is_relational(): void
  {
    $site = $this->site();
    app(UpdateConsentSettings::class)->settings($site, ['enabled' => false, 'lifetime_days' => 30]);
    ConsentSchema::migrate();
    ConsentSchema::migrate();
    $this->assertFalse(app(ConsentConfiguration::class)->settings($site)['enabled']);
    $this->assertSame(30, app(ConsentConfiguration::class)->settings($site)['lifetime_days']);
    foreach (['settings', 'services', 'translations', 'cookies'] as $suffix) {
      $this->assertTrue(Schema::hasTable('wbcms_consent_'.$suffix));
      $this->assertNotContains('json', array_column(Schema::getColumns('wbcms_consent_'.$suffix), 'type_name'));
    }
  }

  #[Test]
  public function api_enforces_read_write_and_site_boundaries(): void
  {
    $site = $this->site();
    $other = $this->site();
    $reader = $this->token(['consent.read'], [$site->id]);
    $this->withToken($reader)->getJson('/webadmin/api/sites/'.$site->id.'/consent')->assertOk()->assertJsonPath('settings.enabled', true)->assertJsonStructure(['presets' => ['youtube', 'google-maps', 'vimeo']]);
    $this->withToken($reader)->getJson('/webadmin/api/sites/'.$other->id.'/consent')->assertForbidden();
    $this->withToken($reader)->patchJson('/webadmin/api/sites/'.$site->id.'/consent', ['enabled' => false])->assertForbidden();
    $writer = $this->token(['consent.read', 'consent.write'], [$site->id]);
    $this->withToken($writer)->patchJson('/webadmin/api/sites/'.$site->id.'/consent', ['enabled' => false, 'lifetime_days' => 60])->assertOk()->assertJsonPath('settings.enabled', false)->assertJsonPath('settings.lifetime_days', 60);
    $this->withToken($writer)->patchJson('/webadmin/api/sites/'.$site->id.'/consent', ['lifetime_days' => 0])->assertUnprocessable();
    $this->withToken($writer)->patchJson('/webadmin/api/sites/'.$site->id.'/consent', ['privacy_url' => ['invalid']])->assertUnprocessable();
    $this->withToken($writer)->patchJson('/webadmin/api/sites/'.$site->id.'/consent', ['privacy_url' => 'javascript:bad()'])->assertUnprocessable();
    $this->assertContains('internal-api.capability:consent.write', Route::getRoutes()->getByName('internal-content-api.consent.services.update')->gatherMiddleware());
  }

  #[Test]
  public function executable_sources_require_a_separate_capability_and_cannot_hide_in_a_preset(): void
  {
    $site = $this->site();
    $writer = $this->token(['consent.read', 'consent.write'], [$site->id]);
    $endpoint = '/webadmin/api/sites/'.$site->id.'/consent/services/measurement';
    $this->withToken($writer)->putJson($endpoint, ['name' => 'Measurement', 'category' => 'analytics'])->assertOk();
    $this->withToken($writer)->putJson($endpoint, ['script_url' => 'https://assets.example.test/measurement.js'])->assertForbidden();
    $coder = $this->token(['consent.read', 'consent.write', 'consent.code.write'], [$site->id]);
    $this->withToken($coder)->putJson($endpoint, ['script_url' => 'https://assets.example.test/measurement.js'])->assertOk();
    $this->withToken($coder)->putJson($endpoint, ['script_url' => 'javascript:bad()'])->assertUnprocessable();
    foreach ([['script_url' => ['invalid']], ['cookies' => [['name' => ['invalid'], 'path' => ['/']]]], ['cookies' => [['name' => 'tracking', 'path' => "/\0invalid"]]]] as $invalid) {
      $this->withToken($coder)->putJson($endpoint, $invalid)->assertUnprocessable();
    }
    $this->withToken($writer)->deleteJson($endpoint)->assertForbidden();
    $this->withToken($coder)->putJson('/webadmin/api/sites/'.$site->id.'/consent/services/youtube', ['script_url' => 'https://assets.example.test/wrong.js'])->assertUnprocessable();
  }

  #[Test]
  public function preset_deletion_does_not_silently_restore_an_enabled_default(): void
  {
    $site = $this->site();
    app(SaveConsentService::class)->delete($site, 'youtube');
    $this->assertFalse(app(ConsentConfiguration::class)->services($site)['youtube']['enabled']);
  }

  #[Test]
  public function translations_use_site_locale_scope_and_blank_values_restore_defaults(): void
  {
    $site = $this->site();
    $other = $this->site();
    $token = $this->token(['consent.read', 'consent.write'], [$site->id]);
    $endpoint = '/webadmin/api/sites/'.$site->id.'/consent/texts';
    $this->withToken($token)->putJson($endpoint, ['locale' => 'en', 'texts' => ['title' => 'Your choices', 'service.youtube.name' => 'Training videos']])->assertOk();
    $this->assertSame('Your choices', app(ConsentConfiguration::class)->text($site, 'title', 'en'));
    $this->assertSame('Training videos', app(ConsentConfiguration::class)->services($site, 'en')['youtube']['name']);
    $this->assertNotSame('Your choices', app(ConsentConfiguration::class)->text($other, 'title', 'en'));
    $this->withToken($token)->putJson($endpoint, ['locale' => 'de', 'texts' => ['title' => 'Wrong locale']])->assertUnprocessable();
    $this->withToken($token)->putJson($endpoint, ['locale' => ['en'], 'texts' => ['title' => 'Invalid locale type']])->assertUnprocessable();
    $this->withToken($token)->putJson($endpoint, ['locale' => 'en', 'texts' => ['script_url' => 'not text']])->assertUnprocessable();
    $this->withToken($token)->putJson($endpoint, ['locale' => 'en', 'texts' => ['title' => '']])->assertOk();
    $this->assertNotSame('Your choices', app(ConsentConfiguration::class)->text($site, 'title', 'en'));
  }

  #[Test]
  public function only_published_known_embeds_are_automatically_activated(): void
  {
    $site = $this->site();
    $page = Page::create(['site_id' => $site->id, 'slug' => 'training', 'status' => 'draft']);
    Block::create(['page_id' => $page->id, 'type' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'status' => 'published']);
    $this->assertArrayNotHasKey('youtube', app(ConsentConfiguration::class)->publicServices($site));
    $page->update(['status' => 'published']);
    request()->attributes->remove('cms.consent.used.'.$site->id);
    $this->assertArrayHasKey('youtube', app(ConsentConfiguration::class)->publicServices($site));
    $this->assertArrayNotHasKey('google-maps', app(ConsentConfiguration::class)->publicServices($site));
    $source = Page::create(['site_id' => $site->id, 'slug' => 'unused-shared-source', 'page_type' => Page::TYPE_SHARED_SLOT_SOURCE, 'status' => 'published']);
    Block::create(['page_id' => $source->id, 'type' => 'video', 'url' => 'https://vimeo.com/76979871', 'status' => 'published']);
    request()->attributes->remove('cms.consent.used.'.$site->id);
    $this->assertArrayNotHasKey('vimeo', app(ConsentConfiguration::class)->publicServices($site));
  }

  #[Test]
  public function choices_are_authenticated_site_scoped_and_new_revisions_need_new_permission(): void
  {
    $site = $this->site();
    $other = $this->site();
    $action = app(SaveConsentService::class);
    $action->execute($site, 'measurement', ['category' => 'analytics']);
    $config = app(ConsentConfiguration::class);
    $services = $config->publicServices($site);
    $state = app(ConsentState::class);
    $cookie = $state->cookie($site, $services, ['measurement' => true], 'custom', 30);
    $request = Request::create('/', cookies: [$state->cookieName($site) => $cookie->getValue()]);
    $this->assertTrue($state->read($request, $site, $services)['services']['measurement']);
    $this->assertFalse($state->read($request, $site, $services)['needs_choice']);
    $this->assertTrue($state->read($request, $other, $services)['needs_choice']);
    $action->execute($site, 'measurement', ['script_url' => 'https://assets.example.test/new.js']);
    $changed = $state->read($request, $site, $config->publicServices($site));
    $this->assertTrue($changed['needs_choice']);
    $this->assertArrayNotHasKey('measurement', $changed['services']);
  }

  #[Test]
  public function legacy_acceptance_does_not_authorize_new_services(): void
  {
    $site = $this->site();
    app(SaveConsentService::class)->execute($site, 'new-service', ['category' => 'marketing']);
    $request = Request::create('/', cookies: ['webblocks_visitor_consent' => 'accepted']);
    $state = app(ConsentState::class)->read($request, $site, app(ConsentConfiguration::class)->publicServices($site));
    $this->assertTrue($state['services']['cms-analytics']);
    $this->assertArrayNotHasKey('new-service', $state['services']);
    $this->assertTrue($state['needs_choice']);
  }

  #[Test]
  public function public_sync_refuses_stale_revisions_and_disabled_sites(): void
  {
    $site = $this->site();
    $service = app(SaveConsentService::class)->execute($site, 'measurement', ['category' => 'analytics']);
    $response = $this->postJson('/privacy-consent/sites/'.$site->id, ['status' => 'custom', 'services' => ['measurement' => true], 'revisions' => ['measurement' => 'stale']]);
    $response->assertOk()->assertJsonPath('services.measurement', false)->assertCookie('webblocks_consent_'.$site->id);
    $accepted = $this->postJson('/privacy-consent/sites/'.$site->id, ['status' => 'custom', 'services' => ['measurement' => true], 'revisions' => ['measurement' => $service->revision]])->assertOk()->assertJsonPath('services.measurement', true);
    $cookie = $accepted->getCookie('webblocks_consent_'.$site->id);
    $reloaded = app(ConsentState::class)->read(Request::create('/', cookies: [$cookie->getName() => $cookie->getValue()]), $site, app(ConsentConfiguration::class)->publicServices($site));
    $this->assertTrue($reloaded['services']['measurement']);
    $this->assertFalse($reloaded['needs_choice']);
    ConsentSetting::updateOrCreate(['site_id' => $site->id], ['enabled' => false]);
    $this->postJson('/privacy-consent/sites/'.$site->id, ['status' => 'accepted', 'services' => [], 'revisions' => []])->assertForbidden();
  }

  #[Test]
  public function expired_or_tampered_choices_are_not_restored_from_an_old_acceptance_cookie(): void
  {
    $site = $this->site();
    $state = app(ConsentState::class);
    $services = app(ConsentConfiguration::class)->publicServices($site);
    $cookie = $state->cookie($site, $services, ['cms-analytics' => true], 'accepted', 1);
    $this->travel(2)->days();
    $request = Request::create('/', cookies: [$state->cookieName($site) => $cookie->getValue(), 'webblocks_visitor_consent' => 'accepted']);
    $expired = $state->read($request, $site, $services);
    $this->assertTrue($expired['needs_choice']);
    $this->assertArrayNotHasKey('cms-analytics', $expired['services']);
    $request = Request::create('/', cookies: [$state->cookieName($site) => $cookie->getValue().'changed']);
    $this->assertTrue($state->read($request, $site, $services)['needs_choice']);
  }

  #[Test]
  public function the_browser_protocol_stays_bounded_with_all_supported_services(): void
  {
    $site = $this->site();
    $services = [];
    for ($i = 0; $i < 36; $i++) {
      $services['service-'.$i.str_repeat('x', 25)] = ['category' => 'external', 'revision' => (string) Str::uuid()];
    }
    $state = app(ConsentState::class);
    $cookie = $state->cookie($site, $services, array_fill_keys(array_keys($services), true), 'accepted', 180);
    $encrypted = app('encrypter')->encrypt(str_repeat('x', 41).$cookie->getValue(), false);
    $this->assertLessThan(4000, strlen($cookie->getName()) + strlen($encrypted));
    $choice = $state->read(Request::create('/', cookies: [$cookie->getName() => $cookie->getValue()]), $site, $services);
    $this->assertCount(36, $choice['services']);
    $this->assertFalse($choice['needs_choice']);
  }

  #[Test]
  public function unrelated_domains_cannot_write_another_sites_decision(): void
  {
    $site = $this->site();
    $other = $this->site();
    $this->postJson('/privacy-consent/sites/'.$other->id, ['status' => 'accepted', 'services' => [], 'revisions' => []])->assertForbidden();
  }

  #[Test]
  public function external_content_api_normalizes_embed_code_and_rejects_active_markup(): void
  {
    $site = $this->site();
    $page = Page::create(['site_id' => $site->id, 'slug' => 'embed-authoring', 'status' => 'draft']);
    $slot = SlotType::firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published']);
    PageSlot::create(['page_id' => $page->id, 'slot_type_id' => $slot->id]);
    $token = $this->token(['content.apply'], [$site->id]);
    $url = '/webadmin/api/pages/'.$page->id.'/slots/main/blocks';
    $this->withToken($token)->postJson($url, ['type' => 'external-content', 'locale' => 'en', 'settings' => ['url' => '<iframe src="https://www.google.com/maps/embed?pb=synthetic" onload="bad()"></iframe>'], 'translations' => ['title' => 'Map']])->assertSuccessful();
    $block = Block::where('page_id', $page->id)->where('type', 'external-content')->firstOrFail();
    $this->assertSame('https://www.google.com/maps/embed?pb=synthetic', $block->url);
    $before = Block::count();
    $this->withToken($token)->postJson($url, ['type' => 'external-content', 'locale' => 'en', 'settings' => ['url' => 'https://evil.example.test/embed'], 'translations' => ['title' => 'Invalid']])->assertUnprocessable();
    $this->assertSame($before, Block::count());
  }

  #[Test]
  public function six_locales_have_real_public_copy_and_rendered_service_labels(): void
  {
    $site = $this->site();
    $titles = ['en' => 'Cookie and privacy preferences', 'tr' => 'Çerez ve gizlilik tercihleri', 'de' => 'Cookie- und Datenschutzeinstellungen', 'es' => 'Preferencias de cookies y privacidad', 'it' => 'Preferenze cookie e privacy', 'fr' => 'Préférences cookies et confidentialité'];
    foreach ($titles as $locale => $title) {
      $presentation = app(ConsentPresenter::class)->forSite($site, $locale);
      $this->assertSame($title, $presentation['texts']['preferences_title']);
      $html = view('webblocks-cms::partials.cookie-consent', ['consent' => $presentation])->render();
      $this->assertStringContainsString(e($title), $html);
      $this->assertStringContainsString('id="wbCookiePreferences"', $html);
    }
  }

  #[Test]
  public function disabled_plugin_definitions_cannot_become_active_public_services(): void
  {
    $site = $this->site();
    $integrations = app(ConsentIntegrations::class);
    $integrations->register('host-feature', 'Host feature', 'preferences', 'Synthetic host integration');
    $integrations->register('plugin-feature', 'Plugin feature', 'external', 'Synthetic disabled plugin integration', plugin: 'not-installed');
    $services = app(ConsentConfiguration::class)->publicServices($site);
    $this->assertArrayHasKey('host-feature', $services);
    $this->assertArrayNotHasKey('plugin-feature', $services);
  }

  #[Test]
  #[RunInSeparateProcess]
  #[PreserveGlobalState(false)]
  public function authorized_panel_users_can_open_all_three_screens_and_preview(): void
  {
    $site = $this->site();
    if (! class_exists('App\\Models\\User')) {
      class_alias(ConsentPanelUser::class, 'App\\Models\\User');
    }
    $class = 'App\\Models\\User';
    $actor = $this->cmsActor(new $class(['name' => 'Synthetic operator', 'role' => 'super_admin', 'is_active' => true]));
    $this->actingAs($actor);
    foreach (['privacy', 'privacy/services', 'privacy/services?edit=youtube&', 'privacy/texts', 'privacy/preview'] as $path) {
      $separator = str_contains($path, '?') ? '' : '?';
      $response = $this->get('/webadmin/'.$path.$separator.'site='.$site->id);
      $response->assertOk();
    }
    $this->withSession(['consent_locale' => 'de'])->get('/webadmin/privacy/texts?site='.$site->id)->assertOk()->assertSee('name="locale" value="en"', false);
    $this->get('/webadmin/system/settings?tab=privacy')->assertOk()->assertSee('/webadmin/privacy', false)->assertDontSee('name="visitor_consent_banner_enabled"', false);
    if ($fixture = getenv('WEBBLOCKS_CONSENT_ADMIN_FIXTURE')) {
      $response = $this->get('/webadmin/privacy/services?site='.$site->id.'&edit=new');
      $response->assertOk();
      file_put_contents($fixture, $response->getContent());
    }
  }

  #[Test]
  public function embed_parsing_rejects_spoofed_hosts_and_active_html(): void
  {
    $presets = app(ConsentPresets::class);
    $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $presets->embed('https://youtu.be/dQw4w9WgXcQ')['url']);
    $this->assertSame('google-maps', $presets->embed('<iframe src="https://www.google.com/maps/embed?pb=synthetic"></iframe>')['service']);
    foreach (['https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ', 'https://user@youtube.com/watch?v=dQw4w9WgXcQ', 'javascript:bad()', '<script src="https://www.google.com/maps/embed"></script>', '<iframe src="https://www.google.com/maps/embed"></iframe><script>bad()</script>'] as $url) {
      $this->assertNull($presets->embed($url));
    }
  }
}

class ConsentPanelUser extends CmsSqlTestUser
{
  protected $table = 'users';
}
