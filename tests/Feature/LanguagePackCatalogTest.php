<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Mail\VerificationEmailTemplates;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

final class LanguagePackCatalogTest extends TestCase
{
  private const CATALOG = 'https://catalog.example.test';

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
    config(['webblocks-plugins.catalog.extensions_base_url' => self::CATALOG]);
    Http::preventStrayRequests();
  }

  public function test_only_super_admins_can_browse_and_download_the_catalog(): void
  {
    Http::fake();
    $this->actingAs($this->actor('editor'));
    $this->get($this->browseUrl())->assertForbidden();
    $this->post($this->browseUrl())->assertForbidden();

    $this->actingAs($this->actor());
    $this->get(route('admin.system.panel-languages.index'))->assertOk()->assertSee('Browse language packs');
    Http::assertNothingSent();
  }

  public function test_panel_only_catalog_releases_are_hidden_and_rejected(): void
  {
    $this->actingAs($this->actor());
    $release = $this->release('panel', '{}');
    $this->serve($release, '{}');

    $this->get($this->browseUrl())->assertOk()->assertSee('No compatible language packs')
      ->assertDontSee('Русский')->assertDontSee('Download for review');
    Http::assertSent(fn ($request) => $request->url() === self::CATALOG.'/api/language-packs?cms='.WebBlocks::VERSION);
    $this->from($this->browseUrl())->post($this->browseUrl(), $this->selection($release))
      ->assertRedirect($this->browseUrl())->assertSessionHasErrors('format');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/download'));
    $this->assertDatabaseCount('wbcms_native_language_packs', 0);
  }

  public function test_native_archive_installs_and_updates_the_full_locale_from_sql(): void
  {
    $this->actingAs($this->actor());
    $publicLocales = Locale::query()->pluck('code')->all();
    $publicDefault = app(SystemSettings::class)->get(SystemSettings::DEFAULT_LOCALE);
    $directory = sys_get_temp_dir().'/webblocks-catalog-lang-'.bin2hex(random_bytes(8));
    File::makeDirectory($directory);

    try {
      (new Process([PHP_BINARY, 'scripts/check-language-pack.php', '--build', $directory], dirname(__DIR__, 2)))->mustRun();
      $path = $directory.'/webblocks-cms-ru-'.WebBlocks::VERSION.'.zip';
      $archive = File::get($path);
      $release = $this->release('native', $archive);
      $this->serve($release, $archive);

      $this->get($this->browseUrl())->assertOk()->assertSee('Language pack catalog')
        ->assertSee('wb-cms-catalog-card', false)->assertSee('wb-icon-languages', false)
        ->assertSeeInOrder(['Русский', 'Latest release: 1.0.0', 'aria-label="Install · Русский"'], false);
      $this->post($this->browseUrl(), $this->selection($release))
        ->assertRedirect(route('admin.system.panel-languages.index'));
      $this->get(route('admin.system.panel-languages.index'))
        ->assertOk()->assertSee('Language pack installed.')->assertSee('Русский');
      $this->assertFileDoesNotExist(lang_path('vendor/webblocks-cms/ru/admin.php'));
      $this->assertDatabaseHas('wbcms_native_language_packs', ['code' => 'ru', 'release_version' => '1.0.0']);
      $this->assertDatabaseCount('wbcms_native_language_packs', 1);
      $this->assertGreaterThan(7000, DB::table('wbcms_native_language_lines')->count());
      $this->assertSame($publicLocales, Locale::query()->pluck('code')->all());
      $this->assertSame($publicDefault, app(SystemSettings::class)->get(SystemSettings::DEFAULT_LOCALE));
      $this->assertSame('Поиск', app(CmsTranslator::class)->public('search.title', 'ru'));
      $this->assertSame('Проверить обновления', trans('publisher-client::updates.check_button', [], 'ru'));
      $this->assertSame('Восстановить установку', trans('webblocks-cms::update_schema.repair', [], 'ru'));
      $this->assertSame('{code} — ваш код подтверждения для CMS', app(VerificationEmailTemplates::class)->defaults('ru')['subject']);
      $this->assertSame('21 непрочитанное сообщение', trans_choice('webblocks-cms::notifications.panel_new_messages', 21, [], 'ru'));
      $token = app(CmsApiTokenIssuer::class)->issue('Manage language packs', auth()->user(), ['panel-languages.manage'])->plainToken;
      $this->withToken($token)->putJson(route('internal-content-api.system.panel-languages.default'), ['code' => 'ru'])
        ->assertOk()->assertJsonPath('ok', true);
      $this->withoutHeader('Authorization');
      $this->get(route('admin.profile.edit'))->assertOk()->assertSee('lang="ru"', false)->assertSee('Язык интерфейса');

      $zip = new ZipArchive;
      $this->assertTrue($zip->open($path));
      $entry = 'lang/vendor/webblocks-cms/ru/admin.php';
      $translatedFile = str_replace('Язык интерфейса', 'Язык панели', $zip->getFromName($entry));
      $zip->addFromString($entry, $translatedFile."\nreturn array();\n");
      $zip->close();
      $unsupported = File::get($path);
      $release = $this->release('native', $unsupported, '1.1.0');
      $this->serve($release, $unsupported);
      $this->post($this->browseUrl(), $this->selection($release))
        ->assertRedirect($this->browseUrl())->assertSessionHasErrors('catalog');
      $this->get(route('admin.profile.edit'))->assertOk()->assertSee('Язык интерфейса');
      $this->assertDatabaseHas('wbcms_native_language_packs', ['code' => 'ru', 'release_version' => '1.0.0']);

      $this->assertTrue($zip->open($path));
      $zip->addFromString($entry, $translatedFile);
      $zip->close();
      $updated = File::get($path);
      $release = $this->release('native', $updated, '1.1.0');
      $this->serve($release, $updated);
      $this->get($this->browseUrl())->assertOk()->assertSee('Обновить');
      $this->post($this->browseUrl(), $this->selection($release))->assertRedirect();
      $this->get(route('admin.profile.edit'))->assertOk()->assertSee('Язык панели');
      $this->assertDatabaseHas('wbcms_native_language_packs', ['code' => 'ru', 'release_version' => '1.1.0']);

      $staleKey = 'search.title';
      DB::table('wbcms_native_language_lines')->where(['language_code' => 'ru', 'catalog_group' => 'webblocks-cms::public', 'translation_key' => $staleKey])
        ->update(['source_hash' => str_repeat('0', 64)]);
      app('translator')->setLoaded([]);
      config(['app.fallback_locale' => 'en']);
      $this->assertSame('Search', app(CmsTranslator::class)->public('search.title', 'ru'));
    } finally {
      File::deleteDirectory($directory);
    }
  }

  public function test_release_identity_download_integrity_and_archive_format_are_checked_before_install(): void
  {
    $this->actingAs($this->actor());
    $release = $this->release('native', 'not a zip');
    $this->serve($release, 'not a zip');
    $this->post($this->browseUrl(), ['checksum' => str_repeat('0', 64)] + $this->selection($release))
      ->assertRedirect($this->browseUrl())->assertSessionHasErrors('catalog');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/download'));
    $this->post($this->browseUrl(), $this->selection($release))
      ->assertRedirect($this->browseUrl())->assertSessionHasErrors('catalog');
    $this->assertStringContainsString('Upload a valid Full CMS language pack ZIP for this CMS version.', session('errors')->first('catalog'));
    $this->assertStringContainsString('Русский · ru · 1.0.0 · CMS '.WebBlocks::VERSION, session('errors')->first('catalog'));
    $this->assertStringContainsString('Diagnostic reference', session('errors')->first('catalog'));

    $this->serve($release, 'other zip');
    $this->post($this->browseUrl(), $this->selection($release))
      ->assertRedirect($this->browseUrl())->assertSessionHasErrors('catalog');
    $this->assertStringContainsString('The download failed or could not be verified.', session('errors')->first('catalog'));
    $this->assertStringContainsString('Русский · ru · 1.0.0 · CMS '.WebBlocks::VERSION, session('errors')->first('catalog'));
    $this->assertStringContainsString('Diagnostic reference', session('errors')->first('catalog'));
    $this->assertDatabaseCount('wbcms_native_language_packs', 0);
    $this->assertDatabaseCount('wbcms_native_language_lines', 0);
  }

  public function test_catalog_unavailable_and_incompatible_cms_version_are_handled(): void
  {
    $this->actingAs($this->actor());
    $this->serve(['cms' => '1.0.0'] + $this->release('native', '{}'), '{}');
    $this->get($this->browseUrl())->assertOk()->assertSee('The Extensions language catalog is temporarily unavailable.');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/download'));
  }

  public function test_update_migration_preserves_legacy_data_without_loading_panel_only_languages(): void
  {
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_11_20_180000_create_native_language_packs.php';
    $migration->down();
    $this->assertFalse(Schema::hasTable('wbcms_native_language_packs'));
    DB::table('wbcms_panel_languages')->insert(['code' => 'ru']);
    $id = DB::table('wbcms_panel_language_catalogs')->insertGetId([
      'language_code' => 'ru',
      'name' => 'Русский',
      'source_version' => WebBlocks::VERSION,
      'created_at' => now(),
    ]);
    DB::table('wbcms_panel_languages')->where('code', 'ru')->update(['active_catalog_id' => $id]);
    app(SystemSettings::class)->save([SystemSettings::ADMIN_LOCALE => 'ru']);
    $this->actingAs($this->actor());
    $url = route('admin.system.panel-languages.index');
    $this->get($url)->assertOk()->assertDontSee('Browse language packs');

    $migration->up();
    $this->assertTrue(Schema::hasTable('wbcms_native_language_packs'));
    $this->get($url)->assertOk()->assertSee('Browse language packs')->assertDontSee('Русский');
    $this->get(route('admin.profile.edit'))->assertOk()->assertSee('lang="en"', false);
    $this->assertDatabaseHas('wbcms_panel_languages', ['code' => 'ru', 'active_catalog_id' => $id]);
    $this->assertSame('ru', app(SystemSettings::class)->get(SystemSettings::ADMIN_LOCALE));
  }

  private function actor(string $role = 'super_admin'): User
  {
    $user = $this->cmsActor((new User)->forceFill(['role' => $role]));

    return User::query()->findOrFail($user->id);
  }

  private function browseUrl(): string
  {
    return route('admin.system.panel-languages.extensions.index');
  }

  private function release(string $format, string $artifact, string $version = '1.0.0'): array
  {
    return [
      'code' => 'ru',
      'name' => 'Русский',
      'version' => $version,
      'cms' => WebBlocks::VERSION,
      'format' => $format,
      'sha256' => hash('sha256', $artifact),
      'size' => strlen($artifact),
    ];
  }

  private function serve(array $release, string $artifact): void
  {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake([
      self::CATALOG.'/api/language-packs?*' => Http::response(['data' => [$release]]),
      self::CATALOG.'/language-packs/ru/releases/'.$release['version'].'/'.$release['format'].'/download' => fn () => Http::response($artifact),
    ]);
  }

  private function selection(array $release): array
  {
    return ['code' => 'ru', 'version' => $release['version'], 'format' => $release['format'], 'checksum' => $release['sha256']];
  }
}
