<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Admin\PageController;
use WebBlocks\Cms\Http\Controllers\Admin\PageRevisionController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRevisionController;
use WebBlocks\Cms\Http\Requests\Admin\ApplyPageRevisionCandidateRequest as AdminApplyRequest;
use WebBlocks\Cms\Http\Requests\Admin\PagePreviewRequest;
use WebBlocks\Cms\Http\Requests\InternalContentApi\ApplyPageRevisionCandidateRequest as ApiApplyRequest;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageRevisionCandidateManager;
use WebBlocks\Cms\Support\Pages\PageRevisionInspector;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Pages\TranslationRemovalConfirmationRequired;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class PageRevisionTranslationRemovalTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(CmsSqlTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
    $this->mock(AdminAuthorization::class)->shouldReceive('abortUnlessSiteAccess')->andReturnNull();
  }

  #[Test]
  public function direct_restore_and_candidate_apply_refuse_removal_without_mutating_any_state(): void
  {
    [$page, $revision, $german, $actor] = $this->fixture();
    $manager = app(PageRevisionCandidateManager::class);
    $candidate = $manager->create($page->fresh(), $revision, $actor);
    $before = $this->pageState($page);
    $revisionCount = $page->revisions()->count();

    foreach ([
      fn () => app(PageRevisionManager::class)->restore($page->fresh(), $revision, $actor),
      fn () => $manager->apply($candidate, $actor),
    ] as $restore) {
      try {
        $restore();
        $this->fail('Expected an explicit translation-removal confirmation.');
      } catch (TranslationRemovalConfirmationRequired $exception) {
        $this->assertSame([$german->id], array_column($exception->translations, 'locale_id'));
        $this->assertSame('de', $exception->translations[0]['code']);
        $this->assertSame('/de/service', $exception->translations[0]['path']);
      }
      $this->assertSame($before, $this->pageState($page));
      $this->assertSame($revisionCount, $page->revisions()->count());
      $this->assertSame(PageRevisionCandidate::STATUS_READY, $candidate->fresh()->status);
      $this->assertNotNull($candidate->fresh()->candidatePage);
    }
  }

  #[Test]
  public function exact_confirmation_allows_removal_and_retains_a_recoverable_safety_snapshot(): void
  {
    [$page, $revision, $german, $actor] = $this->fixture();
    $manager = app(PageRevisionCandidateManager::class);
    $candidate = $manager->create($page, $revision, $actor);
    $manager->apply($candidate, $actor, [$german->id]);

    $this->assertNull($page->translations()->where('locale_id', $german->id)->first());
    $this->assertSame('Original English', $page->fresh()->title);
    $this->assertSame(Page::STATUS_PUBLISHED, $page->fresh()->status);
    $this->assertSame(PageRevisionCandidate::STATUS_APPLIED, $candidate->fresh()->status);
    $this->assertNull($candidate->fresh()->candidate_page_id);
    $safety = $page->revisions()->where('label', 'Pre-restore safety snapshot')->firstOrFail();
    $translation = collect($safety->snapshot['translations'])->firstWhere('locale_id', $german->id);
    $this->assertSame('/de/service', $translation['path']);
    $this->assertSame('German SEO', $translation['seo_title']);
    $this->assertSame($revision->id, $page->revisions()->where('label', 'Revision restored')->firstOrFail()->restored_from_page_revision_id);
  }

  #[Test]
  public function same_translation_count_does_not_hide_a_replaced_language(): void
  {
    [$page, $revision, $german] = $this->fixture();
    $page->translations()->whereHas('locale', fn ($query) => $query->where('code', 'en'))->delete();
    $inspection = app(PageRevisionInspector::class)->inspect($page->fresh(), $revision);
    $this->assertSame(1, $inspection['comparison'][4]['current']);
    $this->assertSame(1, $inspection['comparison'][4]['selected']);
    $this->assertSame([$german->id], array_column($inspection['translation_removals'], 'locale_id'));
  }

  #[Test]
  public function confirmation_cannot_cover_an_additional_language_added_after_preview(): void
  {
    [$page, $revision, $german, $actor] = $this->fixture();
    $manager = app(PageRevisionCandidateManager::class);
    $candidate = $manager->create($page, $revision, $actor);
    // A direct translation write deliberately does not advance the page timestamp.
    $french = Locale::create(['code' => 'fr', 'name' => 'French', 'is_default' => false, 'is_enabled' => true]);
    $page->site->locales()->syncWithoutDetaching([$french->id => ['is_enabled' => true]]);
    $page->translations()->create(['locale_id' => $french->id, 'name' => 'French service', 'slug' => 'service', 'path' => '/fr/service']);

    try {
      $manager->apply($candidate, $actor, [$german->id]);
      $this->fail('A previous locale list must not authorize additional removals.');
    } catch (TranslationRemovalConfirmationRequired $exception) {
      $this->assertSame([$german->id, $french->id], array_column($exception->translations, 'locale_id'));
    }
    $this->assertSame(3, $page->translations()->count());
    $this->assertSame(PageRevisionCandidate::STATUS_READY, $candidate->fresh()->status);
  }

  #[Test]
  public function api_reports_impact_and_requires_both_explicit_consent_and_exact_locale_ids(): void
  {
    [$page, $revision, $german, $actor] = $this->fixture();
    $controller = app(InternalPageRevisionController::class);
    $request = Request::create('/webadmin/api');
    $request->setUserResolver(fn () => $actor);
    $prepared = $controller->prepare($request, $page, $revision);
    $payload = $prepared->getData(true)['candidate'];
    $this->assertTrue($payload['translation_removal_confirmation_required']);
    $this->assertSame([$german->id], array_column($payload['translation_removals'], 'locale_id'));
    $candidate = PageRevisionCandidate::findOrFail($payload['id']);
    $this->assertNotNull($page->translations()->where('locale_id', $german->id)->first());

    foreach ([[], ['translation_removal_locale_ids' => [$german->id]], ['confirm_translation_removal' => true], ['confirm_translation_removal' => true, 'translation_removal_locale_ids' => [$german->id + 100]]] as $input) {
      $response = $controller->apply($this->applyRequest(ApiApplyRequest::class, $input, $actor), $page, $candidate);
      $this->assertSame(409, $response->getStatusCode());
      $this->assertSame('translation_removal_confirmation_required', $response->getData(true)['code']);
      $this->assertSame([$german->id], array_column($response->getData(true)['translation_removals'], 'locale_id'));
      $this->assertNotNull($page->translations()->where('locale_id', $german->id)->first());
    }

    $response = $controller->apply($this->applyRequest(ApiApplyRequest::class, ['confirm_translation_removal' => true, 'translation_removal_locale_ids' => [$german->id]], $actor), $page, $candidate);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('applied', $response->getData(true)['status']);
  }

  #[Test]
  public function admin_preview_confirmation_alone_cannot_remove_a_translation(): void
  {
    [$page, $revision, $german, $actor] = $this->fixture();
    $candidate = app(PageRevisionCandidateManager::class)->create($page, $revision, $actor);
    $controller = app(PageRevisionController::class);
    $response = $controller->applyCandidate($this->applyRequest(AdminApplyRequest::class, ['confirm_apply_candidate' => '1', 'translation_removal_locale_ids' => [$german->id]], $actor), $page, $candidate);
    $this->assertTrue($response->getSession()->get('errors')->has('candidate'));
    $this->assertNotNull($page->translations()->where('locale_id', $german->id)->first());

    $controller->applyCandidate($this->applyRequest(AdminApplyRequest::class, ['confirm_apply_candidate' => '1', 'confirm_translation_removal' => '1', 'translation_removal_locale_ids' => [(string) $german->id]], $actor), $page, $candidate);
    $this->assertNull($page->translations()->where('locale_id', $german->id)->first());
  }

  public static function invalidConsents(): array
  {
    return [
      'false' => [['confirm_translation_removal' => false]],
      'string false' => [['confirm_translation_removal' => 'false']],
      'arbitrary string' => [['confirm_translation_removal' => 'remove']],
      'non-array locales' => [['translation_removal_locale_ids' => '1']],
      'non-integer locale' => [['translation_removal_locale_ids' => ['de']]],
      'duplicate locales' => [['translation_removal_locale_ids' => [1, 1]]],
    ];
  }

  #[Test]
  #[DataProvider('invalidConsents')]
  public function malformed_or_false_consent_is_rejected(array $input): void
  {
    $request = new ApiApplyRequest;
    $this->assertTrue(Validator::make($input, $request->rules())->fails());
  }

  #[Test]
  public function admin_still_requires_the_existing_preview_confirmation(): void
  {
    $request = new AdminApplyRequest;
    $this->assertTrue(Validator::make(['confirm_translation_removal' => true, 'translation_removal_locale_ids' => [1]], $request->rules())->fails());
  }

  #[Test]
  public function review_renders_affected_languages_and_an_unchecked_separate_confirmation(): void
  {
    [$page, $revision, $german, $actor] = $this->fixture();
    $candidate = app(PageRevisionCandidateManager::class)->create($page, $revision, $actor);
    $inspection = app(PageRevisionInspector::class)->inspect($page->fresh(), $revision);
    // Render the real warning and apply content without the unrelated admin shell.
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/pages/revisions/show.blade.php');
    $warningStart = strpos($source, "    @if (\$inspection['translation_removals'] !== [])");
    $warningEnd = strpos($source, '    @if ($candidate)', $warningStart);
    $modalStart = strpos($source, "            <p>{{ \$text('apply_candidate_warning'");
    $modalEnd = strpos($source, '        @endcomponent', $modalStart);
    $text = fn ($key, $replace = []) => app(CmsTranslator::class)->admin('page_revisions.'.$key, 'en', $replace);
    $html = app('blade.compiler')->render(substr($source, $warningStart, $warningEnd - $warningStart).substr($source, $modalStart, $modalEnd - $modalStart), compact('inspection', 'revision', 'text'));
    $this->assertStringContainsString('German (de)', $html);
    $this->assertStringContainsString('/de/service', $html);
    $this->assertStringContainsString('name="translation_removal_locale_ids[]" value="'.$german->id.'"', $html);
    $this->assertStringContainsString('name="confirm_translation_removal" type="checkbox" value="1" required', $html);
    $this->assertStringNotContainsString('checked', $html);

    $inspection['translation_removals'] = [];
    $html = app('blade.compiler')->render(substr($source, $warningStart, $warningEnd - $warningStart).substr($source, $modalStart, $modalEnd - $modalStart), compact('inspection', 'revision', 'text'));
    $this->assertStringNotContainsString('name="confirm_translation_removal"', $html);
  }

  #[Test]
  public function removal_copy_is_available_in_all_six_panel_languages(): void
  {
    foreach (['en', 'tr', 'de', 'es', 'it', 'fr'] as $locale) {
      foreach (['translation_removal_title', 'translation_removal_help', 'confirm_translation_removal', 'translation_removal_required'] as $key) {
        $this->assertTrue(app('translator')->hasForLocale('webblocks-cms::admin.page_revisions.'.$key, $locale), $locale.':'.$key);
      }
    }
  }

  #[Test]
  public function rendered_candidate_preview_shows_removals_without_changing_the_source(): void
  {
    [$page, $revision, $german, $actor] = $this->fixture();
    $candidate = app(PageRevisionCandidateManager::class)->create($page, $revision, $actor);
    $before = $this->pageState($page);
    foreach ([[$candidate->candidatePage, true], [$page, false]] as [$previewPage, $hasRemovalWarning]) {
      $request = PagePreviewRequest::create('/webadmin/pages/'.$previewPage->id.'/preview');
      $route = new Route('GET', 'webadmin/pages/{page}/preview', []);
      $route->bind($request);
      $route->setParameter('page', $previewPage);
      $request->setRouteResolver(fn () => $route);
      $request->attributes->set('cms_internal_preview', true);
      $request->setContainer($this->app)->setRedirector($this->app['redirect']);
      $this->app->instance('request', $request);
      $request->validateResolved();
      $response = app(PageController::class)->preview($previewPage, app(PublicPagePresenter::class));
      $html = $response->getContent();
      if ($hasRemovalWarning) {
        $this->assertStringContainsString('German (de)', $html);
        $this->assertStringContainsString('/de/service', $html);
        $this->assertStringContainsString('Translations will be removed', $html);
      } else {
        $this->assertStringNotContainsString('Translations will be removed', $html);
      }
      $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }
    $this->assertSame($before, $this->pageState($page));
  }

  private function pageState(Page $page): array
  {
    $snapshot = app(PageRevisionManager::class)->snapshotForInspection($page->fresh());
    unset($snapshot['captured_at']);

    return $snapshot;
  }

  private function fixture(): array
  {
    $site = Site::create(['name' => 'Synthetic studio', 'handle' => 'synthetic-studio', 'is_primary' => true]);
    $english = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $german = Locale::create(['code' => 'de', 'name' => 'German', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true], $german->id => ['is_enabled' => true]]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Original English', 'slug' => 'service', 'page_type' => Page::TYPE_DEFAULT, 'status' => Page::STATUS_PUBLISHED]);
    $class = 'App\\Models\\User';
    $actor = $this->cmsActor((new $class)->forceFill(['role' => 'super_admin', 'is_active' => true]));
    $revision = app(PageRevisionManager::class)->capture($page->fresh(), $actor, 'Before German');
    $page->translations()->create(['locale_id' => $german->id, 'name' => 'German service', 'slug' => 'service', 'path' => '/de/service', 'seo_title' => 'German SEO']);

    return [$page->fresh(), $revision, $german, $actor];
  }

  private function applyRequest(string $class, array $input, $actor): AdminApplyRequest
  {
    $request = $class::create('/webadmin/api', 'POST', $input);
    $request->setContainer($this->app);
    $request->setRedirector($this->app['redirect']);
    $request->setLaravelSession($this->app['session']->driver());
    $request->setUserResolver(fn () => $actor);
    $request->validateResolved();

    return $request;
  }
}
