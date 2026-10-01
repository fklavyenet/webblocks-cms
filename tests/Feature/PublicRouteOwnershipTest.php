<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

class PublicRouteOwnershipTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.public.load_routes', true);
    $app['config']->set('app.url', 'https://routing.test');
  }

  protected function defineRoutes($router): void
  {
    foreach (['up', 'settings', 'de', 'en', 'products', 'products/{id}', 'account/settings', 'orders/{order}/items/{item}'] as $path) {
      $router->get($path, fn () => response('host:'.$path))->name('host.'.$path);
    }
    $router->get('host-missing', fn () => abort(404));
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function normal_host_routes_win_over_all_cms_page_patterns(): void
  {
    foreach (['up', 'settings', 'de', 'en', 'products', 'products/42', 'account/settings', 'orders/42/items/7'] as $path) {
      $this->get('/'.$path)->assertOk()->assertSee('host:', false);
    }
  }

  #[Test]
  public function cms_content_is_a_real_fallback_and_keeps_its_named_routes(): void
  {
    foreach (['home', 'localized.home', 'pages.show', 'localized.pages.show', 'pages.legacy', 'localized.pages.legacy'] as $name) {
      $this->assertTrue(Route::getRoutes()->getByName($name)->isFallback, $name);
    }
    $this->assertFalse(Route::getRoutes()->getByName('search')->isFallback);
    $this->assertFalse(Route::getRoutes()->getByName('sitemap')->isFallback);
    $this->assertFalse(Route::getRoutes()->getByName('content-ratings.store')->isFallback);
  }

  #[Test]
  public function unclaimed_home_localized_and_nested_content_still_render(): void
  {
    $site = $this->site();
    $home = $this->page($site, '/');
    $about = $this->page($site, '/about');
    $nested = $this->page($site, '/blog/article');
    $german = Locale::query()->create(['code' => 'de', 'name' => 'German', 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$german->id => ['is_enabled' => true]]);
    $about->translations()->create(['locale_id' => $german->id, 'name' => 'About German', 'slug' => 'about', 'path' => '/about']);

    foreach (['/' => $home, '/about' => $about, '/blog/article' => $nested, '/de/about' => $about] as $path => $page) {
      $this->get('https://routing.test'.$path)->assertOk()->assertViewHas('page', fn ($actual) => $actual->id === $page->id);
    }
    $this->get('/en/about')->assertNotFound();
  }

  #[Test]
  public function a_host_controller_404_never_falls_through_to_cms_content(): void
  {
    $site = $this->site();
    $this->page($site, '/host-missing');
    $this->get('/host-missing')->assertNotFound();
  }

  #[Test]
  public function a_normal_host_catch_all_is_not_reclassified(): void
  {
    Route::get('/{hostPath}', fn () => response('host catch-all'))->where('hostPath', '.*');
    $this->get('/unclaimed/path')->assertOk()->assertSee('host catch-all');
  }

  #[Test]
  public function a_host_named_home_does_not_disable_other_cms_endpoints(): void
  {
    Route::get('/', fn () => response('host homepage'))->name('home');
    $this->get('/')->assertOk()->assertSee('host homepage');
    $this->assertNotNull(Route::getRoutes()->getByName('search'));
    $site = $this->site();
    $this->page($site, '/about');
    $this->get('https://routing.test/about')->assertOk()->assertViewHas('page');
  }

  private function site(): Site
  {
    $english = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $site = Site::query()->create(['name' => 'Routing', 'handle' => 'routing', 'domain' => 'routing.test', 'is_primary' => true]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true]]);

    return $site;
  }

  private function page(Site $site, string $path): Page
  {
    $page = Page::query()->create(['site_id' => $site->id, 'status' => Page::STATUS_PUBLISHED]);
    $page->translations()->create(['locale_id' => Locale::query()->where('code', 'en')->value('id'), 'name' => 'Routing page', 'slug' => $path === '/' ? 'home' : basename($path), 'path' => $path]);

    return $page;
  }
}
