<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Http\Middleware\UseAdminLocale;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Mail\VerificationEmailTemplates;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\PanelLanguages;
use WebBlocks\Cms\Support\Translations\PanelLanguageSource;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

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
