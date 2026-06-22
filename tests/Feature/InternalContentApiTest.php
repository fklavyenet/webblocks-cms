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
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;

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
    $this->setInternalApiToken(null);
  }

  protected function tearDown(): void
  {
    $this->setInternalApiToken(null);

    parent::tearDown();
  }

  #[Test]
  public function api_is_disabled_when_internal_content_api_token_is_missing(): void
  {
    $this->getJson('/webadmin/api/sites')
      ->assertStatus(503)
      ->assertJsonPath('ok', false)
      ->assertJsonPath('code', 'internal_api_disabled');
  }

  #[Test]
  public function missing_or_invalid_bearer_token_is_rejected_with_json(): void
  {
    $this->setInternalApiToken('secret-token');

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
    $this->setInternalApiToken('secret-token');

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
    $this->setInternalApiToken('secret-token');

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
    $this->setInternalApiToken('secret-token');

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
    $this->setInternalApiToken('secret-token');

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
    $this->setInternalApiToken('secret-token');
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
    $this->setInternalApiToken('secret-token');
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
    $this->setInternalApiToken('secret-token');

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

  private function defaultSite(): Site
  {
    return Site::query()->where('is_primary', true)->firstOrFail();
  }

  private function defaultLocale(): Locale
  {
    return Locale::query()->where('is_default', true)->firstOrFail();
  }

  private function setInternalApiToken(?string $token): void
  {
    $value = $token ?? '';
    putenv($token === null ? 'WEBBLOCKS_CMS_INTERNAL_API_TOKEN' : 'WEBBLOCKS_CMS_INTERNAL_API_TOKEN='.$value);

    if ($token === null) {
      unset($_ENV['WEBBLOCKS_CMS_INTERNAL_API_TOKEN'], $_SERVER['WEBBLOCKS_CMS_INTERNAL_API_TOKEN']);

      return;
    }

    $_ENV['WEBBLOCKS_CMS_INTERNAL_API_TOKEN'] = $value;
    $_SERVER['WEBBLOCKS_CMS_INTERNAL_API_TOKEN'] = $value;
  }

  private function withInternalToken(): self
  {
    return $this->withHeader('Authorization', 'Bearer secret-token');
  }
}
