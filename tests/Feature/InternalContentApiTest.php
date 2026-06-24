<?php

namespace Tests\Feature;

use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\PageLayoutSeeder;
use Database\Seeders\SlotTypeSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;
use WebBlocks\Cms\Support\WebBlocks;

class InternalContentApiTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(SlotTypeSeeder::class);
    $this->seed(BlockTypeSeeder::class);
    $this->seed(PageLayoutSeeder::class);
  }

  #[Test]
  public function api_discovery_returns_public_safe_minimal_json_without_token(): void
  {
    $response = $this->getJson('/webadmin/api');

    $response
      ->assertOk()
      ->assertJsonPath('product', 'WebBlocks CMS')
      ->assertJsonPath('authenticated', false)
      ->assertJsonPath('_links.self', '/webadmin/api')
      ->assertJsonMissingPath('_links.content_apply')
      ->assertJsonMissingPath('token.capabilities');

    $encoded = json_encode($response->json(), JSON_UNESCAPED_SLASHES);

    $this->assertIsString($encoded);
    $this->assertStringNotContainsString(base_path(), $encoded);
    $this->assertStringNotContainsString('secret-token', $encoded);
    $this->assertStringNotContainsString('token_hash', $encoded);
  }

  #[Test]
  public function api_discovery_returns_authenticated_links_capabilities_and_steps(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->getJson('/webadmin/api')
      ->assertOk()
      ->assertJsonPath('product', 'WebBlocks CMS')
      ->assertJsonPath('authenticated', true)
      ->assertJsonPath('cms_version', WebBlocks::version())
      ->assertJsonPath('product_version', WebBlocks::version())
      ->assertJsonPath('_links.openapi', '/webadmin/api/openapi.json')
      ->assertJsonPath('_links.ai_guide', '/webadmin/api/ai-guide')
      ->assertJsonPath('_links.content_contract', '/webadmin/api/content-contract')
      ->assertJsonPath('_links.content_validate', '/webadmin/api/content/validate')
      ->assertJsonPath('_links.content_apply', '/webadmin/api/content/apply')
      ->assertJsonFragment(['content.apply'])
      ->assertJsonMissingPath('token.token_hash')
      ->assertJsonMissingPath('token.token_preview');
  }

  #[Test]
  public function openapi_ai_guide_and_examples_are_token_protected_and_secret_safe(): void
  {
    $this->createInternalApiToken('secret-token');

    $openApi = $this->withInternalToken()
      ->getJson('/webadmin/api/openapi.json')
      ->assertOk()
      ->assertJsonPath('openapi', '3.1.0')
      ->assertJsonPath('components.securitySchemes.BearerToken.scheme', 'bearer')
      ->assertJsonPath('paths./content/validate.post.summary', 'Validate content plan');

    $guide = $this->withInternalToken()
      ->getJson('/webadmin/api/ai-guide')
      ->assertOk()
      ->assertJsonPath('format', 'markdown');

    $guideContent = $guide->json('content');

    $this->assertStringContainsString('GET /webadmin/api', (string) $guideContent);
    $this->assertStringContainsString('Authorization: Bearer <token>', (string) $guideContent);
    $this->assertStringContainsString('Do not use browser automation', (string) $guideContent);

    $example = $this->withInternalToken()
      ->getJson('/webadmin/api/examples/contact-page')
      ->assertOk()
      ->assertJsonPath('example.handle', 'contact-page')
      ->assertJsonPath('example.payload.plan.page.status', 'draft')
      ->assertJsonPath('example.payload.plan.slots.main.0.type', 'section')
      ->assertJsonPath('example.payload.plan.slots.main.0.children.1.type', 'contact_form')
      ->assertJsonPath('example.payload.plan.slots.main.0.children.1.settings.send_email_notification', true)
      ->assertJsonPath('example.payload.plan.slots.main.0.children.1.settings.store_submissions', true);

    foreach ([$openApi, $guide, $example] as $response) {
      $encoded = json_encode($response->json(), JSON_UNESCAPED_SLASHES);
      $this->assertIsString($encoded);
      $this->assertStringNotContainsString(base_path(), $encoded);
      $this->assertStringNotContainsString('secret-token', $encoded);
      $this->assertStringNotContainsString('token_hash', $encoded);
      $this->assertStringNotContainsString('mailto:', $encoded);
      $this->assertStringNotContainsString('trusted_html', $encoded);
      $this->assertStringNotContainsString('.env', $encoded);
    }
  }

  #[Test]
  public function missing_database_backed_bearer_token_is_rejected_with_json(): void
  {
    $this->getJson('/webadmin/api/sites')
      ->assertUnauthorized()
      ->assertJsonPath('ok', false)
      ->assertJsonPath('code', 'invalid_internal_api_token');
  }

  #[Test]
  public function missing_or_invalid_bearer_token_is_rejected_with_json(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->getJson('/webadmin/api/sites')
      ->assertUnauthorized()
      ->assertJsonPath('ok', false)
      ->assertJsonPath('code', 'invalid_internal_api_token');

    $this->withHeader('Authorization', 'Bearer wrong-token')
      ->getJson('/webadmin/api/sites')
      ->assertUnauthorized()
      ->assertJsonPath('ok', false)
      ->assertJsonPath('code', 'invalid_internal_api_token');
  }

  #[Test]
  public function valid_token_can_access_resource_endpoints_directly_under_webadmin_api(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->getJson('/webadmin/api/sites')
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('sites.0.handle', 'default');

    $this->withInternalToken()
      ->getJson('/webadmin/api/locales')
      ->assertOk()
      ->assertJsonPath('locales.0.code', 'en');

    $this->withInternalToken()
      ->getJson('/webadmin/api/page-layouts')
      ->assertOk()
      ->assertJsonPath('page_layouts.0.handle', 'default');

    $this->withInternalToken()
      ->getJson('/webadmin/api/block-types')
      ->assertOk()
      ->assertJsonPath('block_types.0.status', 'published');

    $this->withInternalToken()
      ->getJson('/webadmin/api/content/pages')
      ->assertNotFound();

    $this->withInternalToken()
      ->getJson('/webadmin/api/content/blocks')
      ->assertNotFound();

    $this->withInternalToken()
      ->getJson('/webadmin/api/content-plans/example')
      ->assertNotFound();
  }

  #[Test]
  public function content_contract_requires_a_valid_bearer_token(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->getJson('/webadmin/api/content-contract')
      ->assertUnauthorized()
      ->assertJsonPath('ok', false)
      ->assertJsonPath('code', 'invalid_internal_api_token');

    $this->withHeader('Authorization', 'Bearer wrong-token')
      ->getJson('/webadmin/api/content-contract')
      ->assertUnauthorized()
      ->assertJsonPath('ok', false)
      ->assertJsonPath('code', 'invalid_internal_api_token');
  }

  #[Test]
  public function content_contract_returns_safe_ai_page_building_contract_metadata(): void
  {
    $this->createInternalApiToken('secret-token');

    $response = $this->withInternalToken()
      ->getJson('/webadmin/api/content-contract');

    $response
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('api.prefix', '/webadmin/api')
      ->assertJsonPath('api.content_validate', '/webadmin/api/content/validate')
      ->assertJsonPath('api.content_apply', '/webadmin/api/content/apply')
      ->assertJsonPath('api.modes.1', 'replace_existing_draft_page')
      ->assertJsonPath('api.preview_url_template', '/webadmin/pages/{page}/preview')
      ->assertJsonPath('safety.draft_only', true)
      ->assertJsonPath('safety.apply_requires_explicit_user_approval', true)
      ->assertJsonPath('safety.publishes', false)
      ->assertJsonPath('safety.overwrites_existing_content', false)
      ->assertJsonPath('safety.draft_slot_replacement', true)
      ->assertJsonPath('draft_slot_replacement.shared_slot_backed_slots', 'rejected')
      ->assertJsonPath('safety.remote_fetch', false)
      ->assertJsonPath('safety.media_import', false)
      ->assertJsonPath('discovery.sites', '/webadmin/api/sites')
      ->assertJsonPath('discovery.locales', '/webadmin/api/locales')
      ->assertJsonPath('discovery.page_layouts', '/webadmin/api/page-layouts')
      ->assertJsonPath('discovery.block_types', '/webadmin/api/block-types')
      ->assertJsonPath('discovery.navigation_menus', '/webadmin/api/navigation-menus')
      ->assertJsonPath('discovery.shared_slots', '/webadmin/api/shared-slots')
      ->assertJsonStructure([
        'api',
        'safety',
        'discovery',
        'recommended_patterns' => ['marketing_homepage', 'avoid'],
        'block_contracts' => [
          '*' => [
            'handle',
            'slug',
            'label',
            'category',
            'status',
            'is_active',
            'is_container',
            'supports_children',
            'allowed_child_handles',
            'translatable_fields',
            'shared_settings_fields',
            'renderer_root_contract',
          ],
        ],
      ]);

    $handles = collect($response->json('block_contracts'))->pluck('handle');

    foreach (['section', 'container', 'hero', 'cta', 'card', 'card_body', 'plain_text', 'rich-text', 'button_link', 'contact_form'] as $handle) {
      $this->assertContains($handle, $handles, "Expected content contract to include [{$handle}].");
    }

    $contactFormContract = collect($response->json('block_contracts'))
      ->firstWhere('handle', 'contact_form');

    $this->assertIsArray($contactFormContract);
    $this->assertSame(['title', 'content', 'submit_label', 'success_message'], $contactFormContract['translatable_fields']);
    $this->assertSame(['settings.recipient_email', 'settings.send_email_notification', 'settings.store_submissions'], $contactFormContract['shared_settings_fields']);
    $this->assertSame('/contact-messages', $contactFormContract['public_submit_endpoint']['path']);
    $this->assertSame('required for browser submissions', $contactFormContract['public_submit_endpoint']['csrf']);
    $this->assertSame('website', $contactFormContract['spam_behavior']['honeypot_field']);
    $this->assertSame(['block recipient_email', 'site contact_recipient_email', 'CONTACT_RECIPIENT_EMAIL', 'MAIL_FROM_ADDRESS'], $contactFormContract['notification_behavior']['recipient_order']);

    $encoded = json_encode($response->json(), JSON_UNESCAPED_SLASHES);

    $this->assertIsString($encoded);
    $this->assertStringNotContainsString(base_path(), $encoded);
    $this->assertStringNotContainsString(storage_path(), $encoded);
    $this->assertStringNotContainsString(public_path(), $encoded);
    $this->assertStringNotContainsString('secret-token', $encoded);
    $this->assertStringNotContainsString('token_hash', $encoded);
    $this->assertStringNotContainsString('token_preview', $encoded);
    $this->assertStringNotContainsString('WEBBLOCKS_CMS_API_TOKEN', $encoded);
  }

  #[Test]
  public function validate_returns_normalized_plan_without_writing_content(): void
  {
    $this->createInternalApiToken('secret-token');

    $response = $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->validPlanPayload());

    $response
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('normalized_plan.page.status', 'draft')
      ->assertJsonPath('normalized_plan.slots.main.0.type', 'section');

    $this->assertDatabaseCount('pages', 0);
    $this->assertDatabaseCount('blocks', 0);
  }

  #[Test]
  public function content_write_endpoints_return_json_for_missing_invalid_and_empty_payloads(): void
  {
    $this->post('/webadmin/api/content/validate', [], ['Accept' => 'text/html'])
      ->assertUnauthorized()
      ->assertHeader('content-type', 'application/json')
      ->assertJsonPath('code', 'invalid_internal_api_token')
      ->assertJsonPath('api_discovery_url', '/webadmin/api');

    $this->withHeader('Authorization', 'Bearer wrong-token')
      ->post('/webadmin/api/content/apply', [], ['Accept' => 'text/html'])
      ->assertUnauthorized()
      ->assertHeader('content-type', 'application/json')
      ->assertJsonPath('code', 'invalid_internal_api_token');

    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', [])
      ->assertStatus(422)
      ->assertJsonPath('ok', false)
      ->assertJsonPath('api_discovery_url', '/webadmin/api')
      ->assertJsonPath('openapi_url', '/webadmin/api/openapi.json');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', [])
      ->assertStatus(422)
      ->assertJsonPath('ok', false)
      ->assertJsonPath('api_discovery_url', '/webadmin/api')
      ->assertJsonPath('openapi_url', '/webadmin/api/openapi.json');
  }

  #[Test]
  public function missing_write_capability_returns_json_403_without_csrf_or_redirect(): void
  {
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::CONTENT_READ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->validPlanPayload())
      ->assertForbidden()
      ->assertHeader('content-type', 'application/json')
      ->assertJsonPath('code', 'missing_internal_api_capability')
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::CONTENT_VALIDATE)
      ->assertJsonPath('api_discovery_url', '/webadmin/api');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->validPlanPayload())
      ->assertForbidden()
      ->assertHeader('content-type', 'application/json')
      ->assertJsonPath('code', 'missing_internal_api_capability')
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::CONTENT_APPLY)
      ->assertJsonPath('api_discovery_url', '/webadmin/api');
  }

  #[Test]
  public function phase_two_write_endpoints_return_validation_json_without_csrf(): void
  {
    $this->createInternalApiToken('secret-token');
    $site = $this->defaultSite();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);
    app(PageLayoutSlotSyncer::class)->seedInitialSlots($page, 'default');
    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Site Header',
      'handle' => 'site-header',
      'slot_name' => 'header',
      'is_active' => true,
    ]);

    foreach ([
      '/webadmin/api/navigation-menus',
      '/webadmin/api/navigation-menus/'.NavigationItem::MENU_PRIMARY.'/items',
      '/webadmin/api/shared-slots',
      '/webadmin/api/shared-slots/'.$sharedSlot->id.'/blocks',
      '/webadmin/api/pages/'.$page->id.'/slots/header/shared-slot',
    ] as $uri) {
      $this->withInternalToken()
        ->postJson($uri, [])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('ok', false);
    }
  }

  #[Test]
  public function phase_two_write_endpoints_return_capability_403_without_csrf(): void
  {
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::CONTENT_READ]);
    $site = $this->defaultSite();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);
    app(PageLayoutSlotSyncer::class)->seedInitialSlots($page, 'default');
    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Site Header',
      'handle' => 'site-header',
      'slot_name' => 'header',
      'is_active' => true,
    ]);

    foreach ([
      '/webadmin/api/navigation-menus' => CmsApiTokenCapabilities::NAVIGATION_WRITE,
      '/webadmin/api/navigation-menus/'.NavigationItem::MENU_PRIMARY.'/items' => CmsApiTokenCapabilities::NAVIGATION_WRITE,
      '/webadmin/api/shared-slots' => CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
      '/webadmin/api/shared-slots/'.$sharedSlot->id.'/blocks' => CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
      '/webadmin/api/pages/'.$page->id.'/slots/header/shared-slot' => CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
    ] as $uri => $capability) {
      $this->withInternalToken()
        ->postJson($uri, [])
        ->assertForbidden()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('code', 'missing_internal_api_capability')
        ->assertJsonPath('required_capability', $capability)
        ->assertJsonPath('api_discovery_url', '/webadmin/api');
    }
  }

  #[Test]
  public function internal_content_api_write_routes_exclude_csrf_without_weakening_admin_forms(): void
  {
    $expectedCsrfMiddleware = [
      'App\\Http\\Middleware\\VerifyCsrfToken',
      'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
      'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
      'Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken',
    ];

    $writeRouteNames = [
      'internal-content-api.content.validate',
      'internal-content-api.content.apply',
      'internal-content-api.navigation-menus.store',
      'internal-content-api.navigation-menus.items.store',
      'internal-content-api.shared-slots.store',
      'internal-content-api.shared-slots.blocks.store',
      'internal-content-api.pages.slots.shared-slot',
    ];

    foreach ($writeRouteNames as $routeName) {
      $route = Route::getRoutes()->getByName($routeName);

      $this->assertNotNull($route, 'Missing route: '.$routeName);

      foreach ($expectedCsrfMiddleware as $middleware) {
        $this->assertContains($middleware, $route->excludedMiddleware(), $routeName.' should exclude '.$middleware);
      }
    }

    $adminRoute = Route::getRoutes()->getByName('admin.system.api-tokens.store');

    $this->assertNotNull($adminRoute);

    foreach ($expectedCsrfMiddleware as $middleware) {
      $this->assertNotContains($middleware, $adminRoute->excludedMiddleware(), 'Admin forms should keep CSRF middleware: '.$middleware);
    }

    foreach ([PreventRequestForgery::class, ValidateCsrfToken::class, VerifyCsrfToken::class] as $middleware) {
      if (! class_exists($middleware)) {
        continue;
      }

      $this->assertTrue($this->csrfMiddlewareExcludesPath($middleware, '/webadmin/api/content/validate'));
      $this->assertTrue($this->csrfMiddlewareExcludesPath($middleware, '/webadmin/api/navigation-menus'));
      $this->assertFalse($this->csrfMiddlewareExcludesPath($middleware, '/webadmin/system/api-tokens'));
    }
  }

  #[Test]
  public function page_delete_requires_explicit_destructive_capability(): void
  {
    $site = $this->defaultSite();
    $locale = $this->defaultLocale();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);
    PageTranslation::query()->create([
      'page_id' => $page->id,
      'site_id' => $site->id,
      'locale_id' => $locale->id,
      'name' => 'Delete Guard',
      'slug' => 'delete-guard',
      'path' => '/delete-guard',
    ]);

    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->deleteJson('/webadmin/api/pages/'.$page->id)
      ->assertForbidden()
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::PAGES_DELETE);

    $this->assertDatabaseHas('pages', ['id' => $page->id]);

    CmsApiToken::query()->delete();
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::PAGES_DELETE]);

    $this->withInternalToken()
      ->deleteJson('/webadmin/api/pages/'.$page->id)
      ->assertOk()
      ->assertJsonPath('deleted.type', 'page')
      ->assertJsonPath('deleted.id', $page->id);

    $this->assertDatabaseMissing('pages', ['id' => $page->id]);
  }

  #[Test]
  public function openapi_page_delete_path_matches_runtime_route_and_capability_guard(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->getJson('/webadmin/api/openapi.json')
      ->assertOk()
      ->assertJsonPath('paths./pages/{page}.delete.summary', 'Delete page')
      ->assertJsonPath('paths./pages/{page}.delete.x-required-capability', CmsApiTokenCapabilities::PAGES_DELETE);
  }

  #[Test]
  public function apply_creates_a_draft_page_with_page_slots_and_translation_backed_blocks(): void
  {
    $this->createInternalApiToken('secret-token');

    $response = $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->validPlanPayload());

    $response
      ->assertCreated()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.page.status', 'draft')
      ->assertJsonPath('data.page.blocks.0.type', 'section');

    $page = Page::query()->with(['translations', 'slots', 'blocks.textTranslations'])->firstOrFail();

    $this->assertSame(Page::STATUS_DRAFT, $page->status);
    $this->assertSame('internal-api-home', $page->translations->first()->slug);
    $this->assertGreaterThan(0, $page->slots->count());
    $this->assertSame(4, $page->blocks->count());
    $this->assertSame(0, NavigationItem::query()->count());
    $this->assertSame(0, SharedSlot::query()->count());

    $plainText = $page->blocks->firstWhere('type', 'plain_text');

    $this->assertNotNull($plainText);
    $this->assertNull($plainText->getRawOriginal('content'));
    $this->assertSame('Structured draft content.', $plainText->textTranslations->first()->content);
  }

  #[Test]
  public function validate_existing_draft_page_replacement_previews_without_writing(): void
  {
    $this->createInternalApiToken('secret-token');
    [$page, $mainBlock] = $this->createDraftPageWithMainAndSharedChrome();

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->replacementPlanPayload($page))
      ->assertOk()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('normalized_plan.mode', 'replace_existing_draft_page')
      ->assertJsonPath('normalized_plan.replace_page.id', $page->id)
      ->assertJsonPath('normalized_plan.replace_slots.main.0.type', 'plain_text');

    $this->assertDatabaseHas('blocks', ['id' => $mainBlock->id, 'content' => 'Old main copy']);
    $this->assertDatabaseMissing('blocks', ['content' => 'New main copy']);
    $this->assertSame(1, Block::query()->where('page_id', $page->id)->where('slot', 'main')->count());
    $this->assertSame(0, PageRevision::query()->count());
  }

  #[Test]
  public function apply_existing_draft_page_replacement_replaces_only_page_owned_target_slot(): void
  {
    $this->createInternalApiToken('secret-token');
    [$page, $mainBlock, $sharedSlot] = $this->createDraftPageWithMainAndSharedChrome();

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->replacementPlanPayload($page))
      ->assertCreated()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('normalized_plan.mode', 'replace_existing_draft_page')
      ->assertJsonPath('data.page.id', $page->id)
      ->assertJsonPath('data.page.blocks.0.type', 'plain_text');

    $this->assertDatabaseMissing('blocks', ['id' => $mainBlock->id]);
    $this->assertDatabaseHas('blocks', [
      'page_id' => $page->id,
      'slot' => 'main',
      'content' => null,
    ]);
    $this->assertDatabaseHas('block_text_translations', ['content' => 'New main copy']);
    $this->assertDatabaseHas('page_slots', [
      'page_id' => $page->id,
      'slot_type_id' => $this->slotTypeId('header'),
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sharedSlot->id,
    ]);
    $this->assertSame(2, PageRevision::query()->where('page_id', $page->id)->count());
  }

  #[Test]
  public function existing_draft_page_replacement_requires_matching_safety_guard(): void
  {
    $this->createInternalApiToken('secret-token');
    [$page] = $this->createDraftPageWithMainAndSharedChrome();

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->replacementPlanPayload($page, [
        'plan' => [
          'page' => [
            'expected_path' => '/wrong',
          ],
        ],
      ]))
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Expected path does not match the existing page translation.']);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->replacementPlanPayload($page, [
        'plan' => [
          'page' => [
            'expected_path' => null,
            'expected_updated_at' => now()->subDay()->toIso8601String(),
          ],
        ],
      ]))
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Expected updated_at does not match the existing page.']);
  }

  #[Test]
  public function existing_page_replacement_rejects_published_pages_and_shared_slot_backed_slots(): void
  {
    $this->createInternalApiToken('secret-token');
    [$page] = $this->createDraftPageWithMainAndSharedChrome();

    $page->update(['status' => Page::STATUS_PUBLISHED]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->replacementPlanPayload($page))
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Existing page replacement is draft-only. Published pages are not supported.']);

    $page->update(['status' => Page::STATUS_DRAFT]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->replacementPlanPayload($page, [
        'plan' => [
          'replace_slots' => [
            'header' => [
              [
                'type' => 'plain_text',
                'translations' => ['content' => 'Do not touch shared header'],
              ],
            ],
          ],
        ],
      ]))
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Shared-slot-backed slots cannot be replaced by this operation.']);
  }

  #[Test]
  public function resource_endpoints_return_created_page_and_block_details(): void
  {
    $this->createInternalApiToken('secret-token');

    $create = $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->validPlanPayload());

    $pageId = $create->json('data.page.id');
    $blockId = Block::query()->where('type', 'plain_text')->value('id');

    $this->withInternalToken()
      ->getJson('/webadmin/api/pages')
      ->assertOk()
      ->assertJsonPath('pages.0.id', $pageId);

    $this->withInternalToken()
      ->getJson('/webadmin/api/pages/'.$pageId)
      ->assertOk()
      ->assertJsonPath('page.blocks.0.type', 'section');

    $this->withInternalToken()
      ->getJson('/webadmin/api/blocks')
      ->assertOk()
      ->assertJsonPath('blocks.0.type', 'section');

    $this->withInternalToken()
      ->getJson('/webadmin/api/blocks/'.$blockId)
      ->assertOk()
      ->assertJsonPath('block.type', 'plain_text');
  }

  #[Test]
  public function apply_rejects_publish_status_and_phase_one_exclusions_without_writing(): void
  {
    $this->createInternalApiToken('secret-token');
    $payload = $this->validPlanPayload([
      'plan' => [
        'page' => [
          'status' => 'published',
        ],
        'navigation' => [
          'items' => [],
        ],
        'shared_slots' => [
          'header' => [],
        ],
        'media_import' => [
          'url' => 'https://example.test/image.png',
        ],
        'remote_fetch' => 'https://example.test',
        'overwrite' => true,
      ],
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $payload)
      ->assertStatus(422)
      ->assertJsonPath('ok', false)
      ->assertJsonFragment(['message' => 'Phase 1 can only create draft pages.'])
      ->assertJsonFragment(['message' => 'This operation is outside Internal Content API Phase 1.']);

    $this->assertDatabaseCount('pages', 0);
    $this->assertDatabaseCount('blocks', 0);
    $this->assertDatabaseCount('navigation_items', 0);
    $this->assertDatabaseCount('shared_slots', 0);
  }

  #[Test]
  public function apply_rejects_existing_page_path_to_prevent_overwrite(): void
  {
    $this->createInternalApiToken('secret-token');
    $site = $this->defaultSite();
    $locale = $this->defaultLocale();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);
    PageTranslation::query()->create([
      'page_id' => $page->id,
      'site_id' => $site->id,
      'locale_id' => $locale->id,
      'name' => 'Existing Page',
      'slug' => 'internal-api-home',
      'path' => '/internal-api-home',
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->validPlanPayload())
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'A page already exists at this path for the selected site and locale.']);

    $this->assertSame(1, Page::query()->count());
  }

  #[Test]
  public function root_path_normalizes_to_existing_home_slug_model(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->validPlanPayload([
        'plan' => [
          'page' => [
            'title' => 'Homepage Draft',
            'path' => '/',
          ],
        ],
      ]))
      ->assertOk()
      ->assertJsonPath('normalized_plan.page.slug', 'home')
      ->assertJsonPath('normalized_plan.page.path', '/');
  }

  #[Test]
  public function new_phase_two_endpoints_keep_database_token_json_guards(): void
  {
    $this->getJson('/webadmin/api/navigation-menus')
      ->assertUnauthorized()
      ->assertJsonPath('code', 'invalid_internal_api_token');

    $this->createInternalApiToken('secret-token');

    $this->getJson('/webadmin/api/shared-slots')
      ->assertUnauthorized()
      ->assertJsonPath('code', 'invalid_internal_api_token');
  }

  #[Test]
  public function valid_token_can_list_and_create_site_scoped_navigation_menu_items(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->getJson('/webadmin/api/navigation-menus?site=default')
      ->assertOk()
      ->assertJsonPath('navigation_menus.0.handle', NavigationItem::MENU_PRIMARY);

    $this->withInternalToken()
      ->postJson('/webadmin/api/navigation-menus', [
        'site' => 'default',
        'handle' => NavigationItem::MENU_PRIMARY,
        'label' => 'Primary Navigation',
        'items' => [
          ['label' => 'Home', 'url' => '/', 'target' => '_self', 'sort_order' => 10],
        ],
      ])
      ->assertCreated()
      ->assertJsonPath('navigation_menu.items.0.label', 'Home');

    $this->assertDatabaseHas('navigation_items', [
      'site_id' => $this->defaultSite()->id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Home',
      'url' => '/',
    ]);
  }

  #[Test]
  public function duplicate_navigation_menu_and_unsafe_navigation_urls_are_rejected(): void
  {
    $this->createInternalApiToken('secret-token');

    NavigationItem::query()->create([
      'site_id' => $this->defaultSite()->id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Existing',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/',
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/navigation-menus', [
        'site' => 'default',
        'handle' => NavigationItem::MENU_PRIMARY,
        'label' => 'Primary Navigation',
        'items' => [],
      ])
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Navigation menu already has items and will not be overwritten.']);

    $this->withInternalToken()
      ->postJson('/webadmin/api/navigation-menus/'.NavigationItem::MENU_FOOTER.'/items', [
        'site' => 'default',
        'label' => 'Bad',
        'url' => 'javascript:alert(1)',
      ])
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Navigation item URL must be a safe internal path or http(s) URL.']);

    $this->assertSame(1, NavigationItem::query()->count());
  }

  #[Test]
  public function valid_token_can_create_shared_slot_and_add_translation_backed_blocks(): void
  {
    $this->createInternalApiToken('secret-token');

    $response = $this->withInternalToken()
      ->postJson('/webadmin/api/shared-slots', [
        'site' => 'default',
        'locale' => 'en',
        'handle' => 'site-header',
        'label' => 'Site Header',
        'slot' => 'header',
        'blocks' => [],
      ])
      ->assertCreated()
      ->assertJsonPath('shared_slot.handle', 'site-header');

    $sharedSlotId = $response->json('shared_slot.id');

    $this->withInternalToken()
      ->postJson('/webadmin/api/shared-slots/'.$sharedSlotId.'/blocks', [
        'locale' => 'en',
        'type' => 'plain_text',
        'translations' => ['content' => 'Reusable header copy'],
      ])
      ->assertCreated()
      ->assertJsonPath('block.type', 'plain_text');

    $block = Block::query()->where('type', 'plain_text')->firstOrFail();

    $this->assertSame('Reusable header copy', $block->textTranslations()->firstOrFail()->content);
    $this->assertDatabaseHas('shared_slot_blocks', ['shared_slot_id' => $sharedSlotId, 'block_id' => $block->id]);
  }

  #[Test]
  public function duplicate_shared_slot_handle_does_not_overwrite_existing_content(): void
  {
    $this->createInternalApiToken('secret-token');

    SharedSlot::query()->create([
      'site_id' => $this->defaultSite()->id,
      'name' => 'Existing Header',
      'handle' => 'site-header',
      'slot_name' => 'header',
      'is_active' => true,
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/shared-slots', [
        'site' => 'default',
        'locale' => 'en',
        'handle' => 'site-header',
        'label' => 'Replacement Header',
        'slot' => 'header',
      ])
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'A Shared Slot with this handle already exists for the selected site.']);

    $this->assertDatabaseHas('shared_slots', ['handle' => 'site-header', 'name' => 'Existing Header']);
    $this->assertDatabaseMissing('shared_slots', ['name' => 'Replacement Header']);
  }

  #[Test]
  public function shared_slot_assignment_rejects_cross_site_and_page_owned_blocks_without_deleting(): void
  {
    $this->createInternalApiToken('secret-token');
    $site = $this->defaultSite();
    $otherSite = Site::query()->create([
      'name' => 'Other Site',
      'handle' => 'other',
      'domain' => 'other.test',
      'is_primary' => false,
    ]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);
    app(PageLayoutSlotSyncer::class)->seedInitialSlots($page, 'default');
    $headerSlot = $page->slots()->with('slotType')->get()->first(fn (PageSlot $slot) => $slot->slotSlug() === 'header');
    $headerSlotType = $headerSlot->slotType;

    $block = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => BlockType::query()->where('slug', 'plain_text')->value('id'),
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot_type_id' => $headerSlotType->id,
      'slot' => 'header',
      'status' => 'draft',
      'sort_order' => 0,
    ]);

    $crossSiteSharedSlot = SharedSlot::query()->create([
      'site_id' => $otherSite->id,
      'name' => 'Other Header',
      'handle' => 'site-header',
      'slot_name' => 'header',
      'is_active' => true,
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/pages/'.$page->id.'/slots/header/shared-slot', ['shared_slot_id' => $crossSiteSharedSlot->id])
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Shared Slot must belong to the same site as the page.']);

    $sameSiteSharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Site Header',
      'handle' => 'site-header',
      'slot_name' => 'header',
      'is_active' => true,
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/pages/'.$page->id.'/slots/header/shared-slot', ['shared_slot_id' => $sameSiteSharedSlot->id])
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Page slot contains page-owned blocks and must be cleared manually before Shared Slot assignment.']);

    $this->assertDatabaseHas('blocks', ['id' => $block->id]);
    $this->assertDatabaseHas('page_slots', [
      'id' => $headerSlot->id,
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'shared_slot_id' => null,
    ]);
  }

  #[Test]
  public function valid_token_can_assign_compatible_shared_slot_to_empty_page_slot(): void
  {
    $this->createInternalApiToken('secret-token');
    $site = $this->defaultSite();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);
    app(PageLayoutSlotSyncer::class)->seedInitialSlots($page, 'default');

    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Site Header',
      'handle' => 'site-header',
      'slot_name' => 'header',
      'is_active' => true,
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/pages/'.$page->id.'/slots/header/shared-slot', ['shared_slot' => 'site-header'])
      ->assertOk()
      ->assertJsonPath('page_slot.source_type', PageSlot::SOURCE_TYPE_SHARED_SLOT)
      ->assertJsonPath('page_slot.shared_slot_id', $sharedSlot->id);

    $this->assertDatabaseHas('page_slots', [
      'page_id' => $page->id,
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sharedSlot->id,
    ]);
  }

  #[Test]
  public function content_plan_validates_navigation_and_shared_slots_without_writing(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->phaseTwoPlanPayload())
      ->assertOk()
      ->assertJsonPath('normalized_plan.navigation_menus.0.handle', NavigationItem::MENU_PRIMARY)
      ->assertJsonPath('normalized_plan.shared_slots.0.handle', 'site-header');

    $this->assertDatabaseCount('navigation_items', 0);
    $this->assertDatabaseCount('shared_slots', 0);
  }

  #[Test]
  public function content_apply_transactionally_creates_navigation_and_shared_slots_and_rolls_back_on_late_failure(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->phaseTwoPlanPayload())
      ->assertCreated()
      ->assertJsonPath('ok', true);

    $this->assertDatabaseHas('navigation_items', ['title' => 'Home', 'url' => '/']);
    $this->assertDatabaseHas('shared_slots', ['handle' => 'site-header']);

    $failingPayload = $this->phaseTwoPlanPayload([
      'plan' => [
        'page' => ['path' => '/phase-two-rollback'],
        'navigation_menus' => [
          [
            'handle' => NavigationItem::MENU_FOOTER,
            'label' => 'Footer Navigation',
            'items' => [['label' => 'Unsafe', 'url' => '/safe']],
          ],
        ],
        'shared_slots' => [
          [
            'handle' => 'site-footer',
            'label' => 'Site Footer',
            'slot' => 'footer',
            'blocks' => [],
          ],
        ],
        'page_slot_shared_slots' => [
          ['page' => 'created', 'slot' => 'header', 'shared_slot' => 'site-footer'],
        ],
      ],
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $failingPayload)
      ->assertStatus(422);

    $this->assertDatabaseMissing('navigation_items', ['menu_key' => NavigationItem::MENU_FOOTER, 'title' => 'Unsafe']);
    $this->assertDatabaseMissing('shared_slots', ['handle' => 'site-footer']);
  }

  private function validPlanPayload(array $overrides = []): array
  {
    $base = [
      'plan' => [
        'site' => $this->defaultSite()->handle,
        'locale' => $this->defaultLocale()->code,
        'layout' => 'default',
        'page' => [
          'title' => 'Internal API Home',
          'path' => '/internal-api-home',
          'status' => 'draft',
        ],
        'slots' => [
          'main' => [
            [
              'type' => 'section',
              'settings' => [
                'spacing' => 'lg',
              ],
              'children' => [
                [
                  'type' => 'container',
                  'children' => [
                    [
                      'type' => 'plain_text',
                      'translations' => [
                        'content' => 'Structured draft content.',
                      ],
                      'settings' => [
                        'alignment' => 'center',
                      ],
                    ],
                    [
                      'type' => 'button_link',
                      'translations' => [
                        'title' => 'Read more',
                      ],
                      'settings' => [
                        'url' => '/learn',
                        'variant' => 'primary',
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    return array_replace_recursive($base, $overrides);
  }

  private function phaseTwoPlanPayload(array $overrides = []): array
  {
    $base = [
      'plan' => [
        'site' => $this->defaultSite()->handle,
        'locale' => $this->defaultLocale()->code,
        'layout' => 'default',
        'page' => [
          'title' => 'Internal API Phase Two',
          'path' => '/internal-api-phase-two',
          'status' => 'draft',
        ],
        'slots' => [
          'main' => [],
        ],
        'navigation_menus' => [
          [
            'handle' => NavigationItem::MENU_PRIMARY,
            'label' => 'Primary Navigation',
            'items' => [
              ['label' => 'Home', 'url' => '/', 'target' => '_self', 'sort_order' => 10],
            ],
          ],
        ],
        'shared_slots' => [
          [
            'handle' => 'site-header',
            'label' => 'Site Header',
            'slot' => 'header',
            'blocks' => [
              [
                'type' => 'plain_text',
                'translations' => ['content' => 'Shared header'],
              ],
            ],
          ],
        ],
      ],
    ];

    return array_replace_recursive($base, $overrides);
  }

  private function replacementPlanPayload(Page $page, array $overrides = []): array
  {
    $base = [
      'plan' => [
        'mode' => 'replace_existing_draft_page',
        'site' => $this->defaultSite()->handle,
        'locale' => $this->defaultLocale()->code,
        'page' => [
          'id' => $page->id,
          'expected_path' => '/p/existing-contact',
          'status' => 'draft',
        ],
        'replace_slots' => [
          'main' => [
            [
              'type' => 'plain_text',
              'translations' => [
                'content' => 'New main copy',
              ],
            ],
          ],
        ],
      ],
    ];

    return array_replace_recursive($base, $overrides);
  }

  private function createDraftPageWithMainAndSharedChrome(): array
  {
    $site = $this->defaultSite();
    $locale = $this->defaultLocale();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);

    PageTranslation::query()->create([
      'page_id' => $page->id,
      'site_id' => $site->id,
      'locale_id' => $locale->id,
      'name' => 'Existing Contact',
      'slug' => 'existing-contact',
      'path' => '/p/existing-contact',
    ]);

    app(PageLayoutSlotSyncer::class)->seedInitialSlots($page, 'default');

    $mainBlock = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $this->blockTypeId('plain_text'),
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot_type_id' => $this->slotTypeId('main'),
      'slot' => 'main',
      'status' => 'draft',
      'sort_order' => 0,
      'content' => 'Old main copy',
    ]);

    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Site Header',
      'handle' => 'site-header',
      'slot_name' => 'header',
      'is_active' => true,
    ]);

    PageSlot::query()
      ->where('page_id', $page->id)
      ->where('slot_type_id', $this->slotTypeId('header'))
      ->firstOrFail()
      ->update([
        'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
        'shared_slot_id' => $sharedSlot->id,
      ]);

    return [$page->fresh(['translations.locale', 'slots.slotType']), $mainBlock, $sharedSlot];
  }

  private function blockTypeId(string $slug): int
  {
    return (int) BlockType::query()->where('slug', $slug)->value('id');
  }

  private function slotTypeId(string $slug): int
  {
    return (int) SlotType::query()->where('slug', $slug)->value('id');
  }

  private function defaultSite(): Site
  {
    return Site::query()->where('is_primary', true)->firstOrFail();
  }

  private function defaultLocale(): Locale
  {
    return Locale::query()->where('is_default', true)->firstOrFail();
  }

  private function createInternalApiToken(string $token, ?array $capabilities = null): void
  {
    CmsApiToken::query()->create([
      'name' => 'Test token',
      'token_hash' => app(CmsApiTokenIssuer::class)->hash($token),
      'token_preview' => app(CmsApiTokenIssuer::class)->preview($token),
      'capabilities' => $capabilities,
    ]);
  }

  private function withInternalToken(): self
  {
    return $this->withHeader('Authorization', 'Bearer secret-token');
  }

  /**
   * @param  class-string  $middleware
   */
  private function csrfMiddlewareExcludesPath(string $middleware, string $path): bool
  {
    $instance = new $middleware($this->app, $this->app->make('encrypter'));
    $request = Request::create($path, 'POST', server: [
      'HTTP_ACCEPT' => 'application/json',
      'CONTENT_TYPE' => 'application/json',
    ]);

    $reflection = new ReflectionClass($instance);
    $method = $reflection->getMethod('inExceptArray');
    $method->setAccessible(true);

    return (bool) $method->invoke($instance, $request);
  }
}
