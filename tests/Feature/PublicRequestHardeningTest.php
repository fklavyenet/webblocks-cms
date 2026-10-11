<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Public\PageController;
use WebBlocks\Cms\Http\Middleware\ServeCmsPageBeforeRedirectCatchAll;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\VisitorEvent;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Tests\TestCase;

class PublicRequestHardeningTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.public', true);
    $app['config']->set('app.url', 'http://example.test');
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineRoutes($router): void
  {
    foreach (['/probe-api', '/probe-api-other'] as $path) {
      $router->get($path, fn () => response()->json(['ok' => true]))
        ->middleware(['throttle:internal-content-api', 'internal-api.token']);
    }
    $router->get('/host-owned', fn () => response('host'));
  }

  private function seedContent(): void
  {
    $english = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $german = Locale::query()->firstOrCreate(['code' => 'de'], ['name' => 'German', 'is_enabled' => true]);
    $site = Site::query()->create(['name' => 'Example', 'handle' => 'example', 'domain' => 'example.test', 'is_primary' => true]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true], $german->id => ['is_enabled' => true]]);
    foreach (['/', '/about'] as $path) {
      $page = Page::query()->create(['site_id' => $site->id, 'status' => Page::STATUS_PUBLISHED]);
      foreach ([$english, $german] as $locale) {
        $page->translations()->create(['locale_id' => $locale->id, 'name' => 'Example', 'slug' => $path === '/' ? 'home' : 'about', 'path' => $path]);
      }
    }
  }

  #[Test]
  public function rotating_invalid_tokens_and_paths_cannot_reset_the_ip_budget(): void
  {
    config(['cms.internal_api.rate_limit_per_minute' => 10, 'cms.internal_api.ip_rate_limit_per_minute' => 2]);
    $this->withHeader('Authorization', 'Bearer invalid-a')->getJson('/probe-api')->assertUnauthorized();
    $this->withHeader('Authorization', 'Bearer invalid-b')->getJson('/probe-api-other')->assertUnauthorized();
    $this->withHeader('Authorization', 'Bearer invalid-c')->getJson('/probe-api')->assertStatus(429)->assertHeader('Retry-After');
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->getJson('/probe-api')->assertUnauthorized();
  }

  #[Test]
  public function the_token_budget_still_applies_and_matches_authentication_whitespace_normalization(): void
  {
    config(['cms.internal_api.rate_limit_per_minute' => 1, 'cms.internal_api.ip_rate_limit_per_minute' => 10]);
    $issuer = app(CmsApiTokenIssuer::class);
    $first = $issuer->issue('Synthetic first');
    $second = $issuer->issue('Synthetic second');
    $this->withHeader('Authorization', 'Bearer '.$first->plainToken)->getJson('/probe-api')->assertOk();
    $this->withHeader('Authorization', 'Bearer '.$first->plainToken.' ')->getJson('/probe-api')->assertStatus(429);
    $this->withHeader('Authorization', 'Bearer '.$second->plainToken)->getJson('/probe-api')->assertOk();
  }

  #[Test]
  public function malformed_utm_values_are_ignored_without_losing_the_page_view(): void
  {
    $this->seedContent();
    $this->get('http://example.test/about?utm_source[]=bad&utm_medium[nested]=bad&utm_campaign=valid')->assertOk();
    $event = VisitorEvent::query()->latest('id')->firstOrFail();
    $this->assertNull($event->utm_source);
    $this->assertNull($event->utm_medium);
    $this->assertSame('valid', $event->utm_campaign);
    $this->get('http://example.test/about?'.http_build_query(['utm_source' => str_repeat('ü', 300), 'utm_medium' => "one\ntwo"]))->assertOk();
    $event = VisitorEvent::query()->latest('id')->firstOrFail();
    $this->assertSame(255, mb_strlen($event->utm_source));
    $this->assertSame('one two', $event->utm_medium);
  }

  #[Test]
  public function invalid_search_shapes_and_costs_return_422_for_html_and_json(): void
  {
    $this->seedContent();
    foreach (['/search', '/search.json', '/de/search', '/de/search.json'] as $path) {
      foreach (['q[]=bad', 'q[nested]=bad', 'q='.str_repeat('ü', 201), 'q='.urlencode(implode(' ', range(1, 21)))] as $query) {
        $this->get('http://example.test'.$path.'?'.$query)->assertStatus(422)->assertJsonValidationErrors('q');
      }
    }
  }

  #[Test]
  public function empty_and_boundary_searches_remain_valid(): void
  {
    $this->seedContent();
    foreach (['', '?q=', '?q='.str_repeat('ü', 200), '?q='.urlencode(implode(' ', range(1, 20)))] as $query) {
      $this->getJson('http://example.test/search.json'.$query)->assertOk();
    }
    $this->get('http://example.test/search?q=normal')->assertOk();
  }

  #[Test]
  public function pages_share_minute_and_hour_budgets_across_paths_and_locales(): void
  {
    $this->seedContent();
    config(['cms.public_rate_limits.pages_per_minute' => 2, 'cms.public_rate_limits.pages_per_hour' => 3]);
    $this->get('http://example.test/')->assertOk();
    $this->get('http://example.test/about')->assertOk();
    $this->withHeader('User-Agent', 'Googlebot')->get('http://example.test/de/about')->assertStatus(429)->assertHeader('Retry-After');
    $this->travel(61)->seconds();
    $this->get('http://example.test/de/about')->assertOk();
    $this->get('http://example.test/')->assertStatus(429);
    $this->travel(3601)->seconds();
    $this->get('http://example.test/')->assertOk();
  }

  #[Test]
  public function search_has_separate_shared_budgets_without_throttling_host_routes_or_assets(): void
  {
    $this->seedContent();
    config(['cms.public_rate_limits.pages_per_minute' => 1, 'cms.public_rate_limits.search_per_minute' => 1, 'cms.public_rate_limits.search_per_hour' => 2]);
    $this->get('http://example.test/about')->assertOk();
    $this->get('http://example.test/about')->assertStatus(429);
    $this->getJson('http://example.test/search.json')->assertOk();
    $this->get('http://example.test/de/search')->assertStatus(429);
    $this->get('http://example.test/host-owned')->assertOk();
    $this->get('http://example.test/robots.txt')->assertOk();
    $this->get('http://example.test/cms/plugins/missing/file.js')->assertNotFound();
    $this->travel(61)->seconds();
    $this->get('http://example.test/search')->assertOk();
    $this->travel(61)->seconds();
    $this->getJson('http://example.test/search.json')->assertStatus(429);
  }

  #[Test]
  public function protected_routes_all_use_the_intended_limiters(): void
  {
    foreach (['home', 'localized.home', 'pages.show', 'localized.pages.show', 'pages.legacy', 'localized.pages.legacy'] as $name) {
      $this->assertContains('throttle:cms-public-pages', Route::getRoutes()->getByName($name)->gatherMiddleware());
    }
    foreach (['search', 'search.json', 'localized.search', 'localized.search.json'] as $name) {
      $this->assertContains('throttle:cms-public-search', Route::getRoutes()->getByName($name)->gatherMiddleware());
    }
  }

  #[Test]
  public function cms_pages_served_through_the_redirect_bridge_cannot_bypass_throttling(): void
  {
    config(['cms.public_rate_limits.pages_per_minute' => 1]);
    $resolver = Mockery::mock(PageRouteResolver::class);
    $resolver->shouldReceive('findPublishedPageByPath')->andReturn(new Page);
    $view = Mockery::mock(View::class);
    $view->shouldReceive('render')->andReturn('Synthetic page');
    $controller = Mockery::mock(PageController::class);
    $controller->shouldReceive('show')->once()->andReturn($view);
    $this->app->instance(PageRouteResolver::class, $resolver);
    $this->app->instance(PageController::class, $controller);
    Route::get('/bridge/{webblocksRedirectManagerPath}', fn () => response('redirect fallback'))
      ->middleware(ServeCmsPageBeforeRedirectCatchAll::class);
    $this->get('/bridge/first')->assertOk()->assertSee('Synthetic page');
    $this->get('/bridge/second')->assertStatus(429)->assertHeader('Retry-After');
  }

  #[Test]
  public function the_redirect_bridge_does_not_spend_a_cms_budget_when_no_cms_page_matches(): void
  {
    config(['cms.public_rate_limits.pages_per_minute' => 1]);
    $resolver = Mockery::mock(PageRouteResolver::class);
    $resolver->shouldReceive('findPublishedPageByPath')->andReturnNull();
    $this->app->instance(PageRouteResolver::class, $resolver);
    Route::get('/bridge/{webblocksRedirectManagerPath}', fn () => response('redirect fallback'))
      ->middleware(ServeCmsPageBeforeRedirectCatchAll::class);
    foreach (['first', 'second', 'third'] as $path) {
      $this->get('/bridge/'.$path)->assertOk()->assertSee('redirect fallback');
    }
  }
}
