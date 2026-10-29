<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Consent\SaveConsentService;
use WebBlocks\Cms\Actions\Consent\UpdateConsentSettings;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;
use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Consent\ConsentState;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class CookieSettingsBlockTest extends TestCase
{
  private Site $site;

  private Page $page;

  private SlotType $slot;

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.public', true);
    $app['config']->set('webblocks-cms.routes.admin', true);
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
    $this->site = Site::create(['name' => 'Synthetic site', 'handle' => 'synthetic-preferences', 'is_primary' => true]);
    foreach (['en', 'tr', 'de', 'es', 'it', 'fr'] as $code) {
      $locale = Locale::firstOrCreate(['code' => $code], ['name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true]);
      $this->site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    }
    app(CoreBlockTypeCatalogSyncer::class)->sync();
    $this->slot = SlotType::firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'sort_order' => 0, 'status' => 'published']);
    $this->page = Page::create(['site_id' => $this->site->id, 'slug' => 'synthetic', 'status' => 'draft']);
    PageSlot::create(['page_id' => $this->page->id, 'slot_type_id' => $this->slot->id, 'sort_order' => 0]);
    $this->page->translations()->create(['locale_id' => Locale::where('code', 'en')->value('id'), 'name' => 'Synthetic page', 'slug' => 'synthetic', 'path' => '/synthetic']);
  }

  private function token(): string
  {
    return app(CmsApiTokenIssuer::class)->issue('Synthetic preferences', capabilities: ['content.read', 'content.apply', 'content.validate', 'navigation.write'], allowedSiteIds: [$this->site->id])->plainToken;
  }

  private function block(string $locale = 'en', string $variant = 'text'): Block
  {
    $type = BlockType::where('slug', 'cookie-settings')->firstOrFail();
    $block = Block::create(['page_id' => $this->page->id, 'block_type_id' => $type->id, 'type' => $type->slug, 'is_system' => true,
      'slot_type_id' => $this->slot->id, 'slot' => 'main', 'variant' => $variant, 'sort_order' => 0, 'status' => 'published']);
    $block->setRelation('renderPage', $this->page->setRelation('site', $this->site));
    $block->setAttribute('render_locale_code', $locale);

    return $block;
  }

  #[Test]
  public function the_catalog_update_is_idempotent_and_describes_a_system_action_without_copy_or_children(): void
  {
    BlockType::where('slug', 'cookie-settings')->delete();
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_29_120000_add_cookie_settings_block.php';
    $migration->up();
    $migration->up();
    $this->assertSame(1, BlockType::where('slug', 'cookie-settings')->count());
    $contract = app(BlockTypeContractRegistry::class)->resolve('cookie-settings');
    $this->assertTrue($contract->isSystem);
    $this->assertTrue($contract->documented);
    $block = $this->block();
    $this->assertFalse($block->supportsTranslations());
    $this->assertFalse($block->canAcceptChildren());
    $this->assertTrue($block->ownsPublicRoot());
    foreach (['hero', 'cta', 'sticky-navbar', 'cluster', 'stack', 'container'] as $parent) {
      $this->assertTrue((new Block(['type' => $parent]))->canAcceptChildType('cookie-settings'), $parent);
    }
  }

  public static function languages(): array
  {
    return [['en', 'Cookie settings'], ['tr', 'Çerez ayarları'], ['de', 'Cookie-Einstellungen'],
      ['es', 'Configuración de cookies'], ['it', 'Impostazioni cookie'], ['fr', 'Paramètres des cookies']];
  }

  #[Test]
  #[DataProvider('languages')]
  public function the_block_has_a_real_localized_destination_and_accessible_action(string $locale, string $label): void
  {
    $html = view('webblocks-cms::pages.partials.blocks.cookie-settings', ['block' => $this->block($locale, 'secondary')])->render();
    $path = ($locale === 'en' ? '' : '/'.$locale).'/privacy-consent/preferences';
    $this->assertStringContainsString('href="'.$path.'"', $html);
    $this->assertStringContainsString($label, $html);
    $this->assertStringContainsString('wb-btn wb-btn-secondary', $html);
    $this->assertStringContainsString('aria-controls="wbCookiePreferences"', $html);
    $this->assertStringContainsString('data-cms-consent-open', $html);
    $this->assertStringNotContainsString('id="wbCookiePreferences"', $html);
  }

  #[Test]
  public function disabled_consent_hides_the_public_block_and_ready_made_menu_entry(): void
  {
    app(UpdateConsentSettings::class)->settings($this->site, ['enabled' => false]);
    $html = view('webblocks-cms::pages.partials.blocks.cookie-settings', ['block' => $this->block()])->render();
    $this->assertStringNotContainsString('<a ', $html);
    $item = NavigationItem::create(['site_id' => $this->site->id, 'menu_key' => 'footer', 'link_type' => 'cookie_settings', 'visibility' => 'visible', 'position' => 1]);
    $this->assertFalse($item->isVisible());
    $this->assertNull($item->resolvedUrl());
    $this->get('/privacy-consent/preferences')->assertNotFound();
    $this->post('/privacy-consent/preferences', ['status' => 'accepted'])->assertForbidden();
    $this->assertStringContainsString('disabled', view('webblocks-cms::admin.blocks.types.cookie-settings', ['block' => $this->block()])->render());
  }

  #[Test]
  #[DataProvider('languages')]
  public function direct_entry_renders_one_preference_center_without_granting_consent(string $locale, string $label): void
  {
    $path = ($locale === 'en' ? '' : '/'.$locale).'/privacy-consent/preferences';
    $response = $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $response->assertSee($label)->assertDontSee('name="services[youtube]"', false)->assertCookieMissing('webblocks_consent_'.$this->site->id);
    $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    $this->assertStringContainsString('Cookie', $response->headers->get('Vary'));
    $this->assertSame(1, substr_count($response->getContent(), 'id="wbCookiePreferences"'));
    $response->assertSee('data-cms-consent-auto-open', false)->assertSee('<noscript>', false)->assertSee('method="POST"', false);
  }

  #[Test]
  public function the_native_form_uses_the_same_revision_and_cookie_contract_as_json(): void
  {
    $service = app(SaveConsentService::class)->execute($this->site, 'synthetic-service', ['name' => 'Synthetic', 'category' => 'preferences']);
    $path = '/tr/privacy-consent/preferences';
    foreach (['stale' => false, $service->revision => true] as $revision => $expected) {
      $response = $this->post($path, ['status' => 'accepted', 'revisions' => ['synthetic-service' => $revision]])->assertRedirect($path)->assertSessionHas('cms_consent_saved', true);
      $cookie = $response->getCookie('webblocks_consent_'.$this->site->id);
      $this->assertNotNull($cookie);
      $this->assertTrue($cookie->isHttpOnly());
      $state = app(ConsentState::class)->read(Request::create('/', cookies: [$cookie->getName() => $cookie->getValue()]), $this->site, app(ConsentConfiguration::class)->publicServices($this->site));
      $this->assertSame($expected, $state['services']['synthetic-service']);
    }
    $response = $this->post($path, ['status' => 'rejected', 'revisions' => ['synthetic-service' => $service->revision], 'services' => ['synthetic-service' => '1']])->assertRedirect($path);
    $cookie = $response->getCookie('webblocks_consent_'.$this->site->id);
    $state = app(ConsentState::class)->read(Request::create('/', cookies: [$cookie->getName() => $cookie->getValue()]), $this->site, app(ConsentConfiguration::class)->publicServices($this->site));
    $this->assertFalse($state['services']['synthetic-service']);
    $this->post($path, ['status' => 'anything'])->assertSessionHasErrors('status');
  }

  #[Test]
  public function disabled_languages_and_foreign_site_identifiers_cannot_redirect_the_form(): void
  {
    $this->site->locales()->updateExistingPivot(Locale::where('code', 'tr')->value('id'), ['is_enabled' => false]);
    $this->get('/tr/privacy-consent/preferences')->assertNotFound();
    $other = Site::create(['name' => 'Other site', 'handle' => 'other-preferences']);
    $this->post('/privacy-consent/preferences', ['status' => 'rejected', 'site_id' => $other->id, 'return_to' => 'https://example.test/'])->assertRedirect('/privacy-consent/preferences')->assertCookie('webblocks_consent_'.$this->site->id)->assertCookieMissing('webblocks_consent_'.$other->id);
  }

  #[Test]
  public function the_content_api_creates_the_block_and_rejects_unknown_appearance_and_destinations(): void
  {
    $url = '/webadmin/api/pages/'.$this->page->id.'/slots/main/blocks';
    $response = $this->withToken($this->token())->postJson($url, ['type' => 'cookie-settings', 'variant' => 'secondary'])->assertCreated()->assertJsonPath('block.type', 'cookie-settings')->assertJsonPath('block.variant', 'secondary');
    $id = $response->json('block.id');
    $this->withToken($this->token())->patchJson('/webadmin/api/blocks/'.$id, ['variant' => 'text'])->assertOk();
    $this->assertSame('text', Block::findOrFail($id)->variant);
    $this->withToken($this->token())->postJson($url, ['type' => 'cookie-settings', 'variant' => 'arbitrary'])->assertUnprocessable();
    $this->withToken($this->token())->postJson($url, ['type' => 'cookie-settings', 'settings' => ['url' => 'https://example.test/']])->assertUnprocessable();
  }

  #[Test]
  public function navigation_api_and_plan_accept_the_ready_made_link_without_an_authored_url_or_label(): void
  {
    $errors = [];
    $normalized = app(InternalContentApiOperations::class)->normalizeNavigationItem(['link_type' => 'cookie_settings'], $this->site, 'footer', 'item', $errors);
    $this->assertSame([], $errors);
    $this->assertSame('', $normalized['url']);
    $item = NavigationItem::create($normalized);
    $this->assertSame('Cookie settings', $item->resolvedTitle());
    $this->assertSame('/privacy-consent/preferences', $item->resolvedUrl());
    $response = $this->withToken($this->token())->postJson('/webadmin/api/navigation-menus/footer/items', ['site_id' => $this->site->id, 'link_type' => 'cookie_settings'])->assertCreated();
    $this->withToken($this->token())->patchJson('/webadmin/api/navigation-menus/footer/items/'.$item->id, ['site_id' => $this->site->id, 'link_type' => 'cookie_settings', 'target' => '_blank'])->assertUnprocessable();
    $this->assertStringContainsString('cookie_settings', view('webblocks-cms::admin.navigation._form', ['item' => $item, 'site' => $this->site, 'menuOptions' => NavigationItem::menuOptions(), 'parents' => collect(), 'pages' => collect()])->render());
  }

  private function picker(array $eligible): string
  {
    request()->merge(['block_type_tab' => 'system']);

    return view('webblocks-cms::admin.pages.partials.slot-block-picker', [
      'page' => $this->page, 'slot' => $this->slot, 'activeLocale' => Locale::where('code', 'en')->firstOrFail(),
      'pickerSearch' => '', 'isPickerOpen' => true, 'slotModalMode' => 'picker', 'pickerParentBlock' => null,
      'pickerBlockTypes' => collect($eligible), 'blockTypes' => collect($eligible),
    ])->render();
  }

  #[Test]
  public function system_picker_uses_the_system_flag_and_preserves_functional_tabs_and_eligibility(): void
  {
    $plugin = BlockType::create(['slug' => 'synthetic-system', 'name' => 'Synthetic system utility', 'category' => 'content', 'is_system' => true, 'is_container' => false, 'status' => 'published']);
    $html = $this->picker(BlockType::where('status', 'published')->get()->all());
    $doc = new \DOMDocument;
    @$doc->loadHTML($html);
    $xpath = new \DOMXPath($doc);
    $system = $xpath->query('//*[@id="slot-block-picker-panel-system"]')->item(0);
    $this->assertNotNull($system);
    $this->assertSame('false', $system->getAttribute('aria-hidden'));
    $slugs = [];
    foreach ($xpath->query('.//*[@data-block-type-slug]', $system) as $row) {
      $slugs[] = $row->getAttribute('data-block-type-slug');
      $this->assertStringContainsString('System', $row->textContent);
    }
    sort($slugs);
    $expected = ['application', 'breadcrumb', 'comments', 'cookie-settings', 'header-actions', 'navigation-auto', 'page-list', 'rating', 'sticky-navbar', 'synthetic-system', 'toc'];
    sort($expected);
    $this->assertSame($expected, $slugs);
    $this->assertSame(1, $xpath->query('//*[@id="slot-block-picker-panel-navigation"]//*[@data-block-type-slug="header-actions"]')->length);
    $this->assertSame(1, $xpath->query('//*[@id="slot-block-picker-panel-content"]//*[@data-block-type-slug="page-list"]')->length);
    $restricted = $this->picker([$plugin]);
    $this->assertStringNotContainsString('data-block-type-slug="comments"', $restricted);
    $this->assertStringContainsString('data-block-type-slug="synthetic-system"', $restricted);
    if ($path = getenv('WEBBLOCKS_SYSTEM_PICKER_FIXTURE')) {
      $version = WebBlocks::UI_VERSION;
      $document = '<!doctype html><html lang="en" data-wb-theme="light"><head><meta charset="utf-8"><link rel="stylesheet" href="/cms/webblocks-ui/'.$version.'/webblocks-ui.css"><link rel="stylesheet" href="/cms/css/admin.css"><script src="/cms/webblocks-ui/'.$version.'/webblocks-ui.js" defer></script><script src="/cms/js/admin/core.js" defer></script></head><body><main class="wb-container"><h1>Synthetic system block picker</h1><button type="button" data-wb-toggle="modal" data-wb-target="#slot-block-picker-modal">Block Types</button></main>'.$html.'</body></html>';
      file_put_contents($path, $document);
    }
  }

  #[Test]
  public function preview_does_not_change_preferences_and_shared_slot_authorship_uses_the_same_contract(): void
  {
    $response = $this->withToken($this->token())->getJson('/webadmin/api/pages/'.$this->page->id.'/render?locale=en')->assertOk();
    $this->assertStringNotContainsString('data-cms-consent-auto-open', $response->json('html'));
    $errors = [];
    $warnings = [];
    $normalized = app(InternalContentApiOperations::class)->normalizeBlock(['type' => 'cookie-settings', 'settings' => ['variant' => 'primary']], 'block', null, $errors, $warnings);
    $this->assertSame([], $errors);
    $slot = SharedSlot::create(['site_id' => $this->site->id, 'name' => 'Synthetic footer', 'handle' => 'synthetic-footer', 'slot_name' => 'footer', 'is_active' => true]);
    $block = app(InternalContentApiOperations::class)->createSharedSlotBlock($slot, $normalized, 'en', null, 0);
    $this->assertTrue($block->is_system);
    $this->assertSame('primary', $block->variant);
    $this->assertStringContainsString('data-cms-consent-open', view('webblocks-cms::pages.partials.blocks.cookie-settings', ['block' => $block])->render());
  }

  #[Test]
  #[RunInSeparateProcess]
  #[PreserveGlobalState(false)]
  public function native_editor_defaults_and_promo_actions_keep_the_system_link_renderable(): void
  {
    $request = BlockRequest::create('/synthetic-edit', 'POST', [
      'page_id' => $this->page->id, 'block_type_id' => BlockType::where('slug', 'cookie-settings')->value('id'),
      'slot_type_id' => $this->slot->id, 'sort_order' => 0, 'status' => 'draft',
    ]);
    if (! class_exists('App\\Models\\User')) {
      class_alias(CmsSqlTestUser::class, 'App\\Models\\User');
    }
    $actorClass = 'App\\Models\\User';
    $actor = $this->cmsActor(new $actorClass(['name' => 'Synthetic operator', 'role' => 'super_admin', 'is_active' => true]));
    $request->setUserResolver(fn () => $actor);
    $request->setContainer($this->app)->setRedirector($this->app['redirect']);
    $request->validateResolved();
    $values = $request->validatedData();
    $this->assertSame('text', $values['variant']);
    $this->assertNull($values['url']);
    $this->assertNull($values['settings']);
    $stored = app(BlockPayloadWriter::class)->save(new Block, $this->page, $values, 'en');
    $this->assertTrue($stored->is_system);
    $this->assertSame('text', $stored->variant);
    foreach (['hero', 'cta'] as $type) {
      $parent = new Block(['page_id' => $this->page->id, 'type' => $type, 'title' => 'Synthetic heading']);
      $parent->setRelation('children', collect([$stored]));
      $parent->setRelation('renderPage', $this->page->setRelation('site', $this->site));
      $html = view('webblocks-cms::pages.partials.blocks.'.$type, ['block' => $parent, 'page' => $this->page])->render();
      $this->assertStringContainsString('wb-promo-actions', $html);
      $this->assertStringContainsString('data-cms-consent-open', $html);
      $this->assertStringContainsString('Cookie settings', $html);
    }
  }
}
