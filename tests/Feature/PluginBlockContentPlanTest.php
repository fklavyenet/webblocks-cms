<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationRegistry;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class PluginBlockContentPlanTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();

    $plugin = PluginDefinition::make('webblocks-appointments')
      ->label('Appointments')
      ->version('0.10.1')
      ->blockTypes([
        PluginBlockTypeDefinition::make('webblocks-appointments::form')
          ->label('Appointments Form')
          ->translatedFields(['title', 'intro', 'submit_label']),
      ]);

    $registry = new PluginRegistry(['webblocks-appointments' => true]);
    $registry->register($plugin);
    $this->app->forgetInstance(PluginRegistry::class);
    $this->app->singleton(PluginRegistry::class, fn (): PluginRegistry => $registry);
    $this->app->forgetInstance(PluginBlockCatalog::class);
    $this->app->forgetInstance(BlockTranslationRegistry::class);
    $this->app->forgetInstance(BlockTypeContractRegistry::class);

    $site = Site::query()->create(['name' => 'Default', 'handle' => 'default', 'is_primary' => true]);
    $english = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $german = Locale::query()->create(['code' => 'de', 'name' => 'Deutsch', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$english->id, $german->id]);

    $main = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $layout = PageLayout::query()->create(['handle' => 'default', 'name' => 'Default', 'is_active' => true, 'is_system' => true]);
    PageLayoutSlot::query()->create([
      'page_layout_id' => $layout->id,
      'slot_type_id' => $main->id,
      'slot_name' => 'main',
      'label' => 'Main',
      'is_required' => true,
      'is_active' => true,
      'is_system' => true,
      'sort_order' => 0,
    ]);

    BlockType::query()->create([
      'name' => 'Appointments Form',
      'slug' => 'webblocks-appointments-form',
      'category' => 'content',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 0,
      'status' => 'published',
    ]);
  }

  #[Test]
  public function validate_and_apply_can_create_a_plugin_block(): void
  {
    $payload = $this->minimalPlan();
    $service = app(InternalContentPlanService::class);

    $this->assertTrue($service->validate($payload)->ok);

    $result = $service->apply($payload);

    $this->assertTrue($result->ok, json_encode($result->errors, JSON_PRETTY_PRINT));
    $this->assertSame('webblocks-appointments-form', Block::query()->sole()->type);
    $this->assertCount(6, Block::query()->sole()->pluginTranslations);
  }

  #[Test]
  public function plugin_contract_publishes_its_authoring_metadata(): void
  {
    $contract = app(BlockTypeContractRegistry::class)
      ->resolve(BlockType::query()->where('slug', 'webblocks-appointments-form')->sole())
      ->toAuditArray();

    $this->assertTrue($contract['documented']);
    $this->assertSame('plugin', $contract['translation_family']);
    $this->assertSame(['title', 'intro', 'submit_label'], $contract['translatable_fields']);
    $this->assertSame('clear', $contract['current_contract_status']);
  }

  #[Test]
  public function manifest_fallback_keeps_plugin_translated_fields(): void
  {
    $plugin = app(InstalledPluginDefinitionFactory::class)->make([
      'handle' => 'webblocks-appointments',
      'label' => 'Appointments',
      'version' => '0.10.1',
      'block_types' => [[
        'handle' => 'webblocks-appointments::form',
        'label' => 'Appointments Form',
        'translated_fields' => ['title', 'intro', 'submit_label'],
      ]],
    ], sys_get_temp_dir().'/missing-webblocks-appointments', false);

    $definition = array_values($plugin->blockTypeDefinitions())[0];

    $this->assertSame(['title', 'intro', 'submit_label'], $definition->translatedFieldNames());
  }

  #[Test]
  public function apply_write_failures_return_a_stable_diagnostic_code(): void
  {
    Schema::drop('wbcms_block_plugin_translations');

    $result = app(InternalContentPlanService::class)->apply($this->minimalPlan());

    $this->assertFalse($result->ok);
    $this->assertSame('plan.apply', $result->errors[0]['path']);
    $this->assertSame(InternalContentPlanService::APPLY_WRITE_ERROR_CODE, $result->errors[0]['code']);
  }

  private function minimalPlan(): array
  {
    return [
      'plan' => [
        'mode' => 'create_draft_page',
        'site' => 'default',
        'locale' => 'de',
        'layout' => 'default',
        'page' => [
          'title' => 'Appointments API Probe',
          'path' => '/appointments-api-probe',
          'status' => 'draft',
        ],
        'slots' => [
          'main' => [[
            'type' => 'webblocks-appointments-form',
            'settings' => [],
          ]],
        ],
      ],
    ];
  }
}
