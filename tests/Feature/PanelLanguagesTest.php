<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Http\Middleware\UseAdminLocale;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Mail\VerificationEmailTemplates;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\PanelLanguages;
use WebBlocks\Cms\Support\Translations\PanelLanguageSource;
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
      $this->get($restore)->assertOk()->assertSee($manifestError);

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

  public function test_super_admin_can_preview_activate_and_select_russian_without_changing_content_languages(): void
  {
    $this->actingAs($actor = $this->actor());
    $locales = Locale::query()->orderBy('id')->get()->toArray();
    $publicationDefault = app(SystemSettings::class)->get(SystemSettings::DEFAULT_LOCALE);
    $this->get(route('admin.system.panel-languages.index', ['tab' => 'import']))
      ->assertOk()->assertSee('Download English template')
      ->assertSee('class="wb-page-header-title">Panel languages</h1>', false)
      ->assertSee('class="wb-file" type="file"', false);
    $this->post(route('admin.system.panel-languages.store'), ['catalog' => $this->upload()])->assertRedirect();
    $id = DB::table('wbcms_panel_language_catalogs')->value('id');

    $this->get(route('admin.system.panel-languages.show', $id))
      ->assertOk()->assertSee('class="wb-page-header-title">Catalog preview</h1>', false)
      ->assertSee('Язык интерфейса')->assertSee('Interface language')->assertSee('Activate catalog')
      ->assertSee('Catalog uploaded. Review the preview before activating it.');
    $this->get(route('admin.system.panel-languages.show', ['catalog' => $id, 'missing_page' => 2]))
      ->assertOk()->assertViewHas('missing', fn ($missing) => $missing->currentPage() === 2 && $missing->count() === 50);
    $this->assertArrayNotHasKey('ru', app(AdminLocaleResolver::class)->options());
    $this->post(route('admin.system.panel-languages.activate', $id))->assertRedirect();

    $this->from(route('admin.profile.edit'))->patch(route('admin.profile.locale.update'), ['admin_locale' => 'ru'])->assertRedirect();
    $this->assertSame('ru', $actor->fresh()->admin_locale);
    $this->get(route('admin.profile.edit'))->assertOk()->assertSee('lang="ru"', false)->assertSee('Язык интерфейса')->assertSee('Русский');
    $this->put(route('admin.system.panel-languages.default'), ['code' => 'ru'])
      ->assertRedirect(route('admin.system.panel-languages.index', ['tab' => 'settings']));
    $this->followRedirects($this->from(route('admin.system.panel-languages.index'))
      ->put(route('admin.system.panel-languages.default'), ['code' => 'unavailable', 'panel_language_tab' => 'settings']))
      ->assertOk()->assertSee('class="wb-tabs-panel is-active" id="panel-languages-settings"', false);
    $this->assertSame('ru', app(AdminLocaleResolver::class)->defaultLocale());
    $this->assertSame($locales, Locale::query()->orderBy('id')->get()->toArray());
    $this->assertSame($publicationDefault, app(SystemSettings::class)->get(SystemSettings::DEFAULT_LOCALE));
    $this->assertSame('en', app()->getLocale());
    $this->assertSame(['en', 'de', 'tr', 'es', 'it', 'fr'], AdminLocaleResolver::SUPPORTED_LOCALES);

    $request = Request::create('/webadmin');
    $request->setUserResolver(fn () => $actor);
    app(UseAdminLocale::class)->handle($request, function () {
      foreach ([1 => '1 сообщение', 2 => '2 сообщения', 5 => '5 сообщений', 21 => '21 сообщение'] as $count => $expected) {
        $this->assertSame($expected, trans_choice('webblocks-cms::notifications.panel_new_messages', $count));
      }
      $this->assertSame(
        app(VerificationEmailTemplates::class)->defaults('en'),
        app(VerificationEmailTemplates::class)->defaults('ru'),
      );

      return response('ok');
    });
    $this->assertSame('Interface language', __('webblocks-cms::admin.profile.interface_language'));
  }

  public function test_api_requires_super_admin_system_capability_and_keeps_uploaded_catalog_inactive(): void
  {
    $issuer = app(CmsApiTokenIssuer::class);
    $ordinary = $issuer->issue('Content only')->plainToken;
    $this->withToken($ordinary)->getJson(self::API)->assertForbidden();
    $this->actingAs($this->actor('editor'))->get(route('admin.system.panel-languages.index'))->assertForbidden();

    $root = $this->actor();
    $this->actingAs($root);
    $token = $issuer->issue('Manage languages', $root, ['panel-languages.manage'])->plainToken;
    $this->withToken($token)->getJson(self::API.'/template')->assertOk()->assertJsonPath('source_version', WebBlocks::VERSION);
    $response = $this->withHeader('Accept', 'application/json')->post(self::API.'/catalogs', ['catalog' => $this->upload()]);
    $response->assertCreated()->assertJsonPath('catalog.language_code', 'ru')->assertJsonPath('translated', 2);
    $id = $response->json('catalog.id');
    $this->getJson(self::API.'/catalogs/'.$id)->assertOk()->assertJsonPath('catalog.active_catalog_id', null);
    $this->putJson(self::API.'/default', ['code' => 'ru'])->assertUnprocessable();
    $this->postJson(self::API.'/catalogs/'.$id.'/activate')->assertOk();
    $this->putJson(self::API.'/default', ['code' => 'ru'])->assertOk();
    $this->postJson(self::API.'/ru/deactivate')->assertUnprocessable();

    $root->forceFill(['is_active' => false])->save();
    $this->getJson(self::API)->assertForbidden();
  }

  public function test_package_collision_keeps_the_catalog_and_uses_package_then_english_for_missing_keys(): void
  {
    $languages = app(PanelLanguages::class);
    $id = $languages->import($this->upload()->getContent());
    $languages->activate($id);
    $languages->saveDefault('ru');

    // Simulate a later package advertising a Russian admin catalog.
    $source = $this->partialMock(PanelLanguageSource::class);
    $source->shouldReceive('packagedOptions')->andReturn(AdminLocaleResolver::PACKAGED_OPTIONS + ['ru' => 'RU - Русский']);
    app('translator')->addLines(['admin.navigation.dashboard' => 'Панель управления'], 'ru', 'webblocks-cms');

    app(UseAdminLocale::class)->handle(Request::create('/webadmin'), function () {
      $this->assertSame('Язык интерфейса', __('webblocks-cms::admin.profile.interface_language'));
      $this->assertSame('Панель управления', __('webblocks-cms::admin.navigation.dashboard'));
      $this->assertSame('Panel languages', __('webblocks-cms::panel-languages.title'));
      $this->assertSame('en', app()->getLocale());

      return response('ok');
    });
    $this->assertSame('RU - Русский', app(AdminLocaleResolver::class)->options()['ru']);
    app(PanelLanguages::class)->deactivate('ru');
    $this->assertSame('ru', app(AdminLocaleResolver::class)->defaultLocale());
    $this->assertDatabaseHas('wbcms_panel_language_catalogs', ['id' => $id]);
    $this->assertSame([], app(PanelLanguages::class)->translations('ru'));
    app(PanelLanguages::class)->activate($id);
    $this->assertSame('Язык интерфейса', app(PanelLanguages::class)->translations('ru')['admin.profile.interface_language']);
  }

  public function test_import_rejects_public_keys_duplicate_rows_and_wrong_placeholders(): void
  {
    $this->actingAs($this->actor());
    foreach ([
      [['key' => 'public.search.title', 'text' => 'Поиск']],
      array_fill(0, 2, ['key' => 'admin.profile.interface_language', 'text' => 'Язык']),
      [['key' => 'notifications.panel_new_messages', 'text' => 'Сообщение|Сообщения|Сообщений']],
      [['key' => 'admin.profile.interface_language', 'text' => '<b>Язык</b>']],
    ] as $lines) {
      $this->post(route('admin.system.panel-languages.store'), ['catalog' => $this->upload($lines)])
        ->assertSessionHasErrors('catalog');
    }
    $this->assertDatabaseCount('wbcms_panel_language_catalogs', 0);
  }

  public function test_only_inactive_catalogs_can_be_deleted_by_a_super_admin(): void
  {
    $languages = app(PanelLanguages::class);
    $first = $languages->import($this->upload()->getContent());
    $second = $languages->import($this->upload()->getContent());
    $index = route('admin.system.panel-languages.index');
    $deleteFirst = route('admin.system.panel-languages.destroy', $first);
    $deleteSecond = route('admin.system.panel-languages.destroy', $second);

    $this->actingAs($this->actor('editor'))->delete($deleteSecond)->assertForbidden();
    $this->actingAs($this->actor())->get($index)->assertOk()
      ->assertSee('data-wb-target="#delete-panel-catalog-'.$second.'"', false)
      ->assertSee('Activate catalog');
    $this->post(route('admin.system.panel-languages.activate', $first))->assertRedirect($index);
    $this->from($index)->delete($deleteFirst)->assertSessionHasErrors('catalog');
    $this->assertDatabaseHas('wbcms_panel_language_catalogs', ['id' => $first]);

    $this->delete($deleteSecond)->assertRedirect($index);
    $this->get($index)->assertSee('Catalog deleted.');
    $this->assertDatabaseMissing('wbcms_panel_language_catalogs', ['id' => $second]);
    $this->assertDatabaseMissing('wbcms_panel_language_lines', ['catalog_id' => $second]);
    $this->assertDatabaseHas('wbcms_panel_languages', ['code' => 'ru', 'active_catalog_id' => $first]);
  }

  private function actor(string $role = 'super_admin'): User
  {
    $actor = $this->cmsActor((new User)->forceFill(['role' => $role]));

    return User::query()->findOrFail($actor->id);
  }

  private function upload(?array $translations = null): UploadedFile
  {
    return UploadedFile::fake()->createWithContent('ru.json', json_encode([
      'format_version' => 1,
      'source_version' => WebBlocks::VERSION,
      'code' => 'ru',
      'name' => 'Русский',
      'translations' => $translations ?? [
        ['key' => 'admin.profile.interface_language', 'text' => 'Язык интерфейса'],
        ['key' => 'notifications.panel_new_messages', 'text' => ':count сообщение|:count сообщения|:count сообщений'],
      ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
  }
}
