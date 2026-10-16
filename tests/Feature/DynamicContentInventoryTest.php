<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Concerns\HasCmsAdminAccess;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotBlock;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContract;
use WebBlocks\Cms\Support\ContentSources\ContentSourceDefinition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceEditor;
use WebBlocks\Cms\Support\ContentSources\ContentSourceIndicators;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRegistry;
use WebBlocks\Cms\Support\ContentSources\DynamicContentInventory;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class DynamicContentInventoryTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists('App\\Models\\User')) {
      class_alias(DynamicContentTestActor::class, 'App\\Models\\User');
    }
    Route::get('/editor/{block}', fn () => 'editor')->name('admin.blocks.edit');
    Route::get('/dynamic-content', fn () => 'inventory')->name('admin.dynamic-content.index');
    Route::get('/shared/{shared_slot}', fn () => 'shared')->name('admin.shared-slots.blocks.edit');
    Route::get('/pages/{page}/slots/{slot}', fn () => 'slot')->name('admin.pages.slots.blocks');
  }

  #[Test]
  public function standard_requires_every_cms_language(): void
  {
    $this->expectException(InvalidArgumentException::class);
    ContentSourceDefinition::collection('example::items')->standard(['en'], 'Description', 'Example API', 'id');
  }

  #[Test]
  public function standard_validates_actual_locale_schema_and_stable_identity(): void
  {
    $source = $this->source();
    foreach (ContentSourceContract::languages() as $locale) {
      $record = ['_locale' => $locale, 'id' => 'stable-1', 'title' => 'Title', 'image' => '/media/example.png'];
      $this->assertSame($record, ContentSourceContract::record($source, $record, new ContentSourceContext(null, null, $locale, true)));
    }
    $this->expectException(InvalidArgumentException::class);
    ContentSourceContract::record($source, ['_locale' => 'en', 'id' => 'stable-1', 'title' => 'Title', 'image' => '/media/example.png'], new ContentSourceContext(null, null, 'tr', true));
  }

  #[Test]
  public function standard_rejects_missing_fields_and_duplicate_collection_keys(): void
  {
    $source = $this->source();
    $context = new ContentSourceContext(null, null, 'en', true);
    try {
      ContentSourceContract::record($source, ['_locale' => 'en', 'id' => 'stable-1'], $context);
      $this->fail('Missing fields must fail.');
    } catch (InvalidArgumentException) {
      $this->assertTrue(true);
    }
    $record = ['_locale' => 'en', 'id' => 'stable-1', 'title' => 'Title', 'image' => '/media/example.png'];
    $this->expectException(InvalidArgumentException::class);
    ContentSourceContract::records($source, [$record, $record], $context);
  }

  #[Test]
  public function legacy_records_are_preserved_without_claiming_language_compliance(): void
  {
    $source = ContentSourceDefinition::entity('example::legacy')->fields(['title' => 'text']);
    $record = ['title' => 'Existing translation'];
    $this->assertNull($source->contract());
    $this->assertSame($record, ContentSourceContract::record($source, $record, new ContentSourceContext(null, null, 'tr', true)));
  }

  #[Test]
  public function inventory_finds_sql_loops_and_fields_without_resolving_records(): void
  {
    [$site, $grid, $card, $heading] = $this->blocks();
    $this->register();
    $actor = $this->actor('super_admin');
    $inventory = app(DynamicContentInventory::class)->build($actor);
    $source = $inventory['sources']->get('example::items');
    $this->assertSame('declared', $source['state']);
    $this->assertSame(2, $source['blocks_count']);
    $this->assertSame(1, $source['loops_count']);
    $binding = $inventory['usages']->firstWhere('kind', 'binding');
    $this->assertSame($grid->id, $binding['loop_id']);
    $this->assertStringContainsString('Grid #'.$grid->id, $binding['path']);
    $this->assertSame('preserved.png', $heading->fresh()->setting('editor_image'));
  }

  #[Test]
  public function inventory_never_exposes_other_site_usages_and_rejects_forced_site_ids(): void
  {
    [$site] = $this->blocks();
    [$other] = $this->blocks('other');
    $this->register();
    $actor = $this->actor('editor');
    $actor->setRelation('sites', collect([$site]));
    $inventory = app(DynamicContentInventory::class)->build($actor);
    $this->assertCount(2, $inventory['usages']);
    $this->assertSame([$site->id], $inventory['usages']->pluck('site.id')->unique()->values()->all());
    $this->expectException(HttpException::class);
    app(DynamicContentInventory::class)->build($actor, $other->id);
  }

  #[Test]
  public function missing_and_disabled_sources_keep_their_stored_usage(): void
  {
    $this->blocks();
    $this->register(false);
    $this->assertSame('disabled', app(DynamicContentInventory::class)->build($this->actor('super_admin'))['sources']->get('example::items')['state']);
    $this->app->instance(PluginRegistry::class, new PluginRegistry);
    $this->assertSame('missing', app(DynamicContentInventory::class)->build($this->actor('super_admin'))['sources']->get('example::items')['state']);
  }

  #[Test]
  public function tree_distinguishes_parent_loop_template_and_bound_field(): void
  {
    [, $grid, $card, $heading] = $this->blocks();
    $this->register();
    $indicators = app(ContentSourceIndicators::class);
    $this->assertStringContainsString('Loop:', implode(' ', $indicators->labels($grid)));
    $card->setRelation('parent', $grid);
    $this->assertStringContainsString('Repeated by block #'.$grid->id, implode(' ', $indicators->labels($card)));
    $this->assertStringContainsString('Current loop record', implode(' ', $indicators->labels($heading)));
  }

  #[Test]
  public function shared_slot_anchor_pages_are_inventoried_and_open_the_shared_editor(): void
  {
    [$site, $grid, , $heading] = $this->blocks();
    $grid->page->update(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE]);
    $shared = SharedSlot::query()->create(['site_id' => $site->id, 'name' => 'Shared journal', 'handle' => 'shared-journal', 'slot_name' => 'main', 'is_active' => true]);
    SharedSlotBlock::query()->create(['shared_slot_id' => $shared->id, 'block_id' => $grid->id, 'sort_order' => 0]);
    $this->register();
    $inventory = app(DynamicContentInventory::class)->build($this->actor('super_admin'));
    $this->assertCount(2, $inventory['usages']);
    $binding = $inventory['usages']->firstWhere('kind', 'binding');
    $this->assertTrue($binding['shared']);
    $this->assertSame('Shared journal', $binding['owner']);
    $this->assertSame(route('admin.shared-slots.blocks.edit', ['shared_slot' => $shared->id, 'edit' => $heading->id]), $binding['edit_url']);
    $this->assertStringContainsString('edit='.$heading->id, $binding['edit_url']);
  }

  #[Test]
  public function requested_record_language_wins_over_editorial_fallback_and_slot_owner(): void
  {
    $english = new Locale(['code' => 'en']);
    $translation = new PageTranslation;
    $translation->setRelation('locale', $english);
    $owner = new Page;
    $owner->setRelation('currentTranslation', $translation);
    $block = new Block(['type' => 'header']);
    $block->setRelation('renderPage', $owner);
    $block->setAttribute('requested_locale_code', 'tr');
    $block->setAttribute('resolved_locale_code', 'en');
    $this->assertSame('tr', $block->renderLocaleCode());
  }

  #[Test]
  public function legacy_sources_can_keep_existing_connections_but_cannot_receive_new_ones(): void
  {
    [, $grid, , $heading] = $this->blocks();
    $registry = new PluginRegistry(['example' => true]);
    $registry->register(PluginDefinition::make('example')->label('Example Content')->contentSources([
      ContentSourceDefinition::collection('example::items')->fields(['title' => 'text']),
    ]));
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->forgetInstance(ContentSourceRegistry::class);
    $editor = app(ContentSourceEditor::class);
    $this->assertTrue($editor->collectionSelectionIsAllowed($grid, 'example::items'));
    $this->assertFalse($editor->collectionSelectionIsAllowed(new Block(['type' => 'grid']), 'example::items'));
    $this->assertTrue($editor->selectionIsAllowed($heading, 'title', 'example::items|@item|title'));
    $this->assertFalse($editor->selectionIsAllowed(new Block(['type' => 'header']), 'title', 'example::items|@item|title'));
  }

  private function actor(string $role): User
  {
    return (new User)->forceFill(['role' => $role, 'is_active' => true]);
  }

  private function source(): ContentSourceDefinition
  {
    return ContentSourceDefinition::collection('example::items')->label('Example items')
      ->standard(ContentSourceContract::languages(), 'Example source', 'Example application', 'id')
      ->fields(['id' => 'text', 'title' => 'text', 'image' => 'url']);
  }

  private function register(bool $active = true): void
  {
    $registry = new PluginRegistry(['example' => $active]);
    $registry->register(PluginDefinition::make('example')->label('Example Content')->contentSources([$this->source()]));
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->forgetInstance(ContentSourceRegistry::class);
  }

  private function blocks(string $handle = 'example'): array
  {
    $site = Site::query()->create(['handle' => $handle, 'name' => ucfirst($handle)]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'journal', 'status' => 'published']);
    $make = function ($type, $parent = null) use ($page) {
      $bt = BlockType::query()->firstOrCreate(['slug' => $type], ['name' => ucfirst($type), 'status' => 'published', 'category' => 'content', 'is_container' => $type !== 'header']);

      return Block::query()->create(['page_id' => $page->id, 'block_type_id' => $bt->id, 'type' => $type, 'parent_id' => $parent, 'slot' => 'main', 'status' => 'published']);
    };
    $grid = $make('grid');
    $card = $make('card', $grid->id);
    $heading = $make('header', $card->id);
    $grid->update(['settings' => ['content_collection' => ['source' => 'example::items', 'template_block_id' => $card->id, 'limit' => 50]]]);
    $heading->update(['settings' => ['editor_image' => 'preserved.png', 'content_bindings' => ['title' => ['source' => 'example::items', 'record' => '@item', 'field' => 'title']]]]);

    return [$site, $grid, $card, $heading];
  }
}

class DynamicContentTestActor extends BaseUser
{
  use HasCmsAdminAccess;
}
