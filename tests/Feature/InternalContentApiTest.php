<?php

namespace Tests\Feature;

use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Database\Seeders\PageLayoutSeeder;
use Database\Seeders\SlotTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;

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

  private function defaultSite(): Site
  {
    return Site::query()->where('is_primary', true)->firstOrFail();
  }

  private function defaultLocale(): Locale
  {
    return Locale::query()->where('is_default', true)->firstOrFail();
  }

  private function createInternalApiToken(string $token): void
  {
    CmsApiToken::query()->create([
      'name' => 'Test token',
      'token_hash' => app(CmsApiTokenIssuer::class)->hash($token),
      'token_preview' => app(CmsApiTokenIssuer::class)->preview($token),
    ]);
  }

  private function withInternalToken(): self
  {
    return $this->withHeader('Authorization', 'Bearer secret-token');
  }
}
