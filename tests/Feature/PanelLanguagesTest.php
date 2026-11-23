<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemBackupRestore;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

class PanelLanguagesTest extends TestCase
{
  private const API = '/webadmin/api/system/panel-languages';

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('auth.providers.users.model', User::class);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    $this->withoutMiddleware(EnsureInstalled::class);
    Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
  }

  public function test_bundled_languages_render_panel_appearance_and_operation_feedback_in_the_selected_language(): void
  {
    $this->actingAs($actor = $this->actor());

    foreach ([
      'en' => ['Select', 'Page Layout created successfully.', 'Keep editing'],
      'de' => ['Auswählen', 'Seitenlayout erstellt.', 'Weiter bearbeiten'],
      'tr' => ['Seçin', 'Sayfa yerleşimi oluşturuldu.', 'Düzenlemeye devam et'],
      'es' => ['Seleccionar', 'Diseño de página creado.', 'Seguir editando'],
      'it' => ['Seleziona', 'Layout di pagina creato.', 'Continua a modificare'],
      'fr' => ['Sélectionner', 'Gabarit de page créé.', 'Continuer la modification'],
    ] as $locale => [$select, $status, $keepEditing]) {
      $actor->forceFill(['admin_locale' => $locale])->save();
      $this->get(route('admin.system.settings.edit', ['tab' => 'appearance']))
        ->assertOk()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee('<option value="" disabled>'.e($select).'</option>', false)
        ->assertSee('data-wb-dirty-keep-editing="'.e($keepEditing).'"', false)
        ->assertDontSee('admin.common.select');

      $this->get(route('admin.sites.create'))->assertOk()
        ->assertSee(__('webblocks-cms::admin-feedback.panel_copy.font_sans', [], $locale))
        ->assertSee(__('webblocks-cms::admin-feedback.panel_copy.font_monospace', [], $locale));

      $this->followRedirects($this->post(route('admin.page-layouts.store'), ['name' => 'Fixture '.$locale]))
        ->assertOk()
        ->assertSee($status);
    }
  }

  public function test_panel_not_found_and_bulk_selection_feedback_use_the_selected_language(): void
  {
    $this->actingAs($actor = $this->actor());
    $index = route('admin.pages.index');
    $filteredIndex = route('admin.pages.index', ['site' => 'all', 'search' => 'fixture']);

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $actor->forceFill(['admin_locale' => $locale])->save();
      $feedback = fn (string $key, array $replace = []) => __('webblocks-cms::admin-feedback.'.$key, $replace, $locale);

      $this->get(route('admin.pages.edit', 900000000))
        ->assertNotFound()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee($feedback('not_found.title'))
        ->assertSee($feedback('not_found.back'))
        ->assertDontSee('admin-feedback.not_found');

      $this->get('/webadmin/missing-panel-route-fixture')
        ->assertNotFound()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee($feedback('not_found.title'));

      $this->get($filteredIndex)->assertOk();
      $response = $this->from($index)->delete(route('admin.pages.bulk-destroy'), ['page_ids' => [900000000]]);
      $response->assertRedirect($index);
      $this->get($index)->assertRedirect($filteredIndex);
      $this->get($filteredIndex)->assertOk()
        ->assertSee($feedback('bulk.selection_missing', ['entity' => $feedback('bulk_entities.pages')]));
    }
  }

  public function test_forbidden_panel_pages_use_the_selected_language(): void
  {
    config(['app.debug' => false]);
    $site = Site::query()->create(['name' => 'Fixture', 'handle' => 'fixture', 'is_primary' => true]);
    $editor = $this->cmsActor((new User)->forceFill(['role' => 'editor']), [$site->id]);
    $editor = User::query()->findOrFail($editor->id);
    $this->actingAs($editor);
    $url = route('admin.system.settings.edit');

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $editor->forceFill(['admin_locale' => $locale])->save();
      $this->get($url)->assertForbidden()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee(__('webblocks-cms::admin-feedback.panel_http.forbidden_title', [], $locale))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    $this->getJson($url)->assertForbidden()->assertJsonStructure(['message']);
  }

  public function test_embedded_application_validation_handles_css_and_enum_values(): void
  {
    $this->actingAs($actor = $this->actor());
    $actor->forceFill(['admin_locale' => 'tr'])->save();
    $store = route('admin.embedded-applications.store');
    $create = route('admin.embedded-applications.create');
    $payload = ['handle' => 'fixture', 'name' => 'Fixture', 'version' => '1.0.0', 'render_mode' => 'inline', 'mount_element' => 'div'];

    $this->from($create)->post($store, $payload + [
      'css_urls' => "/fixture.css\n\nhttps://example.test/fixture.css",
      'settings' => [['key' => 'choice', 'type' => 'enum']],
    ])->assertRedirect($create)->assertSessionHasErrors([
      'css_urls' => 'Her CSS URL’si aynı kaynağa ait mutlak bir yol olmalıdır (geçersiz satır: 3).',
      'settings.0.values' => 'Seçenekli ayarlar için en az bir değer girin.',
    ]);

    $this->post($store, $payload + [
      'css_urls' => '/fixture.css',
      'settings' => [['key' => 'choice', 'type' => 'enum', 'values' => '0, 1, ']],
    ])->assertRedirect();

    $application = EmbeddedApplication::query()->where('handle', 'fixture')->firstOrFail();
    $this->assertSame(['/fixture.css'], $application->css_assets);
    $this->assertSame(['0', '1'], $application->settings_schema['choice']['values']);
  }

  public function test_panel_localizes_application_options_and_authoring_feedback(): void
  {
    $this->actingAs($actor = $this->actor());
    $site = Site::query()->create(['name' => 'Fixture', 'handle' => 'fixture', 'domain' => 'fixture.example.test', 'is_primary' => true]);
    $contentLocale = Locale::query()->where('code', 'en')->firstOrFail();
    $site->locales()->syncWithoutDetaching([$contentLocale->id => ['is_enabled' => true]]);
    $layout = PageLayout::query()->firstOrCreate(['handle' => 'default'], ['name' => 'Default', 'is_active' => true, 'is_system' => true]);
    $page = Page::query()->create(['site_id' => $site->id, 'title' => 'Fixture', 'slug' => 'fixture', 'status' => 'draft', 'public_shell' => 'default']);
    $slotType = SlotType::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'is_system' => true]);
    $slot = PageSlot::query()->firstOrCreate(['page_id' => $page->id, 'slot_type_id' => $slotType->id], ['source_type' => 'page', 'sort_order' => 1]);
    $search = BlockType::query()->firstOrCreate(['slug' => 'search-form'], ['name' => 'Search Form', 'category' => 'content', 'status' => 'published', 'is_system' => true]);
    foreach (['header' => 'Header', 'plain_text' => 'Plain Text'] as $slug => $name) {
      BlockType::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'category' => 'content', 'status' => 'published', 'is_system' => true]);
    }

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $actor->forceFill(['admin_locale' => $locale])->save();
      $admin = fn (string $key) => __('webblocks-cms::admin.embedded_applications.'.$key, [], $locale);
      $feedback = fn (string $key, array $replace = []) => __('webblocks-cms::admin-feedback.'.$key, $replace, $locale);
      $form = $this->get(route('admin.embedded-applications.create'))->assertOk();
      $form->assertSee($admin('supports_locale'))
        ->assertSee($admin('supports_theme'))
        ->assertSee($admin('supports_fullscreen'))
        ->assertSee($admin('setting_type_enum'))
        ->assertDontSee('> fullscreen</label>', false);

      $this->from(route('admin.navigation.index', ['site' => $site->id]))
        ->post(route('admin.navigation.store'), ['site_id' => $site->id, 'menu_key' => 'primary', 'link_type' => 'page', 'title' => 'Fixture', 'visibility' => 'visible'])
        ->assertSessionHasErrors(['page_id' => $feedback('validation.navigation_page_required')]);

      $this->from(route('admin.pages.slots.blocks', [$page, $slot]))
        ->post(route('admin.blocks.store'), [
          'page_id' => $page->id, 'block_type_id' => $search->id, 'type' => 'search-form', 'status' => 'draft',
          'slot_type_id' => $slotType->id, 'sort_order' => 1,
        ])->assertSessionHasErrors(['content' => $feedback('validation.block_search_placeholder')]);

      $converter = route('admin.pages.converter.index');
      $this->from($converter)->post(route('admin.pages.converter.create-draft'), [
        'plan_payload' => '{}', 'plan_signature' => str_repeat('0', 64),
      ])->assertRedirect($converter)->assertSessionHasErrors(['plan_payload' => $feedback('validation.converter_plan_unverified')]);

      $preview = $this->post(route('admin.pages.converter.analyze'), [
        'site_id' => $site->id, 'locale_id' => $contentLocale->id, 'page_layout' => $layout->handle,
        'page_title' => 'Fixture conversion', 'page_path' => 'fixture-conversion-'.$locale,
        'source_html' => '<main><h1>Fixture heading</h1><p>Fixture paragraph.</p></main>',
      ])->assertOk()
        ->assertSee($feedback('converter.analysis_preview'))
        ->assertSee($feedback('converter.label_plain_text'))
        ->assertDontSee('admin-feedback.converter');

      $this->post(route('admin.pages.converter.create-draft'), [
        'plan_payload' => $preview->viewData('planPayload'),
        'plan_signature' => $preview->viewData('planSignature'),
      ])->assertRedirect()
        ->assertSessionHas('status', $feedback('converter.draft_created', ['created' => 2, 'skipped' => 0, 'warnings' => 0]))
        ->assertSessionHas('status_action.label', $feedback('converter.edit_draft'));
    }
  }

  public function test_page_asset_operation_notifications_follow_the_panel_language(): void
  {
    $this->actingAs($actor = $this->actor());
    $site = Site::query()->create(['name' => 'Fixture', 'handle' => 'fixture', 'domain' => 'fixture.example.test']);
    $page = Page::query()->create(['site_id' => $site->id, 'title' => 'Fixture', 'slug' => 'fixture', 'status' => 'draft', 'public_shell' => 'default']);
    $tab = route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']);

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $actor->forceFill(['admin_locale' => $locale])->save();
      $message = fn (string $action) => __('webblocks-cms::admin-feedback.page_assets.'.$action, ['type' => 'CSS'], $locale);

      $this->post(route('admin.pages.assets.store', [$page, 'css']), ['path' => '/site/fixture/add.css'])
        ->assertRedirect($tab)->assertSessionHas('status', $message('added'));
      $this->get($tab)->assertOk()->assertSee($message('added'));
      $asset = $page->pageAssets()->sole();

      $this->put(route('admin.pages.assets.update', [$page, $asset]), ['path' => '/site/fixture/edit.css'])
        ->assertRedirect($tab)->assertSessionHas('status', $message('updated'));
      $this->get($tab)->assertOk()->assertSee($message('updated'));

      $this->delete(route('admin.pages.assets.destroy', [$page, $asset]))
        ->assertRedirect($tab)->assertSessionHas('status', $message('deleted'));
      $this->get($tab)->assertOk()->assertSee($message('deleted'));
    }
  }

  public function test_backup_archive_errors_follow_the_panel_language(): void
  {
    $this->actingAs($actor = $this->actor());
    $disk = Storage::fake('backups');
    $upload = route('admin.system.backups.upload');
    $archive = $disk->path('fixture.zip');
    $zip = new ZipArchive;
    $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE));
    $zip->addFromString('database/database.sql', 'SELECT 1;');
    $zip->close();
    $sqlArchive = $disk->path('invalid-sql.zip');
    $this->assertTrue($zip->open($sqlArchive, ZipArchive::CREATE | ZipArchive::OVERWRITE));
    $zip->addFromString('manifest.json', '{"backup_id":1}');
    $zip->addFromString('database/database.sql', 'invalid SQL fixture');
    $zip->close();

    $backup = SystemBackup::query()->create([
      'type' => SystemBackup::TYPE_MANUAL, 'status' => SystemBackup::STATUS_COMPLETED,
      'archive_disk' => 'backups', 'archive_path' => 'fixture.zip', 'archive_filename' => 'fixture.zip', 'started_at' => now(),
    ]);
    $missing = $backup->replicate()->fill(['archive_path' => 'missing.zip', 'archive_filename' => 'missing.zip']);
    $missing->save();
    SystemBackupRestore::query()->create([
      'source_backup_id' => $backup->id,
      'status' => SystemBackupRestore::STATUS_COMPLETED,
      'restored_parts' => ['database', 'site_public'],
      'started_at' => now(),
    ]);

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $actor->forceFill(['admin_locale' => $locale])->save();
      $message = fn (string $key, array $replace = []) => __('webblocks-cms::admin-feedback.backup_archive.'.$key, $replace, $locale);
      $manifestError = $message('manifest_missing');
      $restore = route('admin.system.backups.show', $backup);

      $this->post(route('admin.system.backups.upload.store'), ['archive' => new UploadedFile($archive, 'fixture.zip', test: true)])
        ->assertRedirect($upload)->assertSessionHasErrors(['system_backup' => $manifestError]);
      $this->get($upload)->assertOk()->assertSee($manifestError);

      $sqlError = $message('sql_invalid', ['label' => $message('sql_archive_entry', ['entry' => 'database/database.sql'])]);
      $this->post(route('admin.system.backups.upload.store'), ['archive' => new UploadedFile($sqlArchive, 'invalid-sql.zip', test: true)])
        ->assertRedirect($upload)->assertSessionHasErrors(['system_backup' => $sqlError]);
      $this->get($upload)->assertOk()->assertSee($sqlError);

      $this->post(route('admin.system.backups.restore', $backup), ['acknowledge_restore_risk' => true])
        ->assertRedirect($restore)->assertSessionHasErrors(['system_restore' => $manifestError]);
      $this->get($restore)->assertOk()
        ->assertSee($manifestError)
        ->assertSee(__('webblocks-cms::admin.backups.restore_parts.site_public', [], $locale))
        ->assertDontSee('admin.backups.restore_parts.site_public');

      $notFound = __('webblocks-cms::admin.backups.archive_feedback.missing', [], $locale);
      $this->get(route('admin.system.backups.download', $missing))
        ->assertRedirect(route('admin.system.backups.index'))->assertSessionHasErrors(['system_backup' => $notFound]);
      $this->get(route('admin.system.backups.index'))->assertOk()->assertSee($notFound);
    }
  }

  public function test_panel_asset_media_locale_and_backup_feedback_use_the_selected_language(): void
  {
    $this->actingAs($actor = $this->actor());
    $this->app->usePublicPath(Storage::fake('public')->path(''));
    $backups = Storage::fake('backups');
    $archive = $backups->path('fixture.zip');
    $zip = new ZipArchive;
    $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE));
    $zip->addFromString('manifest.json', '{"backup_id":1}');
    $zip->addFromString('database/database.sql', 'SELECT 1;');
    $zip->close();
    $contentLocale = Locale::query()->where('code', 'en')->firstOrFail();
    $site = Site::query()->create(['name' => 'Fixture', 'handle' => 'fixture', 'domain' => 'fixture.example.test']);
    $page = Page::query()->create(['site_id' => $site->id, 'title' => 'Fixture', 'slug' => 'fixture', 'status' => 'draft', 'public_shell' => 'default']);
    $checksum = null;

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $actor->forceFill(['admin_locale' => $locale])->save();
      $text = fn (string $key, array $replace = []) => __('webblocks-cms::admin-feedback.'.$key, $replace, $locale);
      $this->post(route('admin.pages.assets.store', [$page, 'css']), ['path' => '/site/fixture/script.js'])
        ->assertSessionHasErrors(['path' => $text('page_assets.path_extension')]);

      $contents = '/* fixture '.$locale.' */';
      $this->put(route('admin.sites.assets.update', [$site, 'css']), ['contents' => $contents, 'expected_checksum' => $checksum])
        ->assertSessionHas('status', $text('site_assets.saved', ['type' => 'CSS']));
      $checksum = hash('sha256', $contents);

      $this->post(route('admin.media.fetch'), ['source_url' => 'https://localhost/fixture.png'])
        ->assertSessionHasErrors(['source_url' => $text('media.remote_local_host')]);
      $this->post(route('admin.locales.enable', $contentLocale))
        ->assertSessionHasErrors(['locale_lifecycle' => $text('locales.default_enable')]);

      $this->post(route('admin.system.backups.upload.store'), ['archive' => new UploadedFile($archive, 'fixture.zip', test: true)])
        ->assertSessionHas('status', $text('backups.uploaded'));
      $backup = SystemBackup::query()->latest('id')->firstOrFail();
      $this->get(route('admin.system.backups.show', $backup))->assertOk()
        ->assertSee($text('backups.uploaded_summary'))
        ->assertSee($text('backups.uploaded'))
        ->assertSee($text('panel_copy.backup_validation_passed'))
        ->assertSee($text('panel_copy.backup_log_source_filename', ['value' => 'fixture.zip']))
        ->assertSee($text('panel_copy.backup_log_manifest_app', ['value' => $text('panel_copy.backup_log_unknown')]))
        ->assertSee($text('panel_copy.backup_log_contents', ['value' => $text('panel_copy.backup_part_database')]));
    }
  }

  public function test_manual_upload_requires_a_complete_current_cms_pack_and_preserves_locale_preferences(): void
  {
    $actor = $this->actor();
    $actor->forceFill(['admin_locale' => 'ru'])->save();
    $this->actingAs($actor);
    $index = route('admin.system.panel-languages.index');
    $store = route('admin.system.panel-languages.store');
    $this->get($index)->assertOk()->assertSeeInOrder(['data-admin-listing-filters', 'Installed language packs', 'data-wb-target="#panel-language-install"', '<dialog class="wb-modal" id="panel-language-install"'], false)
      ->assertDontSee('panel-language-default-modal', false)->assertDontSee('Imported catalogs');

    $this->from($index)->post($store, [
      'package' => UploadedFile::fake()->createWithContent('ru.json', '{}'),
    ])->assertRedirect($index)->assertSessionHasErrors('package');

    $this->get($index)->assertSeeInOrder(['id="panel-language-install" aria-labelledby="panel-language-install-title"', 'data-wb-admin-autoload-overlay', 'id="panel-language-package-error"'], false);

    $directory = sys_get_temp_dir().'/webblocks-language-'.bin2hex(random_bytes(8));
    File::makeDirectory($directory);

    try {
      (new Process([PHP_BINARY, 'scripts/check-language-pack.php', '--build', $directory], dirname(__DIR__, 2)))->mustRun();
      $filename = 'webblocks-cms-ru-'.WebBlocks::VERSION.'.zip';
      $path = $directory.'/'.$filename;
      $archive = File::get($path);
      $zip = new ZipArchive;
      $this->assertTrue($zip->open($path));
      $this->assertTrue($zip->deleteName('lang/vendor/webblocks-cms/ru/public.php'));
      $zip->close();

      $this->from($index)->post($store, [
        'package' => UploadedFile::fake()->createWithContent($filename, File::get($path)),
      ])->assertRedirect($index)->assertSessionHasErrors('package');
      $this->assertDatabaseCount('wbcms_native_language_packs', 0);
      $this->assertDatabaseCount('wbcms_native_language_lines', 0);

      $this->post($store, ['package' => UploadedFile::fake()->createWithContent($filename, $archive)])
        ->assertRedirect($index);
      $this->assertDatabaseHas('wbcms_native_language_packs', ['code' => 'ru', 'source_version' => WebBlocks::VERSION]);
      $this->assertSame('ru', $actor->fresh()->admin_locale);
      $this->assertSame('en', app(AdminLocaleResolver::class)->defaultLocale());
      $this->assertSame(['en'], Locale::query()->pluck('code')->all());
      $this->get($index.'?search=RU')->assertOk()->assertSee('Русский')->assertSee('data-wb-target="#language-pack-details-ru"', false)
        ->assertSee('aria-labelledby="language-pack-details-ru-title"', false);
      $this->get($index.'?search=absent')->assertOk()->assertDontSee('data-wb-target="#language-pack-details-ru"', false);
      $this->assertSame('Поиск', trans('webblocks-cms::public.search.title', [], 'ru'));
      $this->assertSame('Проверить обновления', trans('publisher-client::updates.check_button', [], 'ru'));
    } finally {
      File::deleteDirectory($directory);
    }
  }

  public function test_api_requires_a_super_admin_system_token_and_drops_the_panel_catalog_endpoints(): void
  {
    $issuer = app(CmsApiTokenIssuer::class);
    $ordinary = $issuer->issue('Content only')->plainToken;
    $this->withToken($ordinary)->getJson(self::API)->assertForbidden();
    $this->actingAs($this->actor('editor'))->get(route('admin.system.panel-languages.index'))->assertForbidden();

    $root = $this->actor();
    $this->actingAs($root);
    $token = $issuer->issue('Manage languages', $root, ['panel-languages.manage'])->plainToken;
    $this->withToken($token)->getJson(self::API)->assertOk()
      ->assertJsonPath('packages', [])->assertJsonPath('defaultLocale', 'en');
    $this->withHeader('Accept', 'application/json')->post(self::API.'/packages', [
      'package' => UploadedFile::fake()->createWithContent('ru.json', '{}'),
    ])->assertUnprocessable()->assertJsonValidationErrors('package');
    $this->getJson(self::API.'/template')->assertNotFound();
    $this->postJson(self::API.'/catalogs')->assertNotFound();
    $this->putJson(self::API.'/default', ['code' => 'ru'])->assertUnprocessable();

    $root->forceFill(['is_active' => false])->save();
    $this->getJson(self::API)->assertForbidden();
  }

  private function actor(string $role = 'super_admin'): User
  {
    $actor = $this->cmsActor((new User)->forceFill(['role' => $role]));

    return User::query()->findOrFail($actor->id);
  }
}
