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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotBlock;
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
      ->assertJsonPath('_links.media', '/webadmin/api/media')
      ->assertJsonPath('_links.media_update', '/webadmin/api/media/{media}')
      ->assertJsonPath('_links.block_update', '/webadmin/api/blocks/{block}')
      ->assertJsonPath('token.can.read_media', true)
      ->assertJsonPath('token.can.write_media_metadata', false)
      ->assertJsonPath('token.can.promote_staged_update', false)
      ->assertJsonPath('workflows.published_page_staged_update.available.promote', false)
      ->assertJsonPath('workflows.published_page_staged_update.do_not_use.0', 'POST /webadmin/api/pages/{staged_page}/publish')
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
      ->assertJsonPath('paths./content/validate.post.summary', 'Validate content plan')
      ->assertJsonPath('paths./content/apply.post.x-supported-modes.2', 'create_staged_update_for_published_page')
      ->assertJsonPath('paths./content/apply.post.x-supported-modes.4', 'promote_staged_page_update')
      ->assertJsonPath('paths./content/apply.post.x-mode-capabilities.promote_staged_page_update', 'content.publish plus content.apply')
      ->assertJsonPath('paths./media.get.summary', 'List Media items for API-safe media assignment')
      ->assertJsonPath('paths./media/{media}.patch.summary', 'Update safe Media Library metadata')
      ->assertJsonPath('paths./media/{media}.patch.x-required-capability', CmsApiTokenCapabilities::MEDIA_WRITE)
      ->assertJsonPath('paths./blocks/{block}.patch.summary', 'Update safe fields on an existing structured block');

    $guide = $this->withInternalToken()
      ->getJson('/webadmin/api/ai-guide')
      ->assertOk()
      ->assertJsonPath('format', 'markdown');

    $guideContent = $guide->json('content');

    $this->assertStringContainsString('GET /webadmin/api', (string) $guideContent);
    $this->assertStringContainsString('Authorization: Bearer <token>', (string) $guideContent);
    $this->assertStringContainsString('Do not use browser automation', (string) $guideContent);
    $this->assertStringContainsString('page._actions.promote', (string) $guideContent);

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
      ->assertJsonPath('sites.0.handle', 'default')
      ->assertJsonPath('sites.0.public_theme_preset', Site::PUBLIC_THEME_CANVAS);

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
  public function valid_token_can_update_safe_site_public_theme_preset(): void
  {
    $this->createInternalApiToken('secret-token');
    $site = $this->defaultSite();

    $this->withInternalToken()
      ->postJson('/webadmin/api/sites/'.$site->id.'/public-theme', [
        'public_theme_preset' => 'canvas',
      ])
      ->assertOk()
      ->assertJsonPath('site.id', $site->id)
      ->assertJsonPath('site.public_theme_preset', 'canvas')
      ->assertJsonPath('writes.0.type', 'site_public_theme_preset');

    $this->assertDatabaseHas('sites', [
      'id' => $site->id,
      'public_theme_preset' => 'canvas',
    ]);
  }

  #[Test]
  public function invalid_site_public_theme_preset_is_rejected(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->postJson('/webadmin/api/sites/'.$this->defaultSite()->id.'/public-theme', [
        'public_theme_preset' => 'midnight-hack',
      ])
      ->assertStatus(422)
      ->assertJsonPath('ok', false)
      ->assertJsonFragment(['path' => 'site.public_theme_preset']);
  }

  #[Test]
  public function valid_token_can_sync_missing_page_layout_slots_before_shared_slot_assignment(): void
  {
    $this->createInternalApiToken('secret-token');
    $site = $this->defaultSite();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $this->slotTypeId('main'),
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'sort_order' => 0,
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/pages/'.$page->id.'/sync-layout-slots')
      ->assertOk()
      ->assertJsonPath('added_count', 3)
      ->assertJsonPath('page.slots.0.slot', 'main')
      ->assertJsonFragment(['slot' => 'header'])
      ->assertJsonFragment(['slot' => 'sidebar'])
      ->assertJsonFragment(['slot' => 'footer']);

    $this->assertDatabaseHas('page_slots', [
      'page_id' => $page->id,
      'slot_type_id' => $this->slotTypeId('header'),
    ]);
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
      ->assertJsonPath('api.modes.2', 'create_staged_update_for_published_page')
      ->assertJsonPath('api.modes.4', 'promote_staged_page_update')
      ->assertJsonPath('api.preview_url_template', '/webadmin/pages/{page}/preview')
      ->assertJsonPath('safety.draft_only', false)
      ->assertJsonPath('safety.apply_requires_explicit_user_approval', true)
      ->assertJsonPath('safety.publishes', false)
      ->assertJsonPath('safety.overwrites_existing_content', false)
      ->assertJsonPath('safety.draft_slot_replacement', true)
      ->assertJsonPath('safety.published_page_staged_updates', true)
      ->assertJsonPath('draft_slot_replacement.shared_slot_backed_slots', 'rejected')
      ->assertJsonPath('published_page_staged_updates.promote_requires_capability', 'content.apply + content.publish')
      ->assertJsonPath('published_page_staged_updates.shared_slot_backed_slots', 'rejected for replace/promote')
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
    $this->assertSame('renderer-generated form_check_{token} field signed by _form_check_name', $contactFormContract['spam_behavior']['check_field']);
    $this->assertSame(['block recipient_email', 'site contact_recipient_email', 'CONTACT_RECIPIENT_EMAIL', 'MAIL_FROM_ADDRESS'], $contactFormContract['notification_behavior']['recipient_order']);

    $contentHeaderContract = collect($response->json('block_contracts'))
      ->firstWhere('handle', 'content_header');
    $plainTextContract = collect($response->json('block_contracts'))
      ->firstWhere('handle', 'plain_text');

    $this->assertIsArray($contentHeaderContract);
    $this->assertContains('settings.icon_tone', $contentHeaderContract['shared_settings_fields']);
    $this->assertIsArray($plainTextContract);
    $this->assertNotContains('settings.icon_tone', $plainTextContract['shared_settings_fields']);

    $encoded = json_encode($response->json(), JSON_UNESCAPED_SLASHES);

    $this->assertIsString($encoded);
    $this->assertStringNotContainsString('"website"', $encoded);
    $this->assertStringNotContainsString(base_path(), $encoded);
    $this->assertStringNotContainsString(storage_path(), $encoded);
    $this->assertStringNotContainsString(public_path(), $encoded);
    $this->assertStringNotContainsString('secret-token', $encoded);
    $this->assertStringNotContainsString('token_hash', $encoded);
    $this->assertStringNotContainsString('token_preview', $encoded);
    $this->assertStringNotContainsString('WEBBLOCKS_CMS_API_TOKEN', $encoded);
  }

  #[Test]
  public function content_validate_accepts_supported_icon_tones_and_rejects_invalid_or_unsupported_icon_tones(): void
  {
    $this->createInternalApiToken('secret-token');

    $validPayload = $this->validPlanPayload([
      'plan' => [
        'slots' => [
          'main' => [
            [
              'children' => [
                [
                  'children' => [
                    [
                      'type' => 'content_header',
                      'translations' => [
                        'title' => 'Docs',
                      ],
                      'settings' => [
                        'icon_slug' => 'sparkles',
                        'icon_tone' => 'brand',
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $validPayload)
      ->assertOk()
      ->assertJsonPath('normalized_plan.slots.main.0.children.0.children.0.settings.icon_tone', 'brand');

    $defaultPayload = $this->validPlanPayload([
      'plan' => [
        'slots' => [
          'main' => [
            [
              'children' => [
                [
                  'children' => [
                    [
                      'type' => 'content_header',
                      'translations' => [
                        'title' => 'Docs',
                      ],
                      'settings' => [
                        'icon_tone' => 'default',
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $defaultPayload)
      ->assertOk()
      ->assertJsonMissingPath('normalized_plan.slots.main.0.children.0.children.0.settings.icon_tone');

    $invalidPayload = $this->validPlanPayload([
      'plan' => [
        'slots' => [
          'main' => [
            [
              'children' => [
                [
                  'children' => [
                    [
                      'type' => 'content_header',
                      'translations' => [
                        'title' => 'Docs',
                      ],
                      'settings' => [
                        'icon_tone' => 'success',
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $invalidPayload)
      ->assertStatus(422)
      ->assertJsonPath('errors.0.path', 'plan.slots.main.0.children.0.children.0.settings.icon_tone');

    $unsupportedPayload = $this->validPlanPayload([
      'plan' => [
        'slots' => [
          'main' => [
            [
              'children' => [
                [
                  'children' => [
                    [
                      'settings' => [
                        'icon_tone' => 'brand',
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $unsupportedPayload)
      ->assertStatus(422)
      ->assertJsonPath('errors.0.path', 'plan.slots.main.0.children.0.children.0.settings.icon_tone');
  }

  #[Test]
  public function content_validate_rejects_flat_parent_references_for_block_trees(): void
  {
    $this->createInternalApiToken('secret-token');

    $payload = $this->validPlanPayload();
    $payload['plan']['slots']['main'] = [
      [
        'id' => 'hero-section',
        'type' => 'section',
      ],
      [
        'id' => 'hero-container',
        'parent_id' => 'hero-section',
        'type' => 'container',
      ],
      [
        'parent_id' => 'hero-container',
        'type' => 'plain_text',
        'translations' => [
          'content' => 'This looks nested but is actually flat.',
        ],
      ],
    ];

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $payload)
      ->assertStatus(422)
      ->assertJsonPath('ok', false)
      ->assertJsonFragment([
        'path' => 'plan.slots.main.0.id',
        'message' => 'Content plans do not accept flat block relationship fields. Nest child blocks inside the parent block children array instead.',
      ])
      ->assertJsonFragment([
        'path' => 'plan.slots.main.1.parent_id',
        'message' => 'Content plans do not accept flat block relationship fields. Nest child blocks inside the parent block children array instead.',
      ]);
  }

  #[Test]
  public function content_validate_rejects_locale_keyed_block_translations(): void
  {
    $this->createInternalApiToken('secret-token');

    $payload = $this->validPlanPayload();
    $payload['plan']['slots']['main'][0]['children'][0]['children'][0] = [
      'type' => 'content_header',
      'translations' => [
        'en' => [
          'title' => 'Locale-keyed title',
        ],
      ],
    ];

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $payload)
      ->assertStatus(422)
      ->assertJsonPath('ok', false)
      ->assertJsonPath('errors.0.path', 'plan.slots.main.0.children.0.children.0.translations.en');
  }

  #[Test]
  public function content_validate_rejects_wrapper_blocks_without_children(): void
  {
    $this->createInternalApiToken('secret-token');

    $payload = $this->validPlanPayload();
    $payload['plan']['slots']['main'] = [
      [
        'type' => 'section',
        'settings' => [
          'spacing' => 'lg',
        ],
      ],
    ];

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $payload)
      ->assertStatus(422)
      ->assertJsonPath('ok', false)
      ->assertJsonPath('errors.0.path', 'plan.slots.main.0.children')
      ->assertJsonPath('renderability.wrapper_blocks_without_children', 1);
  }

  #[Test]
  public function content_validate_returns_renderability_summary_for_nested_native_blocks(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->validPlanPayload())
      ->assertOk()
      ->assertJsonPath('renderability.root_blocks', 1)
      ->assertJsonPath('renderability.total_blocks', 4)
      ->assertJsonPath('renderability.html_blocks', 0)
      ->assertJsonPath('renderability.wrapper_blocks_without_children', 0)
      ->assertJsonPath('renderability.text_blocks_without_visible_content', 0)
      ->assertJsonPath('renderability.button_blocks_without_label_or_url', 0);
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
    $media = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/logo.png',
      'filename' => 'logo.png',
      'original_name' => 'logo.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1024,
      'kind' => Media::KIND_IMAGE,
      'visibility' => 'public',
      'title' => 'Logo',
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
    $media = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/logo.png',
      'filename' => 'logo.png',
      'original_name' => 'logo.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1024,
      'kind' => Media::KIND_IMAGE,
      'visibility' => 'public',
      'title' => 'Logo',
    ]);

    foreach ([
      '/webadmin/api/navigation-menus' => CmsApiTokenCapabilities::NAVIGATION_WRITE,
      '/webadmin/api/navigation-menus/'.NavigationItem::MENU_PRIMARY.'/items' => CmsApiTokenCapabilities::NAVIGATION_WRITE,
      '/webadmin/api/shared-slots' => CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
      '/webadmin/api/shared-slots/'.$sharedSlot->id.'/blocks' => CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
      '/webadmin/api/shared-slots/'.$sharedSlot->id.'/publish-blocks' => CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
      '/webadmin/api/pages/'.$page->id.'/slots/header/shared-slot' => CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
      '/webadmin/api/sites/'.$site->id.'/public-theme' => CmsApiTokenCapabilities::SITE_SETTINGS_WRITE,
      '/webadmin/api/pages/'.$page->id.'/sync-layout-slots' => CmsApiTokenCapabilities::CONTENT_APPLY,
      '/webadmin/api/media/'.$media->id => CmsApiTokenCapabilities::MEDIA_WRITE,
    ] as $uri => $capability) {
      $this->withInternalToken()
        ->json(str_contains($uri, '/media/') ? 'PATCH' : 'POST', $uri, [])
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
      'internal-content-api.shared-slots.blocks.publish',
      'internal-content-api.pages.slots.shared-slot',
      'internal-content-api.sites.public-theme.update',
      'internal-content-api.pages.layout-slots.sync',
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
      $this->assertTrue($this->csrfMiddlewareExcludesPath($middleware, '/webadmin/api/sites/1/public-theme'));
      $this->assertTrue($this->csrfMiddlewareExcludesPath($middleware, '/webadmin/api/pages/1/sync-layout-slots'));
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
  public function published_page_direct_replacement_remains_rejected(): void
  {
    $this->createInternalApiToken('secret-token');
    [$page] = $this->createDraftPageWithMainAndSharedChrome('/docs');
    $page->forceFill(['status' => Page::STATUS_PUBLISHED, 'published_at' => now()])->save();

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->replacementPlanPayload($page))
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Existing page replacement is draft-only. Published pages are not supported.']);
  }

  #[Test]
  public function staged_update_create_copies_published_page_without_changing_public_source(): void
  {
    $this->createInternalApiToken('secret-token');
    [$page, $mainBlock, $sharedSlot] = $this->createDraftPageWithMainAndSharedChrome('/docs');
    $sourceSync = $this->sourceSyncPayload();
    $page->forceFill([
      'status' => Page::STATUS_PUBLISHED,
      'published_at' => now(),
      'settings' => ['source_sync' => $sourceSync],
    ])->save();

    $response = $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', [
        'plan' => [
          'mode' => 'create_staged_update_for_published_page',
          'site' => 'default',
          'locale' => 'en',
          'page' => ['id' => $page->id],
          'expected_source_path' => '/docs',
          'managed_slots' => ['main'],
        ],
      ])
      ->assertCreated()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.source_page.id', $page->id)
      ->assertJsonPath('data.staged_page.status', Page::STATUS_DRAFT)
      ->assertJsonPath('data.staged_page.staged_update.source_page_id', $page->id)
      ->assertJsonPath('data.staged_page.staged_update.managed_slots.0', 'main')
      ->assertJsonPath('data.preview_url', '/webadmin/pages/'.Page::query()->whereKeyNot($page->id)->latest('id')->value('id').'/preview');

    $stagedPageId = $response->json('data.staged_page.id');
    $stagedPage = Page::query()->with(['translations', 'slots.slotType'])->findOrFail($stagedPageId);

    $this->withInternalToken()
      ->getJson('/webadmin/api/pages/'.$stagedPageId)
      ->assertOk()
      ->assertJsonPath('page._actions.promote.method', 'POST')
      ->assertJsonPath('page._actions.promote.url', '/webadmin/api/content/apply')
      ->assertJsonPath('page._actions.promote.available', false)
      ->assertJsonPath('page._actions.promote.required_capabilities.0', CmsApiTokenCapabilities::CONTENT_APPLY)
      ->assertJsonPath('page._actions.promote.required_capabilities.1', CmsApiTokenCapabilities::CONTENT_PUBLISH)
      ->assertJsonPath('page._actions.promote.body.plan.mode', 'promote_staged_page_update')
      ->assertJsonPath('page._actions.promote.body.plan.staged_page_id', $stagedPageId)
      ->assertJsonPath('page._actions.promote.body.plan.expected_source_page_id', $page->id)
      ->assertJsonPath('page._actions.promote.body.plan.expected_source_path', '/docs')
      ->assertJsonPath('page._actions.promote.body.plan.promote_slots.0', 'main')
      ->assertJsonPath('page._actions.page_publish.available', false);

    $this->assertSame(Page::STATUS_PUBLISHED, $page->fresh()->status);
    $this->assertSame('/docs', $page->fresh('translations')->translations->first()->path);
    $this->assertSame(Page::STATUS_DRAFT, $stagedPage->status);
    $this->assertStringStartsWith('/staged-updates/page-'.$page->id.'/update-', $stagedPage->translations->first()->path);
    $this->assertSame('draft', data_get($stagedPage->settings, 'staged_update.state'));
    $this->assertSame($sourceSync['source_id'], data_get($stagedPage->settings, 'source_sync.source_id'));
    $this->assertDatabaseHas('page_slots', [
      'page_id' => $stagedPage->id,
      'slot_type_id' => $this->slotTypeId('header'),
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sharedSlot->id,
    ]);
    $this->assertDatabaseHas('blocks', ['id' => $mainBlock->id, 'page_id' => $page->id]);
    $this->assertSame(1, Block::query()->where('page_id', $stagedPage->id)->where('slot', 'main')->count());
  }

  #[Test]
  public function staged_update_replace_and_promote_preserve_source_path_status_and_source_sync(): void
  {
    $this->createInternalApiToken('secret-token');
    [$page, $mainBlock] = $this->createDraftPageWithMainAndSharedChrome('/docs');
    $sourceSync = $this->sourceSyncPayload();
    $page->forceFill([
      'status' => Page::STATUS_PUBLISHED,
      'published_at' => now(),
      'settings' => ['source_sync' => $sourceSync],
    ])->save();

    $createResponse = $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', [
        'plan' => [
          'mode' => 'create_staged_update_for_published_page',
          'site' => 'default',
          'locale' => 'en',
          'page' => ['id' => $page->id],
          'expected_source_path' => '/docs',
          'managed_slots' => ['main'],
        ],
      ])
      ->assertCreated();

    $stagedPageId = $createResponse->json('data.staged_page.id');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', [
        'plan' => [
          'mode' => 'replace_staged_page_update',
          'staged_page_id' => $stagedPageId,
          'expected_source_page_id' => $page->id,
          'expected_source_path' => '/docs',
          'replace_slots' => [
            'main' => [
              [
                'type' => 'plain_text',
                'translations' => [
                  'content' => 'Promoted staged copy',
                ],
              ],
            ],
          ],
        ],
      ])
      ->assertCreated()
      ->assertJsonPath('normalized_plan.mode', 'replace_staged_page_update')
      ->assertJsonPath('data.staged_page.id', $stagedPageId);

    $this->assertDatabaseHas('blocks', ['id' => $mainBlock->id, 'page_id' => $page->id]);
    $this->assertDatabaseHas('block_text_translations', ['content' => 'Promoted staged copy']);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', [
        'plan' => [
          'mode' => 'promote_staged_page_update',
          'staged_page_id' => $stagedPageId,
          'expected_source_page_id' => $page->id,
          'expected_source_path' => '/docs',
          'promote_slots' => ['main'],
        ],
      ])
      ->assertForbidden()
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::CONTENT_PUBLISH);

    CmsApiToken::query()->delete();
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::CONTENT_APPLY, CmsApiTokenCapabilities::CONTENT_VALIDATE, CmsApiTokenCapabilities::CONTENT_PUBLISH]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', [
        'plan' => [
          'mode' => 'promote_staged_page_update',
          'staged_page_id' => $stagedPageId,
          'expected_source_page_id' => $page->id,
          'expected_source_path' => '/docs',
          'promote_slots' => ['main'],
        ],
      ])
      ->assertCreated()
      ->assertJsonPath('normalized_plan.mode', 'promote_staged_page_update')
      ->assertJsonPath('data.page.id', $page->id)
      ->assertJsonPath('data.page.status', Page::STATUS_PUBLISHED)
      ->assertJsonPath('data.page.translations.0.path', '/docs')
      ->assertJsonPath('data.page.source_sync.source_id', $sourceSync['source_id']);

    $page = $page->fresh(['translations']);
    $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
    $this->assertSame('/docs', $page->translations->first()->path);
    $this->assertSame($sourceSync['source_sha256'], data_get($page->settings, 'source_sync.source_sha256'));
    $this->assertDatabaseMissing('blocks', ['id' => $mainBlock->id]);
    $this->assertDatabaseHas('blocks', [
      'page_id' => $page->id,
      'slot' => 'main',
      'status' => 'published',
    ]);
    $this->assertDatabaseHas('block_text_translations', ['content' => 'Promoted staged copy']);
    $this->assertSame(Page::STATUS_ARCHIVED, Page::query()->findOrFail($stagedPageId)->status);
    $this->assertSame('promoted', data_get(Page::query()->findOrFail($stagedPageId)->settings, 'staged_update.state'));

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', [
        'plan' => [
          'mode' => 'promote_staged_page_update',
          'staged_page_id' => $stagedPageId,
          'expected_source_page_id' => $page->id,
          'expected_source_path' => '/docs',
          'promote_slots' => ['main'],
        ],
      ])
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Only draft staged updates can be changed or promoted.']);
  }

  #[Test]
  public function api_rejects_page_publish_for_staged_updates_with_promote_guidance(): void
  {
    $this->createInternalApiToken('secret-token');
    [$page] = $this->createDraftPageWithMainAndSharedChrome('/docs');
    $page->forceFill([
      'status' => Page::STATUS_PUBLISHED,
      'published_at' => now(),
    ])->save();

    $createResponse = $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', [
        'plan' => [
          'mode' => 'create_staged_update_for_published_page',
          'site' => 'default',
          'locale' => 'en',
          'page' => ['id' => $page->id],
          'expected_source_path' => '/docs',
          'managed_slots' => ['main'],
        ],
      ])
      ->assertCreated();

    $stagedPageId = $createResponse->json('data.staged_page.id');

    $this->createInternalApiToken('publish-token', [CmsApiTokenCapabilities::CONTENT_PUBLISH]);

    $this->withHeader('Authorization', 'Bearer publish-token')
      ->postJson('/webadmin/api/pages/'.$stagedPageId.'/publish')
      ->assertStatus(409)
      ->assertJsonPath('code', 'staged_update_requires_promote')
      ->assertJsonPath('recommended_action.url', '/webadmin/api/content/apply')
      ->assertJsonPath('recommended_action.body.plan.mode', 'promote_staged_page_update')
      ->assertJsonPath('recommended_action.body.plan.staged_page_id', $stagedPageId)
      ->assertJsonPath('recommended_action.body.plan.expected_source_page_id', $page->id)
      ->assertJsonPath('recommended_action.body.plan.expected_source_path', '/docs')
      ->assertJsonPath('recommended_action.body.plan.promote_slots.0', 'main');

    $this->assertSame(Page::STATUS_DRAFT, Page::query()->findOrFail($stagedPageId)->status);
    $this->assertSame(Page::STATUS_PUBLISHED, $page->fresh()->status);
  }

  #[Test]
  public function staged_update_rejects_shared_slot_backed_slots(): void
  {
    $this->createInternalApiToken('secret-token');
    [$page] = $this->createDraftPageWithMainAndSharedChrome('/docs');
    $page->forceFill(['status' => Page::STATUS_PUBLISHED, 'published_at' => now()])->save();

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', [
        'plan' => [
          'mode' => 'create_staged_update_for_published_page',
          'site' => 'default',
          'locale' => 'en',
          'page' => ['id' => $page->id],
          'expected_source_path' => '/docs',
          'managed_slots' => ['header'],
        ],
      ])
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Shared-slot-backed slot [header] cannot be promoted or replaced.']);
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
  public function validate_and_apply_preserve_canonical_slash_page_paths(): void
  {
    $this->createInternalApiToken('secret-token');

    $payload = $this->validPlanPayload([
      'plan' => [
        'page' => [
          'title' => 'Internal Content API',
          'path' => 'docs/internal-content-api/',
        ],
      ],
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $payload)
      ->assertOk()
      ->assertJsonPath('normalized_plan.page.slug', 'internal-content-api')
      ->assertJsonPath('normalized_plan.page.path', '/docs/internal-content-api');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $payload)
      ->assertCreated()
      ->assertJsonPath('data.page.translations.0.path', '/docs/internal-content-api');

    $this->assertDatabaseHas('page_translations', [
      'slug' => 'internal-content-api',
      'path' => '/docs/internal-content-api',
    ]);
    $this->assertDatabaseMissing('page_translations', ['path' => '/p/docsinternal-content-api']);
  }

  #[Test]
  public function apply_rejects_reserved_and_unsafe_canonical_paths(): void
  {
    $this->createInternalApiToken('secret-token');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->validPlanPayload([
        'plan' => [
          'page' => ['path' => '/webadmin/api'],
        ],
      ]))
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Page path is reserved by CMS or host routes.']);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->validPlanPayload([
        'plan' => [
          'page' => ['path' => '/docs/../x'],
        ],
      ]))
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'Page path contains an unsafe segment.']);
  }

  #[Test]
  public function existing_page_conflicts_are_detected_by_canonical_path(): void
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
      'name' => 'Existing Docs',
      'slug' => 'internal-content-api',
      'path' => '/docs/internal-content-api',
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->validPlanPayload([
        'plan' => [
          'page' => ['path' => '/docs/internal-content-api'],
        ],
      ]))
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'A page already exists at this path for the selected site and locale.']);
  }

  #[Test]
  public function existing_draft_page_replacement_accepts_canonical_expected_path(): void
  {
    $this->createInternalApiToken('secret-token');
    [$page] = $this->createDraftPageWithMainAndSharedChrome('/docs/internal-content-api');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->replacementPlanPayload($page, [
        'plan' => [
          'page' => ['expected_path' => '/docs/internal-content-api'],
        ],
      ]))
      ->assertCreated()
      ->assertJsonPath('ok', true)
      ->assertJsonPath('data.page.translations.0.path', '/docs/internal-content-api');
  }

  #[Test]
  public function source_sync_metadata_is_validated_persisted_and_readable(): void
  {
    $this->createInternalApiToken('secret-token');
    $sourceSync = $this->sourceSyncPayload();

    $create = $this->withInternalToken()
      ->postJson('/webadmin/api/content/apply', $this->validPlanPayload([
        'plan' => [
          'page' => [
            'settings' => [
              'source_sync' => $sourceSync,
            ],
          ],
        ],
      ]))
      ->assertCreated()
      ->assertJsonPath('data.page.source_sync.source_path', 'docs/internal-content-api.md');

    $pageId = $create->json('data.page.id');
    $page = Page::query()->findOrFail($pageId);

    $this->assertSame('webblocks-cms:docs/internal-content-api.md', data_get($page->settings, 'source_sync.source_id'));

    $this->withInternalToken()
      ->getJson('/webadmin/api/pages/'.$pageId)
      ->assertOk()
      ->assertJsonPath('page.source_sync.source_sha256', $sourceSync['source_sha256'])
      ->assertJsonMissingPath('page.settings');

    $this->withInternalToken()
      ->postJson('/webadmin/api/content/validate', $this->validPlanPayload([
        'plan' => [
          'page' => [
            'settings' => [
              'source_sync' => [
                ...$sourceSync,
                'local_path' => base_path(),
              ],
            ],
          ],
        ],
      ]))
      ->assertStatus(422)
      ->assertJsonFragment(['message' => 'source_sync contains unsupported fields.']);
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
  public function valid_token_can_discover_media_and_update_shared_slot_brand_logo_block(): void
  {
    $this->createInternalApiToken('secret-token');
    $locale = $this->defaultLocale();
    $site = $this->defaultSite();
    $slotType = SlotType::query()->where('slug', 'header')->firstOrFail();
    $blockType = BlockType::query()->where('slug', 'navbar-brand')->firstOrFail();
    $logo = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/cms-logo.png',
      'filename' => 'cms-logo.png',
      'original_name' => 'cms-logo.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1024,
      'kind' => Media::KIND_IMAGE,
      'visibility' => 'public',
      'title' => 'WebBlocks CMS logo',
      'alt_text' => 'WebBlocks CMS',
      'width' => 120,
      'height' => 120,
    ]);
    Media::query()->create([
      'disk' => 'public',
      'path' => 'media/manual.pdf',
      'filename' => 'manual.pdf',
      'original_name' => 'manual.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 2048,
      'kind' => Media::KIND_DOCUMENT,
      'visibility' => 'public',
      'title' => 'Manual',
    ]);
    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Site Header',
      'handle' => 'site-header',
      'slot_name' => 'header',
      'is_active' => true,
    ]);
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Shared Slot Source: Site Header',
      'slug' => 'shared-slot-site-header',
      'page_type' => Page::TYPE_SHARED_SLOT_SOURCE,
      'status' => Page::STATUS_DRAFT,
      'settings' => [
        'shared_slot_id' => $sharedSlot->id,
        'shared_slot_handle' => $sharedSlot->handle,
      ],
    ]);
    $block = Block::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $blockType->id,
      'slot' => 'header',
      'type' => 'navbar-brand',
      'status' => 'draft',
      'sort_order' => 0,
    ]);
    SharedSlotBlock::query()->create([
      'shared_slot_id' => $sharedSlot->id,
      'block_id' => $block->id,
      'sort_order' => 0,
    ]);

    $this->withInternalToken()
      ->getJson('/webadmin/api/media?kind=image&search=logo')
      ->assertOk()
      ->assertJsonPath('media.0.id', $logo->id)
      ->assertJsonPath('media.0.kind', Media::KIND_IMAGE)
      ->assertJsonMissing(['filename' => 'manual.pdf']);

    $this->withInternalToken()
      ->patchJson('/webadmin/api/blocks/'.$block->id, [
        'locale' => $locale->code,
        'media_id' => $logo->id,
        'settings' => [
          'url' => '/',
          'target' => '_self',
          'aria_label' => 'WebBlocks CMS home',
        ],
        'translations' => [
          'title' => 'WebBlocks CMS',
          'subtitle' => 'Composable content operations',
        ],
      ])
      ->assertOk()
      ->assertJsonPath('block.id', $block->id)
      ->assertJsonPath('block.media.id', $logo->id)
      ->assertJsonPath('block.settings.aria_label', 'WebBlocks CMS home')
      ->assertJsonPath('shared_slot.handle', 'site-header');

    $this->assertDatabaseHas('blocks', [
      'id' => $block->id,
      'media_id' => $logo->id,
    ]);
    $this->assertDatabaseHas('block_text_translations', [
      'block_id' => $block->id,
      'locale_id' => $locale->id,
      'title' => 'WebBlocks CMS',
      'subtitle' => 'Composable content operations',
    ]);
  }

  #[Test]
  public function media_api_uses_media_capabilities_and_updates_safe_metadata_only(): void
  {
    $media = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/cms-logo.png',
      'filename' => 'cms-logo.png',
      'original_name' => 'cms-logo.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1024,
      'kind' => Media::KIND_IMAGE,
      'visibility' => 'public',
      'title' => 'Old title',
      'alt_text' => null,
      'caption' => null,
      'description' => null,
      'width' => 64,
      'height' => 64,
    ]);

    $this->createInternalApiToken('media-read-token', [CmsApiTokenCapabilities::MEDIA_READ]);
    $this->withHeader('Authorization', 'Bearer media-read-token')
      ->getJson('/webadmin/api/media?kind=image&search=cms')
      ->assertOk()
      ->assertJsonPath('media.0.id', $media->id)
      ->assertJsonPath('media.0.title', 'Old title');

    $this->createInternalApiToken('content-read-token', [CmsApiTokenCapabilities::CONTENT_READ]);
    $this->withHeader('Authorization', 'Bearer content-read-token')
      ->getJson('/webadmin/api/media?kind=image&search=cms')
      ->assertOk()
      ->assertJsonPath('media.0.id', $media->id);

    $this->createInternalApiToken('validate-token', [CmsApiTokenCapabilities::CONTENT_VALIDATE]);
    $this->withHeader('Authorization', 'Bearer validate-token')
      ->getJson('/webadmin/api/media?kind=image&search=cms')
      ->assertForbidden()
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::MEDIA_READ);

    $this->createInternalApiToken('media-write-token', [CmsApiTokenCapabilities::MEDIA_WRITE]);
    $this->withHeader('Authorization', 'Bearer media-write-token')
      ->patchJson('/webadmin/api/media/'.$media->id, [
        'title' => 'WebBlocks CMS logo',
        'alt_text' => 'WebBlocks CMS mark',
        'caption' => 'CMS product identity',
        'description' => 'Square logo used by the CMS homepage brand block.',
      ])
      ->assertOk()
      ->assertJsonPath('media.id', $media->id)
      ->assertJsonPath('media.title', 'WebBlocks CMS logo')
      ->assertJsonPath('media.alt_text', 'WebBlocks CMS mark');

    $this->assertDatabaseHas('media', [
      'id' => $media->id,
      'title' => 'WebBlocks CMS logo',
      'alt_text' => 'WebBlocks CMS mark',
      'caption' => 'CMS product identity',
      'description' => 'Square logo used by the CMS homepage brand block.',
      'path' => 'media/cms-logo.png',
      'kind' => Media::KIND_IMAGE,
    ]);

    $this->withHeader('Authorization', 'Bearer media-write-token')
      ->patchJson('/webadmin/api/media/'.$media->id, [
        'path' => 'media/replaced.png',
      ])
      ->assertStatus(422)
      ->assertJsonPath('code', 'unsupported_media_update_fields')
      ->assertJsonPath('blocked_fields.0', 'path');
  }

  #[Test]
  public function media_upload_can_feed_site_favicon_and_brand_logo_assignments(): void
  {
    Storage::fake('public');
    $this->createInternalApiToken('brand-token', [
      CmsApiTokenCapabilities::MEDIA_UPLOAD,
      CmsApiTokenCapabilities::MEDIA_READ,
      CmsApiTokenCapabilities::SITE_SETTINGS_WRITE,
      CmsApiTokenCapabilities::CONTENT_APPLY,
    ]);
    $site = $this->defaultSite();
    $slotType = SlotType::query()->where('slug', 'header')->firstOrFail();
    $blockType = BlockType::query()->where('slug', 'navbar-brand')->firstOrFail();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Home',
      'slug' => 'home',
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);
    $block = Block::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $blockType->id,
      'slot' => 'header',
      'type' => 'navbar-brand',
      'status' => 'draft',
      'sort_order' => 0,
    ]);

    $upload = $this->withHeader('Authorization', 'Bearer brand-token')
      ->post('/webadmin/api/media', [
        'file' => UploadedFile::fake()->image('play-logo.png', 128, 128),
        'title' => 'Play logo',
        'alt_text' => 'Play',
      ]);

    $upload
      ->assertCreated()
      ->assertJsonPath('media.kind', Media::KIND_IMAGE)
      ->assertJsonPath('media.title', 'Play logo')
      ->assertJsonPath('media.alt_text', 'Play')
      ->assertJsonPath('writes.0.type', 'media_upload');

    $mediaId = (int) $upload->json('media.id');
    $media = Media::query()->findOrFail($mediaId);

    Storage::disk('public')->assertExists($media->path);

    $this->withHeader('Authorization', 'Bearer brand-token')
      ->patchJson('/webadmin/api/sites/'.$site->id.'/branding', [
        'display_name' => 'Play',
        'tagline' => 'WebBlocks playground',
        'favicon_media_id' => $mediaId,
      ])
      ->assertOk()
      ->assertJsonPath('site.id', $site->id)
      ->assertJsonPath('site.display_name', 'Play')
      ->assertJsonPath('site.favicon_media.id', $mediaId)
      ->assertJsonPath('writes.0.type', 'site_branding');

    $this->withHeader('Authorization', 'Bearer brand-token')
      ->patchJson('/webadmin/api/blocks/'.$block->id, [
        'media_id' => $mediaId,
        'translations' => [
          'title' => 'Play',
        ],
      ])
      ->assertOk()
      ->assertJsonPath('block.media.id', $mediaId);

    $this->assertDatabaseHas('sites', [
      'id' => $site->id,
      'display_name' => 'Play',
      'tagline' => 'WebBlocks playground',
      'favicon_media_id' => $mediaId,
    ]);
    $this->assertDatabaseHas('blocks', [
      'id' => $block->id,
      'media_id' => $mediaId,
    ]);
  }

  #[Test]
  public function media_upload_requires_explicit_upload_capability_and_accepts_library_file_types(): void
  {
    Storage::fake('public');
    $this->createInternalApiToken('media-read-token', [CmsApiTokenCapabilities::MEDIA_READ]);

    $this->withHeader('Authorization', 'Bearer media-read-token')
      ->post('/webadmin/api/media', [
        'file' => UploadedFile::fake()->image('logo.png'),
      ])
      ->assertForbidden()
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::MEDIA_UPLOAD);

    $this->createInternalApiToken('upload-token', [CmsApiTokenCapabilities::MEDIA_UPLOAD]);

    $this->withHeader('Authorization', 'Bearer upload-token')
      ->post('/webadmin/api/media', [
        'file' => UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf'),
      ])
      ->assertCreated()
      ->assertJsonPath('media.kind', Media::KIND_DOCUMENT)
      ->assertJsonPath('media.mime_type', 'application/pdf');
  }

  #[Test]
  public function media_api_can_replace_move_and_delete_with_explicit_capabilities(): void
  {
    Storage::fake('public');
    $this->createInternalApiToken('media-admin-token', [
      CmsApiTokenCapabilities::MEDIA_UPLOAD,
      CmsApiTokenCapabilities::MEDIA_READ,
      CmsApiTokenCapabilities::MEDIA_REPLACE,
      CmsApiTokenCapabilities::MEDIA_MOVE,
      CmsApiTokenCapabilities::MEDIA_DELETE,
      CmsApiTokenCapabilities::SITE_SETTINGS_WRITE,
    ]);
    $folder = MediaFolder::query()->create(['name' => 'Brand']);

    $upload = $this->withHeader('Authorization', 'Bearer media-admin-token')
      ->post('/webadmin/api/media', [
        'file' => UploadedFile::fake()->image('logo-old.png', 80, 80),
        'title' => 'Old logo',
      ])
      ->assertCreated();

    $mediaId = (int) $upload->json('media.id');
    $oldMedia = Media::query()->findOrFail($mediaId);
    $oldPath = $oldMedia->path;
    Storage::disk('public')->assertExists($oldPath);

    $this->withHeader('Authorization', 'Bearer media-admin-token')
      ->post('/webadmin/api/media/'.$mediaId.'/replace', [
        'file' => UploadedFile::fake()->image('logo-new.png', 120, 120),
        'title' => 'New logo',
      ])
      ->assertOk()
      ->assertJsonPath('media.id', $mediaId)
      ->assertJsonPath('media.title', 'New logo')
      ->assertJsonPath('media.width', 120)
      ->assertJsonPath('writes.0.type', 'media_replace');

    $replacedMedia = Media::query()->findOrFail($mediaId);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($replacedMedia->path);

    $this->withHeader('Authorization', 'Bearer media-admin-token')
      ->post('/webadmin/api/media/'.$mediaId.'/replace', [
        'file' => UploadedFile::fake()->create('manual.pdf', 20, 'application/pdf'),
      ])
      ->assertStatus(422)
      ->assertJsonPath('code', 'incompatible_media_replacement')
      ->assertJsonPath('errors.0.path', 'file');

    $this->assertSame($replacedMedia->path, Media::query()->findOrFail($mediaId)->path);

    $this->withHeader('Authorization', 'Bearer media-admin-token')
      ->postJson('/webadmin/api/media/'.$mediaId.'/move', [
        'folder_id' => $folder->id,
      ])
      ->assertOk()
      ->assertJsonPath('media.id', $mediaId)
      ->assertJsonPath('writes.0.type', 'media_move');

    $this->assertDatabaseHas('media', [
      'id' => $mediaId,
      'folder_id' => $folder->id,
    ]);

    $site = $this->defaultSite();

    $this->withHeader('Authorization', 'Bearer media-admin-token')
      ->patchJson('/webadmin/api/sites/'.$site->id.'/branding', [
        'favicon_media_id' => $mediaId,
      ])
      ->assertOk();

    $this->withHeader('Authorization', 'Bearer media-admin-token')
      ->deleteJson('/webadmin/api/media/'.$mediaId)
      ->assertStatus(422)
      ->assertJsonPath('code', 'media_in_use')
      ->assertJsonPath('usage_count', 1)
      ->assertJsonPath('usages.0.context', 'Site favicon');

    $documentUpload = $this->withHeader('Authorization', 'Bearer media-admin-token')
      ->post('/webadmin/api/media', [
        'file' => UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf'),
      ])
      ->assertCreated();
    $documentId = (int) $documentUpload->json('media.id');
    $documentPath = Media::query()->findOrFail($documentId)->path;

    $this->withHeader('Authorization', 'Bearer media-admin-token')
      ->deleteJson('/webadmin/api/media/'.$documentId)
      ->assertOk()
      ->assertJsonPath('deleted_media.id', $documentId)
      ->assertJsonPath('writes.0.type', 'media_delete');

    $this->assertDatabaseMissing('media', ['id' => $documentId]);
    Storage::disk('public')->assertMissing($documentPath);
  }

  #[Test]
  public function site_branding_requires_image_media_and_block_settings_reject_unknown_logo_url(): void
  {
    $this->createInternalApiToken('brand-token', [
      CmsApiTokenCapabilities::SITE_SETTINGS_WRITE,
      CmsApiTokenCapabilities::CONTENT_APPLY,
    ]);
    $site = $this->defaultSite();
    $document = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/documents/manual.pdf',
      'filename' => 'manual.pdf',
      'original_name' => 'manual.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 2048,
      'kind' => Media::KIND_DOCUMENT,
      'visibility' => 'public',
      'title' => 'Manual',
    ]);
    $slotType = SlotType::query()->where('slug', 'header')->firstOrFail();
    $blockType = BlockType::query()->where('slug', 'navbar-brand')->firstOrFail();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Home',
      'slug' => 'home',
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);
    $block = Block::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $blockType->id,
      'slot' => 'header',
      'type' => 'navbar-brand',
      'status' => 'draft',
      'sort_order' => 0,
    ]);

    $this->withHeader('Authorization', 'Bearer brand-token')
      ->patchJson('/webadmin/api/sites/'.$site->id.'/branding', [
        'favicon_media_id' => $document->id,
      ])
      ->assertStatus(422)
      ->assertJsonPath('errors.0.path', 'favicon_media_id');

    $this->withHeader('Authorization', 'Bearer brand-token')
      ->patchJson('/webadmin/api/blocks/'.$block->id, [
        'settings' => [
          'logo_url' => '/cms/brand/logo-mark.svg',
        ],
      ])
      ->assertStatus(422)
      ->assertJsonPath('code', 'unsupported_block_settings_fields')
      ->assertJsonPath('blocked_fields.0', 'settings.logo_url');
  }

  #[Test]
  public function shared_slot_existing_block_updates_require_shared_slot_write_capability(): void
  {
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::CONTENT_APPLY]);
    $site = $this->defaultSite();
    $slotType = SlotType::query()->where('slug', 'header')->firstOrFail();
    $blockType = BlockType::query()->where('slug', 'navbar-brand')->firstOrFail();
    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Site Header',
      'handle' => 'site-header',
      'slot_name' => 'header',
      'is_active' => true,
    ]);
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Shared Slot Source: Site Header',
      'slug' => 'shared-slot-site-header',
      'page_type' => Page::TYPE_SHARED_SLOT_SOURCE,
      'status' => Page::STATUS_DRAFT,
      'settings' => ['shared_slot_id' => $sharedSlot->id],
    ]);
    $block = Block::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $blockType->id,
      'slot' => 'header',
      'type' => 'navbar-brand',
      'status' => 'draft',
      'sort_order' => 0,
    ]);
    SharedSlotBlock::query()->create([
      'shared_slot_id' => $sharedSlot->id,
      'block_id' => $block->id,
      'sort_order' => 0,
    ]);

    $this->withInternalToken()
      ->patchJson('/webadmin/api/blocks/'.$block->id, [
        'translations' => ['title' => 'WebBlocks CMS'],
      ])
      ->assertForbidden()
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::SHARED_SLOTS_WRITE);
  }

  #[Test]
  public function existing_block_update_rejects_topology_fields_and_non_image_brand_media(): void
  {
    $this->createInternalApiToken('secret-token');
    $site = $this->defaultSite();
    $slotType = SlotType::query()->where('slug', 'header')->firstOrFail();
    $blockType = BlockType::query()->where('slug', 'navbar-brand')->firstOrFail();
    $document = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/manual.pdf',
      'filename' => 'manual.pdf',
      'original_name' => 'manual.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 2048,
      'kind' => Media::KIND_DOCUMENT,
      'visibility' => 'public',
      'title' => 'Manual',
    ]);
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Home',
      'slug' => 'home',
      'status' => Page::STATUS_DRAFT,
    ]);
    $block = Block::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $blockType->id,
      'slot' => 'header',
      'type' => 'navbar-brand',
      'status' => 'draft',
      'sort_order' => 0,
    ]);

    $this->withInternalToken()
      ->patchJson('/webadmin/api/blocks/'.$block->id, [
        'parent_id' => 123,
      ])
      ->assertStatus(422)
      ->assertJsonPath('code', 'unsupported_existing_block_update_fields')
      ->assertJsonPath('blocked_fields.0', 'parent_id');

    $this->withInternalToken()
      ->patchJson('/webadmin/api/blocks/'.$block->id, [
        'media_id' => $document->id,
      ])
      ->assertStatus(422)
      ->assertJsonPath('message', 'Brand logo media must be an image from Media.');

    $this->withInternalToken()
      ->patchJson('/webadmin/api/blocks/'.$block->id, [
        'settings' => ['url' => 'javascript:alert(1)'],
      ])
      ->assertStatus(422)
      ->assertJsonPath('code', 'unsafe_url');
  }

  #[Test]
  public function shared_slot_blocks_require_explicit_publish_capability_to_publish(): void
  {
    $this->createInternalApiToken('secret-token');
    $sharedSlot = SharedSlot::query()->create([
      'site_id' => $this->defaultSite()->id,
      'name' => 'Site Header',
      'handle' => 'site-header',
      'slot_name' => 'header',
      'is_active' => true,
    ]);
    $block = Block::query()->create([
      'page_id' => Page::query()->create([
        'site_id' => $this->defaultSite()->id,
        'page_type' => Page::TYPE_SHARED_SLOT_SOURCE,
        'status' => Page::STATUS_DRAFT,
      ])->id,
      'block_type_id' => $this->blockTypeId('plain_text'),
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot_type_id' => $this->slotTypeId('header'),
      'slot' => 'header',
      'status' => Page::STATUS_DRAFT,
      'sort_order' => 0,
    ]);

    SharedSlotBlock::query()->create([
      'shared_slot_id' => $sharedSlot->id,
      'block_id' => $block->id,
      'sort_order' => 0,
    ]);

    $this->withInternalToken()
      ->postJson('/webadmin/api/shared-slots/'.$sharedSlot->id.'/publish-blocks')
      ->assertForbidden()
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::CONTENT_PUBLISH);

    $this->createInternalApiToken('publish-token', [
      CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
      CmsApiTokenCapabilities::CONTENT_PUBLISH,
    ]);

    $this->withHeader('Authorization', 'Bearer publish-token')
      ->postJson('/webadmin/api/shared-slots/'.$sharedSlot->id.'/publish-blocks')
      ->assertOk()
      ->assertJsonPath('published_blocks_count', 1);

    $this->assertSame('published', $block->fresh()->status);
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
  public function api_publish_defaults_to_page_only_and_requires_publish_capability(): void
  {
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::CONTENT_READ]);
    [$page, $draftBlock] = $this->createDraftPageWithPublishableBlocks();

    $this->withInternalToken()
      ->postJson('/webadmin/api/pages/'.$page->id.'/publish')
      ->assertForbidden()
      ->assertJsonPath('code', 'missing_internal_api_capability');

    $this->createInternalApiToken('publish-token', [CmsApiTokenCapabilities::CONTENT_PUBLISH]);

    $this->withHeader('Authorization', 'Bearer publish-token')
      ->postJson('/webadmin/api/pages/'.$page->id.'/publish')
      ->assertOk()
      ->assertJsonPath('page.status', Page::STATUS_PUBLISHED)
      ->assertJsonPath('included_page_owned_blocks', false)
      ->assertJsonPath('page_owned_blocks_published_count', 0);

    $this->assertSame('draft', $draftBlock->fresh()->status);
  }

  #[Test]
  public function api_publish_can_explicitly_include_page_owned_blocks_without_shared_slot_cascade(): void
  {
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::CONTENT_PUBLISH]);
    [$page, $draftBlock, $reviewBlock, $sharedBlock] = $this->createDraftPageWithPublishableBlocks(includeSharedSlot: true);

    $this->withInternalToken()
      ->postJson('/webadmin/api/pages/'.$page->id.'/publish', [
        'include_page_owned_blocks' => true,
      ])
      ->assertOk()
      ->assertJsonPath('page.status', Page::STATUS_PUBLISHED)
      ->assertJsonPath('included_page_owned_blocks', true)
      ->assertJsonPath('page_owned_blocks_published_count', 2)
      ->assertJsonPath('shared_slots_excluded.0.shared_slot_label', 'Site Header')
      ->assertJsonPath('revision_id', PageRevision::query()->latest('id')->value('id'));

    $this->assertSame('published', $draftBlock->fresh()->status);
    $this->assertSame('published', $reviewBlock->fresh()->status);
    $this->assertSame('draft', $sharedBlock->fresh()->status);
  }

  #[Test]
  public function api_publish_can_include_many_nested_page_owned_blocks_without_shared_slot_cascade(): void
  {
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::CONTENT_PUBLISH]);
    [$page, $draftBlock, $reviewBlock, $sharedBlock] = $this->createDraftPageWithPublishableBlocks(includeSharedSlot: true);

    $sourceSync = [
      'type' => 'markdown_documentation',
      'source_id' => 'webblocks-cms:docs/large-page.md',
      'source_path' => 'docs/large-page.md',
      'source_sha256' => str_repeat('a', 64),
      'managed_slots' => ['main'],
      'last_synced_at' => now()->toIso8601String(),
    ];
    $page->forceFill(['settings' => ['source_sync' => $sourceSync]])->save();

    $parent = $draftBlock;
    for ($index = 0; $index < 360; $index++) {
      $parent = Block::query()->create([
        'page_id' => $page->id,
        'parent_id' => $index % 3 === 0 ? $parent->id : null,
        'block_type_id' => $this->blockTypeId('plain_text'),
        'type' => 'plain_text',
        'source_type' => 'static',
        'slot_type_id' => $this->slotTypeId('main'),
        'slot' => 'main',
        'status' => $index % 2 === 0 ? 'draft' : 'in_review',
        'sort_order' => $index + 1,
      ]);
    }

    $this->withInternalToken()
      ->postJson('/webadmin/api/pages/'.$page->id.'/publish', [
        'include_page_owned_blocks' => true,
      ])
      ->assertOk()
      ->assertJsonPath('page.status', Page::STATUS_PUBLISHED)
      ->assertJsonPath('included_page_owned_blocks', true)
      ->assertJsonPath('page_owned_blocks_published_count', 362)
      ->assertJsonPath('shared_slots_excluded.0.shared_slot_label', 'Site Header')
      ->assertJsonPath('page.source_sync.source_id', 'webblocks-cms:docs/large-page.md')
      ->assertJsonPath('revision_id', PageRevision::query()->latest('id')->value('id'));

    $this->assertSame(0, Block::query()
      ->where('page_id', $page->id)
      ->where('slot_type_id', $this->slotTypeId('main'))
      ->whereIn('status', ['draft', 'in_review'])
      ->count());
    $this->assertSame('draft', $sharedBlock->fresh()->status);
    $this->assertSame($sourceSync['source_sha256'], data_get($page->fresh()->settings, 'source_sync.source_sha256'));
    $this->assertSame('published', $draftBlock->fresh()->status);
    $this->assertSame('published', $reviewBlock->fresh()->status);
  }

  #[Test]
  public function api_rejects_shared_slot_cascade_publish_attempts(): void
  {
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::CONTENT_PUBLISH]);
    [$page, $draftBlock] = $this->createDraftPageWithPublishableBlocks();

    $this->withInternalToken()
      ->postJson('/webadmin/api/pages/'.$page->id.'/publish', [
        'include_page_owned_blocks' => true,
        'publish_shared_slots' => true,
      ])
      ->assertStatus(422)
      ->assertJsonFragment(['Shared Slot cascade publishing is not supported by this endpoint. Review and publish Shared Slots separately.']);

    $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);
    $this->assertSame('draft', $draftBlock->fresh()->status);
  }

  #[Test]
  public function api_can_publish_page_owned_blocks_without_changing_page_status(): void
  {
    $this->createInternalApiToken('secret-token', [CmsApiTokenCapabilities::CONTENT_PUBLISH]);
    [$page, $draftBlock, $reviewBlock] = $this->createDraftPageWithPublishableBlocks();

    $this->withInternalToken()
      ->postJson('/webadmin/api/pages/'.$page->id.'/publish-page-owned-blocks')
      ->assertOk()
      ->assertJsonPath('changed_page_status', false)
      ->assertJsonPath('page.status', Page::STATUS_DRAFT)
      ->assertJsonPath('page_owned_blocks_published_count', 2);

    $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);
    $this->assertSame('published', $draftBlock->fresh()->status);
    $this->assertSame('published', $reviewBlock->fresh()->status);
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

  private function sourceSyncPayload(): array
  {
    return [
      'type' => 'markdown_documentation',
      'source_id' => 'webblocks-cms:docs/internal-content-api.md',
      'source_path' => 'docs/internal-content-api.md',
      'source_sha256' => hash('sha256', 'docs internal content api'),
      'managed_slots' => ['main'],
      'last_synced_at' => '2026-06-25T00:00:00Z',
    ];
  }

  private function createDraftPageWithMainAndSharedChrome(string $path = '/p/existing-contact'): array
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
      'slug' => trim(basename($path), '/') ?: 'existing-contact',
      'path' => $path,
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

  private function createDraftPageWithPublishableBlocks(bool $includeSharedSlot = false): array
  {
    $site = $this->defaultSite();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ]);

    app(PageLayoutSlotSyncer::class)->seedInitialSlots($page, 'default');

    $draftBlock = Block::query()->create([
      'page_id' => $page->id,
      'block_type_id' => $this->blockTypeId('section'),
      'type' => 'section',
      'source_type' => 'static',
      'slot_type_id' => $this->slotTypeId('main'),
      'slot' => 'main',
      'status' => 'draft',
      'sort_order' => 0,
    ]);

    $reviewBlock = Block::query()->create([
      'page_id' => $page->id,
      'parent_id' => $draftBlock->id,
      'block_type_id' => $this->blockTypeId('plain_text'),
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot_type_id' => $this->slotTypeId('main'),
      'slot' => 'main',
      'status' => 'in_review',
      'sort_order' => 0,
    ]);

    $sharedBlock = null;

    if ($includeSharedSlot) {
      $sharedSlot = SharedSlot::query()->create([
        'site_id' => $site->id,
        'name' => 'Site Header',
        'handle' => 'site-header',
        'slot_name' => 'header',
        'is_active' => true,
      ]);
      $sharedSourcePage = Page::query()->create([
        'site_id' => $site->id,
        'page_type' => Page::TYPE_SHARED_SLOT_SOURCE,
        'status' => Page::STATUS_DRAFT,
      ]);
      $sharedBlock = Block::query()->create([
        'page_id' => $sharedSourcePage->id,
        'block_type_id' => $this->blockTypeId('plain_text'),
        'type' => 'plain_text',
        'source_type' => 'static',
        'slot_type_id' => $this->slotTypeId('header'),
        'slot' => 'header',
        'status' => 'draft',
        'sort_order' => 0,
      ]);

      SharedSlotBlock::query()->create([
        'shared_slot_id' => $sharedSlot->id,
        'block_id' => $sharedBlock->id,
        'sort_order' => 0,
      ]);

      PageSlot::query()
        ->where('page_id', $page->id)
        ->where('slot_type_id', $this->slotTypeId('header'))
        ->firstOrFail()
        ->update([
          'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
          'shared_slot_id' => $sharedSlot->id,
        ]);
    }

    return [$page->fresh(['slots.slotType']), $draftBlock, $reviewBlock, $sharedBlock];
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
