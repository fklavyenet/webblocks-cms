<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Actions\AutomaticPages\ActivateAutomaticPageRule;
use WebBlocks\Cms\Actions\AutomaticPages\PauseAutomaticPageRule;
use WebBlocks\Cms\Actions\AutomaticPages\SaveAutomaticPageRule;
use WebBlocks\Cms\Models\AutomaticPageAdoption;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Concerns\HasCmsAdminAccess;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageRouter;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageTransition;
use WebBlocks\Cms\Support\ContentSources\ContentPublicationPage;
use WebBlocks\Cms\Support\ContentSources\ContentPublicationRecord;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContract;
use WebBlocks\Cms\Support\ContentSources\ContentSourceDefinition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRegistry;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentPublicationRouteSource;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
use WebBlocks\Cms\Support\Pages\PageDeleter;
use WebBlocks\Cms\Support\Pages\PageSiteMoveValidator;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Revisions\RevisionCleanupQuery;
use WebBlocks\Cms\Support\Sitemap\SitemapGenerator;
use WebBlocks\Cms\Tests\TestCase;

class AutomaticPageRoutingTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('cms.multisite.unknown_host_fallback', false);
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists('App\\Models\\User')) {
    class_alias(AutomaticPagesRoutingActor::class, 'App\\Models\\User');
    }
    RoutingPublicationFixture::$mode = 'ready';
    $this->register();
    Route::middleware('web')->get('/test/route/{slug}', fn ($slug) => app(AutomaticPageRouter::class)->response(request(), '/library/'.$slug) ?? response('native-fallback'));
    Route::middleware('web')->get('/test/{locale}/route/{slug}', fn ($locale, $slug) => app(AutomaticPageRouter::class)->response(request(), '/library/'.$slug) ?? response('native-fallback'));
  }

  private function prepared(): array
  {
    [$site, $page] = $this->fixture();
    $site->update(['domain' => 'library.test', 'is_primary' => true]);
    $rule = app(SaveAutomaticPageRule::class)->handle($this->data($site, $page), $this->actor());

    return [$site, $page, $rule];
  }

  private function activate($rule): void
  {
    $plan = app(AutomaticPageTransition::class)->inspect($rule, $this->actor());
    $this->assertSame([], $plan['errors']);
    app(ActivateAutomaticPageRule::class)->handle($rule, $this->actor(), $plan['digest']);
  }

  #[Test]
  public function draft_does_not_own_routes_and_activation_retains_full_revision_and_native_translations(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $before = PageTranslation::all()->map(fn ($translation) => $translation->makeHidden(['created_at', 'updated_at'])->toArray())->all();
    $this->get('http://library.test/test/route/second')->assertSee('native-fallback');
    $this->activate($rule);
    $this->assertSame('active', $rule->fresh()->status);
    $this->assertSame('draft', $page->fresh()->status);
    $this->assertSame($before, PageTranslation::all()->map(fn ($translation) => $translation->makeHidden(['created_at', 'updated_at'])->toArray())->all());
    $adoption = AutomaticPageAdoption::firstOrFail();
    $revision = PageRevision::findOrFail($adoption->page_revision_id);
    $this->assertCount(6, $revision->snapshot['translations']);
    $this->assertCount(2, $revision->snapshot['blocks']);
    $this->assertSame('published', $adoption->original_status);
  }

  #[Test]
  public function new_and_adopted_records_render_in_six_languages_without_creating_pages(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    foreach (ContentSourceContract::languages() as $locale) {
      foreach (['second', 'brand-new'] as $slug) {
        $this->get('http://library.test/test/'.$locale.'/route/'.$slug)->assertOk()->assertSee($slug.' '.$locale)->assertSee('Second image '.$locale)->assertHeader('Cache-Control', 'no-store, private');
      }
    }
    $this->assertSame(1, Page::count());
  }

  #[Test]
  public function withdrawal_is_404_and_source_failure_is_503_without_native_fallback(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    RoutingPublicationFixture::$mode = 'missing';
    $this->get('http://library.test/test/route/second')->assertNotFound()->assertDontSee('native-fallback');
    RoutingPublicationFixture::$mode = 'unavailable';
    $this->get('http://library.test/test/route/second')->assertStatus(503);
    RoutingPublicationFixture::$mode = 'wrong_locale';
    $this->get('http://library.test/test/route/second')->assertStatus(503);
    RoutingPublicationFixture::$mode = 'ready';
    $this->get('http://library.test/test/route/second')->assertOk();
    $this->get('http://unknown.test/test/route/second')->assertNotFound();
    $this->get('http://library.test/test/zz/route/second')->assertNotFound();
  }

  #[Test]
  public function a_stale_review_cannot_activate_or_change_existing_pages(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $plan = app(AutomaticPageTransition::class)->inspect($rule, $this->actor());
    $page->translations()->first()->update(['name' => 'Changed after review']);
    try {
      app(ActivateAutomaticPageRule::class)->handle($rule, $this->actor(), $plan['digest']);
      $this->fail('Expected stale review refusal.');
    } catch (HttpException $e) {
      $this->assertSame(409, $e->getStatusCode());
    }
    $this->assertSame('draft', $rule->fresh()->status);
    $this->assertSame('published', $page->fresh()->status);
    $this->assertSame(0, AutomaticPageAdoption::count());
  }

  #[Test]
  public function sitemap_follows_live_publication_without_waiting_for_native_cache_expiry(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    $generator = app(SitemapGenerator::class);
    $this->assertStringContainsString('/library/brand-new', $generator->sitemap($site));
    RoutingPublicationFixture::$mode = 'missing';
    $this->assertStringNotContainsString('/library/second', $generator->sitemap($site));
    RoutingPublicationFixture::$mode = 'ready';
    $this->assertStringContainsString('/library/second', $generator->sitemap($site));
  }

  #[Test]
  public function restoring_previous_publication_retains_content_and_can_be_reviewed_again(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $translations = PageTranslation::all()->map(fn ($translation) => $translation->makeHidden(['created_at', 'updated_at'])->toArray())->all();
    $this->activate($rule);
    $rule = $rule->fresh();
    app(PauseAutomaticPageRule::class)->handle($rule, $this->actor(), $rule->revision);
    $this->assertSame('paused', $rule->fresh()->status);
    $this->assertSame('published', $page->fresh()->status);
    $this->assertSame($translations, PageTranslation::all()->map(fn ($translation) => $translation->makeHidden(['created_at', 'updated_at'])->toArray())->all());
    $this->get('http://library.test/test/route/second')->assertSee('native-fallback');
    $this->activate($rule->fresh());
    $this->assertSame('draft', $page->fresh()->status);
    $this->assertSame(1, AutomaticPageAdoption::count());
  }

  #[Test]
  public function rollback_refuses_to_overwrite_content_edited_since_activation(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    $page->translations()->first()->update(['name' => 'Deliberate new edit']);
    try {
      app(PauseAutomaticPageRule::class)->handle($rule->fresh(), $this->actor(), $rule->fresh()->revision);
      $this->fail('Expected drift refusal.');
    } catch (HttpException $e) {
      $this->assertSame(409, $e->getStatusCode());
    }
    $this->assertSame('active', $rule->fresh()->status);
    $this->assertSame('draft', $page->fresh()->status);
    $this->assertSame('Deliberate new edit', $page->translations()->first()->name);
  }

  #[Test]
  public function an_editor_cannot_activate_a_rule(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $actor = $this->actor();
    $actor->role = 'editor';
    $this->expectException(AuthorizationException::class);
    app(ActivateAutomaticPageRule::class)->handle($rule, $actor, str_repeat('a', 64));
  }

  #[Test]
  public function recovery_revisions_are_excluded_from_retention_cleanup(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    $adoption = AutomaticPageAdoption::firstOrFail();
    PageRevision::whereKey($adoption->page_revision_id)->update(['created_at' => now()->subYear()]);
    $query = app(RevisionCleanupQuery::class);
    $this->assertContains($adoption->page_revision_id, $query->protectedIds([$adoption->page_revision_id]));
    $eligible = [];
    foreach ($query->batches(PageRevision::class, 'page_id', 1, 0, true) as $batch) {
      $eligible = array_merge($eligible, array_column($batch, 'id'));
    }
    $this->assertNotContains($adoption->page_revision_id, $eligible);
  }

  #[Test]
  public function retained_designs_refuse_delete_or_cross_site_move_with_a_validation_message(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    $other = Site::create(['handle' => 'unrelated', 'name' => 'Unrelated site']);
    foreach (['delete', 'move'] as $operation) {
      try {
        $operation === 'delete'
          ? app(PageDeleter::class)->delete($page->fresh())
          : app(PageSiteMoveValidator::class)->validate($page->fresh(), $other);
        $this->fail('Expected retained design refusal.');
      } catch (ValidationException $exception) {
        $this->assertArrayHasKey('page', $exception->errors());
      }
    }
    $this->assertSame($site->id, $page->fresh()->site_id);
  }

  private function actor(string $role = 'super_admin'): User
  {
    return User::find(1) ?? User::forceCreate(['id' => 1, 'name' => 'Test editor', 'email' => 'editor@example.test', 'password' => 'local-fixture', 'role' => $role, 'is_active' => true]);
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
      ContentSourceDefinition::collection('library::books')->standard(ContentSourceContract::languages(), 'Public books', 'Library API', 'id')->resolver(RoutingPublicationFixture::class)->fields($fields)->publishesPages('library::book', 'slug', 'title', null, 'image'),
      ContentSourceDefinition::entity('library::book')->standard(ContentSourceContract::languages(), 'Public book', 'Library API', 'id')->resolver(RoutingPublicationFixture::class)->fields($fields),
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

class AutomaticPagesRoutingActor extends BaseUser
{
 use HasCmsAdminAccess;
 }
class RoutingPublicationFixture implements ContentCollectionSourceResolver, ContentPublicationRouteSource, ContentSourceResolver
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
    return new ContentPublicationPage(self::$mode === 'missing' ? [] : [['key' => 'second', 'label' => 'Second book'], ['key' => 'brand-new', 'label' => 'New book']], null, self::$mode === 'missing' ? 0 : 2);
  }

  public function publicationKey(string $slug, ContentSourceContext $context): ?string
  {
    if (self::$mode === 'unavailable') {
    throw new \RuntimeException('Upstream failure');
    }

    return self::$mode === 'missing' || $slug === 'unknown' ? null : $slug;
  }

  public function publicationRecord(string $key, ContentSourceContext $context): ContentPublicationRecord
  {
    self::$calls[] = $context->locale;

    return match (self::$mode) {
    'missing' => ContentPublicationRecord::missing(), 'unavailable' => ContentPublicationRecord::unavailable(), default => ContentPublicationRecord::available($this->resolve($key, $context))
    };
  }
}
