<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Tests\TestCase;

class ManagedCtaApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function hero_payload_normalizes_managed_ctas(): void
  {
    $this->seedBlockTypes();

    $errors = [];
    $warnings = [];
    $normalized = app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'hero',
      'translations' => ['title' => 'Welcome'],
      'primary_cta' => ['label' => 'Get started', 'url' => '/signup'],
      'secondary_cta' => ['label' => 'Docs', 'url' => 'https://example.com/docs'],
    ], 'block', null, $errors, $warnings);

    $this->assertSame([], $errors);
    $this->assertSame('Get started', $normalized['_managed_ctas'][0]['label']);
    $this->assertSame('/signup', $normalized['_managed_ctas'][0]['url']);
    $this->assertSame('primary', $normalized['_managed_ctas'][0]['variant']);
    $this->assertSame('Docs', $normalized['_managed_ctas'][1]['label']);
    $this->assertSame('secondary', $normalized['_managed_ctas'][1]['variant']);
  }

  #[Test]
  public function creating_a_hero_through_the_api_produces_editable_managed_button_children(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();

    $errors = [];
    $warnings = [];
    $normalized = app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'hero',
      'translations' => ['title' => 'Welcome'],
      'primary_cta' => ['label' => 'Get started', 'url' => '/signup'],
    ], 'block', null, $errors, $warnings);

    $hero = app(InternalContentApiOperations::class)
      ->createPageSlotBlock($page, $slotType, $normalized, 'en', null, 0);

    $button = Block::query()->where('parent_id', $hero->id)->with('textTranslations')->first();

    $this->assertNotNull($button, 'The API hero must create a managed button child.');
    $this->assertSame('button_link', $button->type);
    $this->assertSame('/signup', $button->url);
    $this->assertSame('primary', $button->variant);
    $this->assertSame($hero->slot_type_id, $button->slot_type_id);

    // The visible label lives in the normal translation row, exactly like an
    // admin-authored CTA, so it stays editable in the block editor.
    $this->assertSame('Get started', $button->textTranslations->first()?->title);
  }

  #[Test]
  public function managed_cta_url_must_be_safe(): void
  {
    $this->seedBlockTypes();

    $errors = [];
    $warnings = [];
    app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'hero',
      'primary_cta' => ['label' => 'Bad', 'url' => 'javascript:alert(1)'],
    ], 'block', null, $errors, $warnings);

    $this->assertContains('block.primary_cta.url', array_column($errors, 'path'));
  }

  #[Test]
  public function managed_cta_requires_label_and_url(): void
  {
    $this->seedBlockTypes();

    $errors = [];
    $warnings = [];
    app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'hero',
      'primary_cta' => ['label' => 'Only label'],
    ], 'block', null, $errors, $warnings);

    $this->assertContains('block.primary_cta.url', array_column($errors, 'path'));
  }

  #[Test]
  public function managed_cta_fields_are_rejected_on_unsupported_block_types(): void
  {
    $this->seedBlockTypes();

    $errors = [];
    $warnings = [];
    app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'header',
      'primary_cta' => ['label' => 'Nope', 'url' => '/x'],
    ], 'block', null, $errors, $warnings);

    $this->assertContains('block.primary_cta', array_column($errors, 'path'));
  }

  #[Test]
  public function blocks_without_managed_ctas_are_unaffected(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();

    $errors = [];
    $warnings = [];
    $normalized = app(InternalContentApiOperations::class)
      ->normalizeBlock(['type' => 'hero', 'translations' => ['title' => 'Plain']], 'block', null, $errors, $warnings);

    $hero = app(InternalContentApiOperations::class)
      ->createPageSlotBlock($page, $slotType, $normalized, 'en', null, 0);

    $this->assertSame([], $errors);
    $this->assertSame(0, Block::query()->where('parent_id', $hero->id)->count());
  }

  #[Test]
  public function navigation_auto_has_a_discoverable_contract(): void
  {
    $blockType = new BlockType(['slug' => 'navigation-auto', 'name' => 'Navigation Auto']);
    $contract = app(BlockTypeContractRegistry::class)->resolve($blockType)->toAuditArray();

    $this->assertSame('clear', $contract['current_contract_status']);
    $this->assertContains('settings.menu_key', $contract['shared_settings_fields']);
    $this->assertStringContainsString('data-wb-menu-key', $contract['renderer_root_contract']);
  }

  #[Test]
  public function card_variant_is_part_of_the_documented_contract(): void
  {
    $contract = app(BlockTypeContractRegistry::class)->resolve('card')->toAuditArray();

    $this->assertContains('variant', $contract['shared_settings_fields']);
    $this->assertContains('Card style', $contract['admin_form_fields']);
  }

  #[Test]
  public function hero_renders_managed_button_link_actions(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();

    $heroType = BlockType::query()->where('slug', 'hero')->firstOrFail();
    $buttonType = BlockType::query()->where('slug', 'button_link')->firstOrFail();

    $hero = Block::query()->create([
      'page_id' => $page->id, 'type' => 'hero', 'block_type_id' => $heroType->id,
      'source_type' => 'static', 'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => 0, 'status' => 'published', 'title' => 'Welcome',
    ]);

    Block::query()->create([
      'page_id' => $page->id, 'parent_id' => $hero->id, 'type' => 'button_link',
      'block_type_id' => $buttonType->id, 'source_type' => 'static',
      'slot' => $slotType->slug, 'slot_type_id' => $slotType->id, 'sort_order' => 0,
      'status' => 'published', 'title' => 'Get started', 'url' => '/signup', 'variant' => 'primary',
      'settings' => json_encode(['url' => '/signup', 'target' => '_self']),
    ]);

    $html = view('webblocks-cms::pages.partials.blocks.hero', [
      'block' => $hero->fresh(['children.blockType', 'blockType']),
    ])->render();

    // Regression: hero, cta, and the shared _actions partial used to accept only
    // the unpublished `button` type, so managed `button_link` CTAs never rendered.
    $this->assertStringContainsString('Get started', $html);
    $this->assertStringContainsString('/signup', $html);
  }

  #[Test]
  public function managed_cta_is_written_in_the_button_link_shape_its_renderer_reads(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();

    $errors = [];
    $warnings = [];
    $normalized = app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'hero',
      'primary_cta' => ['label' => 'Get started', 'url' => '/signup'],
    ], 'block', null, $errors, $warnings);

    $hero = app(InternalContentApiOperations::class)
      ->createPageSlotBlock($page, $slotType, $normalized, 'en', null, 0);

    $button = Block::query()->where('parent_id', $hero->id)->firstOrFail();

    // button_link resolves its href from settings, so a managed CTA that only
    // set the legacy url column rendered as an empty action.
    $this->assertSame('/signup', $button->buttonLinkUrl());
    $this->assertSame('_self', $button->buttonLinkTarget());
  }

  private function seedBlockTypes(): void
  {
    foreach ([
      ['name' => 'Hero', 'slug' => 'hero', 'category' => 'content', 'is_container' => true],
      ['name' => 'Header', 'slug' => 'header', 'category' => 'content', 'is_container' => false],
      ['name' => 'Button Link', 'slug' => 'button_link', 'category' => 'content', 'is_container' => false],
    ] as $index => $definition) {
      BlockType::query()->create($definition + [
        'source_type' => 'static',
        'is_system' => false,
        'sort_order' => $index,
        'status' => 'published',
      ]);
    }
  }

  /**
   * @return array{0: Page, 1: SlotType}
   */
  private function seedPage(): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slotType = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'home', 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 0]);

    return [$page, $slotType];
  }
}
