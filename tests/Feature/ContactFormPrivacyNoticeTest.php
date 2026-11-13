<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\Tests\TestCase;

class ContactFormPrivacyNoticeTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function the_editor_offers_a_consent_default_and_reset_for_the_content_language(): void
  {
    $block = $this->fixture(null);
    $block->update(['settings' => ['consent_required' => true]]);
    $tr = Locale::where('code', 'tr')->firstOrFail();
    $block->contactFormTranslations()->create(['locale_id' => $tr->id, 'consent_label' => null]);
    $default = app(CmsTranslator::class)->get('blocks.contact_form.default_consent_notice', 'tr');
    $render = fn () => view('webblocks-cms::admin.blocks.types.contact_form', [
      'block' => app(BlockTranslationResolver::class)->resolve($block->fresh(), $tr),
      'activeLocale' => $tr,
      'isDefaultLocale' => false,
      'errors' => new ViewErrorBag,
    ])->render();
    $this->assertStringContainsString('data-wb-contact-consent-default="'.$default.'">'.$default.'</textarea>', $render());
    $this->assertStringContainsString('data-wb-contact-consent-reset', $render());

    $writer = app(BlockPayloadWriter::class);
    $writer->save($block, $block->page, $this->adminData($block, ['locale' => 'tr', 'consent_label' => 'Özel onay metni.']), 'tr');
    $this->assertStringContainsString('data-wb-contact-consent-default="'.$default.'">Özel onay metni.</textarea>', $render());
    $this->assertStringContainsString('Özel onay metni.', $this->renderForm($block->fresh(), 'tr'));
    $writer->save($block, $block->page, $this->adminData($block, ['locale' => 'tr', 'consent_label' => $default]), 'tr');
    $this->assertSame($default, $block->contactFormTranslations()->where('locale_id', $tr->id)->value('consent_label'));
    $this->assertSame('I agree.', $block->contactFormTranslations()->where('locale_id', '!=', $tr->id)->value('consent_label'));
  }

  #[Test]
  public function visibility_and_localized_labels_survive_preview_save_and_revision_restore(): void
  {
    $block = $this->fixture(null);
    $writer = app(BlockPayloadWriter::class);
    $data = $this->adminData($block, ['contact_show_name' => '0', 'contact_show_subject' => '0', 'email_label' => 'Reply address']);
    $preview = $writer->preview($block, $block->page, $data, 'en');
    $previewHtml = $this->renderForm($preview, 'en');
    $this->assertStringContainsString('>Reply address</label>', $previewHtml);
    $this->assertStringNotContainsString('name="name"', $previewHtml);
    $this->assertStringContainsString('name="name"', $this->renderForm($block->fresh(), 'en'));

    $writer->save($block, $block->page, $data, 'en');
    $tr = $this->adminData($block, ['locale' => 'tr', 'contact_show_name' => '1', 'message_label' => 'Talebiniz <örnek>']);
    $writer->save($block, $block->page, $tr, 'tr');
    $trHtml = $this->renderForm($block->fresh(), 'tr');
    $this->assertStringContainsString('>Talebiniz &lt;örnek&gt;</label>', $trHtml);
    $this->assertStringContainsString('>E-posta adresiniz</label>', $trHtml);
    $this->assertStringNotContainsString('name="name"', $trHtml);
    $this->assertStringNotContainsString('name="subject"', $trHtml);

    $revisions = app(PageRevisionManager::class);
    $revision = $revisions->capture($block->page->fresh());
    $writer->save($block, $block->page, $this->adminData($block, ['email_label' => '']), 'en');
    $this->assertStringContainsString('>Email</label>', $this->renderForm($block->fresh(), 'en'));
    $candidate = Page::create(['site_id' => $block->page->site_id, 'status' => 'draft']);
    $revisions->applySnapshotToCandidate($candidate, $revision->fresh()->snapshot, ['source_page_id' => $block->page_id, 'revision_id' => $revision->id]);
    $restored = $candidate->blocks()->sole();
    $this->assertFalse($restored->setting('show_name'));
    $this->assertFalse($restored->setting('show_subject'));
    $this->assertStringContainsString('>Reply address</label>', $this->renderForm($restored, 'en'));
    $this->assertStringContainsString('>Talebiniz &lt;örnek&gt;</label>', $this->renderForm($restored, 'tr'));
  }

  #[Test]
  public function the_api_keeps_labels_locale_owned_and_supports_clearing_an_override(): void
  {
    $block = $this->fixture(null);
    $controller = app(InternalContentResourceController::class);
    $response = $controller->updateBlock($this->jsonRequest([
      'locale' => 'tr',
      'settings' => ['show_subject' => false],
      'translations' => ['contact_form' => ['subject_label' => 'Sipariş numarası']],
    ]), $block);
    $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    $this->assertFalse($block->fresh()->setting('show_subject'));
    $tr = $block->contactFormTranslations()->whereHas('locale', fn ($query) => $query->where('code', 'tr'));
    $this->assertSame('Sipariş numarası', $tr->value('subject_label'));
    $response = $controller->updateBlock($this->jsonRequest([
      'locale' => 'tr',
      'settings' => ['show_subject' => true],
      'translations' => ['subject_label' => null],
    ]), $block->fresh());
    $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    $this->assertNull($tr->value('subject_label'));
    $this->assertStringContainsString('>Konu</label>', $this->renderForm($block->fresh(), 'tr'));
  }

  #[Test]
  public function the_update_migration_adds_label_and_visibility_storage_without_replacing_existing_copy(): void
  {
    $block = $this->fixture('/privacy');
    $labels = ['name_label', 'email_label', 'subject_label', 'message_label'];
    Schema::table('wbcms_block_contact_form_translations', fn (Blueprint $table) => $table->dropColumn($labels));
    Schema::table('wbcms_contact_messages', fn (Blueprint $table) => $table->string('name')->change());
    foreach (['wbcms_block_content_settings', 'wbcms_snapshot_block_content_settings', 'wbcms_snapshot_contact_translations'] as $table) {
      $fields = $table === 'wbcms_snapshot_contact_translations' ? $labels : ['show_name', 'show_subject'];
      $columns = array_merge(...array_map(fn ($field) => [$field, $field.'_kind', $field.'_text'], $fields));
      Schema::table($table, fn (Blueprint $schema) => $schema->dropColumn($columns));
    }

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_11_13_120000_configure_contact_form_fields.php';
    $migration->up();
    $migration->up();
    $this->assertTrue(Schema::hasColumns('wbcms_block_contact_form_translations', $labels));
    $this->assertTrue(Schema::hasColumns('wbcms_snapshot_contact_translations', $labels));
    $this->assertTrue(Schema::hasColumns('wbcms_block_content_settings', ['show_name', 'show_subject']));
    $this->assertTrue(Schema::hasColumns('wbcms_snapshot_block_content_settings', ['show_name', 'show_subject']));
    $this->assertTrue(collect(Schema::getColumns('wbcms_contact_messages'))->firstWhere('name', 'name')['nullable']);
    $this->assertSame('I agree.', $block->contactFormTranslations()->sole()->consent_label);
    $this->assertSame('/privacy', $block->fresh()->setting('privacy_notice_url'));
  }

  #[Test]
  public function the_notice_link_follows_the_requested_locale_even_when_form_copy_falls_back(): void
  {
    $block = $this->fixture('/privacy?source=contact#rights');
    $html = $this->renderForm($block, 'tr');
    $this->assertStringContainsString('href="/tr/aydinlatma?source=contact#rights"', $html);
    $this->assertStringContainsString('Aydınlatma metni', $html);
    $this->assertStringNotContainsString('name="consent"', $html);
    $this->assertDoesNotMatchRegularExpression('/<button[^>]*type="submit"[^>]*disabled/', $html);
    $this->assertSame('/privacy?source=contact#rights', $block->fresh()->setting('privacy_notice_url'));
  }

  #[Test]
  public function missing_translations_keep_the_stored_url_and_external_urls_stay_unchanged(): void
  {
    $block = $this->fixture('/privacy');
    Page::whereHas('translations', fn ($query) => $query->where('path', '/privacy'))->firstOrFail()->translations()->whereHas('locale', fn ($query) => $query->where('code', 'tr'))->delete();
    $this->assertStringContainsString('href="/privacy"', $this->renderForm($block, 'tr'));
    $block->settings = ['privacy_notice_url' => 'https://example.test/notice?lang=tr', 'consent_required' => false];
    $block->save();
    $this->assertStringContainsString('href="https://example.test/notice?lang=tr"', $this->renderForm($block->fresh(), 'tr'));
  }

  #[Test]
  public function absent_or_unsafe_stored_urls_do_not_render_a_link_and_consent_remains_independent(): void
  {
    $block = $this->fixture(null);
    foreach ([null, '', 'javascript:alert(1)', '//example.test', '/\\example.test'] as $url) {
      $block->settings = ['privacy_notice_url' => $url, 'consent_required' => true];
      $block->save();
      $html = $this->renderForm($block->fresh(), 'en');
      $this->assertStringNotContainsString('>Privacy notice</a>', $html);
      $this->assertStringNotContainsString('javascript:', $html);
      $this->assertStringContainsString('name="consent"', $html);
    }
  }

  #[Test]
  public function api_creation_patch_omission_and_clearing_preserve_other_form_settings(): void
  {
    $block = $this->fixture(null);
    $block->page->update(['status' => 'draft']);
    $controller = app(InternalContentResourceController::class);
    $created = $controller->storeSlotBlock($this->jsonRequest(['type' => 'contact_form', 'locale' => 'en', 'settings' => ['privacy_notice_url' => ' /privacy ', 'consent_required' => false], 'translations' => ['title' => 'Contact', 'submit_label' => 'Send', 'success_message' => 'Received.']]), $block->page, 'main');
    $this->assertSame(201, $created->getStatusCode(), $created->getContent());
    $form = Block::where('id', '!=', $block->id)->where('type', 'contact_form')->sole();
    $this->assertSame('/privacy', $form->setting('privacy_notice_url'));
    $response = $controller->updateBlock($this->jsonRequest(['settings' => ['privacy_notice_url' => '/privacy#rights']]), $form);
    $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    $controller->updateBlock($this->jsonRequest(['locale' => 'en', 'translations' => ['title' => 'Updated contact']]), $form->fresh());
    $this->assertSame('/privacy#rights', $form->fresh()->setting('privacy_notice_url'));
    foreach (['', null] as $clear) {
      $controller->updateBlock($this->jsonRequest(['settings' => ['privacy_notice_url' => '/privacy']]), $form->fresh());
      $controller->updateBlock($this->jsonRequest(['settings' => ['privacy_notice_url' => $clear]]), $form->fresh());
      $this->assertNull($form->fresh()->setting('privacy_notice_url'));
      $this->assertFalse($form->fresh()->setting('consent_required'));
    }
  }

  #[Test]
  #[DataProvider('invalidUrls')]
  public function admin_and_api_refuse_invalid_notice_urls_without_changing_saved_values(mixed $url): void
  {
    $block = $this->fixture('/privacy');
    try {
      app(InternalContentResourceController::class)->updateBlock($this->jsonRequest(['settings' => ['privacy_notice_url' => $url]]), $block);
      $this->fail('An invalid notice URL must be rejected.');
    } catch (HttpResponseException $exception) {
      $this->assertSame(422, $exception->getResponse()->getStatusCode());
    }
    try {
      $this->adminData($block, ['privacy_notice_url' => $url]);
      $this->fail('The native editor must reject the same invalid URL.');
    } catch (ValidationException $exception) {
      $this->assertArrayHasKey('privacy_notice_url', $exception->errors());
    }
    $this->assertSame('/privacy', $block->fresh()->setting('privacy_notice_url'));
  }

  public static function invalidUrls(): iterable
  {
    foreach (['javascript:alert(1)', 'data:text/html,hello', '//example.test', '/\\example.test', '/privacy with spaces', "/privacy\0", 'mailto:privacy@example.test', '#rights', 'https://', 'https://user:pass@example.test/notice', str_repeat('a', 2049), ['url' => '/privacy']] as $index => $value) {
      yield (string) $index => [$value];
    }
  }

  #[Test]
  public function native_editor_saves_shared_urls_preserves_omission_and_keeps_them_when_translating(): void
  {
    $block = $this->fixture('/privacy');
    $settings = json_decode($this->adminData($block, ['privacy_notice_url' => ' /privacy#rights '])['settings'], true);
    $this->assertSame('/privacy#rights', $settings['privacy_notice_url']);
    $this->assertSame('/privacy#rights', json_decode($this->adminData($block, ['locale' => 'en', 'privacy_notice_url' => '/privacy#rights'])['settings'], true)['privacy_notice_url']);
    $this->assertSame('/privacy', json_decode($this->adminData($block, [])['settings'], true)['privacy_notice_url']);
    $this->assertSame('/privacy', json_decode($this->adminData($block, ['locale' => 'tr', 'privacy_notice_url' => 'https://example.test/replacement'])['settings'], true)['privacy_notice_url']);
    $this->assertNull(json_decode($this->adminData($block, ['privacy_notice_url' => ''])['settings'], true)['privacy_notice_url']);
    $html = view('webblocks-cms::admin.blocks.types.contact_form', ['block' => $block, 'activeLocale' => Locale::where('code', 'tr')->firstOrFail(), 'isDefaultLocale' => false, 'errors' => new ViewErrorBag])->render();
    $this->assertMatchesRegularExpression('/<input[^>]*name="privacy_notice_url"[^>]*disabled/', $html);
  }

  #[Test]
  public function upgrade_adds_live_and_revision_columns_idempotently_and_revisions_keep_the_original_url(): void
  {
    $block = $this->fixture(null);
    foreach (['wbcms_block_content_settings', 'wbcms_snapshot_block_content_settings'] as $table) {
      Schema::table($table, fn (Blueprint $schema) => $schema->dropColumn(['privacy_notice_url', 'privacy_notice_url_kind', 'privacy_notice_url_text']));
    }
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_26_120000_add_contact_privacy_notice_url.php';
    $migration->up();
    $migration->up();
    $block->settings = ['privacy_notice_url' => '/privacy', 'consent_required' => false];
    $block->save();
    $this->assertSame('/privacy', DB::table('wbcms_block_content_settings')->where('privacy_notice_url', '/privacy')->value('privacy_notice_url'));
    $revision = app(PageRevisionManager::class)->capture($block->page->fresh());
    $this->assertSame('/privacy', json_decode($revision->fresh()->snapshot['blocks'][0]['settings'], true)['privacy_notice_url']);
    $this->assertSame('/privacy', DB::table('wbcms_snapshot_block_content_settings')->where('privacy_notice_url', '/privacy')->value('privacy_notice_url'));
    $block->settings = ['privacy_notice_url' => '/new-notice'];
    $block->save();
    $this->assertSame('/privacy', json_decode($revision->fresh()->snapshot['blocks'][0]['settings'], true)['privacy_notice_url']);
    $candidate = Page::create(['site_id' => $block->page->site_id, 'status' => 'draft']);
    app(PageRevisionManager::class)->applySnapshotToCandidate($candidate, $revision->fresh()->snapshot, ['source_page_id' => $block->page_id, 'revision_id' => $revision->id]);
    $this->assertSame('/privacy', $candidate->blocks()->where('type', 'contact_form')->sole()->setting('privacy_notice_url'));
    $migration->down();
    $this->assertSame('/new-notice', $block->fresh()->setting('privacy_notice_url'));
  }

  #[Test]
  public function notice_labels_and_editor_feedback_exist_in_all_six_locales(): void
  {
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      foreach (['blocks.contact_form.privacy_notice', 'admin.blocks.contact_form.privacy_notice_url', 'admin.blocks.contact_form.privacy_notice_help', 'admin.blocks.contact_form.privacy_notice_invalid'] as $key) {
        $this->assertTrue(app('translator')->hasForLocale('webblocks-cms::'.$key, $locale), $locale.': '.$key);
      }
    }
  }

  private function fixture(?string $url): Block
  {
    $site = Site::create(['name' => 'Privacy fixture', 'handle' => 'privacy-fixture', 'is_primary' => true]);
    $en = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $tr = Locale::create(['code' => 'tr', 'name' => 'Türkçe', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->sync([$en->id, $tr->id]);
    $target = Page::create(['site_id' => $site->id, 'slug' => 'privacy', 'status' => 'published']);
    $target->translations()->create(['locale_id' => $en->id, 'name' => 'Privacy', 'slug' => 'privacy', 'path' => '/privacy']);
    $target->translations()->create(['locale_id' => $tr->id, 'name' => 'Aydınlatma', 'slug' => 'aydinlatma', 'path' => '/aydinlatma']);
    $page = Page::create(['site_id' => $site->id, 'slug' => 'contact', 'status' => 'published']);
    $page->translations()->create(['locale_id' => $en->id, 'name' => 'Contact', 'slug' => 'contact', 'path' => '/contact']);
    $page->translations()->create(['locale_id' => $tr->id, 'name' => 'İletişim', 'slug' => 'iletisim', 'path' => '/iletisim']);
    $slot = SlotType::create(['slug' => 'main', 'name' => 'Main', 'status' => 'published']);
    PageSlot::create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'source_type' => 'page']);
    $type = BlockType::create(['slug' => 'contact_form', 'name' => 'Contact Form', 'category' => 'content', 'source_type' => 'static', 'status' => 'published', 'is_container' => false]);
    $block = Block::create(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'contact_form', 'slot_type_id' => $slot->id, 'status' => 'published', 'settings' => ['privacy_notice_url' => $url, 'consent_required' => false, 'send_email_notification' => false, 'store_submissions' => true]]);
    $block->contactFormTranslations()->create(['locale_id' => $en->id, 'title' => 'Contact', 'submit_label' => 'Send', 'success_message' => 'Received.', 'consent_label' => 'I agree.']);

    return $block->fresh(['page.site']);
  }

  private function renderForm(Block $block, string $locale): string
  {
    $resolved = app(BlockTranslationResolver::class)->resolve($block, $locale, $block->page->site);
    $resolved->setRelation('renderPage', $block->page);

    return view('webblocks-cms::pages.partials.blocks.contact_form', ['block' => $resolved, 'page' => $block->page, 'errors' => new ViewErrorBag])->render();
  }

  private function jsonRequest(array $payload): Request
  {
    return Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
  }

  private function adminData(Block $block, array $values): array
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
    $request = BlockRequest::create('/blocks/'.$block->id, 'PUT', $values + ['page_id' => $block->page_id, 'block_type_id' => $block->block_type_id, 'slot_type_id' => $block->slot_type_id, 'sort_order' => 0, 'status' => 'draft', 'submit_label' => 'Send', 'success_message' => 'Received.', 'send_email_notification' => false, 'store_submissions' => true]);
    $request->setContainer($this->app)->setRedirector(app('redirect'));
    $route = new Route(['PUT'], '/blocks/{block}', []);
    $route->bind($request);
    $route->setParameter('block', $block);
    $request->setRouteResolver(fn () => $route);
    $request->validateResolved();

    return $request->validatedData();
  }
}
