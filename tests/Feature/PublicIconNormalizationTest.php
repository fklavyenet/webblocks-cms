<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The full content plan and the incremental block endpoints must normalize
 * public icons identically. They used to drift: the incremental normalizer
 * ignored icon_slug entirely and rejected icon_tone on feature-item.
 */
class PublicIconNormalizationTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function incremental_create_normalizes_a_valid_icon_slug(): void
  {
    $this->seedIconAndBlockTypes();

    $errors = [];
    $warnings = [];
    $normalized = app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'column_item',
      'settings' => ['icon_slug' => 'Rocket'],
    ], 'block', null, $errors, $warnings);

    $this->assertSame([], $errors);
    $this->assertSame('rocket', $normalized['settings']['icon_slug']);
  }

  #[Test]
  public function incremental_create_rejects_an_unknown_icon_slug(): void
  {
    $this->seedIconAndBlockTypes();

    $errors = [];
    $warnings = [];
    $normalized = app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'column_item',
      'settings' => ['icon_slug' => 'not-a-real-icon'],
    ], 'block', null, $errors, $warnings);

    // Regression: this used to survive normalization unvalidated, so the block
    // saved an icon the public renderer would silently skip.
    $this->assertContains('block.settings.icon_slug', array_column($errors, 'path'));
    $this->assertArrayNotHasKey('icon_slug', $normalized['settings']);
  }

  #[Test]
  public function incremental_create_rejects_an_icon_slug_on_a_block_without_icons(): void
  {
    $this->seedIconAndBlockTypes();

    $errors = [];
    $warnings = [];
    app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'header',
      'settings' => ['icon_slug' => 'rocket'],
    ], 'block', null, $errors, $warnings);

    $this->assertContains('block.settings.icon_slug', array_column($errors, 'path'));
  }

  #[Test]
  public function incremental_create_accepts_an_icon_tone_on_feature_item(): void
  {
    $this->seedIconAndBlockTypes();

    $errors = [];
    $warnings = [];
    $normalized = app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'feature-item',
      'settings' => ['icon_tone' => 'brand'],
    ], 'block', null, $errors, $warnings);

    // Regression: the incremental list omitted feature-item, so a supported
    // icon tone was rejected as "not icon-enabled".
    $this->assertSame([], $errors);
    $this->assertSame('brand', $normalized['settings']['icon_tone']);
  }

  #[Test]
  public function the_icon_enabled_block_type_list_has_one_owner(): void
  {
    $this->assertSame([
      'content_header',
      'card_header',
      'column_item',
      'feature-item',
      'link-list-item',
    ], InternalContentApiOperations::PUBLIC_ICON_BLOCK_TYPES);
  }

  private function seedIconAndBlockTypes(): void
  {
    IconCatalogItem::query()->create([
      'source' => 'webblocks-ui',
      'slug' => 'rocket',
      'label' => 'Rocket',
      'css_class' => 'wb-icon-rocket',
      'contexts' => ['content'],
      'is_active' => true,
    ]);

    foreach ([
      ['name' => 'Column Item', 'slug' => 'column_item'],
      ['name' => 'Feature Item', 'slug' => 'feature-item'],
      ['name' => 'Header', 'slug' => 'header'],
    ] as $index => $definition) {
      BlockType::query()->create($definition + [
        'category' => 'content',
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => false,
        'sort_order' => $index,
        'status' => 'published',
      ]);
    }
  }
}
