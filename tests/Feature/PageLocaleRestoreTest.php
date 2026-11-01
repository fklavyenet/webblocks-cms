<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Http\Controllers\Admin\PageController;
use WebBlocks\Cms\Http\Controllers\Admin\PageRevisionController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRevisionController;
use WebBlocks\Cms\Http\Requests\Admin\PagePreviewRequest;
use WebBlocks\Cms\Http\Requests\Admin\PageRevisionScopeRequest;
use WebBlocks\Cms\Http\Requests\InternalContentApi\ApplyPageRevisionCandidateRequest;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Pages\PageLocaleRestoreException;
use WebBlocks\Cms\Support\Pages\PageLocaleRestorePlanner;
use WebBlocks\Cms\Support\Pages\PageLocaleRestoreSchema;
use WebBlocks\Cms\Support\Pages\PageRevisionCandidateManager;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class PageLocaleRestoreTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $database = getenv('WEBBLOCKS_IDENTITY_TEST_DATABASE');
    if ($database !== false && $database !== '') {
      if (! preg_match('/^wb_identity_test_[a-f0-9]+$/D', $database)) {
        throw new LogicException('Locale restore tests require an isolated database name.');
      }
      $app['config']->set('database.default', 'mysql');
      $app['config']->set('database.connections.mysql', ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => $database, 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]);
    }
  }

  protected function defineDatabaseMigrations(): void
  {
    $mysql = $this->app['config']->get('database.default') === 'mysql';
    if ($mysql) {
      Schema::dropAllTables();
    }
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
    if ($mysql) {
      array_shift($this->cachedTestMigratorProcessors);
      $this->beforeApplicationDestroyed(fn () => DB::disconnect());
    }
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(CmsSqlTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
    view()->share('errors', new ViewErrorBag);
    $this->mock(AdminAuthorization::class)->shouldReceive('abortUnlessSiteAccess')->andReturnNull();
  }

  #[Test]
  public function full_language_apply_preserves_other_locales_row_ids_structure_media_and_status(): void
  {
    $f = $this->fixture();
    extract($f);
    $before = $this->state($page);
    $ids = $this->rowIds($page);
    $candidate = $this->candidate($f);
    $this->assertSame($before, $this->state($page), 'Preparing must not mutate the source.');
    $this->assertSame('page_locale', $candidate->restore_scope);
    $this->assertSame($en->id, $candidate->restore_locale_id);
    $preview = $candidate->candidatePage;
    $this->assertSame('Old English', $preview->translations()->where('locale_id', $en->id)->value('name'));
    $this->assertSame('Current German', $preview->translations()->where('locale_id', $de->id)->value('name'));
    $this->assertSame(3, $preview->translations()->count());
    $this->assertSame('current', $preview->blocks()->where('revision_key', $blocks['text']->revision_key)->value('variant'));
    $this->assertSame('Current plugin', $preview->blocks()->where('revision_key', $blocks['plugin']->revision_key)->firstOrFail()->pluginTranslations()->value('value'));
    $this->assertSame('Current HTML', $preview->blocks()->where('revision_key', $blocks['html']->revision_key)->firstOrFail()->textTranslations()->where('locale_id', $en->id)->value('content'));
    app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
    $this->assertSame('Old English', $page->translations()->where('locale_id', $en->id)->value('name'));
    $this->assertSame($ids, $this->rowIds($page));
    $this->assertSame(Page::STATUS_PUBLISHED, $page->fresh()->status);
    $this->assertSame('Old English', $page->fresh()->title);
    foreach (['text', 'button', 'image', 'contact'] as $family) {
      [$relation, $field] = $this->family($family);
      $this->assertSame('Old '.$family, $blocks[$family]->{$relation}()->where('locale_id', $en->id)->value($field));
      $this->assertSame('Current German '.$family, $blocks[$family]->{$relation}()->where('locale_id', $de->id)->value($field));
      $this->assertSame('Turkish '.$family, $blocks[$family]->{$relation}()->where('locale_id', $tr->id)->value($field));
    }
    foreach ($items as $index => $item) {
      $this->assertSame('Old gallery '.$index, $item->galleryItemTranslations()->where('locale_id', $en->id)->value('caption'));
      $this->assertSame('Current German gallery '.$index, $item->galleryItemTranslations()->where('locale_id', $de->id)->value('caption'));
    }
    $after = $this->state($page);
    $this->assertSame(Arr::except($before, ['page', 'translations', 'blocks']), Arr::except($after, ['page', 'translations', 'blocks']));
    $this->assertSame(Arr::except($before['page'], ['title', 'slug']), Arr::except($after['page'], ['title', 'slug']));
    foreach ($after['blocks'] as $index => $block) {
      $this->assertSame(Arr::except($before['blocks'][$index], array_merge(array_keys(PageLocaleRestorePlanner::FAMILIES), ['block_media'])), Arr::except($block, array_merge(array_keys(PageLocaleRestorePlanner::FAMILIES), ['block_media'])));
    }
    $safety = $page->revisions()->where('label', 'Pre-restore safety snapshot')->firstOrFail();
    $this->assertSame('Current English', collect($safety->snapshot['translations'])->firstWhere('locale_id', $en->id)['name']);
    $this->assertSame($revision->id, $page->revisions()->where('label', 'Language restored')->firstOrFail()->restored_from_page_revision_id);
    $this->assertSame('applied', $candidate->fresh()->status);
    $this->assertNull($candidate->fresh()->candidate_page_id);
  }

  #[Test]
  public function metadata_only_works_with_changed_structure_and_keeps_current_native_content(): void
  {
    $f = $this->fixture();
    extract($f);
    $new = $page->blocks()->create(['type' => 'plain_text', 'slot_type_id' => $blocks['text']->slot_type_id, 'source_type' => 'static', 'sort_order' => 99]);
    $this->assertContains('structure_incompatible', array_column($this->plan($f)['issues'], 'code'));
    $candidate = $this->candidate($f, 'page_fields');
    $this->assertTrue($candidate->candidatePage->blocks()->where('revision_key', $new->revision_key)->exists());
    app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
    $this->assertSame('Old English', $page->translations()->where('locale_id', $en->id)->value('name'));
    $this->assertSame('Current text', $blocks['text']->textTranslations()->where('locale_id', $en->id)->value('title'));
    $this->assertNotNull($new->fresh());
  }

  #[Test]
  public function a_scoped_safety_restore_preserves_subsequent_other_language_changes(): void
  {
    $f = $this->fixture();
    extract($f);
    app(PageRevisionCandidateManager::class)->apply($this->candidate($f), $actor, [], true);
    $safety = $page->revisions()->where('label', 'Pre-restore safety snapshot')->firstOrFail();
    $page->translations()->where('locale_id', $de->id)->update(['name' => 'Later German']);
    $blocks['text']->textTranslations()->where('locale_id', $de->id)->update(['title' => 'Later German block']);
    $undo = app(PageRevisionCandidateManager::class)->create($page->fresh(), $safety, $actor, 'page_locale', $en->id);
    app(PageRevisionCandidateManager::class)->apply($undo, $actor, [], true);
    $this->assertSame('Current English', $page->translations()->where('locale_id', $en->id)->value('name'));
    $this->assertSame('Current text', $blocks['text']->textTranslations()->where('locale_id', $en->id)->value('title'));
    $this->assertSame('Later German', $page->translations()->where('locale_id', $de->id)->value('name'));
    $this->assertSame('Later German block', $blocks['text']->textTranslations()->where('locale_id', $de->id)->value('title'));
  }

  #[Test]
  public function explicit_null_and_empty_values_restore_but_absent_fields_and_rows_do_not_clear(): void
  {
    $f = $this->fixture();
    extract($f);
    $snapshot = $revision->snapshot;
    $snapshot['translations'][0]['seo_title'] = null;
    $textIndex = array_search($blocks['text']->id, array_column($snapshot['blocks'], 'snapshot_id'), true);
    $snapshot['blocks'][$textIndex]['text_translations'][0]['subtitle'] = '';
    $f['revision'] = $this->revision($page, $snapshot);
    app(PageRevisionCandidateManager::class)->apply($this->candidate($f), $actor, [], true);
    $this->assertNull($page->translations()->where('locale_id', $en->id)->value('seo_title'));
    $this->assertSame('', $blocks['text']->textTranslations()->where('locale_id', $en->id)->value('subtitle'));
    unset($snapshot['blocks'][$textIndex]['text_translations'][0]['subtitle']);
    $f['revision'] = $this->revision($page, $snapshot);
    $this->assertContains('incomplete_snapshot', array_column($this->plan($f)['issues'], 'code'));
    $snapshot['blocks'][$textIndex]['text_translations'] = [];
    $f['revision'] = $this->revision($page, $snapshot);
    $this->assertContains('missing_translation', array_column($this->plan($f)['issues'], 'code'));
  }

  #[Test]
  public function missing_current_selected_locale_rows_can_be_recreated_from_complete_history(): void
  {
    $f = $this->fixture();
    extract($f);
    $page->translations()->where('locale_id', $en->id)->delete();
    $blocks['image']->imageTranslations()->where('locale_id', $en->id)->delete();
    $items[0]->galleryItemTranslations()->where('locale_id', $en->id)->delete();
    app(PageRevisionCandidateManager::class)->apply($this->candidate($f), $actor, [], true);
    $this->assertSame('Old English', $page->translations()->where('locale_id', $en->id)->value('name'));
    $this->assertSame('Old image', $blocks['image']->imageTranslations()->where('locale_id', $en->id)->value('caption'));
    $this->assertSame('Old gallery 0', $items[0]->galleryItemTranslations()->where('locale_id', $en->id)->value('caption'));
  }

  #[Test]
  public function disabled_or_historically_absent_locales_and_foreign_history_are_refused(): void
  {
    $f = $this->fixture();
    extract($f);
    $this->assertSame('historical_locale_missing', app(PageLocaleRestorePlanner::class)->plan($page, $revision, 'page_fields', $tr->id)['issues'][0]['code']);
    $page->site->locales()->updateExistingPivot($en->id, ['is_enabled' => false]);
    $this->assertSame('locale_disabled', $this->plan($f)['issues'][0]['code']);
    $page->site->locales()->updateExistingPivot($en->id, ['is_enabled' => true]);
    $other = Page::create(['site_id' => $page->site_id, 'title' => 'Other', 'slug' => 'other']);
    $this->assertSame('invalid_scope', app(PageLocaleRestorePlanner::class)->plan($other, $revision, 'page_fields', $de->id)['issues'][0]['code']);
  }

  #[Test]
  public function restoring_the_default_language_cannot_change_other_languages_through_fallback(): void
  {
    $f = $this->fixture();
    extract($f);
    $blocks['text']->textTranslations()->where('locale_id', $de->id)->delete();
    $plan = $this->plan($f);
    $this->assertFalse($plan['ok']);
    $issue = collect($plan['issues'])->firstWhere('code', 'fallback_impact');
    $this->assertSame([$de->id], $issue['locale_ids']);
    $this->assertTrue($this->plan($f, 'page_fields')['ok']);
  }

  public static function staleEdits(): array
  {
    return [['page'], ['block'], ['gallery'], ['locale'], ['revision'], ['preview']];
  }

  #[Test]
  #[DataProvider('staleEdits')]
  public function edits_after_preview_require_a_new_preview_without_partial_writes(string $edit): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = $this->candidate($f);
    match ($edit) {
      'page' => $page->translations()->where('locale_id', $de->id)->update(['seo_title' => 'Changed after preview']),
      'block' => $blocks['text']->textTranslations()->where('locale_id', $en->id)->update(['title' => 'Changed after preview']),
      'gallery' => $items[0]->galleryItemTranslations()->where('locale_id', $de->id)->update(['caption' => 'Changed after preview']),
      'locale' => $page->site->locales()->updateExistingPivot($tr->id, ['is_enabled' => false]),
      'revision' => $revision->forceFill(['snapshot' => array_replace_recursive($revision->snapshot, ['translations' => [0 => ['seo_title' => 'Changed history']]])])->save(),
      'preview' => $candidate->candidatePage->translations()->where('locale_id', $en->id)->update(['name' => 'Tampered preview']),
    };
    $before = $this->state($page);
    $count = $page->revisions()->count();
    try {
      app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
      $this->fail('Expected stale preview refusal.');
    } catch (PageLocaleRestoreException $exception) {
      $this->assertSame($edit === 'preview' ? 'preview_changed' : 'stale', $exception->issues[0]['code']);
    }
    $this->assertSame($before, $this->state($page));
    $this->assertSame($count, $page->revisions()->count());
    $this->assertSame('ready', $candidate->fresh()->status);
  }

  #[Test]
  public function confirmation_is_enforced_by_the_domain_and_api_and_scope_cannot_be_overridden(): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = $this->candidate($f);
    try {
      app(PageRevisionCandidateManager::class)->apply($candidate, $actor);
      $this->fail('Expected explicit language confirmation.');
    } catch (PageLocaleRestoreException $exception) {
      $this->assertSame('confirmation_required', $exception->issues[0]['code']);
    }
    foreach ([['restore_scope' => 'full_page'], ['locale_id' => $de->id], ['confirm_locale_restore' => false], ['confirm_locale_restore' => 'no']] as $input) {
      $this->assertTrue(Validator::make($input, (new ApplyPageRevisionCandidateRequest)->rules())->fails());
    }
    $controller = app(InternalPageRevisionController::class);
    $result = $controller->apply($this->request(ApplyPageRevisionCandidateRequest::class, [], $actor), $page, $candidate);
    $this->assertSame(409, $result->getStatusCode());
    $this->assertSame('confirmation_required', $result->getData(true)['issues'][0]['code']);
    $result = $controller->apply($this->request(ApplyPageRevisionCandidateRequest::class, ['confirm_locale_restore' => true], $actor), $page, $candidate);
    $this->assertSame(200, $result->getStatusCode());
    $this->assertSame('Old English', $page->translations()->where('locale_id', $en->id)->value('name'));
    $this->expectException(\RuntimeException::class);
    app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
  }

  #[Test]
  public function page_address_conflicts_reserved_paths_and_missing_social_media_are_blocked(): void
  {
    $f = $this->fixture();
    extract($f);
    $other = Page::create(['site_id' => $page->site_id, 'title' => 'Other', 'slug' => 'other']);
    $other->translations()->where('locale_id', $en->id)->update(['slug' => 'old-service', 'path' => '/old-service']);
    $this->assertSame('invalid_page_fields', $this->plan($f)['issues'][0]['code']);
    $other->delete();
    foreach ([['path', '/webadmin'], ['og_image_media_id', 9999999]] as [$field, $value]) {
      $snapshot = $revision->snapshot;
      $snapshot['translations'][0][$field] = $value;
      $f['revision'] = $this->revision($page, $snapshot);
      $this->assertSame($field === 'path' ? 'invalid_page_fields' : 'references_missing', $this->plan($f)['issues'][0]['code']);
    }
  }

  #[Test]
  public function required_contact_consent_failure_rolls_back_all_rows_snapshots_and_candidate_state(): void
  {
    $f = $this->fixture();
    extract($f);
    $blocks['contact']->forceFill(['settings' => ['consent_required' => true]])->save();
    $candidate = $this->candidate($f);
    $before = $this->state($page);
    $count = $page->revisions()->count();
    try {
      app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
      $this->fail('A published consent-required form must retain valid consent copy.');
    } catch (ValidationException $exception) {
      $this->assertNotEmpty($exception->errors());
    }
    $this->assertSame($before, $this->state($page));
    $this->assertSame($count, $page->revisions()->count());
    $this->assertSame('ready', $candidate->fresh()->status);
  }

  #[Test]
  public function review_prepare_and_discard_expose_the_bound_scope_and_do_not_mutate_source(): void
  {
    $f = $this->fixture();
    extract($f);
    $before = $this->state($page);
    $request = $this->request(PageRevisionScopeRequest::class, ['restore_scope' => 'page_locale', 'locale_id' => $en->id], $actor);
    $controller = app(InternalPageRevisionController::class);
    $show = $controller->show($request, $page, $revision)->getData(true);
    $this->assertTrue($show['locale_plan']['ok']);
    $this->assertSame('http://localhost/old-service', $show['locale_plan']['proposed_url']);
    $this->assertArrayNotHasKey('projected_snapshot', $show['locale_plan']);
    $prepared = $controller->prepare($request, $page, $revision)->getData(true);
    $this->assertSame('page_locale', $prepared['candidate']['restore_scope']);
    $this->assertStringContainsString('locale=en', $prepared['candidate']['preview_url']);
    $this->assertSame($en->id, $prepared['candidate']['locale_id']);
    $this->assertFalse($prepared['candidate']['translation_removal_confirmation_required']);
    $candidate = PageRevisionCandidate::findOrFail($prepared['candidate']['id']);
    app(PageRevisionCandidateManager::class)->discard($candidate);
    $this->assertSame('discarded', $candidate->fresh()->status);
    $this->assertSame($before, $this->state($page));
  }

  #[Test]
  public function scoped_review_and_confirmation_render_in_all_six_panel_languages(): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = $this->candidate($f);
    $localePlan = app(PageLocaleRestorePlanner::class)->publicPlan($this->plan($f));
    $restoreScope = 'page_locale';
    $restoreLocaleId = $en->id;
    $restoreLocales = $page->site->enabledLocales()->get();
    $inspection = ['translation_removals' => []];
    foreach (['en', 'tr', 'de', 'es', 'it', 'fr'] as $language) {
      $text = fn ($key, $replace = []) => app(CmsTranslator::class)->admin('page_revisions.'.$key, $language, $replace);
      $data = compact('page', 'revision', 'candidate', 'localePlan', 'restoreScope', 'restoreLocaleId', 'restoreLocales', 'inspection', 'text');
      $html = view('webblocks-cms::admin.pages.revisions.partials.locale-restore-review', $data)->render().view('webblocks-cms::admin.pages.revisions.partials.apply-confirmation', $data)->render();
      $this->assertStringContainsString('name="restore_scope"', $html);
      $this->assertStringContainsString('name="confirm_locale_restore" type="checkbox" value="1" required', $html);
      $this->assertStringContainsString('Old English', $html);
      $this->assertStringContainsString('Current English', $html);
      $this->assertStringNotContainsString('name="confirm_translation_removal"', $html);
      $this->assertStringNotContainsString('page_revisions.locale_restore.', $html);
    }
  }

  #[Test]
  public function scope_schema_is_typed_idempotent_and_defaults_existing_candidates_to_full_page(): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = app(PageRevisionCandidateManager::class)->create($page, $revision, $actor);
    $this->assertSame('full_page', $candidate->restore_scope);
    $this->assertNull($candidate->restore_locale_id);
    PageLocaleRestoreSchema::ensure();
    PageLocaleRestoreSchema::ensure();
    $this->assertTrue(Schema::hasColumns('wbcms_page_revision_candidates', ['restore_scope', 'restore_locale_id', 'source_fingerprint', 'revision_fingerprint', 'preview_fingerprint']));
    $this->assertSame('full_page', $candidate->fresh()->restore_scope);
  }

  #[Test]
  public function a_non_default_language_restores_without_changing_english(): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = app(PageRevisionCandidateManager::class)->create($page, $revision, $actor, 'page_locale', $de->id);
    $url = $candidate->previewUrl(false);
    $this->assertStringContainsString('locale=de', $url);
    $request = PagePreviewRequest::create($url);
    $route = new Route('GET', 'webadmin/pages/{page}/preview', []);
    $route->bind($request);
    $route->setParameter('page', $candidate->candidatePage);
    $request->setRouteResolver(fn () => $route);
    $request->attributes->set('cms_internal_preview', true);
    $request->setContainer($this->app)->setRedirector($this->app['redirect']);
    $this->app->instance('request', $request);
    $request->validateResolved();
    $html = app(PageController::class)->preview($candidate->candidatePage, app(PublicPagePresenter::class))->getContent();
    $this->assertStringContainsString('<html lang="de">', $html);
    $this->assertStringContainsString('Old German', $html);
    $this->assertStringContainsString('German gallery 0', $html);
    $this->assertStringNotContainsString('Current gallery 0', $html);

    app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
    $this->assertSame('Old German', $page->translations()->where('locale_id', $de->id)->value('name'));
    $this->assertSame('German text', $blocks['text']->textTranslations()->where('locale_id', $de->id)->value('title'));
    $this->assertSame('Current English', $page->translations()->where('locale_id', $en->id)->value('name'));
    $this->assertSame('Current text', $blocks['text']->textTranslations()->where('locale_id', $en->id)->value('title'));
  }

  #[Test]
  public function native_candidate_preview_renders_current_exclusions_both_gallery_items_and_proposed_path(): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = $this->candidate($f);
    $before = $this->state($page);
    $previewPage = $candidate->candidatePage;
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
    $this->assertStringContainsString('Language restore', $html);
    $this->assertStringContainsString('/old-service', $html);
    $this->assertStringContainsString('Old gallery 0', $html);
    $this->assertStringContainsString('Old gallery 1', $html);
    $this->assertStringContainsString('Current HTML', $html);
    $this->assertStringNotContainsString('Translations will be removed', $html);
    $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    $this->assertSame($before, $this->state($page));
  }

  #[Test]
  public function full_admin_review_renders_the_selected_scope_without_full_restore_warnings(): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = $this->candidate($f);
    $request = $this->request(PageRevisionScopeRequest::class, ['restore_scope' => 'page_locale', 'locale_id' => $en->id], $actor);
    $this->app->instance('request', $request);
    $this->app['auth']->setUser($actor);
    $this->app->forgetInstance(AdminAuthorization::class);
    $html = app(PageRevisionController::class)->show($request, $page, $revision)->render();
    $this->assertStringContainsString('name="restore_scope"', $html);
    $this->assertStringContainsString('Old English', $html);
    $this->assertStringContainsString('name="confirm_locale_restore"', $html);
    $this->assertStringNotContainsString('Translations will be removed', $html);
  }

  #[Test]
  public function shared_slot_edits_invalidate_the_preview_without_rewinding_shared_content(): void
  {
    $f = $this->fixture();
    extract($f);
    $shared = SharedSlot::create(['site_id' => $page->site_id, 'name' => 'Shared header', 'handle' => 'shared-header', 'slot_name' => 'main', 'is_active' => true]);
    $source = app(SharedSlotSourcePageManager::class)->ensureFor($shared);
    $sourceBlock = $source->blocks()->create(['type' => 'plain_text', 'source_type' => 'static', 'slot_type_id' => $source->slots()->value('slot_type_id'), 'status' => 'published']);
    $sourceBlock->textTranslations()->create(['locale_id' => $en->id, 'title' => 'Current shared title']);
    $page->slots()->firstOrFail()->forceFill(['source_type' => 'shared_slot', 'shared_slot_id' => $shared->id])->save();
    $candidate = $this->candidate($f);
    $sourceBlock->textTranslations()->where('locale_id', $en->id)->update(['title' => 'Later shared title']);
    $before = $this->state($page);
    try {
      app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
      $this->fail('A changed shared dependency must invalidate the preview.');
    } catch (PageLocaleRestoreException $exception) {
      $this->assertSame('stale', $exception->issues[0]['code']);
    }
    $this->assertSame($before, $this->state($page));
    $this->assertSame('Later shared title', $sourceBlock->textTranslations()->value('title'));
    app(PageRevisionCandidateManager::class)->discard($candidate);
    $candidate = $this->candidate($f);
    app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
    $this->assertSame('Later shared title', $sourceBlock->textTranslations()->value('title'));
  }

  #[Test]
  public function blocked_preparation_preserves_the_existing_preview_and_read_only_planning_writes_nothing(): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = $this->candidate($f);
    $page->blocks()->create(['type' => 'plain_text', 'source_type' => 'static', 'sort_order' => 99]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->plan($f);
    $writes = array_filter(DB::getQueryLog(), fn ($row) => preg_match('/^\s*(insert|update|delete|alter|create)\b/i', $row['query']));
    DB::disableQueryLog();
    $this->assertSame([], $writes);
    try {
      $this->candidate($f);
      $this->fail('Unsafe structure must refuse preparation.');
    } catch (PageLocaleRestoreException $exception) {
      $this->assertSame('structure_incompatible', $exception->issues[0]['code']);
    }
    $this->assertSame('ready', $candidate->fresh()->status);
    $this->assertNotNull($candidate->fresh()->candidatePage);
    $review = app(PageLocaleRestorePlanner::class)->reviewCandidate($candidate->fresh());
    $this->assertFalse($review['ok']);
  }

  #[Test]
  public function malformed_historical_rows_do_not_get_treated_as_empty_translations(): void
  {
    $f = $this->fixture();
    extract($f);
    foreach ([null, 'not a row list', [null], [['locale_id' => $en->id], ['locale_id' => $en->id]]] as $rows) {
      $snapshot = $revision->snapshot;
      $snapshot['translations'] = $rows;
      $bad = new PageRevision;
      $bad->page_id = $page->id;
      $bad->site_id = $page->site_id;
      $bad->snapshot = $snapshot;
      $plan = app(PageLocaleRestorePlanner::class)->plan($page, $bad, 'page_fields', $en->id);
      $this->assertFalse($plan['ok']);
      $this->assertSame('incomplete_snapshot', $plan['issues'][0]['code']);
    }
  }

  #[Test]
  public function update_migration_adds_scope_to_legacy_candidates_without_rewriting_history(): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = app(PageRevisionCandidateManager::class)->create($page, $revision, $actor);
    $hash = SnapshotContent::hash($revision->fresh()->snapshot);
    Schema::table('wbcms_page_revision_candidates', fn ($table) => $table->dropColumn(['restore_scope', 'restore_locale_id', 'source_fingerprint', 'revision_fingerprint', 'preview_fingerprint']));
    $this->assertFalse(app(PageRevisionCandidateManager::class)->tableExists());
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_11_01_130000_add_page_locale_restore_scope.php';
    $migration->up();
    $migration->up();
    $migration->down();
    $this->assertTrue(app(PageRevisionCandidateManager::class)->tableExists());
    $this->assertSame('full_page', $candidate->fresh()->restore_scope);
    $this->assertNull($candidate->fresh()->source_fingerprint);
    $this->assertSame($candidate->id, app(PageRevisionCandidateManager::class)->readyFor($page, $revision)->id);
    $this->assertSame($hash, SnapshotContent::hash($revision->fresh()->snapshot));
  }

  #[Test]
  public function scope_and_locale_selection_are_validated_before_preparation(): void
  {
    $f = $this->fixture();
    extract($f);
    foreach ([['restore_scope' => 'unknown'], ['restore_scope' => 'page_locale'], ['restore_scope' => 'page_fields', 'locale_id' => 0], ['restore_scope' => 'page_fields', 'locale_id' => 'en']] as $input) {
      try {
        $this->request(PageRevisionScopeRequest::class, $input, $actor);
        $this->fail('Expected scope validation failure.');
      } catch (ValidationException $exception) {
        $this->assertNotEmpty($exception->errors());
      }
    }
    $request = $this->request(PageRevisionScopeRequest::class, [], $actor);
    $this->assertSame('full_page', $request->restoreScope());
    $this->assertNull($request->restoreLocaleId());
  }

  #[Test]
  public function other_languages_keep_the_same_resolved_copy_and_gallery_markup(): void
  {
    $f = $this->fixture();
    extract($f);
    $resolve = fn () => app(BlockTranslationResolver::class)->resolve($blocks['gallery']->fresh(['blockMedia.media', 'blockMedia.galleryItemTranslations', 'textTranslations']), 'de', $page->site);
    $before = view('webblocks-cms::pages.partials.blocks.gallery', ['block' => $resolve()])->render();
    app(PageRevisionCandidateManager::class)->apply($this->candidate($f), $actor, [], true);
    $after = view('webblocks-cms::pages.partials.blocks.gallery', ['block' => $resolve()])->render();
    $this->assertSame($before, $after);
    $this->assertStringContainsString('German gallery heading', $after);
    $resolved = app(BlockTranslationResolver::class)->resolve($blocks['text']->fresh(), 'de', $page->site);
    $this->assertSame('Current German text', $resolved->title);
  }

  #[Test]
  public function field_level_legacy_fallback_is_blocked_even_when_the_other_language_has_a_row(): void
  {
    $f = $this->fixture();
    extract($f);
    $link = $page->blocks()->create(['type' => 'link-list', 'slot_type_id' => $blocks['text']->slot_type_id, 'source_type' => 'static', 'sort_order' => 99]);
    $link->textTranslations()->create(['locale_id' => $en->id, 'title' => 'Old fallback']);
    $link->textTranslations()->create(['locale_id' => $de->id, 'title' => null]);
    $link->textTranslations()->create(['locale_id' => $tr->id, 'title' => 'Turkish title']);
    $f['revision'] = app(PageRevisionManager::class)->capture($page->fresh());
    $link->textTranslations()->where('locale_id', $en->id)->update(['title' => 'Current fallback']);
    $plan = $this->plan($f);
    $issue = collect($plan['issues'])->firstWhere('code', 'fallback_impact');
    $this->assertFalse($plan['ok']);
    $this->assertSame([$de->id], $issue['locale_ids']);
    $this->assertSame(['title'], $issue['fields']);
  }

  #[Test]
  public function other_language_rows_keep_their_timestamps_as_well_as_content(): void
  {
    $f = $this->fixture();
    extract($f);
    $rows = function () use ($page, $de, $tr) {
      $locales = [$de->id, $tr->id];
      $result = ['page' => $page->translations()->whereIn('locale_id', $locales)->orderBy('id')->get()->map->getAttributes()->all()];
      foreach ($page->blocks()->orderBy('id')->get() as $block) {
        foreach ([...array_column(PageLocaleRestorePlanner::FAMILIES, 0), 'pluginTranslations'] as $relation) {
          $result['blocks'][$block->id][$relation] = $block->{$relation}()->whereIn('locale_id', $locales)->orderBy('id')->get()->map->getAttributes()->all();
        }
        foreach ($block->blockAssets()->orderBy('id')->get() as $item) {
          $result['gallery'][$item->id] = $item->galleryItemTranslations()->whereIn('locale_id', $locales)->orderBy('id')->get()->map->getAttributes()->all();
        }
      }

      return $result;
    };
    $candidate = $this->candidate($f);
    $before = $rows();
    $this->travel(3)->seconds();
    app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
    $this->assertSame($before, $rows());
  }

  public static function inactiveLocaleModes(): array
  {
    return [['page_fields', false], ['page_fields', true], ['page_locale', false], ['page_locale', true]];
  }

  #[Test]
  #[DataProvider('inactiveLocaleModes')]
  public function inactive_other_languages_stay_intact_through_preview_and_apply(string $scope, bool $global): void
  {
    $f = $this->fixture();
    extract($f);
    if ($global) {
      $de->update(['is_enabled' => false]);
    } else {
      $page->site->locales()->updateExistingPivot($de->id, ['is_enabled' => false]);
    }
    $beforePage = $page->translations()->where('locale_id', $de->id)->firstOrFail()->getAttributes();
    $beforeBlock = $blocks['text']->textTranslations()->where('locale_id', $de->id)->firstOrFail()->getAttributes();
    $beforeGallery = $items[0]->galleryItemTranslations()->where('locale_id', $de->id)->firstOrFail()->getAttributes();
    $candidate = $this->candidate($f, $scope);
    $this->assertSame('Current German', $page->translations()->where('locale_id', $de->id)->value('name'));
    $this->assertFalse($candidate->candidatePage->translations()->where('locale_id', $de->id)->exists());
    $this->assertSame(2, $candidate->candidatePage->translations()->count());
    $this->travel(3)->seconds();
    app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
    $this->assertSame('Old English', $page->translations()->where('locale_id', $en->id)->value('name'));
    $this->assertSame($beforePage, $page->translations()->where('locale_id', $de->id)->firstOrFail()->getAttributes());
    $this->assertSame($beforeBlock, $blocks['text']->textTranslations()->where('locale_id', $de->id)->firstOrFail()->getAttributes());
    $this->assertSame($beforeGallery, $items[0]->galleryItemTranslations()->where('locale_id', $de->id)->firstOrFail()->getAttributes());
    $safety = $page->revisions()->where('label', 'Pre-restore safety snapshot')->firstOrFail();
    $this->assertSame('Current German', collect($safety->snapshot['translations'])->firstWhere('locale_id', $de->id)['name']);
  }

  #[Test]
  public function non_default_legacy_field_fallback_cannot_change_another_language(): void
  {
    $f = $this->fixture();
    extract($f);
    $link = $page->blocks()->create(['type' => 'sidebar-footer', 'slot_type_id' => $blocks['text']->slot_type_id, 'source_type' => 'static', 'sort_order' => 99]);
    // No English row: the legacy renderer falls through to the first stored row.
    $link->textTranslations()->create(['locale_id' => $de->id, 'title' => 'Old German fallback']);
    $link->textTranslations()->create(['locale_id' => $tr->id, 'title' => null]);
    $saved = app(PageRevisionManager::class)->capture($page->fresh());
    $link->textTranslations()->where('locale_id', $de->id)->update(['title' => 'Current German fallback']);
    $resolved = app(BlockTranslationResolver::class)->resolve($link->fresh(), 'tr', $page->site);
    $this->assertStringContainsString('Current German fallback', view('webblocks-cms::pages.partials.blocks.sidebar-footer', ['block' => $resolved])->render());
    $plan = app(PageLocaleRestorePlanner::class)->plan($page, $saved, 'page_locale', $de->id);
    $this->assertFalse($plan['ok']);
    $issue = collect($plan['issues'])->firstWhere('code', 'fallback_impact');
    $this->assertContains($tr->id, $issue['locale_ids']);
    $this->assertSame(['title'], $issue['fields']);
    $this->assertTrue(app(PageLocaleRestorePlanner::class)->plan($page, $saved, 'page_fields', $de->id)['ok']);
  }

  #[Test]
  public function prepare_flash_and_discard_preserve_the_selected_language_context(): void
  {
    $f = $this->fixture();
    extract($f);
    $request = $this->request(PageRevisionScopeRequest::class, ['restore_scope' => 'page_locale', 'locale_id' => $de->id], $actor);
    $this->app->instance('request', $request);
    $this->app['auth']->setUser($actor);
    $controller = app(PageRevisionController::class);
    $prepared = $controller->prepareCandidate($request, $page, $revision);
    $this->assertStringContainsString('locale=de', $prepared->getSession()->get('status_action')['url']);
    $candidate = app(PageRevisionCandidateManager::class)->readyFor($page, $revision, 'page_locale', $de->id);
    $discarded = $controller->discardCandidate($page, $candidate);
    parse_str(parse_url($discarded->getTargetUrl(), PHP_URL_QUERY), $query);
    $this->assertSame('page_locale', $query['restore_scope']);
    $this->assertSame((string) $de->id, $query['locale_id']);
  }

  #[Test]
  public function page_asset_changes_without_a_parent_timestamp_invalidate_the_preview(): void
  {
    $f = $this->fixture();
    extract($f);
    $asset = $page->pageAssets()->create(['type' => 'css', 'path' => 'synthetic/original.css', 'load_position' => 'head', 'is_enabled' => true, 'sort_order' => 0]);
    $candidate = $this->candidate($f);
    $asset->update(['path' => 'synthetic/edited.css']);
    try {
      app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
      $this->fail('Asset changes require a fresh preview.');
    } catch (PageLocaleRestoreException $exception) {
      $this->assertSame('stale', $exception->issues[0]['code']);
    }
    $this->assertSame('Current English', $page->translations()->where('locale_id', $en->id)->value('name'));
    $this->assertSame('synthetic/edited.css', $asset->fresh()->path);
    $this->assertSame('ready', $candidate->fresh()->status);
  }

  #[Test]
  public function concurrent_asset_and_preview_edits_are_locked_during_apply(): void
  {
    if ($this->app['config']->get('database.default') !== 'mysql') {
      $this->markTestSkipped('Row lock contention is verified separately on the disposable MySQL-driver database.');
    }
    $f = $this->fixture();
    extract($f);
    $asset = $page->pageAssets()->create(['type' => 'css', 'path' => 'synthetic/original.css', 'load_position' => 'head', 'is_enabled' => true, 'sort_order' => 0]);
    $candidate = $this->candidate($f);
    $previewRow = $candidate->candidatePage->translations()->where('locale_id', $en->id)->firstOrFail();
    $this->app['config']->set('database.connections.locale_restore_racer', $this->app['config']->get('database.connections.mysql'));
    $racer = DB::connection('locale_restore_racer');
    $racer->statement('SET SESSION innodb_lock_wait_timeout = 1');
    $attempted = false;
    $blocked = [];
    DB::listen(function ($query) use (&$attempted, &$blocked, $racer, $asset, $previewRow): void {
      if ($attempted || ! preg_match('/^insert into [`"]?wbcms_page_revisions[`"]?/i', $query->sql)) {
        return;
      }
      $attempted = true;
      foreach (['asset' => ['wbcms_page_assets', $asset->id, ['path' => 'synthetic/raced.css']], 'preview' => ['wbcms_page_translations', $previewRow->id, ['name' => 'Raced preview']]] as $key => [$table, $id, $values]) {
        try {
          $racer->table($table)->where('id', $id)->update($values);
          $blocked[$key] = false;
        } catch (QueryException $exception) {
          $this->assertSame(1205, $exception->errorInfo[1]);
          $blocked[$key] = true;
        }
      }
    });
    try {
      app(PageRevisionCandidateManager::class)->apply($candidate, $actor, [], true);
    } finally {
      DB::disconnect('locale_restore_racer');
    }
    $this->assertTrue($attempted);
    $this->assertSame(['asset' => true, 'preview' => true], $blocked);
    $this->assertSame('synthetic/original.css', $asset->fresh()->path);
    $this->assertSame('Old English', $page->translations()->where('locale_id', $en->id)->value('name'));
  }

  public static function scopedRestoreRoles(): array
  {
    return [['editor', true, false], ['site_admin', false, false], ['site_admin', true, true]];
  }

  #[Test]
  #[DataProvider('scopedRestoreRoles')]
  public function scoped_api_restore_enforces_editor_and_site_boundaries(string $role, bool $assigned, bool $allowed): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = $this->candidate($f);
    $delegate = $this->cmsActor((new User)->forceFill(['role' => $role, 'is_active' => true]), $assigned ? [$page->site_id] : []);
    $this->app->forgetInstance(AdminAuthorization::class);
    $controller = app(InternalPageRevisionController::class);
    $before = $this->state($page);
    $request = $this->request(PageRevisionScopeRequest::class, ['restore_scope' => 'page_locale', 'locale_id' => $en->id], $delegate);
    foreach (['prepare', 'apply'] as $operation) {
      try {
        if ($operation === 'prepare') {
          $response = $controller->prepare($request, $page, $revision);
          if ($allowed) {
            $candidate = PageRevisionCandidate::findOrFail($response->getData(true)['candidate']['id']);
          }
        } else {
          $response = $controller->apply($this->request(ApplyPageRevisionCandidateRequest::class, ['confirm_locale_restore' => true], $delegate), $page, $candidate);
        }
        $this->assertTrue($allowed, 'Unauthorized '.$operation.' must be refused.');
        $this->assertSame($operation === 'prepare' ? 201 : 200, $response->getStatusCode());
      } catch (HttpException $exception) {
        $this->assertFalse($allowed);
        $this->assertSame(403, $exception->getStatusCode());
        $this->assertSame($before, $this->state($page));
        $this->assertSame('ready', $candidate->fresh()->status);
      }
    }
  }

  #[Test]
  public function a_scoped_candidate_cannot_be_applied_through_another_page_route(): void
  {
    $f = $this->fixture();
    extract($f);
    $candidate = $this->candidate($f);
    $other = Page::create(['site_id' => $page->site_id, 'title' => 'Other page', 'slug' => 'other-page']);
    $before = $this->state($page);
    try {
      app(InternalPageRevisionController::class)->apply($this->request(ApplyPageRevisionCandidateRequest::class, ['confirm_locale_restore' => true], $actor), $other, $candidate);
      $this->fail('A candidate is bound to its original page.');
    } catch (HttpException $exception) {
      $this->assertSame(404, $exception->getStatusCode());
    }
    $this->assertSame($before, $this->state($page));
    $this->assertSame('Other page', $other->fresh()->title);
    $this->assertSame('ready', $candidate->fresh()->status);
  }

  #[Test]
  public function non_default_gallery_restore_cannot_change_a_language_using_first_row_fallback(): void
  {
    $f = $this->fixture();
    extract($f);
    $items[0]->galleryItemTranslations()->where('locale_id', $en->id)->delete();
    $resolved = app(BlockTranslationResolver::class)->resolve($blocks['gallery']->fresh(['blockMedia.galleryItemTranslations']), 'en', $page->site);
    $this->assertSame('Current German gallery 0', $resolved->resolvedGalleryItemTranslation($items[0]->fresh())->caption);
    $plan = app(PageLocaleRestorePlanner::class)->plan($page, $revision, 'page_locale', $de->id);
    $this->assertFalse($plan['ok']);
    $issue = collect($plan['issues'])->first(fn ($issue) => $issue['code'] === 'fallback_impact' && $issue['owner_id'] === $items[0]->id && $issue['entity'] === 'gallery');
    $this->assertContains($en->id, $issue['locale_ids']);
    $this->assertTrue(app(PageLocaleRestorePlanner::class)->plan($page, $revision, 'page_fields', $de->id)['ok']);
  }

  private function fixture(): array
  {
    $site = Site::create(['name' => 'Synthetic studio', 'handle' => 'studio', 'is_primary' => true]);
    $en = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $de = Locale::create(['code' => 'de', 'name' => 'German', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$en->id => ['is_enabled' => true], $de->id => ['is_enabled' => true]]);
    $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Old English', 'slug' => 'old-service', 'status' => Page::STATUS_PUBLISHED]);
    $page->slots()->create(['slot_type_id' => $slot->id, 'sort_order' => 0, 'source_type' => 'page']);
    $blocks = [];
    foreach (['text' => 'plain_text', 'button' => 'button', 'image' => 'image', 'contact' => 'contact_form', 'gallery' => 'gallery', 'html' => 'html', 'external' => 'external-content', 'plugin' => 'plugin:sample/card'] as $key => $type) {
      $blocks[$key] = $page->blocks()->create(['type' => $type, 'slot_type_id' => $slot->id, 'source_type' => 'static', 'sort_order' => count($blocks), 'status' => 'published']);
      if (in_array($key, ['text', 'button', 'image', 'contact'], true)) {
        [$relation, $field] = $this->family($key);
        $blocks[$key]->{$relation}()->create(['locale_id' => $en->id, $field => 'Old '.$key]);
        $blocks[$key]->{$relation}()->create(['locale_id' => $de->id, $field => 'German '.$key]);
      } elseif (in_array($key, ['html', 'external'], true)) {
        $blocks[$key]->textTranslations()->create(['locale_id' => $en->id, 'content' => 'Old '.$key]);
      }
    }
    $blocks['plugin']->pluginTranslations()->create(['locale_id' => $en->id, 'field' => 'heading', 'value' => 'Old plugin']);
    $media = Media::create(['disk' => 'public', 'path' => 'synthetic/photo.jpg', 'filename' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'image', 'visibility' => 'public']);
    $items = [];
    foreach ([0, 1] as $index) {
      $items[$index] = $blocks['gallery']->blockAssets()->create(['media_id' => $media->id, 'role' => 'gallery_item', 'position' => $index]);
      foreach ([$en, $de] as $language) {
        $items[$index]->galleryItemTranslations()->create(['locale_id' => $language->id, 'caption' => ($language->id === $en->id ? 'Old' : 'German').' gallery '.$index]);
      }
    }
    $page->translations()->create(['locale_id' => $de->id, 'name' => 'Old German', 'slug' => 'service', 'path' => '/de/service']);
    $blocks['gallery']->textTranslations()->create(['locale_id' => $en->id, 'title' => 'Old gallery heading']);
    $blocks['gallery']->textTranslations()->create(['locale_id' => $de->id, 'title' => 'German gallery heading']);
    $actor = $this->cmsActor((new User)->forceFill(['role' => 'super_admin', 'is_active' => true]));
    $revision = app(PageRevisionManager::class)->capture($page->fresh(), $actor);
    $tr = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$tr->id => ['is_enabled' => true]]);
    $page->translations()->where('locale_id', $en->id)->update(['name' => 'Current English', 'slug' => 'current-service', 'path' => '/current-service', 'seo_title' => 'Current SEO']);
    $page->translations()->where('locale_id', $de->id)->update(['name' => 'Current German']);
    $page->translations()->create(['locale_id' => $tr->id, 'name' => 'Turkish page', 'slug' => 'service', 'path' => '/tr/service']);
    foreach (['text', 'button', 'image', 'contact'] as $family) {
      [$relation, $field] = $this->family($family);
      $blocks[$family]->{$relation}()->where('locale_id', $en->id)->update([$field => 'Current '.$family]);
      $blocks[$family]->{$relation}()->where('locale_id', $de->id)->update([$field => 'Current German '.$family, ...($family === 'contact' ? ['consent_label' => 'German consent'] : [])]);
      $blocks[$family]->{$relation}()->create(['locale_id' => $tr->id, $field => 'Turkish '.$family, ...($family === 'contact' ? ['consent_label' => 'Turkish consent'] : [])]);
    }
    $blocks['contact']->contactFormTranslations()->where('locale_id', $en->id)->update(['consent_label' => 'Current consent']);
    $blocks['text']->forceFill(['variant' => 'current'])->save();
    $blocks['html']->textTranslations()->where('locale_id', $en->id)->update(['content' => 'Current HTML']);
    $blocks['external']->textTranslations()->where('locale_id', $en->id)->update(['content' => 'Current external']);
    $blocks['plugin']->pluginTranslations()->update(['value' => 'Current plugin']);
    $blocks['gallery']->textTranslations()->where('locale_id', $en->id)->update(['title' => 'Current gallery heading']);
    $blocks['gallery']->textTranslations()->create(['locale_id' => $tr->id, 'title' => 'Turkish gallery heading']);
    foreach ($items as $index => $item) {
      $item->galleryItemTranslations()->where('locale_id', $en->id)->update(['caption' => 'Current gallery '.$index]);
      $item->galleryItemTranslations()->where('locale_id', $de->id)->update(['caption' => 'Current German gallery '.$index]);
      $item->galleryItemTranslations()->create(['locale_id' => $tr->id, 'caption' => 'Turkish gallery '.$index]);
    }
    $page = $page->fresh();

    return compact('page', 'revision', 'en', 'de', 'tr', 'blocks', 'items', 'actor');
  }

  private function family(string $family): array
  {
    return match ($family) {
      'text' => ['textTranslations', 'title'], 'button' => ['buttonTranslations', 'title'], 'image' => ['imageTranslations', 'caption'], 'contact' => ['contactFormTranslations', 'title'],
    };
  }

  private function plan(array $f, string $scope = 'page_locale'): array
  {
    return app(PageLocaleRestorePlanner::class)->plan($f['page']->fresh(), $f['revision']->fresh(), $scope, $f['en']->id);
  }

  private function candidate(array $f, string $scope = 'page_locale'): PageRevisionCandidate
  {
    return app(PageRevisionCandidateManager::class)->create($f['page']->fresh(), $f['revision']->fresh(), $f['actor'], $scope, $f['en']->id);
  }

  private function state(Page $page): array
  {
    return Arr::except(app(PageRevisionManager::class)->snapshotForInspection($page->fresh()), ['captured_at']);
  }

  private function rowIds(Page $page): array
  {
    $ids = ['pages' => $page->translations()->orderBy('id')->pluck('id')->all(), 'blocks' => $page->blocks()->orderBy('id')->pluck('id')->all()];
    foreach ($page->blocks()->orderBy('id')->get() as $block) {
      foreach (array_column(PageLocaleRestorePlanner::FAMILIES, 0) as $relation) {
        $ids[$block->id][$relation] = $block->{$relation}()->orderBy('id')->pluck('id')->all();
      }
      foreach ($block->blockAssets()->orderBy('id')->get() as $item) {
        $ids['gallery'][$item->id] = $item->galleryItemTranslations()->orderBy('id')->pluck('id')->all();
      }
    }

    return $ids;
  }

  private function revision(Page $page, array $snapshot): PageRevision
  {
    return PageRevision::create(['page_id' => $page->id, 'site_id' => $page->site_id, 'snapshot' => $snapshot, 'snapshot_hash' => SnapshotContent::hash($snapshot), 'snapshot_bytes' => strlen(json_encode($snapshot, JSON_THROW_ON_ERROR))]);
  }

  private function request(string $class, array $input, $actor)
  {
    $request = $class::create('/webadmin/api', 'POST', $input);
    $request->setContainer($this->app)->setRedirector($this->app['redirect']);
    $request->setLaravelSession($this->app['session']->driver());
    $request->setUserResolver(fn () => $actor);
    $request->validateResolved();

    return $request;
  }
}
