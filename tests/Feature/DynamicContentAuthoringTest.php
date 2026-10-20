<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Actions\DynamicContent\CreateDynamicContentStarter;
use WebBlocks\Cms\Actions\DynamicContent\SaveDynamicContent;
use WebBlocks\Cms\Http\Controllers\Admin\DynamicContentAuthoringController;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Http\Requests\Admin\DynamicContentAuthoringRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Concerns\HasCmsAdminAccess;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionLoader;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionRenderer;
use WebBlocks\Cms\Support\ContentSources\ContentSourceAuthoring;
use WebBlocks\Cms\Support\ContentSources\ContentSourceAuthoringException;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContract;
use WebBlocks\Cms\Support\ContentSources\ContentSourceDefinition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceEditor;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRegistry;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceAccessPolicy;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
use WebBlocks\Cms\Support\ContentSources\DynamicContentDesign;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class DynamicContentAuthoringTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists(User::class)) {
    class_alias(AuthoringActor::class, User::class);
    }
    $sources = new PluginRegistry(['authoring' => true]);
    $sources->register(PluginDefinition::make('authoring')->contentSources([
      ContentSourceDefinition::collection('authoring::items')->label('Items')->standard(ContentSourceContract::languages(), 'Items', 'Test provider', 'id')->resolver(AuthoringRecords::class)->fields(['id' => 'text', 'title' => 'text', 'image' => 'url']),
      ContentSourceDefinition::entity('authoring::item')->label('Item')->standard(ContentSourceContract::languages(), 'Item', 'Test provider', 'id')->resolver(AuthoringRecords::class)->fields(['id' => 'text', 'title' => 'text', 'image' => 'url']),
      ContentSourceDefinition::collection('authoring::denied')->label('Denied')->standard(ContentSourceContract::languages(), 'Denied', 'Test provider', 'id')->resolver(AuthoringRecords::class)->accessPolicy(AuthoringDenied::class)->fields(['id' => 'text']),
    ]));
    $this->app->instance(PluginRegistry::class, $sources);
    $this->app->forgetInstance(ContentSourceRegistry::class);
  }

  #[Test]
  public function an_absent_binding_is_literal_but_an_incomplete_existing_binding_stays_visible(): void
  {
    $editor = app(ContentSourceEditor::class);
    $block = new Block;
    $this->assertSame('', $editor->selectedValue($block, 'title'));
    $block->settings = ['content_bindings' => ['title' => ['source' => 'authoring::item', 'record' => '42']]];
    $this->assertSame('authoring::item|42|', $editor->selectedValue($block, 'title'));
    $block->settings = ['content_bindings' => ['title' => ['source' => 'authoring::items', 'record' => '@item', 'field' => 'title']]];
    $this->assertSame('authoring::items|@item|title', $editor->selectedValue($block, 'title'));
  }

  #[Test]
  public function admin_rejects_missing_foreign_or_incompatible_templates_without_losing_settings(): void
  {
    [$page, $grid, $card, $title] = $this->fixture();
    $before = $grid->fresh()->settings;
    foreach ([null, $title->id, 99999] as $template) {
      $errors = $this->errors($grid, ['content_collection_source' => 'authoring::items', 'content_collection_template_id' => $template]);
      $this->assertArrayHasKey('content_collection_template_id', $errors);
      $this->assertSame($before, $grid->fresh()->settings);
    }
  }

  #[Test]
  public function admin_rejects_unknown_target_record_field_and_wrong_type(): void
  {
    [, , , $title] = $this->fixture();
    foreach ([['title', 'authoring::item|missing|title'], ['title', 'authoring::item|one|missing'], ['title', 'authoring::item|one|image'], ['url', 'authoring::item|one|image']] as [$target, $selection]) {
      $errors = $this->errors($title, ['text' => 'Fallback', 'level' => 'h3', 'content_binding_selection' => [$target => $selection]]);
      $this->assertArrayHasKey('content_binding_selection.'.$target, $errors);
    }
  }

  #[Test]
  public function current_item_is_available_only_inside_the_selected_template(): void
  {
    [, $grid, , $title] = $this->fixture();
    $sibling = $this->make($grid->page, 'header', $grid->id);
    $editor = app(ContentSourceEditor::class);
    $this->assertTrue($editor->selectionIsAllowed($title, 'title', 'authoring::items|@item|title'));
    $this->assertFalse($editor->selectionIsAllowed($sibling, 'title', 'authoring::items|@item|title'));
  }

  #[Test]
  public function changing_or_removing_a_loop_cannot_silently_orphan_existing_mappings(): void
  {
    [, $grid] = $this->fixture();
    try {
      app(ContentSourceAuthoring::class)->merge($grid, (array) $grid->settings, null, false, null, true);
      $this->fail('A loop with current-item mappings cannot be removed silently.');
    } catch (ContentSourceAuthoringException $exception) {
      $this->assertSame('content_collection.template_block_id', $exception->path);
    }
    $this->assertSame('authoring::items', $grid->fresh()->setting('content_collection.source'));
  }

  #[Test]
  public function access_policy_is_enforced_for_collections_and_current_record_choices(): void
  {
    [, $grid, , $title] = $this->fixture();
    $grid->update(['settings' => ['content_collection' => ['source' => 'authoring::denied', 'template_block_id' => $grid->setting('content_collection.template_block_id')]]]);
    $editor = app(ContentSourceEditor::class);
    $this->assertFalse($editor->collectionSelectionIsAllowed($grid->fresh(), 'authoring::denied'));
    $this->assertFalse($editor->selectionIsAllowed($title->fresh(), 'title', 'authoring::denied|@item|id'));
  }

  #[Test]
  public function configured_preview_uses_filter_sort_pagination_and_preserves_requested_locale(): void
  {
    [, $grid] = $this->fixture();
    $grid->setAttribute('requested_locale_code', 'tr');
    $result = app(ContentCollectionLoader::class)->load($grid, ['source' => 'authoring::items', 'limit' => 50, 'sort_field' => 'id', 'sort_direction' => 'desc', 'filter_field' => 'id', 'filter_value' => 'one', 'paginate' => true, 'per_page' => 1], true);
    $this->assertSame(1, $result->total);
    $this->assertSame('one', $result->records[0]['id']);
    $this->assertSame('tr', $result->records[0]['_locale']);
  }

  #[Test]
  public function preview_configuration_is_atomic_and_never_saves_source_or_translations(): void
  {
    [$page, $grid, $card, $title] = $this->fixture();
    $before = $grid->fresh()->settings;
    [$preview] = app(SaveDynamicContent::class)->configure($page, ['block_id' => $grid->id, 'source' => 'authoring::items', 'collection' => ['template_block_id' => $card->id, 'limit' => 3], 'mappings' => [$title->id => ['title' => 'authoring::items|@item|id']]]);
    $this->assertSame(3, $preview->setting('content_collection.limit'));
    $this->assertSame($before, $grid->fresh()->settings);
    $this->assertSame('title', $title->fresh()->setting('content_bindings.title.field'));
    try {
      app(SaveDynamicContent::class)->configure($page, ['block_id' => $grid->id, 'source' => 'authoring::items', 'collection' => ['template_block_id' => $card->id], 'mappings' => [$title->id => ['title' => 'authoring::item|one|image']]]);
      $this->fail('Type mismatch must fail.');
    } catch (ValidationException) {
      $this->assertSame($before, $grid->fresh()->settings);
    }
  }

  #[Test]
  public function admin_unrelated_edit_preserves_provider_settings_and_fallbacks(): void
  {
    [, $grid, $card, $title] = $this->fixture();
    $request = $this->request($grid, ['content_collection_source' => 'authoring::items', 'content_collection_template_id' => $card->id, 'content_collection_limit' => 10]);
    $request->validateResolved();
    $data = $request->validatedData();
    $this->assertSame('unchanged', data_get(json_decode($data['settings'], true), 'content_collection.source_settings.custom'));
    $request = $this->request($title, ['text' => 'Fallback', 'level' => 'h3', 'content_binding_selection' => ['title' => 'authoring::items|@item|title']]);
    $request->validateResolved();
    $this->assertSame('preserved', data_get(json_decode($request->validatedData()['settings'], true), 'content_bindings.title.fallback'));
  }

  #[Test]
  public function staging_remaps_loop_templates_and_keeps_all_locale_translations(): void
  {
    [$page, $grid, $card, $title] = $this->fixture();
    $page->update(['status' => Page::STATUS_PUBLISHED]);
    foreach (Locale::all() as $locale) {
    $title->textTranslations()->create(['locale_id' => $locale->id, 'title' => 'Title '.$locale->code]);
    }
    $before = $page->fresh()->updated_at->toISOString();
    $actor = $this->cmsActor((new User)->forceFill(['role' => 'super_admin', 'is_active' => true]));
    $draft = app(InternalContentPlanService::class)->prepareAuthoringDraft($page, $actor);
    $copiedGrid = $draft->blocks()->where('type', 'grid')->firstOrFail();
    $copiedCard = $draft->blocks()->where('type', 'card')->firstOrFail();
    $this->assertSame($copiedCard->id, $copiedGrid->setting('content_collection.template_block_id'));
    $this->assertSame($copiedGrid->id, $copiedCard->parent_id);
    $this->assertCount(6, $draft->blocks()->where('type', 'header')->first()->textTranslations);
    $this->assertSame($card->id, $grid->fresh()->setting('content_collection.template_block_id'));
    $this->assertSame($before, $page->fresh()->updated_at->toISOString());
    $this->assertSame($draft->id, app(InternalContentPlanService::class)->prepareAuthoringDraft($page->fresh(), $actor)->id);
  }

  #[Test]
  public function saving_a_draft_preserves_translations_and_provider_configuration_and_rejects_stale_or_published_writes(): void
  {
    [$page, $grid, $card, $title] = $this->fixture();
    $actor = $this->cmsActor((new User)->forceFill(['role' => 'super_admin', 'is_active' => true]));
    $design = app(DynamicContentDesign::class);
    $input = ['source' => 'authoring::items', 'block_id' => $grid->id, 'version' => $design->fingerprint($page), 'collection' => ['template_block_id' => $card->id, 'limit' => 8]];
    $action = app(SaveDynamicContent::class);
    $action->handle($page, $input, $actor);
    $this->assertSame(8, $grid->fresh()->setting('content_collection.limit'));
    $this->assertSame('unchanged', $grid->fresh()->setting('content_collection.source_settings.custom'));
    $this->assertSame('preserved', $title->fresh()->setting('content_bindings.title.fallback'));
    try {
      $action->handle($page->fresh(), $input, $actor);
      $this->fail('Stale content must not be overwritten, including changes within the same timestamp second.');
    } catch (ValidationException $exception) {
      $this->assertArrayHasKey('version', $exception->errors());
    }
    $page->update(['status' => Page::STATUS_PUBLISHED]);
    try {
      $action->handle($page->fresh(), $input, $actor);
      $this->fail('Published pages cannot be saved through this flow.');
    } catch (HttpException $exception) {
      $this->assertSame(409, $exception->getStatusCode());
    }
  }

  #[Test]
  public function an_empty_draft_gets_a_valid_starter_without_changing_existing_blocks(): void
  {
    [$page, $grid] = $this->fixture();
    foreach (['card_body', 'card_footer', 'image', 'plain_text', 'button_link'] as $slug) {
      BlockType::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'category' => 'content', 'source_type' => 'static', 'is_system' => false, 'is_container' => in_array($slug, ['card_body', 'card_footer']), 'sort_order' => 0, 'status' => 'published']);
    }
    $before = $grid->fresh()->settings;
    $actor = $this->cmsActor((new User)->forceFill(['role' => 'super_admin', 'is_active' => true]));
    $starter = app(CreateDynamicContentStarter::class)->handle($page, $page->slots()->first()->id, $actor);
    $this->assertSame('grid', $starter->typeSlug());
    $this->assertSame('card', $starter->children()->first()->typeSlug());
    $this->assertSame('3', $starter->appearanceSetting('columns'));
    $this->assertSame($before, $grid->fresh()->settings);
    $this->assertNull($starter->setting('content_collection'));
  }

  #[Test]
  public function preview_view_contains_actual_cards_and_never_replaces_the_yield_with_an_empty_page_shell(): void
  {
    [$page, $grid] = $this->fixture();
    $block = app(DynamicContentDesign::class)->blocks($page)->get($grid->id);
    $html = view('webblocks-cms::admin.dynamic-content.preview', ['site' => $page->site, 'source' => app(ContentSourceRegistry::class)->find('authoring::items'), 'block' => $block, 'locale' => 'en', 'status' => 'available', 'total' => 2])->render();
    $this->assertStringContainsString('Record one', $html);
    $this->assertStringContainsString('Private preview', $html);
  }

  #[Test]
  public function moving_a_template_or_current_record_field_outside_its_loop_is_rejected(): void
  {
    [$page, $grid, $card, $title] = $this->fixture();
    $errors = $this->errors($card, ['parent_id' => null]);
    $this->assertArrayHasKey('parent_id', $errors);
    $errors = $this->errors($title, ['parent_id' => $grid->id, 'text' => 'Fallback', 'level' => 'h3']);
    $this->assertArrayHasKey('parent_id', $errors);
  }

  #[Test]
  public function denied_public_collections_keep_the_stored_template_even_when_error_behavior_hides_failures(): void
  {
    [, $grid, $card] = $this->fixture();
    $grid->settings = ['content_collection' => ['source' => 'authoring::denied', 'template_block_id' => $card->id, 'error_behavior' => 'hide_template']];
    $this->assertSame($card->id, app(ContentCollectionRenderer::class)->children($grid)->first()->id);
  }

  #[Test]
  public function explicit_preview_distinguishes_empty_invalid_and_failed_sources_without_saving_or_exposing_errors(): void
  {
    [$page, $grid] = $this->fixture();
    $this->actingAs($this->cmsActor((new User)->forceFill(['role' => 'super_admin', 'is_active' => true])));
    $before = $grid->fresh()->settings;
    try {
      foreach (['empty' => 'Source responded; no matching records', 'invalid' => 'Source response does not match the contract', 'failed' => 'Source unavailable; retry'] as $mode => $message) {
        AuthoringRecords::$mode = $mode;
        $request = DynamicContentAuthoringRequest::create('/preview', 'POST', ['source' => 'authoring::items', 'page_id' => $page->id, 'block_id' => $grid->id, 'locale' => 'en', 'collection' => $grid->setting('content_collection')]);
        $request->setContainer($this->app);
        $request->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => auth()->user());
        // source_settings is provider-owned and not an authoring-form field.
        $collection = $request->input('collection');
        unset($collection['source_settings']);
        $request->merge(['collection' => $collection]);
        $request->validateResolved();
        $response = app(DynamicContentAuthoringController::class)->preview($request, app(SaveDynamicContent::class), app(BlockTranslationResolver::class));
        $html = $response->getContent();
        $this->assertStringContainsString($message, $html);
        $this->assertStringNotContainsString('private-transport-detail', $html);
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertSame($before, $grid->fresh()->settings);
      }
    } finally {
      AuthoringRecords::$mode = 'valid';
    }
  }

  #[Test]
  public function configured_preview_pagination_posts_the_same_unsaved_design_instead_of_using_a_get_only_link(): void
  {
    $block = new Block(['type' => 'grid']);
    $block->setAttribute('content_source_pagination', ['last_page' => 2, 'current_page' => 1, 'next_url' => '/preview?wb_collection_1_page=2']);
    $html = view('webblocks-cms::pages.partials.blocks.content-collection-pagination', ['block' => $block, 'dynamicAuthoringPreviewFields' => collect(['source' => 'authoring::items', 'collection[per_page]' => '1'])])->render();
    $this->assertStringContainsString('method="POST"', $html);
    $this->assertStringContainsString('name="collection[per_page]" value="1"', $html);
    $this->assertStringNotContainsString('<a ', $html);
    $public = view('webblocks-cms::pages.partials.blocks.content-collection-pagination', ['block' => $block])->render();
    $this->assertStringContainsString('<a ', $public);
    $this->assertStringNotContainsString('<form ', $public);
  }

  private function errors(Block $block, array $overrides): array
  {
    $request = $this->request($block, $overrides);

    return (new ReflectionMethod(BlockRequest::class, 'getValidatorInstance'))->invoke($request)->errors()->toArray();
  }

  private function request(Block $block, array $overrides): BlockRequest
  {
    $request = BlockRequest::create('/blocks/'.$block->id, 'PUT', $overrides + ['page_id' => $block->page_id, 'block_type_id' => $block->block_type_id, 'slot_type_id' => $block->slot_type_id, 'parent_id' => $block->parent_id, 'sort_order' => 0, 'status' => 'published']);
    $request->setContainer($this->app);
    $request->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $this->cmsActor((new User)->forceFill(['role' => 'super_admin', 'is_active' => true])));
    $route = new Route(['PUT'], '/blocks/{block}', []);
    $route->bind($request);
    $route->setParameter('block', $block);
    $request->setRouteResolver(fn () => $route);

    return $request;
  }

  private function fixture(): array
  {
    $site = Site::create(['handle' => 'authoring', 'name' => 'Authoring']);
    foreach (ContentSourceContract::languages() as $code) {
      $locale = Locale::create(['code' => $code, 'name' => $code, 'is_enabled' => true, 'is_default' => $code === 'en']);
      $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    }
    $page = Page::create(['site_id' => $site->id, 'slug' => 'items', 'status' => 'draft']);
    $slot = SlotType::create(['slug' => 'main', 'name' => 'Main', 'status' => 'published', 'sort_order' => 0]);
    PageSlot::create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'sort_order' => 0]);
    $grid = $this->make($page, 'grid');
    $card = $this->make($page, 'card', $grid->id);
    $body = $this->make($page, 'card_body', $card->id);
    $title = $this->make($page, 'header', $body->id);
    $grid->update(['settings' => ['content_collection' => ['source' => 'authoring::items', 'template_block_id' => $card->id, 'source_settings' => ['custom' => 'unchanged'], 'limit' => 50]]]);
    $title->update(['settings' => ['content_bindings' => ['title' => ['source' => 'authoring::items', 'record' => '@item', 'field' => 'title', 'fallback' => 'preserved']]]]);

    return [$page, $grid->fresh(), $card, $title->fresh()];
  }

  private function make(Page $page, string $type, ?int $parent = null): Block
  {
    $bt = BlockType::firstOrCreate(['slug' => $type], ['name' => ucfirst($type), 'category' => 'content', 'source_type' => 'static', 'is_system' => false, 'is_container' => $type !== 'header', 'sort_order' => 0, 'status' => 'published']);

    return Block::create(['page_id' => $page->id, 'block_type_id' => $bt->id, 'slot_type_id' => SlotType::where('slug', 'main')->value('id'), 'slot' => 'main', 'type' => $type, 'parent_id' => $parent, 'sort_order' => 0, 'status' => 'published']);
  }
}

class AuthoringRecords implements ContentCollectionSourceResolver, ContentSourceResolver
{
  public static string $mode = 'valid';

  public function resolveCollection(array $settings, ContentSourceContext $context): iterable
  {
    if (self::$mode === 'failed') {
    throw new \RuntimeException('private-transport-detail');
    }
    if (self::$mode === 'invalid') {
    return [['_locale' => 'wrong']];
    }
    if (self::$mode === 'empty') {
    return [];
    }

    return [$this->resolve('one', $context), $this->resolve('two', $context)];
  }

  public function options(ContentSourceContext $context): array
  {
  return ['one' => 'One', 'two' => 'Two'];
  }

  public function resolve(string $recordKey, ContentSourceContext $context): ?array
  {
  return ['_locale' => $context->locale, 'id' => $recordKey, 'title' => 'Record '.$recordKey, 'image' => '/image.png'];
  }
}
class AuthoringDenied implements ContentSourceAccessPolicy
{
  public function allows(ContentSourceContext $context): bool
  {
  return false;
  }
}
class AuthoringActor extends BaseUser
{
 use HasCmsAdminAccess;
 }
