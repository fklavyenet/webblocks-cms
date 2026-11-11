<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

final class PublicThemesTest extends TestCase
{
  private const PACKAGES = '/webadmin/api/system/themes';

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('webblocks-cms.routes.public', true);
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

  public function test_panel_installs_passively_compares_and_restores_with_no_content_changes(): void
  {
    $this->actingAs($this->actor());
    [$site, $page] = $this->sitePage('one', true);
    [$other, $otherPage] = $this->sitePage('two');
    $content = DB::table('wbcms_page_translations')->orderBy('id')->get()->toArray();
    $this->get(route('admin.system.themes.index'))->assertOk()->assertSee('Upload theme');
    $this->post(route('admin.system.themes.store'), ['package' => $this->archive()])->assertRedirect();
    $version = DB::table('wbcms_theme_versions')->value('id');
    $checksum = DB::table('wbcms_theme_versions')->value('checksum');
    $overview = route('admin.sites.themes.index', $site);

    $this->get(route('admin.sites.edit', [$site, 'tab' => 'theme']))->assertOk()->assertSee($overview);
    $this->assertDatabaseCount('wbcms_site_theme_changes', 0);
    $workspace = $this->get(route('admin.sites.themes.index', [$site, 'version' => $version, 'page' => $page->id, 'button_corners' => 'soft']))
      ->assertOk()->assertSee('Current appearance')->assertSee('Candidate appearance')->assertSee('Activate on this site');
    preg_match('/name="confirmation" value="([^"]+)"/', $workspace->getContent(), $matches);
    $preview = route('admin.sites.themes.preview', [$site, 'version' => $version, 'page' => $page->id, 'mode' => 'dark']);
    $this->get($preview)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
      ->assertSee('data-mode="dark"', false)->assertSee('data-wb-theme-package="editorial"', false)->assertSee('Title for one');
    $this->assertDatabaseCount('wbcms_site_theme_changes', 0);

    $activate = route('admin.sites.themes.activate', $site);
    $this->post($activate, ['confirmation' => $matches[1]])->assertRedirect($overview);
    $this->get('/one')->assertOk()->assertSee('data-wb-theme-corners="soft"', false)->assertSee('Title for one');
    $this->get(route('admin.pages.preview', $otherPage))->assertOk()->assertDontSee('data-wb-theme-package=', false);
    $assetUrl = route('public-theme-assets.show', [$version, $checksum, 'theme.css'], false);
    $this->get($assetUrl)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertSee('@scope', false);
    $this->get(route('public-theme-assets.show', [$version, $checksum, 'assets/dot.png'], false))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->assertDatabaseMissing('wbcms_site_theme_changes', ['site_id' => $other->id]);

    $this->from($overview)->post($activate, ['confirmation' => $matches[1]])->assertSessionHasErrors('confirmation');
    $revision = DB::table('wbcms_site_theme_changes')->where('site_id', $site->id)->value('id');
    $this->from($overview)->delete(route('admin.system.themes.destroy', $version))->assertSessionHasErrors('package');
    $this->post(route('admin.sites.themes.restore', $site), ['expected' => $revision])->assertRedirect($overview);
    $this->get('/one')->assertOk()->assertDontSee('data-wb-theme-package=', false)->assertSee('data-wb-public-theme="atlas"', false);
    $this->assertEquals($content, DB::table('wbcms_page_translations')->orderBy('id')->get()->toArray());
  }

  public function test_api_checks_scope_archives_layouts_and_version_changes(): void
  {
    $issuer = app(CmsApiTokenIssuer::class);
    $root = $this->actor();
    [$site, $page] = $this->sitePage('primary', true);
    [$other, $otherPage] = $this->sitePage('secondary');
    $ordinary = $issuer->issue('Site settings', $root, allowedSiteIds: [$site->id])->plainToken;
    $system = $issuer->issue('Theme packages', $root, ['themes.manage'])->plainToken;
    $this->withToken($ordinary)->getJson(self::PACKAGES)->assertForbidden();
    $this->withToken($ordinary)->getJson('/webadmin/api/sites/'.$other->id.'/themes')->assertForbidden();

    $this->withToken($system)->withHeader('Accept', 'application/json')
      ->post(self::PACKAGES, ['package' => $this->archive(css: '} body { color: red; } {')])
      ->assertUnprocessable()->assertJsonValidationErrors('package');
    $this->post(self::PACKAGES, ['package' => $this->archive(css: ':scope { background: url(https://example.com/image.png); }')])
      ->assertUnprocessable()->assertJsonValidationErrors('package');
    $this->assertDatabaseCount('wbcms_theme_versions', 0);
    $v1 = $this->post(self::PACKAGES, ['package' => $this->archive()])->assertCreated()->json('version.id');
    $this->post(self::PACKAGES, ['package' => $this->archive(css: ':scope { color: red; }')])
      ->assertUnprocessable()->assertJsonValidationErrors('package');

    $endpoint = '/webadmin/api/sites/'.$site->id.'/themes';
    $this->withToken($ordinary)->getJson($endpoint)->assertOk()->assertJsonPath('current', null);
    $this->get($endpoint.'/preview?version='.$v1.'&page='.$otherPage->id)->assertNotFound();
    $confirmation = $this->get($endpoint.'/preview?version='.$v1.'&page='.$page->id)->assertOk()->headers->get('X-WebBlocks-Theme-Confirmation');
    $this->assertNotNull($confirmation);
    $docs = Page::query()->create(['site_id' => $site->id, 'title' => 'Docs', 'slug' => 'docs', 'settings' => ['public_shell' => 'docs']]);
    $this->postJson($endpoint.'/activate', ['confirmation' => $confirmation])->assertUnprocessable()->assertJsonValidationErrors('version');
    $this->assertNull($this->get($endpoint.'/preview?version='.$v1.'&page='.$page->id)->assertOk()->headers->get('X-WebBlocks-Theme-Confirmation'));
    $docs->delete();

    $first = $this->postJson($endpoint.'/activate', ['confirmation' => $confirmation])->assertOk()->json('revision');
    $this->withToken($system)->post(self::PACKAGES, ['package' => $this->archive('1.1.0')])->assertCreated();
    $v2 = DB::table('wbcms_theme_versions')->where('version', '1.1.0')->value('id');
    $this->withToken($ordinary)->getJson($endpoint)->assertOk()->assertJsonPath('current.theme_version_id', $v1);
    $secondConfirmation = $this->get($endpoint.'/preview?version='.$v2.'&button_corners=sharp')->assertOk()->headers->get('X-WebBlocks-Theme-Confirmation');
    $second = $this->postJson($endpoint.'/activate', ['confirmation' => $secondConfirmation])->assertOk()->json('revision');
    $this->postJson($endpoint.'/restore', ['expected' => $second, 'revision' => $first])->assertOk();
    $this->getJson($endpoint)->assertJsonPath('current.theme_version_id', $v1)->assertJsonPath('current.button_corners', 'default');

    $this->withToken($system)->deleteJson(self::PACKAGES.'/'.$v2)->assertUnprocessable();
    $unused = $this->post(self::PACKAGES, ['package' => $this->archive('1.2.0')])->assertCreated()->json('version.id');
    $this->deleteJson(self::PACKAGES.'/'.$unused)->assertOk();
    $this->assertDatabaseMissing('wbcms_theme_assets', ['theme_version_id' => $unused]);
  }

  private function actor(): User
  {
    return User::query()->findOrFail($this->cmsActor((new User)->forceFill(['role' => 'super_admin']))->id);
  }

  private function sitePage(string $handle, bool $primary = false): array
  {
    $site = Site::query()->create(['name' => $handle, 'handle' => $handle, 'is_primary' => $primary, 'public_theme_preset' => 'atlas']);
    $page = Page::query()->create([
      'site_id' => $site->id, 'title' => 'Title for '.$handle, 'slug' => $handle, 'status' => Page::STATUS_PUBLISHED,
    ]);

    return [$site, $page];
  }

  private function archive(string $version = '1.0.0', string $css = ':scope { background-image: url(assets/dot.png); }'): UploadedFile
  {
    $path = tempnam(sys_get_temp_dir(), 'wbcms-test-theme-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('webblocks-theme.json', json_encode([
      'format_version' => 1, 'name' => 'Editorial', 'handle' => 'editorial', 'version' => $version,
      'license' => 'MIT', 'cms' => '^1.131.1', 'ui' => '^2.35.1', 'layouts' => ['default'], 'button_corners' => true,
    ], JSON_THROW_ON_ERROR));
    $zip->addFromString('theme.css', $css);
    $zip->addFromString('assets/dot.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6O0AAAAASUVORK5CYII='));
    $zip->close();
    $file = UploadedFile::fake()->createWithContent('editorial.zip', file_get_contents($path));
    unlink($path);

    return $file;
  }
}
