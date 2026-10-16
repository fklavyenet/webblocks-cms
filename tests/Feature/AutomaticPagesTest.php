<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\AutomaticPages\SaveAutomaticPageRule;
use WebBlocks\Cms\Http\Controllers\Admin\AutomaticPageController;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Concerns\HasCmsAdminAccess;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageInspector;
use WebBlocks\Cms\Support\ContentSources\ContentPublicationPage;
use WebBlocks\Cms\Support\ContentSources\ContentPublicationRecord;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContract;
use WebBlocks\Cms\Support\ContentSources\ContentSourceDefinition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRegistry;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentPublicationSource;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
use WebBlocks\Cms\Support\ContentSources\PublicationSources;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class AutomaticPagesTest extends TestCase
{
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
    if (! class_exists('App\\Models\\User')) {
    class_alias(AutomaticPagesActor::class, 'App\\Models\\User');
    }
    // Exercise the same controller and FormRequest without host login/setup middleware.
    Route::middleware('web')->group(function (): void {
      Route::post('/test/automatic-pages', [AutomaticPageController::class, 'store']);
      Route::put('/test/automatic-pages/{automaticPage}', [AutomaticPageController::class, 'update']);
      Route::get('/test/automatic-pages/{automaticPage}/preview', [AutomaticPageController::class, 'preview']);
      Route::get('/test/automatic-pages/{automaticPage}/records', [AutomaticPageController::class, 'records']);
    });
    PublicationFixture::$mode = 'ready';
    PublicationFixture::$calls = [];
    $this->register();
  }

  #[Test]
  public function existing_installations_receive_the_draft_table_through_the_update_path(): void
  {
    Schema::drop('wbcms_automatic_page_rules');
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_16_150000_create_automatic_page_rules.php';
    $migration->up();
    $migration->up();
    [$site, $page] = $this->fixture();
    $rule = app(SaveAutomaticPageRule::class)->handle($this->data($site, $page), $this->actor());
    $migration->down();
    $this->assertSame('draft', $rule->fresh()->status);
    $this->assertSame('published', $page->fresh()->status);
  }

  #[Test]
  public function source_pair_is_optional_and_disabled_providers_cannot_be_selected(): void
  {
    [$site] = $this->fixture();
    $this->assertNotNull(app(PublicationSources::class)->find('library::books', $site, $this->actor()));
    $this->register(false);
    $this->assertNull(app(PublicationSources::class)->find('library::books', $site, $this->actor()));
    $this->assertFalse(ContentSourceDefinition::collection('other::legacy')->capabilities()['automatic_pages']);
    $this->assertFalse(app(PublicationSources::class)->options($site, $this->actor()) !== []);
  }

  #[Test]
  public function draft_form_saves_relational_rule_without_changing_pages_media_or_translations(): void
  {
    [$site, $page] = $this->fixture();
    $before = PageTranslation::all()->toArray();
    $this->actingAs($this->actor())->post('/test/automatic-pages', $this->data($site, $page))->assertRedirect();
    $rule = AutomaticPageRule::firstOrFail();
    $this->assertSame('draft', $rule->status);
    $this->assertSame(1, $rule->revision);
    $this->assertSame($before, PageTranslation::all()->toArray());
    $this->assertSame('published', $page->fresh()->status);
    $this->assertCount(1, Page::all());
  }

  #[Test]
  public function draft_requests_reject_status_injection_reserved_paths_and_missing_design_language(): void
  {
    [$site, $page] = $this->fixture();
    $this->actingAs($this->actor())->postJson('/test/automatic-pages', $this->data($site, $page) + ['status' => 'published'])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/test/automatic-pages', array_replace($this->data($site, $page), ['base_path' => '/plugins']))->assertUnprocessable()->assertJsonValidationErrors('base_path');
    $page->translations()->whereHas('locale', fn ($q) => $q->where('code', 'tr'))->delete();
    $this->postJson('/test/automatic-pages', $this->data($site, $page))->assertUnprocessable()->assertJsonValidationErrors('template_page_id');
    $this->assertSame(0, AutomaticPageRule::count());
  }

  #[Test]
  public function other_sites_are_forbidden_and_stale_edits_do_not_overwrite(): void
  {
    [$site, $page] = $this->fixture();
    $actor = $this->actor();
    $rule = app(SaveAutomaticPageRule::class)->handle($this->data($site, $page), $actor);
    $this->actingAs($actor)->putJson('/test/automatic-pages/'.$rule->id, $this->data($site, $page) + ['revision' => 1])->assertRedirect();
    $this->putJson('/test/automatic-pages/'.$rule->id, $this->data($site, $page) + ['revision' => 1])->assertUnprocessable()->assertJsonValidationErrors('revision');
    $this->assertSame(2, $rule->fresh()->revision);
    $editor = $this->actor('editor');
    $editor->setRelation('sites', collect());
    $this->actingAs($editor)->get('/test/automatic-pages/'.$rule->id.'/records')->assertForbidden();
    $this->get('/test/automatic-pages/'.$rule->id.'/preview?record=second&locale=en')->assertForbidden();
    $this->postJson('/test/automatic-pages', $this->data($site, $page))->assertForbidden();
  }

  #[Test]
  public function inspection_validates_all_six_languages_and_reports_existing_addresses(): void
  {
    [$site, $page] = $this->fixture();
    $rule = app(SaveAutomaticPageRule::class)->handle($this->data($site, $page), $this->actor());
    $inspector = app(AutomaticPageInspector::class);
    $result = $inspector->record($rule, 'second', $this->actor());
    $this->assertSame('available', $result['status']);
    $this->assertCount(6, $result['records']);
    $this->assertCount(6, PublicationFixture::$calls);
    $this->assertSame([$page->id], $result['conflicts']);
    $this->assertSame('second tr', $result['records']['tr']['title']);
    PublicationFixture::$mode = 'wrong_locale';
    $this->assertSame('incomplete', $inspector->record($rule, 'second', $this->actor())['status']);
    PublicationFixture::$mode = 'missing';
    $this->assertSame('missing', $inspector->record($rule, 'second', $this->actor())['status']);
    PublicationFixture::$mode = 'unavailable';
    $this->assertSame('unavailable', $inspector->record($rule, 'second', $this->actor())['status']);
  }

  #[Test]
  public function enumeration_is_paginated_independently_and_rejects_non_advancing_cursors(): void
  {
    [$site, $page] = $this->fixture();
    $rule = app(SaveAutomaticPageRule::class)->handle($this->data($site, $page), $this->actor());
    $inspector = app(AutomaticPageInspector::class);
    $this->assertSame('next', $inspector->entries($rule, $this->actor())['page']->nextCursor);
    $this->assertSame('unavailable', $inspector->entries($rule, $this->actor(), 'next')['status']);
  }

  #[Test]
  public function real_design_preview_switches_records_images_and_alt_without_saving_or_public_routing(): void
  {
    [$site, $page, $heading] = $this->fixture();
    $rule = app(SaveAutomaticPageRule::class)->handle($this->data($site, $page), $this->actor());
    $translations = PageTranslation::all()->toArray();
    $binding = $heading->fresh()->settings;
    foreach (ContentSourceContract::languages() as $locale) {
      $response = $this->actingAs($this->actor())->get('/test/automatic-pages/'.$rule->id.'/preview?record=second&locale='.$locale);
      $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('second '.$locale)->assertSee('/images/second.png', false)->assertSee('alt="Second image '.$locale.'"', false);
      $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
      $this->assertStringContainsString('hreflang="fr" href="http://localhost/fr/library/second"', $response->getContent());
    }
    $this->assertSame($translations, PageTranslation::all()->toArray());
    $this->assertSame($binding, $heading->fresh()->settings);
    $this->assertSame('first en', $heading->fresh()->setRelation('renderPage', $page)->setAttribute('requested_locale_code', 'en')->boundPublicValue('title'));
    PublicationFixture::$mode = 'missing';
    $this->get('/test/automatic-pages/'.$rule->id.'/preview?record=second&locale=en')->assertUnprocessable();
    $this->assertCount(1, Page::all());
  }

  #[Test]
  public function publication_pages_reject_duplicate_keys_and_empty_pages_with_continuations(): void
  {
    foreach ([[[['key' => 'a', 'label' => 'A'], ['key' => 'a', 'label' => 'B']], null], [[], 'next']] as [$entries, $cursor]) {
      try {
      new ContentPublicationPage($entries, $cursor);
      $this->fail('Malformed pagination must fail.');
      } catch (\InvalidArgumentException) {
      $this->assertTrue(true);
      }
    }
  }

  #[Test]
  public function record_screen_and_draft_form_render_with_declared_connections(): void
  {
    [$site, $page] = $this->fixture();
    $rule = app(SaveAutomaticPageRule::class)->handle($this->data($site, $page), $this->actor());
    Route::middleware('web')->get('/test/automatic-pages/{automaticPage}/edit', [AutomaticPageController::class, 'edit']);
    $this->actingAs($this->actor())->get('/test/automatic-pages/'.$rule->id.'/edit')->assertOk()->assertSee('Fields supplied by the selected record')->assertSee('subtitle');
    $this->get('/test/automatic-pages/'.$rule->id.'/records?record=second')->assertOk()->assertSee('Source reports 31 records')->assertSee('Render preview · FR')->assertSee('Existing pages use this address');
  }

  #[Test]
  public function a_static_design_cannot_be_saved_as_a_dynamic_detail_template(): void
  {
    [$site, $page] = $this->fixture();
    foreach ($page->blocks()->get() as $block) {
    $block->update(['settings' => []]);
    }
    $this->actingAs($this->actor())->postJson('/test/automatic-pages', $this->data($site, $page))->assertUnprocessable()->assertJsonValidationErrors('template_page_id');
  }

  #[Test]
  public function a_cross_site_design_reference_is_never_inspected_or_rendered(): void
  {
    [$site, $page] = $this->fixture();
    $rule = app(SaveAutomaticPageRule::class)->handle($this->data($site, $page), $this->actor());
    $other = Site::create(['handle' => 'other-library', 'name' => 'Other library']);
    $otherPage = Page::create(['site_id' => $other->id, 'slug' => 'other-design', 'status' => 'draft']);
    $rule->update(['template_page_id' => $otherPage->id]);
    $rule = $rule->fresh();
    $this->assertSame('unavailable', app(AutomaticPageInspector::class)->entries($rule, $this->actor())['status']);
    $this->assertSame('unavailable', app(AutomaticPageInspector::class)->record($rule, 'second', $this->actor())['status']);
    $this->assertSame([], PublicationFixture::$calls);
    $this->actingAs($this->actor())->get('/test/automatic-pages/'.$rule->id.'/preview?record=second&locale=en')->assertUnprocessable();
  }

  private function actor(string $role = 'super_admin'): User
  {
    return (new User)->forceFill(['id' => 1, 'name' => 'Test editor', 'role' => $role, 'is_active' => true]);
  }

  private function data(Site $site, Page $page): array
  {
    return ['name' => 'Library detail pages', 'site_id' => $site->id, 'source_handle' => 'library::books', 'template_page_id' => $page->id, 'base_path' => '/library'];
  }

  private function register(bool $active = true): void
  {
    $fields = ['id' => 'text', 'slug' => 'text', 'title' => 'text', 'image' => 'url', 'alt' => 'text'];
    $registry = new PluginRegistry(['library' => $active]);
    $registry->register(PluginDefinition::make('library')->contentSources([
      ContentSourceDefinition::collection('library::books')->standard(ContentSourceContract::languages(), 'Public books', 'Library API', 'id')->resolver(PublicationFixture::class)->fields($fields)->publishesPages('library::book', 'slug', 'title', null, 'image'),
      ContentSourceDefinition::entity('library::book')->standard(ContentSourceContract::languages(), 'Public book', 'Library API', 'id')->resolver(PublicationFixture::class)->fields($fields),
    ]));
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->forgetInstance(ContentSourceRegistry::class);
  }

  private function fixture(): array
  {
    $site = Site::create(['handle' => 'library', 'name' => 'Library']);
    $page = Page::create(['site_id' => $site->id, 'slug' => 'design', 'status' => 'published']);
    foreach (ContentSourceContract::languages() as $code) {
      $locale = Locale::firstOrCreate(['code' => $code], ['name' => $code, 'is_enabled' => true, 'is_default' => $code === 'en']);
      $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
      $page->translations()->create(['site_id' => $site->id, 'locale_id' => $locale->id, 'name' => 'Existing '.$code, 'slug' => 'second', 'path' => '/library/second']);
    }
    $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
    PageSlot::create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'source_type' => 'page']);
    $make = function ($type, $bindings) use ($page, $slot) {
      $bt = BlockType::firstOrCreate(['slug' => $type], ['name' => ucfirst($type), 'status' => 'published', 'category' => 'content']);

      return Block::create(['page_id' => $page->id, 'block_type_id' => $bt->id, 'type' => $type, 'slot_type_id' => $slot->id, 'slot' => 'main', 'status' => 'published',
        'settings' => ['content_bindings' => collect($bindings)->map(fn ($field) => ['source' => 'library::book', 'record' => 'first', 'field' => $field])->all()]]);
    };
    $heading = $make('header', ['title' => 'title']);
    $make('image', ['image_source' => 'image', 'subtitle' => 'alt']);

    return [$site, $page, $heading];
  }
}

class AutomaticPagesActor extends BaseUser
{
 use HasCmsAdminAccess;
 }
class PublicationFixture implements ContentCollectionSourceResolver, ContentPublicationSource, ContentSourceResolver
{
  public static string $mode = 'ready';

  public static array $calls = [];

  public function resolveCollection(array $settings, ContentSourceContext $context): iterable
  {
    return [];
  }

  public function resolve(string $recordKey, ContentSourceContext $context): ?array
  {
    return ['_locale' => self::$mode === 'wrong_locale' && $context->locale === 'tr' ? 'en' : $context->locale, 'id' => $recordKey, 'slug' => $recordKey, 'title' => $recordKey.' '.$context->locale, 'image' => '/images/'.$recordKey.'.png', 'alt' => 'Second image '.$context->locale];
  }

  public function options(ContentSourceContext $context): array
  {
    return [];
  }

  public function publicationPage(?string $cursor, int $limit, ContentSourceContext $context): ContentPublicationPage
  {
    return new ContentPublicationPage([['key' => 'second', 'label' => 'Second book']], 'next', 31);
  }

  public function publicationRecord(string $key, ContentSourceContext $context): ContentPublicationRecord
  {
    self::$calls[] = $context->locale;

    return match (self::$mode) {
    'missing' => ContentPublicationRecord::missing(), 'unavailable' => ContentPublicationRecord::unavailable(), default => ContentPublicationRecord::available($this->resolve($key, $context))
    };
  }
}
