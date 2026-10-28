<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\BlockTypes\BlockSettingsSchema;
use WebBlocks\Cms\Support\BlockTypes\BlockSettingsValidator;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Relational\SettingDefinitions;
use WebBlocks\Cms\Support\Relational\SettingTree;
use WebBlocks\Cms\Support\Relational\StructuredSettings;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\Tests\TestCase;

class BlockSettingsSchemaTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function jsonRequest(array $payload): Request
  {
    return Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
  }

  private function fixture(): Page
  {
    $site = Site::create(['name' => 'Schema fixture', 'handle' => 'schema', 'is_primary' => true]);
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
    $layout = PageLayout::create(['handle' => 'default', 'name' => 'Default', 'is_active' => true]);
    PageLayoutSlot::create(['page_layout_id' => $layout->id, 'slot_type_id' => $slot->id, 'slot_name' => 'main', 'is_active' => true, 'is_required' => true]);
    app(CoreBlockTypeCatalogSyncer::class)->sync();
    $page = Page::create(['site_id' => $site->id, 'title' => 'Schema fixture', 'path' => '/schema', 'status' => 'draft', 'settings' => ['public_shell' => 'default']]);
    $page->slots()->create(['slot_type_id' => $slot->id, 'slot_name' => 'main', 'sort_order' => 0]);

    return $page;
  }

  private function block(Page $page, array $settings): Block
  {
    return Block::create(['page_id' => $page->id, 'block_type_id' => BlockType::where('slug', 'plain_text')->value('id'), 'type' => 'plain_text', 'slot_type_id' => SlotType::where('slug', 'main')->value('id'), 'status' => 'draft', 'settings' => $settings]);
  }

  private function merge(Block $block, array $settings): array
  {
    return (new ReflectionMethod(InternalContentResourceController::class, 'mergeSettings'))->invoke(app(InternalContentResourceController::class), $block, Request::create('/', 'PATCH', ['settings' => $settings]));
  }

  #[Test]
  public function every_published_core_contract_has_typed_discovery_without_changing_legacy_schema_fields(): void
  {
    $registry = app(BlockSettingsSchema::class);
    $contracts = app(BlockTypeContractRegistry::class)->publishedCoreContracts();
    $this->assertCount(56, $contracts);
    foreach ($contracts as $contract) {
      $schema = $registry->forType($contract->slug);
      $this->assertSame('core', $schema['mode'], $contract->slug);
      $this->assertSame(1, $schema['revision']);
      foreach ($contract->sharedSettingsFields as $field) {
        if (str_starts_with($field, 'settings.')) {
          $key = substr($field, 9);
          $this->assertArrayHasKey($key, $schema['fields'], $contract->slug.'.'.$key);
          $this->assertArrayHasKey('patch', $schema['fields'][$key]['writes']);
        }
      }
    }
    $html = $registry->forType('html');
    foreach ($html['fields'] as $field) {
      $this->assertFalse($field['writes']['create']);
      $this->assertFalse($field['writes']['patch']);
    }
    $contact = $registry->forType('contact_form')['fields']['recipient_email'];
    $this->assertTrue($contact['writes']['create']); // Existing operation policy.
    $this->assertFalse($contact['writes']['patch']);
    $this->assertSame('deliberate', $contact['closed_reason']);
    $this->assertFalse($registry->forType('hero')['fields']['media_id']['writes']['patch']);
  }

  #[Test]
  public function create_plan_slot_and_patch_reject_typos_and_bad_values_before_writing(): void
  {
    $page = $this->fixture();
    $block = $this->block($page, ['alignment' => 'left']);
    foreach ([['aligment' => 'center'], ['alignment' => 'sideways'], ['alignment' => ['center']], ['mobile_media_id' => 1]] as $settings) {
      $errors = $warnings = [];
      app(InternalContentApiOperations::class)->normalizeBlock(['type' => 'plain_text', 'settings' => $settings], 'block', null, $errors, $warnings);
      $this->assertNotEmpty($errors);
      $this->assertSame('block.settings.'.array_key_first($settings), $errors[0]['path']);
      $plan = ['plan' => ['mode' => 'create_draft_page', 'site' => 'schema', 'locale' => 'en', 'layout' => 'default', 'page' => ['title' => 'New page', 'path' => '/new-page', 'status' => 'draft'], 'slots' => ['main' => [['type' => 'plain_text', 'settings' => $settings, 'translations' => ['content' => 'Synthetic']]]]]];
      $result = app(InternalContentPlanService::class)->apply($plan);
      $this->assertFalse($result->ok);
      $this->assertSame(1, Page::count());
      $response = app(InternalContentResourceController::class)->storeSlotBlock($this->jsonRequest(['type' => 'plain_text', 'locale' => 'en', 'settings' => $settings]), $page, 'main');
      $this->assertSame(422, $response->getStatusCode());
      $this->assertSame('block.settings.'.array_key_first($settings), $response->getData(true)['errors'][0]['path']);
      try {
        $this->merge($block, $settings);
        $this->fail('Invalid PATCH must fail.');
      } catch (HttpResponseException $exception) {
        $this->assertSame(422, $exception->getResponse()->getStatusCode());
      }
      $this->assertSame(['alignment' => 'left'], $block->fresh()->decodedSettings());
      $this->assertSame(1, Block::count());
    }
    $schema = app(InternalContentApiPresenter::class)->blockType($block->blockType)['authoring_settings_schema'];
    $this->assertSame(['left', 'center', 'right'], $schema['fields']['alignment']['rule'][1]);
  }

  #[Test]
  public function historical_values_survive_unrelated_changes_snapshots_and_sql_roundtrips(): void
  {
    $page = $this->fixture();
    $legacy = ['legacy' => ['items' => ['x', 'x', 0, '0', false, null, [], 'İçerik 🎯']], 'alignment' => ['old-array'], 'empty' => '', 'nil' => null];
    $block = $this->block($page, $legacy);
    $this->assertSame($legacy, $this->merge($block, []));
    $this->assertSame($legacy, $this->merge($block, $legacy));
    $updated = $this->merge($block, ['alignment' => 'center']);
    $this->assertSame(array_replace($legacy, ['alignment' => 'center']), $updated);
    $block->settings = $updated;
    $block->save();
    $this->assertSame($updated, $block->fresh()->decodedSettings());
    $snapshot = app(PageRevisionManager::class)->snapshotForInspection($page->fresh());
    $stored = collect($snapshot['blocks'])->firstWhere('snapshot_id', $block->id);
    $this->assertSame($updated, json_decode($stored['settings'], true));
    $this->assertArrayNotHasKey('alignment', $this->merge($block->fresh(), ['alignment' => null]));
    try {
      $this->merge($block->fresh(), ['legacy' => ['changed' => true]]);
      $this->fail('An existing unknown key is not permission to change it.');
    } catch (HttpResponseException $exception) {
      $this->assertSame(422, $exception->getResponse()->getStatusCode());
    }
  }

  #[Test]
  public function scalar_rules_preserve_false_zero_and_clear_distinctly_without_clamping_or_truncating(): void
  {
    foreach (['admin', 'create', 'patch'] as $operation) {
      $errors = [];
      $value = app(BlockSettingsValidator::class)->normalize('application', ['min_height' => 0, 'show_loading_state' => false], 'settings', $errors, $operation);
      $this->assertSame([], $errors);
      $this->assertSame(['min_height' => 0, 'show_loading_state' => false], $value);
      foreach ([['min_height' => 2001], ['min_height' => 1.5], ['show_loading_state' => 'maybe'], ['application_settings' => ['list']], ['application_handle' => null]] as $invalid) {
        $errors = [];
        app(BlockSettingsValidator::class)->normalize('application', $invalid, 'settings', $errors, $operation);
        $this->assertNotEmpty($errors);
      }
    }
    $errors = [];
    app(BlockSettingsValidator::class)->normalize('plain_text', ['url' => '//unsafe.example'], 'settings', $errors);
    $this->assertNotEmpty($errors);
  }

  private function plugin(?array $schema, bool $enabled = true): void
  {
    $definition = PluginBlockTypeDefinition::make('schema-test::card')->label('Synthetic plugin');
    if ($schema !== null) {
      $definition->settingsSchema($schema);
    }
    $registry = new PluginRegistry(['schema-test' => $enabled]);
    $registry->register(PluginDefinition::make('schema-test')->label('Schema test')->version('1.0.0')->blockTypes([$definition]));
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->forgetInstance(PluginBlockCatalog::class);
  }

  #[Test]
  public function plugins_opt_in_without_a_required_interface_change_and_bad_declarations_fail_locally(): void
  {
    $this->plugin(null);
    $errors = [];
    $legacy = ['custom' => ['nested' => ['x', 'x', false]]];
    $this->assertSame($legacy, app(BlockSettingsValidator::class)->normalize('schema-test-card', $legacy, 'settings', $errors));
    $this->assertSame([], $errors);
    $this->plugin(['enabled' => ['bool'], 'limit' => ['int', 0, 12], 'style' => ['enum', ['plain', 'cards']]]);
    $errors = [];
    $this->assertSame(['enabled' => false, 'limit' => 0], app(BlockSettingsValidator::class)->normalize('schema-test-card', ['enabled' => '0', 'limit' => '0'], 'settings', $errors));
    $this->assertSame([], $errors);
    app(BlockSettingsValidator::class)->normalize('schema-test-card', ['limit' => 13], 'settings', $errors);
    $this->assertNotEmpty($errors);
    $this->plugin(['bad' => ['unknown']]);
    $errors = [];
    app(BlockSettingsValidator::class)->normalize('schema-test-card', [], 'settings', $errors);
    $this->assertSame('invalid_block_settings_schema', $errors[0]['code']);
    $this->assertSame('core', app(BlockSettingsSchema::class)->forType('plain_text')['mode']);
    $manifest = app(InstalledPluginDefinitionFactory::class)->make(['handle' => 'schema-test', 'label' => 'Schema', 'version' => '1.0.0', 'block_types' => [['handle' => 'schema-test::card', 'settings_schema' => ['limit' => ['int', 1, 12]]]]], 'synthetic', false);
    $this->assertSame(['limit' => ['int', 1, 12]], array_values($manifest->blockTypeDefinitions())[0]->settingRules());
  }

  #[Test]
  public function admin_form_uses_the_shared_rules_and_preserves_unknown_settings(): void
  {
    $this->app->instance(AdminAuthorization::class, new class extends AdminAuthorization
    {
      public function normalizeAllowedMediaId($user, ?int $mediaId): ?int
      {
      return null;
      }

      public function filterAllowedMediaIds($user, array $mediaIds): array
      {
      return [];
      }
    });
    $page = $this->fixture();
    $block = $this->block($page, ['historical' => ['keep', 'keep', false], 'alignment' => 'left']);
    $request = BlockRequest::create('/blocks/'.$block->id, 'PUT', ['page_id' => $page->id, 'block_type_id' => $block->block_type_id, 'slot_type_id' => $block->slot_type_id, 'sort_order' => 0, 'status' => 'draft', 'text' => 'Synthetic copy', 'alignment' => 'center']);
    $request->setContainer($this->app);
    $request->setRedirector(app('redirect'));
    $route = new Route(['PUT'], '/blocks/{block}', []);
    $route->bind($request);
    $route->setParameter('block', $block);
    $request->setRouteResolver(fn () => $route);
    $request->validateResolved();
    $settings = json_decode($request->validatedData()['settings'], true);
    $this->assertSame('center', $settings['alignment']);
    $this->assertSame(['keep', 'keep', false], $settings['historical']);
    $this->assertSame($this->merge($block, ['alignment' => 'center']), $settings);
  }

  #[Test]
  public function valid_creation_and_patch_use_identical_normalized_values(): void
  {
    $page = $this->fixture();
    $payload = ['type' => 'plain_text', 'locale' => 'en', 'settings' => ['alignment' => 'center'], 'translations' => ['content' => 'Synthetic copy']];
    $response = app(InternalContentResourceController::class)->storeSlotBlock($this->jsonRequest($payload), $page, 'main');
    $this->assertSame(201, $response->getStatusCode(), $response->getContent());
    $block = Block::where('page_id', $page->id)->sole();
    $this->assertSame(['alignment' => 'center'], $block->decodedSettings());
    $this->assertSame($block->decodedSettings(), $this->merge($block, ['alignment' => 'center']));
    $errors = $warnings = [];
    $normalized = app(InternalContentApiOperations::class)->normalizeBlock($payload, 'block', null, $errors, $warnings);
    $this->assertSame([], $errors);
    $this->assertSame($block->decodedSettings(), $normalized['settings']);
  }

  #[Test]
  public function settings_aliases_update_the_canonical_fields_used_by_renderers(): void
  {
    $page = $this->fixture();
    foreach (['header' => ['variant' => 'h2'], 'button_link' => ['url' => '/changed', 'variant' => 'secondary'], 'card' => ['variant' => 'accent']] as $type => $settings) {
      $block = Block::create(['page_id' => $page->id, 'type' => $type, 'block_type_id' => BlockType::where('slug', $type)->value('id'), 'status' => 'draft']);
      $request = Request::create('/', 'PATCH', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['settings' => $settings]));
      $response = app(InternalContentResourceController::class)->updateBlock($request, $block);
      $this->assertSame(200, $response->getStatusCode(), $response->getContent());
      foreach ($settings as $field => $value) {
        $this->assertSame($value, $block->fresh()->getRawOriginal($field));
      }
    }
  }

  #[Test]
  public function opted_in_plugin_slot_patch_and_version_changes_preserve_existing_values(): void
  {
    $page = $this->fixture();
    $this->plugin(['enabled' => ['bool']]);
    $type = BlockType::create(['name' => 'Schema plugin', 'slug' => 'schema-test-card', 'category' => 'content', 'status' => 'published']);
    $controller = app(InternalContentResourceController::class);
    $response = $controller->storeSlotBlock($this->jsonRequest(['type' => $type->slug, 'locale' => 'en', 'settings' => ['enabled' => false]]), $page, 'main');
    $this->assertSame(201, $response->getStatusCode(), $response->getContent());
    $block = Block::where('block_type_id', $type->id)->sole();
    $this->assertSame(['enabled' => false], $block->decodedSettings());
    // A later plugin version adds a field, then an older version no longer
    // declares it. Existing values remain independent of the active definition.
    $this->plugin(['enabled' => ['bool'], 'limit' => ['int', 0, 12]]);
    $this->assertSame(['enabled' => false, 'limit' => 0], $this->merge($block, ['limit' => '0']));
    $block->settings = ['enabled' => false, 'limit' => 0, 'extension' => ['x', 'x', null]];
    $block->save();
    $this->plugin(['enabled' => ['bool']]);
    $this->assertSame(['enabled' => true, 'limit' => 0, 'extension' => ['x', 'x', null]], $this->merge($block, ['enabled' => true]));
    $before = $block->fresh()->decodedSettings();
    $this->plugin(['enabled' => ['bool']], false);
    $errors = $warnings = [];
    $this->assertNull(app(InternalContentApiOperations::class)->normalizeBlock(['type' => $type->slug], 'block', null, $errors, $warnings));
    $this->assertNotEmpty($errors);
    $this->assertSame($before, $block->fresh()->decodedSettings());
    $this->plugin(null);
    $this->assertSame($before, $block->fresh()->decodedSettings());
    $this->assertSame('legacy_plugin', app(BlockSettingsSchema::class)->forType($type->slug)['mode']);
  }

  #[Test]
  public function new_payload_bounds_do_not_retroactively_limit_unchanged_historical_data(): void
  {
    $validator = app(BlockSettingsValidator::class);
    $huge = ['legacy' => str_repeat('x', 262145)];
    $errors = [];
    $this->assertSame($huge, $validator->normalize('plain_text', $huge, 'settings', $errors, 'patch', $huge));
    $this->assertSame([], $errors);
    $validator->normalize('plain_text', $huge, 'settings', $errors);
    $this->assertSame('invalid_block_settings', $errors[0]['code']);
    foreach ([['content_bindings' => ['title' => array_fill(0, 4096, 'x')]], ['content_bindings' => ['title' => array_reduce(range(1, 33), fn ($value) => ['nested' => $value], [])]]] as $invalid) {
      $errors = [];
      $validator->normalize('plain_text', $invalid, 'settings', $errors);
      $this->assertSame('invalid_block_settings', $errors[0]['code']);
    }
    $errors = [];
    $this->assertSame(['columns' => '2'], $validator->normalize('grid', ['columns' => 2], 'settings', $errors));
    $this->assertSame([], $errors);
    $this->assertSame(['transition' => 'slide'], $validator->normalize('slider', ['transition' => 'fade'], 'settings', $errors));
    $this->assertSame(['variant' => 'masonry'], $validator->normalize('gallery', ['variant' => 'masonary'], 'settings', $errors));
    foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
      $this->assertNotSame('webblocks-cms::block-settings.invalid', trans('webblocks-cms::block-settings.invalid', [], $locale));
    }
  }

  #[Test]
  public function native_gallery_form_preserves_its_viewer_title(): void
  {
    $this->app->instance(AdminAuthorization::class, new class extends AdminAuthorization
    {
      public function normalizeAllowedMediaId($user, ?int $mediaId): ?int
      {
      return null;
      }

      public function filterAllowedMediaIds($user, array $mediaIds): array
      {
      return [];
      }
    });
    $page = $this->fixture();
    $block = $this->block($page, []);
    $block->update(['type' => 'gallery', 'block_type_id' => BlockType::where('slug', 'gallery')->value('id')]);
    $request = BlockRequest::create('/blocks/'.$block->id, 'PUT', ['page_id' => $page->id, 'block_type_id' => $block->block_type_id, 'slot_type_id' => $block->slot_type_id, 'sort_order' => 0, 'status' => 'draft', 'gallery_viewer_title' => 'Synthetic gallery title']);
    $request->setContainer($this->app);
    $request->setRedirector(app('redirect'));
    $route = new Route(['PUT'], '/blocks/{block}', []);
    $route->bind($request);
    $route->setParameter('block', $block);
    $request->setRouteResolver(fn () => $route);
    $request->validateResolved();
    $settings = json_decode($request->validatedData()['settings'], true);
    $this->assertSame('Synthetic gallery title', $settings['viewer_title']);
    $this->assertSame('below', $settings['captions_mode']);
  }

  #[Test]
  public function compatibility_report_is_read_only_and_never_prints_setting_values(): void
  {
    $page = $this->fixture();
    $block = $this->block($page, ['unknown' => 'private-fixture-value', 'alignment' => 'center']);
    $before = $block->fresh()->portableAttributes();
    $this->artisan('webblocks:blocks:audit-settings')->expectsOutputToContain('unsupported_block_settings_fields')->doesntExpectOutputToContain('private-fixture-value')->assertExitCode(1);
    $this->assertSame($before, $block->fresh()->portableAttributes());
  }

  #[Test]
  public function comment_fields_have_typed_sql_authority_and_update_migration_is_idempotent(): void
  {
    $page = $this->fixture();
    $values = ['sort_order' => 'oldest', 'show_approved' => false, 'show_author_name' => true, 'store_submissions' => false, 'unknown' => ['keep', 'keep']];
    $block = $this->block($page, $values);
    $document = DB::table('wbcms_setting_documents')->where('block_id', $block->id)->value('id');
    $this->assertDatabaseHas('wbcms_block_content_settings', ['document_id' => $document, 'sort_order' => 'oldest', 'show_approved' => 0, 'show_author_name' => 1]);
    [$nodes] = SettingTree::encode((object) $values, true, 'settings', fn () => null);
    DB::table('wbcms_setting_nodes')->where('document_id', $document)->delete();
    foreach ($nodes as $node) {
      DB::table('wbcms_setting_nodes')->insert(['document_id' => $document, ...$node]);
    }
    Schema::table('wbcms_block_content_settings', function ($table): void {
      foreach (['sort_order', 'show_approved', 'show_author_name', 'store_submissions'] as $key) {
        $table->dropColumn([$key, $key.'_kind', $key.'_text']);
      }
    });
    app(StructuredSettings::class)->clearAvailability();
    $this->assertSame($values, $block->fresh()->decodedSettings());
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_21_120000_declare_block_authoring_settings.php';
    $migration->up();
    $migration->up();
    $this->assertSame($values, $block->fresh()->decodedSettings());
    $this->assertDatabaseHas('wbcms_block_content_settings', ['document_id' => $document, 'sort_order' => 'oldest', 'show_approved' => 0, 'show_author_name' => 1]);
    foreach (['sort_order', 'show_approved', 'show_author_name', 'store_submissions'] as $key) {
      $this->assertArrayHasKey($key, SettingDefinitions::tables()['block_content_settings']);
    }
  }
}
