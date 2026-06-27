<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PublicIconBadgeSupportTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function content_block_icon_slug_validation_accepts_active_content_catalog_icons(): void
  {
    $this->seedFoundation();
    $this->createIcon('sparkles', true);

    $user = User::factory()->superAdmin()->create();
    [$page, $pageSlot, $slotType] = $this->pageWithSlot();
    $blockType = BlockType::query()->where('slug', 'content_header')->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $blockType->id,
      'sort_order' => 0,
      'title' => 'Docs',
      'intro_text' => 'Build faster.',
      'icon_slug' => 'sparkles',
      'icon_tone' => 'brand',
      'badge_label' => 'New <Badge>',
      'badge_tone' => 'success',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $block = Block::query()->where('type', 'content_header')->firstOrFail();

    $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
    $this->assertSame('sparkles', $block->setting('icon_slug'));
    $this->assertSame('brand', $block->setting('icon_tone'));
    $this->assertSame('success', $block->setting('badge_tone'));
    $this->assertDatabaseHas('block_text_translations', [
      'block_id' => $block->id,
      'locale_id' => $this->defaultLocale()->id,
      'eyebrow' => 'New <Badge>',
    ]);
  }

  #[Test]
  public function content_block_icon_tone_validation_rejects_unknown_tones(): void
  {
    $this->seedFoundation();
    $this->createIcon('sparkles', true);

    $user = User::factory()->superAdmin()->create();
    [$page, , $slotType] = $this->pageWithSlot();
    $blockType = BlockType::query()->where('slug', 'content_header')->firstOrFail();

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'block_type_id' => $blockType->id,
      'sort_order' => 0,
      'title' => 'Docs',
      'icon_slug' => 'sparkles',
      'icon_tone' => 'success',
      'status' => 'published',
      '_slot_block_mode' => 'create',
    ]);

    $response->assertSessionHasErrors('icon_tone');
  }

  #[Test]
  public function content_block_icon_slug_validation_rejects_inactive_or_unknown_icons(): void
  {
    $this->seedFoundation();
    $this->createIcon('archive', false);

    $user = User::factory()->superAdmin()->create();
    [$page, , $slotType] = $this->pageWithSlot();
    $blockType = BlockType::query()->where('slug', 'content_header')->firstOrFail();

    foreach (['archive', 'not-in-catalog'] as $icon) {
      $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
        'page_id' => $page->id,
        'slot_type_id' => $slotType->id,
        'block_type_id' => $blockType->id,
        'sort_order' => 0,
        'title' => 'Docs',
        'icon_slug' => $icon,
        'badge_tone' => 'info',
        'status' => 'published',
        '_slot_block_mode' => 'create',
      ]);

      $response->assertSessionHasErrors('icon_slug');
    }
  }

  #[Test]
  public function public_rendering_outputs_safe_icon_classes_and_escaped_badges(): void
  {
    $this->createIcon('sparkles', true);

    $block = new Block([
      'type' => 'content_header',
      'title' => 'Docs',
      'settings' => json_encode([
        'icon_slug' => 'sparkles',
        'icon_tone' => 'brand',
        'badge_tone' => 'danger',
      ], JSON_UNESCAPED_SLASHES),
    ]);
    $block->setAttribute('eyebrow', '<strong>Beta</strong>');

    $html = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.content_header', [
      'block' => $block,
    ])->render();

    $this->assertStringContainsString('class="wb-icon wb-icon-sparkles wb-icon-tone-brand" aria-hidden="true"', $html);
    $this->assertStringContainsString('class="wb-badge wb-badge-danger"', $html);
    $this->assertStringContainsString('&lt;strong&gt;Beta&lt;/strong&gt;', $html);
    $this->assertStringNotContainsString('<strong>Beta</strong>', $html);
  }

  #[Test]
  public function public_icon_tone_renders_for_all_supported_icon_blocks(): void
  {
    $this->createIcon('sparkles', true);

    $cases = [
      'content_header' => new Block([
        'type' => 'content_header',
        'title' => 'Docs',
        'settings' => json_encode(['icon_slug' => 'sparkles', 'icon_tone' => 'accent'], JSON_UNESCAPED_SLASHES),
      ]),
      'card_header' => new Block([
        'type' => 'card_header',
        'settings' => json_encode(['icon_slug' => 'sparkles', 'icon_tone' => 'accent'], JSON_UNESCAPED_SLASHES),
      ]),
      'column_item' => new Block([
        'type' => 'column_item',
        'title' => 'Docs',
        'content' => 'Build faster.',
        'settings' => json_encode(['icon_slug' => 'sparkles', 'icon_tone' => 'accent'], JSON_UNESCAPED_SLASHES),
      ]),
      'link-list-item' => new Block([
        'type' => 'link-list-item',
        'title' => 'Docs',
        'url' => '/docs',
        'settings' => json_encode(['icon_slug' => 'sparkles', 'icon_tone' => 'accent'], JSON_UNESCAPED_SLASHES),
      ]),
    ];

    foreach ($cases as $view => $block) {
      $block->setRelation('children', collect());

      $html = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.'.$view, [
        'block' => $block,
      ])->render();

      $this->assertStringContainsString('wb-icon wb-icon-sparkles wb-icon-tone-accent', $html, $view);
    }
  }

  #[Test]
  public function default_and_invalid_public_icon_tones_do_not_render_tone_classes(): void
  {
    $this->createIcon('sparkles', true);

    foreach (['default', 'success', ''] as $tone) {
      $block = new Block([
        'type' => 'content_header',
        'title' => 'Docs',
        'settings' => json_encode([
          'icon_slug' => 'sparkles',
          'icon_tone' => $tone,
        ], JSON_UNESCAPED_SLASHES),
      ]);

      $html = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.content_header', [
        'block' => $block,
      ])->render();

      $this->assertStringContainsString('class="wb-icon wb-icon-sparkles" aria-hidden="true"', $html);
      $this->assertStringNotContainsString('wb-icon-tone-', $html);
    }
  }

  #[Test]
  public function inactive_public_icon_does_not_render_a_raw_icon_class(): void
  {
    $this->createIcon('sparkles', false);

    $block = new Block([
      'type' => 'content_header',
      'title' => 'Docs',
      'settings' => json_encode(['icon_slug' => 'sparkles', 'icon_tone' => 'brand'], JSON_UNESCAPED_SLASHES),
    ]);

    $html = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.content_header', [
      'block' => $block,
    ])->render();

    $this->assertStringNotContainsString('wb-icon-sparkles', $html);
    $this->assertStringNotContainsString('wb-icon-tone-brand', $html);
  }

  #[Test]
  public function block_contract_discovery_includes_public_icon_and_badge_fields(): void
  {
    $this->seedFoundation();

    $contract = app(BlockTypeContractRegistry::class)
      ->resolve(BlockType::query()->where('slug', 'link-list-item')->firstOrFail())
      ->toAuditArray();

    $this->assertContains('settings.icon_slug', $contract['shared_settings_fields']);
    $this->assertContains('settings.icon_tone', $contract['shared_settings_fields']);
    $this->assertContains('settings.badge_tone', $contract['shared_settings_fields']);
    $this->assertContains('optional eyebrow as badge_label', $contract['translatable_fields']);
  }

  #[Test]
  public function admin_forms_render_content_icon_picker_options(): void
  {
    $this->seedFoundation();
    $this->createIcon('sparkles', true);

    $block = new Block(['type' => 'content_header']);

    $html = view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.types.content_header', [
      'block' => $block,
    ])->render();

    $this->assertStringContainsString('name="icon_slug"', $html);
    $this->assertStringContainsString('value="sparkles"', $html);
    $this->assertStringContainsString('name="icon_tone"', $html);
    $this->assertStringContainsString('value="brand"', $html);
    $this->assertStringContainsString('name="badge_label"', $html);
    $this->assertStringContainsString('name="badge_tone"', $html);
  }

  private function seedFoundation(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(BlockTypeSeeder::class);
  }

  private function createIcon(string $slug, bool $active): IconCatalogItem
  {
    return IconCatalogItem::query()->create([
      'source' => 'webblocks-ui',
      'slug' => $slug,
      'label' => str($slug)->replace('-', ' ')->title()->toString(),
      'css_class' => 'wb-icon-'.$slug,
      'contexts' => ['content'],
      'categories' => ['content'],
      'keywords' => [$slug],
      'is_active' => $active,
      'sort_order' => 1,
    ]);
  }

  private function defaultLocale(): Locale
  {
    return Locale::query()->where('is_default', true)->firstOrFail();
  }

  private function pageWithSlot(): array
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $slotType = SlotType::query()->updateOrCreate(
      ['slug' => 'main'],
      ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
    );
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Docs',
      'slug' => 'docs',
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
      ['site_id' => $site->id, 'name' => 'Docs', 'slug' => 'docs', 'path' => '/docs'],
    );

    $pageSlot = PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
    ]);

    return [$page, $pageSlot, $slotType];
  }
}
