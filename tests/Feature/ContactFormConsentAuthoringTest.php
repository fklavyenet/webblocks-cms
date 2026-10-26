<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Blocks\UpdateBlockStatus;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPagePublishController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalSharedSlotController;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotBlock;
use WebBlocks\Cms\Models\SharedSlotRevision;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Contact\ContactConsentRequirements;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Pages\PageOwnedBlockPublisher;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotRevisionManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\Tests\TestCase;

class ContactFormConsentAuthoringTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(ConsentAuthoringTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
  }

  public static function emptyNotices(): iterable
  {
    yield 'omitted' => [[]];
    yield 'null' => [['consent_label' => null]];
    yield 'empty' => [['consent_label' => '']];
    yield 'whitespace' => [['consent_label' => " \t\n "]];
  }

  #[Test]
  #[DataProvider('emptyNotices')]
  public function native_creation_and_api_normalizers_refuse_required_forms_without_wording(array $copy): void
  {
    $block = $this->fixture();
    $block->contactFormTranslations()->delete();
    $this->rejects(fn () => $this->adminData(null, $block, ['consent_required' => true] + $copy), 'consent_label');
    $operations = app(InternalContentApiOperations::class);
    foreach ([['consent_required' => true], ['consent_required' => true, 'consent_label' => 'Legacy notice']] as $settings) {
      // An explicitly blank translation wins over the legacy settings alias.
      if ($copy === [] && isset($settings['consent_label'])) {
        continue;
      }
      $errors = $warnings = [];
      $operations->normalizeBlock(['type' => 'contact_form', 'settings' => $settings, 'translations' => $copy], 'block', null, $errors, $warnings);
      $this->assertContains(ContactConsentRequirements::CODE, array_column($errors, 'code'));
      $this->assertContains('block.translations.consent_label', array_column($errors, 'path'));
    }
    $response = app(InternalContentResourceController::class)->storeSlotBlock($this->jsonRequest(['type' => 'contact_form', 'settings' => ['consent_required' => true], 'translations' => $copy]), $block->page, 'main');
    $this->assertSame(422, $response->getStatusCode());
    $this->assertContains(ContactConsentRequirements::CODE, array_column($response->getData(true)['errors'], 'code'));
    $this->assertSame(0, PageRevision::count());
    $this->assertSame(1, Block::count());
  }

  #[Test]
  #[DataProvider('emptyNotices')]
  public function patch_and_native_edit_refuse_clearing_required_notices_without_mutation(array $copy): void
  {
    $block = $this->fixture();
    // Omission cannot repair an already-invalid legacy row.
    if ($copy === []) {
      $block->contactFormTranslations()->update(['consent_label' => null]);
    }
    $before = $block->contactFormTranslations()->first()->getAttributes();
    $this->rejects(fn () => $this->adminData($block, $block, $copy), 'consent_label');
    foreach ([$copy, ['contact_form' => $copy + ['title' => 'Changed']]] as $translations) {
      $response = app(InternalContentResourceController::class)->updateBlock($this->jsonRequest(['locale' => 'en', 'translations' => $translations]), $block->fresh());
      $this->assertSame(422, $response->getStatusCode(), $response->getContent());
      $this->assertSame(ContactConsentRequirements::CODE, $response->getData(true)['code']);
      $this->assertSame($before, $block->contactFormTranslations()->first()->getAttributes());
    }
  }

  #[Test]
  public function saves_preserve_omitted_notices_and_require_wording_for_new_locale_rows(): void
  {
    $block = $this->fixture();
    $controller = app(InternalContentResourceController::class);
    $response = $controller->updateBlock($this->jsonRequest(['translations' => ['title' => 'Updated']]), $block);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('I agree.', $block->contactFormTranslations()->first()->consent_label);
    $data = $this->adminData($block->fresh(), $block, ['title' => 'Updated']);
    $this->assertTrue(json_decode($data['settings'], true)['consent_required']);
    $response = $controller->updateBlock($this->jsonRequest(['locale' => 'tr', 'translations' => ['title' => 'İletişim']]), $block->fresh());
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame(1, $block->contactFormTranslations()->count());
    $this->rejects(fn () => $this->adminData($block->fresh(), $block, ['locale' => 'tr', 'consent_required' => false]), 'consent_label');
    $response = $controller->updateBlock($this->jsonRequest(['locale' => 'tr', 'translations' => ['title' => 'İletişim', 'consent_label' => 'Kabul ediyorum.']]), $block->fresh());
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('Kabul ediyorum.', $block->contactFormTranslations()->whereHas('locale', fn ($query) => $query->where('code', 'tr'))->value('consent_label'));
  }

  #[Test]
  public function disabling_the_requirement_allows_clearing_and_default_locale_toggle_is_honoured(): void
  {
    $block = $this->fixture();
    $data = $this->adminData($block, $block, ['locale' => 'en', 'consent_required' => false, 'consent_label' => '']);
    $this->assertFalse(json_decode($data['settings'], true)['consent_required']);
    $block->settings = json_decode($data['settings'], true);
    $block->save();
    $controller = app(InternalContentResourceController::class);
    $response = $controller->updateBlock($this->jsonRequest(['translations' => ['consent_label' => null]]), $block->fresh());
    $this->assertSame(200, $response->getStatusCode());
    $this->assertNull($block->contactFormTranslations()->first()->consent_label);
    $this->rejects(fn () => $this->adminData($block->fresh(), $block, ['locale' => 'en', 'consent_required' => true]), 'consent_label');
    $data = $this->adminData($block->fresh(), $block, ['locale' => 'en', 'consent_required' => true, 'consent_label' => 'Restored notice']);
    $this->assertTrue(json_decode($data['settings'], true)['consent_required']);
  }

  #[Test]
  public function legacy_broken_forms_can_be_repaired_one_locale_at_a_time(): void
  {
    $block = $this->fixture('published');
    $block->page->update(['status' => 'published']);
    $block->contactFormTranslations()->update(['consent_label' => null]);
    $tr = Locale::where('code', 'tr')->firstOrFail();
    $block->contactFormTranslations()->create(['locale_id' => $tr->id, 'title' => 'Contact', 'consent_label' => null]);
    foreach (['en' => 'I agree.', 'tr' => 'Kabul ediyorum.'] as $locale => $notice) {
      $data = $this->adminData($block->fresh(), $block, ['locale' => $locale, 'status' => 'published', 'consent_label' => $notice]);
      $this->assertTrue(json_decode($data['settings'], true)['consent_required']);
      $response = app(InternalContentResourceController::class)->updateBlock($this->jsonRequest(['locale' => $locale, 'translations' => ['consent_label' => $notice]]), $block->fresh());
      $this->assertSame(200, $response->getStatusCode());
    }
    app(ContactConsentRequirements::class)->assertPage($block->page->fresh());
  }

  #[Test]
  public function publication_checks_all_enabled_locales_and_preserves_missing_row_fallback(): void
  {
    $block = $this->fixture('published');
    $page = $block->page;
    $requirements = app(ContactConsentRequirements::class);
    $requirements->assertPage($page->fresh()); // No tr row: the valid default notice is used.
    $tr = Locale::where('code', 'tr')->firstOrFail();
    $block->contactFormTranslations()->create(['locale_id' => $tr->id, 'title' => 'Contact', 'consent_label' => '   ']);
    $path = 'blocks.'.$block->id.'.translations.tr.consent_label';
    $this->rejects(fn () => app(PageWorkflowManager::class)->apply($page->fresh(), $this->actor(), 'publish'), $path);
    $this->rejects(fn () => app(InternalPagePublishController::class)->publish($this->jsonRequest([]), $page->fresh()), $path);
    $this->assertSame('draft', $page->fresh()->status);
    $this->assertNull($page->fresh()->published_at);
    $this->assertSame(0, PageRevision::count());
    $page->site->locales()->updateExistingPivot($tr->id, ['is_enabled' => false]);
    $response = app(InternalPagePublishController::class)->publish($this->jsonRequest([]), $page->fresh());
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('published', $page->fresh()->status);
  }

  #[Test]
  public function single_and_bulk_block_publication_roll_back_without_partial_changes(): void
  {
    $block = $this->fixture();
    $block->contactFormTranslations()->update(['consent_label' => null]);
    $page = $block->page;
    $path = 'blocks.'.$block->id.'.translations.en.consent_label';
    $this->rejects(fn () => app(UpdateBlockStatus::class)->execute($block->fresh(), 'published', $page->fresh(), null, $this->actor()), $path);
    $this->rejects(fn () => $this->adminData($block->fresh(), $block, ['status' => 'published', 'locale' => 'tr', 'consent_label' => 'Kabul ediyorum.']), $path);
    $this->rejects(fn () => app(PageOwnedBlockPublisher::class)->publish($page->fresh()), $path);
    $this->rejects(fn () => app(InternalPagePublishController::class)->publish($this->jsonRequest(['include_page_owned_blocks' => true]), $page->fresh()), $path);
    $this->assertSame('draft', $page->fresh()->status);
    $this->assertSame('draft', $block->fresh()->status);
    $this->assertSame(0, PageRevision::count());
    $block->contactFormTranslations()->update(['consent_label' => 'Repaired notice']);
    $response = app(InternalPagePublishController::class)->publish($this->jsonRequest(['include_page_owned_blocks' => true]), $page->fresh());
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('published', $block->fresh()->status);
  }

  #[Test]
  public function enabling_a_live_form_requirement_checks_other_locale_rows_before_mutation_in_the_editor(): void
  {
    $block = $this->fixture('published');
    $block->settings = ['consent_required' => false];
    $block->save();
    $tr = Locale::where('code', 'tr')->firstOrFail();
    $block->contactFormTranslations()->create(['locale_id' => $tr->id, 'title' => 'Contact', 'consent_label' => null]);
    $path = 'blocks.'.$block->id.'.translations.tr.consent_label';
    $this->rejects(fn () => $this->adminData($block->fresh(), $block, ['status' => 'published', 'locale' => 'en', 'consent_required' => true]), $path);
    $this->assertFalse($block->fresh()->setting('consent_required'));
  }

  #[Test]
  public function unpublished_ancestors_and_disabled_slots_do_not_block_page_only_publication(): void
  {
    $block = $this->fixture('published');
    $block->contactFormTranslations()->update(['consent_label' => null]);
    $parent = Block::create(['page_id' => $block->page_id, 'type' => 'columns', 'slot_type_id' => $block->slot_type_id, 'status' => 'draft']);
    $block->update(['parent_id' => $parent->id]);
    app(ContactConsentRequirements::class)->assertPage($block->page->fresh());
    $this->rejects(fn () => app(UpdateBlockStatus::class)->execute($parent, 'published', $block->page->fresh(), null, $this->actor()), 'blocks.'.$block->id.'.translations.en.consent_label');
    $this->assertSame('draft', $parent->fresh()->status);
    $block->update(['parent_id' => null]);
    $block->page->slots()->update(['source_type' => 'disabled']);
    app(ContactConsentRequirements::class)->assertPage($block->page->fresh(), true);
    $this->assertSame([], app(ContactConsentRequirements::class)->publicationErrors($block->page->fresh(), true));
  }

  #[Test]
  public function active_shared_content_is_checked_without_cascading_draft_blocks(): void
  {
    $block = $this->fixture('published');
    $page = $block->page;
    $block->contactFormTranslations()->update(['consent_label' => null]);
    $shared = SharedSlot::create(['site_id' => $page->site_id, 'name' => 'Shared contact', 'handle' => 'shared-contact', 'slot_name' => 'main', 'is_active' => true]);
    SharedSlotBlock::create(['shared_slot_id' => $shared->id, 'block_id' => $block->id, 'sort_order' => 0]);
    $page->slots()->update(['source_type' => 'shared_slot', 'shared_slot_id' => $shared->id]);
    $path = 'blocks.'.$block->id.'.translations.en.consent_label';
    $this->rejects(fn () => app(ContactConsentRequirements::class)->assertPage($page->fresh()), $path);
    $this->rejects(fn () => app(ContactConsentRequirements::class)->assertSharedSlot($shared->fresh()), $path);
    $shared->update(['is_active' => false]);
    app(ContactConsentRequirements::class)->assertPage($page->fresh());
    $shared->update(['is_active' => true]);
    $block->update(['status' => 'draft']);
    app(ContactConsentRequirements::class)->assertPage($page->fresh(), true);
    $this->assertSame('draft', $block->fresh()->status);
  }

  #[Test]
  public function content_plans_refuse_invalid_new_forms_and_legacy_stage_promotion_without_mutation(): void
  {
    $block = $this->fixture('published');
    $page = $block->page;
    $page->update(['status' => 'published']);
    $service = app(InternalContentPlanService::class);
    $created = $service->apply(['plan' => ['mode' => 'create_staged_update_for_published_page', 'source_page_id' => $page->id, 'expected_source_path' => '/contact', 'managed_slots' => ['main']]]);
    $this->assertTrue($created->ok, json_encode($created->errors));
    $stage = Page::findOrFail($created->data['staged_page']['id']);
    $replace = ['plan' => ['mode' => 'replace_staged_page_update', 'staged_page_id' => $stage->id, 'expected_source_page_id' => $page->id, 'expected_source_path' => '/contact', 'replace_slots' => ['main' => [['type' => 'contact_form', 'settings' => ['consent_required' => true], 'translations' => ['consent_label' => ' ']]]]]];
    foreach ([$service->validate($replace), $service->apply($replace)] as $result) {
      $this->assertFalse($result->ok);
      $this->assertContains(ContactConsentRequirements::CODE, array_column($result->errors, 'code'));
    }
    $stageForm = $stage->blocks()->where('type', 'contact_form')->sole();
    $this->assertSame('I agree.', $stageForm->contactFormTranslations()->first()->consent_label);
    $stageForm->contactFormTranslations()->update(['consent_label' => null]);
    $stageForm->update(['status' => 'draft']);
    $promote = ['plan' => ['mode' => 'promote_staged_page_update', 'staged_page_id' => $stage->id, 'expected_source_page_id' => $page->id, 'expected_source_path' => '/contact', 'promote_slots' => ['main']]];
    $revisions = PageRevision::count();
    foreach ([$service->validate($promote), $service->apply($promote)] as $result) {
      $this->assertFalse($result->ok);
      $this->assertContains(ContactConsentRequirements::CODE, array_column($result->errors, 'code'));
    }
    $this->assertNotNull($stage->fresh());
    $this->assertSame('I agree.', $block->contactFormTranslations()->first()->consent_label);
    $this->assertSame($revisions, PageRevision::count());
    $stageForm->contactFormTranslations()->update(['consent_label' => 'Repaired stage']);
    // A valid selected replacement can repair a legacy invalid live form.
    $block->contactFormTranslations()->update(['consent_label' => null]);
    $result = $service->apply($promote);
    $this->assertTrue($result->ok, json_encode($result->errors));
    $this->assertNull($stage->fresh());
    $this->assertSame('Repaired stage', $page->blocks()->where('type', 'contact_form')->sole()->contactFormTranslations()->first()->consent_label);
  }

  #[Test]
  public function restoring_a_broken_live_revision_rolls_back_while_draft_preview_remains_available(): void
  {
    $block = $this->fixture('published');
    $page = $block->page;
    $page->update(['status' => 'published']);
    $block->contactFormTranslations()->update(['consent_label' => null]);
    $manager = app(PageRevisionManager::class);
    $revision = $manager->capture($page->fresh());
    $block->contactFormTranslations()->update(['consent_label' => 'Current valid notice']);
    $count = PageRevision::count();
    $this->rejects(fn () => $manager->restore($page->fresh(), $revision, $this->actor()));
    $this->assertSame('Current valid notice', $block->contactFormTranslations()->first()->consent_label);
    $this->assertSame($count, PageRevision::count());
    $candidate = Page::create(['site_id' => $page->site_id, 'status' => 'draft']);
    $manager->applySnapshotToCandidate($candidate, $revision->snapshot, ['source_page_id' => $page->id, 'revision_id' => $revision->id]);
    $this->assertSame('draft', $candidate->fresh()->status);
    $this->assertNull($candidate->blocks()->where('type', 'contact_form')->sole()->contactFormTranslations()->first()->consent_label);
  }

  #[Test]
  public function shared_publication_activation_and_live_restore_refuse_legacy_invalid_wording(): void
  {
    $block = $this->fixture();
    $shared = SharedSlot::create(['site_id' => $block->page->site_id, 'name' => 'Shared contact', 'handle' => 'shared-contact', 'slot_name' => 'main', 'is_active' => false]);
    $sourcePages = app(SharedSlotSourcePageManager::class);
    $source = $sourcePages->ensureFor($shared);
    $block->update(['page_id' => $source->id]);
    $sourcePages->rebuildAssignments($shared);
    $block->contactFormTranslations()->update(['consent_label' => null]);
    $controller = app(InternalSharedSlotController::class);
    $this->rejects(fn () => $controller->publishBlocks($shared->fresh()));
    $this->assertSame('draft', $block->fresh()->status);
    $block->update(['status' => 'published']);
    $count = SharedSlotRevision::count();
    $this->rejects(fn () => $controller->update($this->jsonRequest(['is_active' => true]), $shared->fresh()));
    $this->assertFalse($shared->fresh()->is_active);
    $this->assertSame($count, SharedSlotRevision::count());
    $shared->update(['is_active' => true]);
    $manager = app(SharedSlotRevisionManager::class);
    $revision = $manager->capture($shared->fresh(), $this->actor(), 'test', 'Old invalid version');
    $block->contactFormTranslations()->update(['consent_label' => 'Current notice']);
    $count = SharedSlotRevision::count();
    $this->rejects(fn () => $manager->restore($shared->fresh(), $revision, $this->actor()));
    $this->assertSame($count, SharedSlotRevision::count());
    $this->assertSame('Current notice', $block->contactFormTranslations()->first()->consent_label);
  }

  #[Test]
  public function publication_guards_keep_the_default_locale_check_without_site_locale_assignments(): void
  {
    $block = $this->fixture();
    $block->contactFormTranslations()->update(['consent_label' => null]);
    $block->page->site->locales()->detach();
    $this->rejects(fn () => app(UpdateBlockStatus::class)->execute($block->fresh(), 'published', $block->page->fresh(), null, $this->actor()), 'blocks.'.$block->id.'.translations.en.consent_label');
    $this->assertSame('draft', $block->fresh()->status);
  }

  #[Test]
  public function a_zero_string_is_preserved_as_nonblank_notice_text(): void
  {
    $block = $this->fixture();
    $response = app(InternalContentResourceController::class)->updateBlock($this->jsonRequest(['translations' => ['consent_label' => '0']]), $block);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('0', $block->contactFormTranslations()->first()->consent_label);
  }

  #[Test]
  public function editor_and_publication_errors_are_translated_in_every_admin_locale(): void
  {
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      foreach (['consent_notice_required', 'consent_publish_missing'] as $key) {
        $this->assertTrue(app('translator')->hasForLocale('webblocks-cms::admin.blocks.contact_form.'.$key, $locale));
      }
    }
  }

  private function fixture(string $status = 'draft'): Block
  {
    $site = Site::create(['name' => 'Consent fixture', 'handle' => 'consent-fixture', 'is_primary' => true]);
    $en = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $tr = Locale::create(['code' => 'tr', 'name' => 'Türkçe', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->sync([$en->id, $tr->id]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Contact', 'slug' => 'contact', 'status' => 'draft']);
    $page->translations()->updateOrCreate(['locale_id' => $en->id], ['name' => 'Contact', 'slug' => 'contact', 'path' => '/contact']);
    $slot = SlotType::create(['slug' => 'main', 'name' => 'Main', 'status' => 'published']);
    PageSlot::create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'source_type' => 'page']);
    $type = BlockType::create(['slug' => 'contact_form', 'name' => 'Contact Form', 'category' => 'content', 'source_type' => 'static', 'status' => 'published']);
    $block = Block::create(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'contact_form', 'slot_type_id' => $slot->id, 'status' => $status, 'settings' => ['consent_required' => true, 'send_email_notification' => false, 'store_submissions' => true]]);
    $block->contactFormTranslations()->create(['locale_id' => $en->id, 'title' => 'Contact', 'submit_label' => 'Send', 'success_message' => 'Received.', 'consent_label' => 'I agree.']);

    return $block->fresh(['page.site']);
  }

  private function jsonRequest(array $payload): Request
  {
    return Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
  }

  private function actor(): User
  {
    $class = 'App\\Models\\User';

    return $this->cmsActor((new $class)->forceFill(['role' => 'super_admin', 'is_active' => true]));
  }

  private function rejects(callable $action, ?string $path = null): void
  {
    try {
      $action();
      $this->fail('Expected consent validation failure.');
    } catch (ValidationException $exception) {
      if ($path !== null) {
        $this->assertArrayHasKey($path, $exception->errors());
      } else {
        $this->assertNotEmpty($exception->errors());
      }
    }
  }

  private function adminData(?Block $editing, Block $context, array $values): array
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
    $request = BlockRequest::create('/blocks', 'PUT', $values + ['page_id' => $context->page_id, 'block_type_id' => $context->block_type_id, 'slot_type_id' => $context->slot_type_id, 'sort_order' => 0, 'status' => 'draft', 'submit_label' => 'Send', 'success_message' => 'Received.', 'send_email_notification' => false, 'store_submissions' => true]);
    $request->setContainer($this->app)->setRedirector(app('redirect'));
    $route = new Route(['PUT'], '/blocks/{block}', []);
    $route->bind($request);
    $route->setParameter('block', $editing);
    $request->setRouteResolver(fn () => $route);
    $request->validateResolved();

    return $request->validatedData();
  }
}

class ConsentAuthoringTestUser extends AuthenticatableUser {}
