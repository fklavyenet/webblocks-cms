<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Actions\AutomaticPages\ActivateAutomaticPageRule;
use WebBlocks\Cms\Actions\AutomaticPages\PauseAutomaticPageRule;
use WebBlocks\Cms\Actions\AutomaticPages\SaveAutomaticPageRule;
use WebBlocks\Cms\Http\Middleware\ServeCmsPageBeforeRedirectCatchAll;
use WebBlocks\Cms\Models\AutomaticPageAdoption;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Concerns\HasCmsAdminAccess;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAccessRule;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\PublicSearchIndex;
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
use WebBlocks\Cms\Support\Search\PublicSearchQuery;
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
  public function redirect_manager_bridge_serves_adopted_and_new_records_and_owns_withdrawn_urls(): void
  {
    [$site, $page, $rule] = $this->prepared();
    Route::get('/{webblocksRedirectManagerPath}', fn () => response('redirect fallback'))
      ->where('webblocksRedirectManagerPath', '.+')
      ->middleware(ServeCmsPageBeforeRedirectCatchAll::class);
    $this->activate($rule);
    $this->get('http://library.test/library/second')->assertOk()->assertSee('second en')->assertHeader('Cache-Control', 'no-store, private');
    $this->get('http://library.test/library/brand-new')->assertOk()->assertSee('brand-new en');
    $this->get('http://library.test/unrelated')->assertSee('redirect fallback');
    RoutingPublicationFixture::$mode = 'missing';
    $this->get('http://library.test/library/second')->assertNotFound()->assertDontSee('redirect fallback');
    RoutingPublicationFixture::$mode = 'unavailable';
    $this->get('http://library.test/library/brand-new')->assertStatus(503)->assertDontSee('redirect fallback');
  }

  #[Test]
  public function automatic_routes_through_redirect_bridge_share_the_public_page_budget(): void
  {
    [$site, $page, $rule] = $this->prepared();
    Route::get('/{webblocksRedirectManagerPath}', fn () => response('redirect fallback'))
      ->where('webblocksRedirectManagerPath', '.+')
      ->middleware(ServeCmsPageBeforeRedirectCatchAll::class);
    $this->activate($rule);
    config(['cms.public_rate_limits.pages_per_minute' => 1]);
    $this->get('http://library.test/library/second')->assertOk();
    $this->get('http://library.test/library/brand-new')->assertStatus(429)->assertHeader('Retry-After');
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

  #[Test]
  public function automatic_search_uses_actual_translated_titles_and_descriptions_in_all_six_languages(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    foreach (ContentSourceContract::languages() as $locale) {
      $prefix = $locale === 'en' ? '' : '/'.$locale;
      $this->getJson('http://library.test'.$prefix.'/search.json?q=Only'.$locale)
        ->assertOk()->assertJsonPath('count', 2)
        ->assertJsonPath('results.0.title', 'brand-new '.$locale)
        ->assertJsonPath('results.0.url', $prefix.'/library/brand-new')
        ->assertJsonPath('results.0.excerpt', 'Translated description Only'.$locale);
      $this->getJson('http://library.test'.$prefix.'/search.json?q=Only'.($locale === 'en' ? 'tr' : 'en'))
        ->assertOk()->assertJsonPath('count', 0);
    }
    $this->get('http://library.test/tr/search?q=Onlytr')->assertOk()->assertSee('brand-new tr')->assertSee('/tr/library/brand-new')->assertDontSee('Existing tr');
    $this->assertSame(1, Page::count());
    RoutingPublicationFixture::$calls = [];
    $this->getJson('http://library.test/search.json')->assertOk()->assertJsonPath('count', 0);
    $this->getJson('http://library.test/search.json?q=x')->assertOk()->assertJsonPath('count', 0);
    $this->assertSame([], RoutingPublicationFixture::$calls);
  }

  #[Test]
  public function search_immediately_follows_withdrawal_and_republication_and_never_uses_an_old_native_copy(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    // Even an accidentally republished copy must not bypass prefix ownership.
    $page->update(['status' => 'published']);
    PublicSearchIndex::updateOrCreate(['page_id' => $page->id, 'locale_id' => Locale::where('code', 'en')->firstOrFail()->id], ['site_id' => $site->id, 'title' => 'Old native Onlyen', 'content' => 'Stale Onlyen', 'url' => '/library/second', 'indexed_at' => now()]);
    $this->getJson('http://library.test/search.json?q=Onlyen')->assertOk()->assertJsonPath('count', 2);
    RoutingPublicationFixture::$mode = 'missing';
    $this->getJson('http://library.test/search.json?q=Onlyen')->assertOk()->assertJsonPath('count', 0);
    RoutingPublicationFixture::$mode = 'ready';
    $this->getJson('http://library.test/search.json?q=Onlyen')->assertOk()->assertJsonPath('count', 2)->assertDontSee('Old native');
    RoutingPublicationFixture::$mode = 'withdraw_second';
    $this->getJson('http://library.test/search.json?q=Onlyen')->assertOk()->assertJsonPath('count', 1)->assertJsonPath('results.0.url', '/library/brand-new');
    $this->get('http://library.test/test/route/second')->assertNotFound();
  }

  #[Test]
  public function search_source_failure_or_incomplete_translation_returns_503_instead_of_stale_results(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    foreach (['unavailable', 'wrong_locale'] as $mode) {
      RoutingPublicationFixture::$mode = $mode;
      $this->getJson('http://library.test/search.json?q=Onlyen')->assertStatus(503)->assertDontSee('Existing en');
    }
  }

  #[Test]
  public function automatic_search_is_site_scoped_and_excludes_protected_and_inactive_rules(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->getJson('http://library.test/search.json?q=Onlyen')->assertOk()->assertJsonPath('count', 0);
    $this->activate($rule);
    $other = Site::create(['handle' => 'other-library', 'name' => 'Other', 'domain' => 'other-library.test']);
    $other->locales()->syncWithoutDetaching([Locale::where('code', 'en')->firstOrFail()->id => ['is_enabled' => true]]);
    $this->getJson('http://other-library.test/search.json?q=Onlyen')->assertOk()->assertJsonPath('count', 0);
    PageAccessRule::create(['page_id' => $page->id, 'package_id' => null]);
    $this->getJson('http://library.test/search.json?q=Onlyen')->assertOk()->assertJsonPath('count', 0);
    PageAccessRule::query()->delete();
    $rule->forceFill(['status' => 'paused'])->save();
    $this->getJson('http://library.test/search.json?q=Onlyen')->assertOk()->assertJsonPath('count', 0);
    $this->getJson('http://unknown.test/search.json?q=Onlyen')->assertNotFound();
  }

  #[Test]
  public function automatic_search_keeps_mounts_and_omits_a_disabled_site_language(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    config(['webblocks-cms.public.mount' => 'wb']);
    $turkish = Locale::where('code', 'tr')->firstOrFail();
    $results = app(PublicSearchQuery::class)->search($site, $turkish, 'Onlytr');
    $this->assertSame('/wb/tr/library/brand-new', $results->items()[0]->url);
    $site->locales()->updateExistingPivot($turkish->id, ['is_enabled' => false]);
    $this->assertSame(0, app(PublicSearchQuery::class)->search($site->fresh(), $turkish, 'Onlytr')->total());
  }

  #[Test]
  public function turkish_source_search_matches_both_dotted_and_dotless_case_pairs(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    RoutingPublicationFixture::$mode = 'turkish_case';
    foreach (['ihtiyacınız', 'İHTİYACINIZ', 'ışık', 'IŞIK'] as $query) {
      $this->getJson('http://library.test/tr/search.json?q='.urlencode($query))
        ->assertOk()->assertJsonPath('count', 2)->assertJsonPath('results.0.url', '/tr/library/brand-new');
    }
    $this->getJson('http://library.test/search.json?q='.urlencode('ışık'))->assertOk()->assertJsonPath('count', 0);
    $this->getJson('http://library.test/tr/search.json?q=MEMBERSHIPS')->assertOk()->assertJsonPath('count', 1);
  }

  #[Test]
  public function german_source_search_uses_unicode_case_folding_for_sharp_s_and_umlauts(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    RoutingPublicationFixture::$mode = 'german_case';
    foreach (['STRASSE', 'straße', 'ÄPFEL', 'äpfel'] as $query) {
      $this->getJson('http://library.test/de/search.json?q='.urlencode($query))
        ->assertOk()->assertJsonPath('count', 2);
    }
  }

  #[Test]
  public function native_and_automatic_search_share_relevance_count_and_pagination_without_duplicates(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    $english = Locale::where('code', 'en')->firstOrFail();
    $native = Page::create(['site_id' => $site->id, 'slug' => 'native', 'status' => 'published']);
    $native->translations()->create(['site_id' => $site->id, 'locale_id' => $english->id, 'name' => 'Native brand-new', 'slug' => 'native', 'path' => '/native']);
    PublicSearchIndex::updateOrCreate(['page_id' => $native->id, 'locale_id' => $english->id], ['site_id' => $site->id, 'title' => 'Native brand-new', 'content' => 'Onlyen native description', 'url' => '/old-mount/native', 'indexed_at' => now()]);
    $search = app(PublicSearchQuery::class);
    $first = $search->search($site, $english, 'brand-new', 1);
    $this->assertSame(2, $first->total());
    $this->assertSame('/native', $first->items()[0]->url);
    Paginator::currentPageResolver(fn () => 2);
    $second = $search->search($site, $english, 'brand-new', 1);
    $this->assertSame(2, $second->total());
    $this->assertSame('/library/brand-new', $second->items()[0]->url);
    Paginator::currentPageResolver(fn () => 3);
    $this->assertCount(0, $search->search($site, $english, 'brand-new', 1)->items());
    Paginator::currentPageResolver(fn () => 1);
    // All terms may match separate fields; source-specific alt text is not indexed.
    $this->assertSame(2, $search->search($site, $english, 'brand-new Onlyen')->total());
    $this->assertSame(0, $search->search($site, $english, 'Second image')->total());
  }

  #[Test]
  public function mixed_search_pagination_handles_bucket_boundaries_and_large_page_numbers(): void
  {
    [$site, $page, $rule] = $this->prepared();
    $this->activate($rule);
    $english = Locale::where('code', 'en')->firstOrFail();
    foreach (range(1, 4) as $number) {
      $native = Page::create(['site_id' => $site->id, 'slug' => 'native-'.$number, 'status' => 'published']);
      $native->translations()->create(['site_id' => $site->id, 'locale_id' => $english->id, 'name' => 'Onlyen '.$number, 'slug' => 'native-'.$number, 'path' => '/native-'.$number]);
      PublicSearchIndex::updateOrCreate(['page_id' => $native->id, 'locale_id' => $english->id], ['site_id' => $site->id, 'title' => 'Onlyen '.$number, 'content' => 'Native', 'url' => '/native-'.$number, 'indexed_at' => now()]);
    }
    $urls = [];
    foreach (range(1, 3) as $number) {
      Paginator::currentPageResolver(fn () => $number);
      $results = app(PublicSearchQuery::class)->search($site, $english, 'Onlyen', 2);
      $this->assertSame(6, $results->total());
      $this->assertCount(2, $results->items());
      $urls = [...$urls, ...collect($results->items())->pluck('url')->all()];
    }
    $this->assertSame(['/native-1', '/native-2', '/native-3', '/native-4', '/library/brand-new', '/library/second'], $urls);
    Paginator::currentPageResolver(fn () => 1000000);
    $this->assertCount(0, app(PublicSearchQuery::class)->search($site, $english, 'Onlyen', 2)->items());
    Paginator::currentPageResolver(fn () => 1);
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
    $fields = ['id' => 'text', 'slug' => 'text', 'title' => 'text', 'summary' => 'text', 'image' => 'url', 'alt' => 'text'];
    $registry = new PluginRegistry(['library' => $active]);
    $registry->register(PluginDefinition::make('library')->contentSources([
      ContentSourceDefinition::collection('library::books')->standard(ContentSourceContract::languages(), 'Public books', 'Library API', 'id')->resolver(RoutingPublicationFixture::class)->fields($fields)->publishesPages('library::book', 'slug', 'title', 'summary', 'image'),
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

 protected $table = 'users';

 public function getForeignKey()
 {
   return 'user_id';
 }
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
    $summary = match (true) {
      self::$mode === 'turkish_case' && $context->locale === 'tr' => 'İhtiyacınız için IŞIK',
      self::$mode === 'german_case' && $context->locale === 'de' => 'Straße und Äpfel',
      default => 'Translated description Only'.$context->locale,
    };

    return ['_locale' => self::$mode === 'wrong_locale' && $context->locale === 'tr' ? 'en' : $context->locale, 'id' => $recordKey, 'slug' => $recordKey, 'title' => self::$mode === 'turkish_case' && $recordKey === 'brand-new' ? 'Memberships' : $recordKey.' '.$context->locale, 'summary' => $summary, 'image' => '/images/'.$recordKey.'.png', 'alt' => 'Second image '.$context->locale];
  }

  public function options(ContentSourceContext $context): array
  {
    return [];
  }

  public function publicationPage(?string $cursor, int $limit, ContentSourceContext $context): ContentPublicationPage
  {
    $entries = [['key' => 'second', 'label' => 'Second book'], ['key' => 'brand-new', 'label' => 'New book']];
    $entries = match (self::$mode) {
      'missing' => [], 'withdraw_second' => [$entries[1]], default => $entries,
    };

    return new ContentPublicationPage($entries, null, count($entries));
  }

  public function publicationKey(string $slug, ContentSourceContext $context): ?string
  {
    if (self::$mode === 'unavailable') {
    throw new \RuntimeException('Upstream failure');
    }

    return self::$mode === 'missing' || (self::$mode === 'withdraw_second' && $slug === 'second') || $slug === 'unknown' ? null : $slug;
  }

  public function publicationRecord(string $key, ContentSourceContext $context): ContentPublicationRecord
  {
    self::$calls[] = $context->locale;

    return match (self::$mode) {
    'missing' => ContentPublicationRecord::missing(), 'unavailable' => ContentPublicationRecord::unavailable(), default => ContentPublicationRecord::available($this->resolve($key, $context))
    };
  }
}
