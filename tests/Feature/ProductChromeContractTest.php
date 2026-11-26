<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Updates\CmsRuntimeAssetSynchronizer;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class ProductChromeContractTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('auth.providers.users.model', User::class);
    $app['config']->set('app.name', 'Unrelated host product');
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists(User::class)) {
      class_alias(ProductChromeUser::class, User::class);
    }
  }

  public static function rawTitles(): array
  {
    return [
      [null, 'WebBlocks CMS'],
      ['  ', 'WebBlocks CMS'],
      ['WebBlocks CMS', 'WebBlocks CMS'],
      ['Sayfalar', 'Sayfalar - WebBlocks CMS'],
      ['Edit: WebBlocks CMS guide', 'Edit: WebBlocks CMS guide - WebBlocks CMS'],
      ['Release - WebBlocks CMS', 'Release - WebBlocks CMS - WebBlocks CMS'],
      ['Release · WebBlocks CMS', 'Release · WebBlocks CMS - WebBlocks CMS'],
    ];
  }

  #[Test]
  #[DataProvider('rawTitles')]
  public function raw_titles_preserve_authored_names_and_have_one_layout_suffix(?string $raw, string $expected): void
  {
    $this->assertSame($expected, app(SystemSettings::class)->adminBrowserTitle($raw));
  }

  #[Test]
  public function panel_title_uses_the_localized_view_instead_of_a_controller_override(): void
  {
    $user = User::forceCreate(['name' => 'Synthetic operator', 'email' => 'chrome@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true, 'admin_locale' => 'tr']);
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);
    request()->setRouteResolver(fn () => Route::getRoutes()->getByName('admin.dashboard'));
    view()->share('errors', new ViewErrorBag);
    $raw = 'Düzenle: WebBlocks CMS <guide>';
    $html = Blade::render("@extends('webblocks-cms::layouts.admin', ['title' => \$screen, 'heading' => 'Generic parent']) @section('content') @include('webblocks-cms::admin.partials.page-header', ['title' => \$screen]) @endsection", ['screen' => $raw, 'adminBrowserTitle' => 'Old English Title - WebBlocks CMS', 'faviconUrl' => '/host.ico']);
    $xpath = $this->document($html);
    $this->assertSame(1, $xpath->query('//title')->length);
    $this->assertSame($raw.' - WebBlocks CMS', $xpath->evaluate('string(//title)'));
    $this->assertSame($raw, $xpath->evaluate('string(//h1)'));
    $this->assertSame($raw, $xpath->evaluate('string(//header//span[@aria-current="page"])'));
    $this->assertStringNotContainsString('<guide>', $html);
    $this->assertStringNotContainsString('/host.ico', $html);
    $this->assertFavicons($html);
    foreach (['/webadmin/users' => 'users', '/webadmin/profile' => 'user-round'] as $href => $icon) {
      $this->assertSame(1, $xpath->query('//a[@href="'.route($href === '/webadmin/users' ? 'admin.users.index' : 'admin.profile.edit').'"]/i[contains(@class,"wb-icon-'.$icon.'")][@aria-hidden="true"]')->length);
    }
    $this->assertSame(1, $xpath->query('//summary[i[contains(@class,"wb-icon-plug")]]/span[@class="wb-nav-group-label"]')->length);
    $this->exportFixture('panel', $html);
  }

  #[Test]
  public function auth_and_panel_errors_share_localized_titles_and_product_favicons(): void
  {
    app()->setLocale('tr');
    view()->share('errors', new ViewErrorBag);
    $text = app(CmsTranslator::class);
    foreach ([
      'install.notice' => ['auth.install_title', []],
      'auth.login' => ['auth.login_title', []],
      'auth.forgot-password' => ['auth.reset_title', []],
      'auth.reset-password' => ['auth.new_password_title', ['request' => request()->merge(['email' => 'synthetic@example.test']), 'token' => 'synthetic-token']],
    ] as $view => [$key, $data]) {
      $html = view('webblocks-cms::'.$view, $data)->render();
      $this->assertSame($text->admin($key, 'tr').' - WebBlocks CMS', $this->document($html)->evaluate('string(//title)'));
      $this->assertFavicons($html);
      $this->exportFixture(str_replace('.', '-', $view), $html);
    }
    foreach (['errors.admin-404', 'errors.admin-http', 'layouts.admin-recovery'] as $view) {
      $html = view('webblocks-cms::'.$view, ['title' => 'Erişim reddedildi', 'body' => 'Synthetic error'])->render();
      $this->assertSame('Erişim reddedildi - WebBlocks CMS', $this->document($html)->evaluate('string(//title)'));
      $this->assertFavicons($html);
      $this->exportFixture(str_replace('.', '-', $view), $html);
    }
  }

  #[Test]
  public function profile_title_tracks_the_authenticated_accounts_current_name(): void
  {
    $user = User::forceCreate(['name' => 'Synthetic operator', 'email' => 'profile-chrome@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true, 'admin_locale' => 'tr']);
    $this->withoutMiddleware(EnsureInstalled::class);
    foreach (['Synthetic operator', 'Updated WebBlocks CMS operator'] as $name) {
      $user->forceFill(['name' => $name])->save();
      $this->actingAs($user->fresh());
      $html = $this->get(route('admin.profile.edit'))->assertOk()->getContent();
      $title = app(CmsTranslator::class)->admin('profile.edit_title', 'tr', ['name' => $name]);
      $xpath = $this->document($html);
      $this->assertSame($title, $xpath->evaluate('string(//h1)'));
      $this->assertSame($title, $xpath->evaluate('string(//header//span[@aria-current="page"])'));
      $this->assertSame($title.' - WebBlocks CMS', $xpath->evaluate('string(//title)'));
    }
  }

  #[Test]
  public function public_seo_and_custom_site_favicons_remain_independent(): void
  {
    $html = view('webblocks-cms::partials.head-meta', ['title' => 'Authored SEO · Custom site', 'faviconUrl' => '/site-icon.svg'])->render();
    $this->assertSame('Authored SEO · Custom site', $this->document($html)->evaluate('string(//title)'));
    $this->assertStringContainsString('href="/site-icon.svg"', $html);
    $this->assertStringNotContainsString('cms/brand/', $html);
  }

  #[Test]
  public function package_asset_sync_delivers_the_ico_without_replacing_the_host_favicon(): void
  {
    $target = sys_get_temp_dir().'/cms-chrome-assets-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($target.'/public');
    file_put_contents($target.'/public/favicon.ico', 'Host-owned icon');
    try {
      $output = [];
      app(CmsRuntimeAssetSynchronizer::class)->sync(dirname(__DIR__, 2), $target, $output);
      $this->assertSame('Host-owned icon', file_get_contents($target.'/public/favicon.ico'));
      $this->assertSame(hash_file('sha256', dirname(__DIR__, 2).'/public/cms/brand/favicon.ico'), hash_file('sha256', $target.'/public/cms/brand/favicon.ico'));
      $this->assertContains('cms/brand/favicon.ico', WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ASSET_FILES);
      $this->assertContains('cms/brand/favicon.ico', WebBlocksCmsServiceProvider::ROOT_PUBLIC_ASSET_COMPATIBILITY_FILES);
    } finally {
      File::deleteDirectory($target);
    }
  }

  private function assertFavicons(string $html): void
  {
    $xpath = $this->document($html);
    foreach (['favicon.svg' => ['image/svg+xml', 'any'], 'favicon-16x16.png' => ['image/png', '16x16'], 'favicon-32x32.png' => ['image/png', '32x32'], 'favicon.ico' => ['image/x-icon', '16x16 32x32 48x48'], 'apple-touch-icon.png' => ['image/png', '180x180']] as $file => [$type, $size]) {
      $url = WebBlocks::faviconUrl($file);
      $this->assertSame(1, $xpath->query('//link[@href="'.$url.'"][@type="'.$type.'"][@sizes="'.$size.'"]')->length, $file);
    }
  }

  private function document(string $html): DOMXPath
  {
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($document);
  }

  private function exportFixture(string $name, string $html): void
  {
    $directory = getenv('WEBBLOCKS_PRODUCT_CHROME_FIXTURES');
    if ($directory && is_dir($directory)) {
      file_put_contents($directory.'/'.$name.'.html', $html);
    }
  }
}

class ProductChromeUser extends CmsSqlTestUser
{
  protected $table = 'users';
}
