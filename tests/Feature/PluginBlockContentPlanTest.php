<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\PageSlot;
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
    $this->assertSame(
      ['intro' => 'Wählen Sie einen verfügbaren Termin.', 'submit_label' => 'Termin verbindlich buchen', 'title' => 'Online-Termin buchen'],
      Block::query()->sole()->pluginTranslations()->whereHas('locale', fn ($query) => $query->where('code', 'de'))->pluck('value', 'field')->sortKeys()->all(),
    );
  }

  #[Test]
  public function direct_page_slot_post_creates_a_plugin_block_with_translations(): void
  {
    $site = Site::query()->where('handle', 'default')->sole();
    $main = SlotType::query()->where('slug', 'main')->sole();
    $page = Page::query()->create(['site_id' => $site->id, 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'sort_order' => 0,
    ]);

    $request = Request::create(
      '/webadmin/api/pages/'.$page->id.'/slots/main/blocks?locale=de',
      'POST',
      [],
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode($this->pluginBlockPayload(), JSON_THROW_ON_ERROR),
    );

    $response = app(InternalContentResourceController::class)->storeSlotBlock($request, $page, 'main');
    $payload = $response->getData(true);

    $this->assertSame(201, $response->getStatusCode());
    $this->assertTrue($payload['ok']);
    $this->assertSame('webblocks-appointments-form', $payload['block']['type']);
    $this->assertSame(
      ['intro' => 'Wählen Sie einen verfügbaren Termin.', 'submit_label' => 'Termin verbindlich buchen', 'title' => 'Online-Termin buchen'],
      Block::query()->sole()->pluginTranslations()->whereHas('locale', fn ($query) => $query->where('code', 'de'))->pluck('value', 'field')->sortKeys()->all(),
    );
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

  #[Test]
  public function repair_migration_restores_a_missing_plugin_translation_table(): void
  {
    Schema::drop('wbcms_block_plugin_translations');

    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_08_26_120000_repair_block_plugin_translations_table.php';
    $migration->up();
    $migration->up();

    $this->assertTrue(Schema::hasTable('wbcms_block_plugin_translations'));
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
            ...$this->pluginBlockPayload(),
          ]],
        ],
      ],
    ];
  }

  private function pluginBlockPayload(): array
  {
    return [
      'type' => 'webblocks-appointments-form',
      'translations' => [
        'title' => 'Online-Termin buchen',
        'intro' => 'Wählen Sie einen verfügbaren Termin.',
        'submit_label' => 'Termin verbindlich buchen',
      ],
      'settings' => [],
    ];
  }
}
