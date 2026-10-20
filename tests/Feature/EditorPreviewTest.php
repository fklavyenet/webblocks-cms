<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Concerns\HasCmsAdminAccess;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionQuery;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionResult;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;
use WebBlocks\Cms\Support\ContentSources\ContentSourceDefinition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRegistry;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRuntime;
use WebBlocks\Cms\Support\ContentSources\Contracts\QueryableContentCollectionSourceResolver;
use WebBlocks\Cms\Support\EditorRecovery\EditorRecovery;
use WebBlocks\Cms\Support\EditorRecovery\RecoveryFields;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class EditorPreviewTest extends TestCase
{
  private User $actor;

  private Page $page;

  private Block $block;

  private string $editingSession;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists(User::class)) {
      class_alias(PreviewTestActor::class, User::class);
    }
    $this->withoutMiddleware([RedirectIfNotInstalled::class]);
    $this->actor = User::forceCreate(['name' => 'Preview administrator', 'email' => 'preview@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]);
    $this->actingAs($this->actor);
    $locale = Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $site = Site::create(['name' => 'Recovery studio', 'handle' => 'recovery']);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $this->page = Page::create(['site_id' => $site->id, 'title' => 'Saved title', 'slug' => 'saved-title', 'status' => 'draft']);
    $type = BlockType::firstOrCreate(['slug' => 'rich-text'], ['name' => 'Rich Text', 'category' => 'content', 'source_type' => 'static', 'status' => 'published']);
    $slot = SlotType::firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published']);
    PageSlot::create(['page_id' => $this->page->id, 'slot_type_id' => $slot->id, 'source_type' => 'page']);
    $this->block = Block::create(['page_id' => $this->page->id, 'block_type_id' => $type->id, 'slot_type_id' => $slot->id, 'type' => 'rich-text', 'content' => 'Saved block', 'status' => 'draft']);
    $this->editingSession = (string) Str::uuid();
  }

  private function render(array $values, array $extra = [])
  {
    $entries = collect($values)->map(fn ($value, $name) => ['name' => $name, 'present' => $value !== null, 'value' => $value === null ? null : base64_encode((string) $value)])->values()->all();

    return $this->postJson(route('admin.pages.editor.preview', $this->page), ['locale' => 'en', 'form_locale' => 'en', 'entries' => $entries, ...$extra]);
  }

  #[Test]
  public function unsaved_page_and_rich_text_render_without_content_revisions_recovery_or_workflow_writes(): void
  {
    $before = app(EditorRecovery::class)->fingerprint($this->page);
    $revisions = DB::table('wbcms_page_revisions')->count();
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
      if (preg_match('/^\\s*(insert|update|delete|replace)\\b/i', $query->sql) && str_contains($query->sql, 'wbcms_')) {
      $writes[] = $query->sql;
      }
    });
    $response = $this->render(['title' => 'Unsaved preview title', 'slug' => 'unsaved-preview-title']);
    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertJsonPath('issues', []);
    $this->assertStringContainsString('Unsaved preview title', $response->json('html'));
    $response = $this->render(['content' => '<p>Unsaved <strong>rich text</strong></p>', 'parent_id' => '', 'content_binding_selection[content]' => ''], ['block_id' => $this->block->id]);
    $response->assertOk();
    $this->assertStringContainsString('Unsaved <strong>rich text</strong>', $response->json('html'));
    $this->assertSame($before, app(EditorRecovery::class)->fingerprint($this->page));
    $this->assertSame($revisions, DB::table('wbcms_page_revisions')->count());
    $this->assertDatabaseCount('wbcms_editor_recoveries', 0);
    $this->assertSame([], $writes, 'Preview must not write CMS rows.');
    $this->assertSame('draft', $this->page->fresh()->status);
  }

  #[Test]
  public function nested_block_overlay_uses_the_saved_tree_and_leaves_siblings_intact(): void
  {
    $type = BlockType::firstOrCreate(['slug' => 'stack'], ['name' => 'Stack', 'category' => 'layout', 'source_type' => 'static', 'status' => 'published', 'is_container' => true]);
    $parent = Block::create(['page_id' => $this->page->id, 'block_type_id' => $type->id, 'slot_type_id' => $this->block->slot_type_id, 'status' => 'draft']);
    $this->block->update(['parent_id' => $parent->id]);
    $sibling = Block::create(['page_id' => $this->page->id, 'block_type_id' => $this->block->block_type_id, 'slot_type_id' => $this->block->slot_type_id, 'content' => 'Saved sibling', 'status' => 'draft']);
    $response = $this->render(['content' => '<p>Nested unsaved text</p>'], ['block_id' => $this->block->id])->assertOk();
    $this->assertStringContainsString('Nested unsaved text', $response->json('html'));
    $this->assertStringContainsString('Saved sibling', $response->json('html'));
    $this->assertSame($parent->id, $this->block->fresh()->parent_id);
    $this->assertNotNull($sibling->fresh());
  }

  #[Test]
  public function six_content_locales_keep_overlay_translation_separate_and_missing_translation_is_explicit(): void
  {
    foreach (['de', 'tr', 'es', 'it', 'fr'] as $code) {
      $locale = Locale::create(['code' => $code, 'name' => $code, 'is_enabled' => true, 'is_default' => false]);
      $this->page->site->locales()->attach($locale->id, ['is_enabled' => true]);
      $this->page->translations()->create(['site_id' => $this->page->site_id, 'locale_id' => $locale->id, 'name' => 'Saved '.$code, 'slug' => 'saved-'.$code]);
      $this->block->textTranslations()->create(['locale_id' => $locale->id, 'content' => 'Saved '.$code.' block']);
      $response = $this->render(['content' => 'Unsaved '.$code], ['block_id' => $this->block->id, 'form_locale' => $code, 'locale' => $code])->assertOk();
      $this->assertStringContainsString('Unsaved '.$code, $response->json('html'));
      $other = $this->render(['content' => 'Unsaved '.$code], ['block_id' => $this->block->id, 'form_locale' => $code, 'locale' => 'en'])->assertOk();
      $this->assertStringNotContainsString('Unsaved '.$code, $other->json('html'));
    }
    $locale = Locale::create(['code' => 'nl', 'name' => 'Dutch', 'is_enabled' => true, 'is_default' => false]);
    $this->page->site->locales()->attach($locale->id, ['is_enabled' => true]);
    $this->render(['title' => 'Unsaved', 'slug' => 'unsaved'], ['locale' => 'nl'])->assertUnprocessable()->assertJsonValidationErrors('locale');
    $this->render(['title' => 'Unsaved', 'slug' => 'unsaved'], ['locale' => 'zz'])->assertUnprocessable();
  }

  #[Test]
  public function staged_preview_preserves_published_source_and_staged_identity(): void
  {
    $published = Page::create(['site_id' => $this->page->site_id, 'title' => 'Published source', 'slug' => 'published-source', 'status' => 'published']);
    $this->page->update(['settings' => ['staged_update' => ['type' => 'published_page_update', 'source_page_id' => $published->id]]]);
    $before = app(EditorRecovery::class)->fingerprint($this->page);
    $response = $this->render(['title' => 'Staged unsaved', 'slug' => 'staged-unsaved', 'public_shell' => 'default'])->assertOk();
    $this->assertStringContainsString('Staged unsaved', $response->json('html'));
    $this->assertStringContainsString('wb-page-published-source', $response->json('html'));
    $this->assertSame($before, app(EditorRecovery::class)->fingerprint($this->page));
    $this->assertSame('published', $published->fresh()->status);
    $this->assertSame('Published source', $published->fresh()->title);
  }

  #[Test]
  public function invalid_values_cross_page_blocks_and_structural_changes_are_rejected(): void
  {
    $before = app(EditorRecovery::class)->fingerprint($this->page);
    $this->render(['title' => '', 'slug' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('title');
    $this->render(['content' => 'Invalid', 'sort_order' => '99'], ['block_id' => $this->block->id])->assertUnprocessable()->assertJsonValidationErrors('entries');
    $this->render(['content' => 'Invalid'], ['block_id' => 99999])->assertNotFound();
    $other = Page::create(['site_id' => $this->page->site_id, 'title' => 'Other', 'status' => 'draft']);
    $foreign = Block::create(['page_id' => $other->id, 'block_type_id' => $this->block->block_type_id, 'slot_type_id' => $this->block->slot_type_id, 'status' => 'draft']);
    $this->render(['content' => 'Foreign'], ['block_id' => $foreign->id])->assertNotFound();
    $this->render(['settings' => '{"secret":"no"}'], ['block_id' => $this->block->id])->assertUnprocessable();
    $this->render(['content' => str_repeat('a', RecoveryFields::MAX_BYTES + 1)], ['block_id' => $this->block->id])->assertUnprocessable();
    $this->assertSame($before, app(EditorRecovery::class)->fingerprint($this->page));
  }

  #[Test]
  public function published_and_inactive_or_read_only_actors_cannot_render_unsaved_content(): void
  {
    $this->page->update(['status' => 'published']);
    $this->render(['title' => 'Blocked'])->assertForbidden();
    $this->page->update(['status' => 'draft']);
    $role = app(WriteCmsAccess::class)->role($this->actor, ['name' => 'Preview reader', 'permissions' => ['cms.panel.access', 'cms.pages.read']]);
    $reader = User::forceCreate(['name' => 'Reader', 'email' => 'preview-reader@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]);
    $reader->sites()->attach($this->page->site_id, ['role_id' => $role->id]);
    $this->actingAs($reader);
    $this->render(['title' => 'Blocked'])->assertForbidden();
    $reader->update(['is_active' => false]);
    $this->render(['title' => 'Blocked'])->assertForbidden();
  }

  private function makeBlock(string $slug, ?int $parent = null): Block
  {
    $type = BlockType::firstOrCreate(['slug' => $slug], ['name' => $slug, 'category' => 'content', 'source_type' => 'static', 'status' => 'published', 'is_container' => in_array($slug, ['grid', 'card'])]);

    return Block::create(['page_id' => $this->page->id, 'block_type_id' => $type->id, 'slot_type_id' => $this->block->slot_type_id, 'parent_id' => $parent, 'status' => 'draft']);
  }

  private function sources(): void
  {
    $registry = new PluginRegistry(['preview' => true]);
    $registry->register(PluginDefinition::make('preview')->contentSources([
      ContentSourceDefinition::collection('preview::items')->label('Items')->standard(['en', 'tr', 'de', 'fr', 'it', 'es'], 'Items', 'Synthetic provider', 'id')->resolver(PreviewRecords::class)->fields(['id' => 'text', 'title' => 'text']),
    ]));
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->forgetInstance(ContentSourceRegistry::class);
    PreviewRecords::$calls = [];
    PreviewRecords::$fail = false;
    PreviewRecords::$empty = false;
  }

  #[Test]
  public function selected_media_and_its_locale_metadata_are_overlaid_without_changing_media_rows(): void
  {
    $image = $this->makeBlock('image');
    $media = Media::create(['disk' => 'public', 'path' => 'preview-test.svg', 'filename' => 'preview-test.svg', 'kind' => 'image', 'visibility' => 'public', 'mime_type' => 'image/svg+xml', 'size' => 123, 'site_id' => $this->page->site_id, 'scope' => 'site']);
    $before = app(EditorRecovery::class)->fingerprint($this->page);
    $response = $this->render(['media_id' => (string) $media->id, 'title' => 'Unsaved image caption', 'subtitle' => 'Unsaved image description'], ['block_id' => $image->id])->assertOk();
    $this->assertStringContainsString('preview-test.svg', $response->json('html'));
    $this->assertStringContainsString('Unsaved image caption', $response->json('html'));
    $this->assertStringContainsString('Unsaved image description', $response->json('html'));
    $this->assertNull($image->fresh()->media_id);
    $this->assertSame($before, app(EditorRecovery::class)->fingerprint($this->page));
  }

  #[Test]
  public function configured_collection_uses_unsaved_query_pagination_and_mappings_with_one_provider_call(): void
  {
    $this->sources();
    $grid = $this->makeBlock('grid');
    $card = $this->makeBlock('card', $grid->id);
    $body = $this->makeBlock('card_body', $card->id);
    $heading = $this->makeBlock('header', $body->id);
    $heading->update(['settings' => ['content_bindings' => ['title' => ['source' => 'preview::items', 'record' => '@item', 'field' => 'title']]]]);
    $before = app(EditorRecovery::class)->fingerprint($this->page);
    $response = $this->render([
      'content_collection_source' => 'preview::items', 'content_collection_template_id' => (string) $card->id,
      'content_collection_filter_field' => 'title', 'content_collection_filter_value' => 'selected',
      'content_collection_sort_field' => 'title', 'content_collection_sort_direction' => 'desc',
      'content_collection_limit' => '7', 'content_collection_paginate' => '1', 'content_collection_per_page' => '2',
    ], ['block_id' => $grid->id, 'pagination' => ['wb_collection_'.$grid->id.'_page' => 2]])->assertOk()->assertJsonPath('issues', []);
    $this->assertStringContainsString('Configured record on page 2', $response->json('html'));
    $this->assertCount(1, PreviewRecords::$calls);
    $query = PreviewRecords::$calls[0];
    $this->assertSame([7, 'title', 'selected', 'title', 'desc', 2, 2], [$query->limit, $query->filterField, $query->filterValue, $query->sortField, $query->sortDirection, $query->page, $query->perPage]);
    $this->assertSame($before, app(EditorRecovery::class)->fingerprint($this->page));
    PreviewRecords::$empty = true;
    $this->render(['content_collection_source' => 'preview::items', 'content_collection_template_id' => (string) $card->id], ['block_id' => $grid->id])->assertOk()->assertJsonPath('issues', []);
    PreviewRecords::$empty = false;
    PreviewRecords::$fail = true;
    $failed = $this->render(['content_collection_source' => 'preview::items', 'content_collection_template_id' => (string) $card->id], ['block_id' => $grid->id])->assertOk();
    $this->assertContains('unavailable', $failed->json('issues'));
    $this->assertStringNotContainsString('private-provider-detail', $failed->getContent());
    $this->assertSame($before, app(EditorRecovery::class)->fingerprint($this->page));
  }

  #[Test]
  public function preview_runtime_memoizes_provider_resolution_and_bounds_unique_calls_per_request(): void
  {
    $this->sources();
    $runtime = app(ContentSourceRuntime::class);
    $source = app(ContentSourceRegistry::class)->find('preview::items');
    $context = new ContentSourceContext($this->page->site, $this->page, 'en', true, $this->actor);
    request()->attributes->set('cms_editor_preview', true);
    $calls = 0;
    $resolve = function () use (&$calls) {
    $calls++;

    return 'resolved';
    };
    for ($i = 0; $i < 64; $i++) {
      $this->assertSame('resolved', $runtime->remember($source, $context, ['record' => $i], $resolve));
      $this->assertSame('resolved', $runtime->remember($source, $context, ['record' => $i], $resolve));
    }
    $this->assertSame(64, $calls);
    try {
      $runtime->remember($source, $context, ['record' => 65], $resolve);
      $this->fail('The per-render resolution limit must be enforced.');
    } catch (\UnexpectedValueException) {
      $this->assertSame(['limit'], request()->attributes->get('cms_editor_preview_issues'));
    }
  }

  #[Test]
  public function current_site_and_translation_grants_are_rechecked_for_each_preview(): void
  {
    $locale = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true, 'is_default' => false]);
    $this->page->site->locales()->attach($locale->id, ['is_enabled' => true]);
    $role = app(WriteCmsAccess::class)->role($this->actor, ['name' => 'Preview writer', 'permissions' => ['cms.panel.access', 'cms.pages.read', 'cms.pages.update-draft']]);
    $writer = User::forceCreate(['name' => 'Preview writer', 'email' => 'preview-writer@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]);
    $writer->sites()->attach($this->page->site_id, ['role_id' => $role->id]);
    $this->actingAs($writer);
    $this->render(['title' => 'Allowed', 'slug' => 'allowed'])->assertOk();
    $this->render(['content' => 'Not permitted'], ['block_id' => $this->block->id, 'form_locale' => 'tr'])->assertForbidden();
    $writer->sites()->detach($this->page->site_id);
    $this->render(['title' => 'Now forbidden', 'slug' => 'forbidden'])->assertForbidden();
  }

  #[Test]
  public function every_panel_language_has_complete_preview_copy(): void
  {
    $root = dirname(__DIR__, 2).'/resources/lang/';
    $english = require $root.'en/preview.php';
    foreach (['en', 'de', 'tr', 'es', 'fr', 'it'] as $locale) {
      $copy = require $root.$locale.'/preview.php';
      $this->assertSame(array_keys($english), array_keys($copy));
      foreach ($copy as $value) {
      $this->assertNotSame('', trim($value));
      }
    }
  }

  #[Test]
  public function editor_panel_browser_fixture(): void
  {
    request()->setUserResolver(fn () => $this->actor);
    $partial = view('webblocks-cms::admin.partials.editor-preview', ['previewPage' => $this->page])->render();
    $this->assertStringContainsString('data-wb-editor-preview', $partial);
    $this->assertStringContainsString('sandbox="allow-scripts"', $partial);
    if ($path = getenv('WEBBLOCKS_EDITOR_PREVIEW_FIXTURE')) {
      $html = '<!doctype html><html lang="en"><body><button type="button" id="run-preview-tests">Run preview regressions</button><output id="preview-test-results" role="status"></output><form><label>Title<input name="title" value="Fixture title"></label><input name="slug" value="fixture-title">'.$partial.'</form></body></html>';
      file_put_contents($path, str_replace(['http://localhost/cms/', 'data-base="http://localhost/"'], ['/cms/', 'data-base="/"'], $html));
    }
  }

  #[Test]
  public function panel_is_offered_only_on_existing_editable_drafts_with_saved_preview_fallback(): void
  {
    $this->get(route('admin.pages.edit', $this->page))->assertOk()->assertSee('data-wb-editor-preview', false)->assertSee('Saved preview in new tab');
    $this->page->update(['status' => 'published']);
    $this->get(route('admin.pages.edit', $this->page))->assertOk()->assertDontSee('data-wb-editor-preview', false);
    $this->assertSame([], session()->get('_old_input', []));
  }
}

class PreviewTestActor extends BaseUser
{
  use HasCmsAdminAccess;

  protected $table = 'users';

  protected $guarded = [];

  public function getForeignKey()
  {
    return 'user_id';
  }
}

class PreviewRecords implements QueryableContentCollectionSourceResolver
{
  public static array $calls = [];

  public static bool $fail = false;

  public static bool $empty = false;

  public function resolveCollection(array $settings, ContentSourceContext $context): iterable
  {
    throw new \LogicException('The configured query must be used.');
  }

  public function queryCollection(ContentCollectionQuery $query, array $settings, ContentSourceContext $context): ContentCollectionResult
  {
    self::$calls[] = $query;
    if (self::$fail) {
    throw new \RuntimeException('private-provider-detail');
    }

    return new ContentCollectionResult(self::$empty ? [] : [['_locale' => $context->locale, 'id' => 'one', 'title' => 'Configured record on page '.$query->page]], self::$empty ? 0 : 4, $query->page, $query->perPage ?? 4);
  }
}
