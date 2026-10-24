<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Public\PageController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Memberships\MemberDirectory;
use WebBlocks\Cms\Support\Memberships\MemberIdentity;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Plugins\PluginServiceRegistry;
use WebBlocks\Cms\Tests\TestCase;

/** Reproducible local query profiles; timings are not production benchmarks. */
class PublicRenderingPerformanceTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function public_page_profiles_preserve_translated_content_and_links(): void
  {
    [$site, $locales, $pages] = $this->fixture();
    config()->set('cms.visitor_reports.enabled', false);
    config()->set('cms.multisite.unknown_host_fallback', true);
    session()->put('_token', str_repeat('p', 40));

    foreach ($pages as $name => $page) {
      foreach ($locales as $code => $locale) {
        for ($sample = 0; $sample < 3; $sample++) {
          $path = $name === 'home' ? '/' : '/'.$name;
          $request = $this->bindRequest($path, $code);
          [$profile, $html] = $this->profile(function () use ($request, $path): string {
            $controller = app(PageController::class);
            $response = $path === '/' ? $controller->home($request) : $controller->show($request, ltrim($path, '/'));

            return $response instanceof View ? $response->render() : $response->getContent();
          });

          $this->assertStringContainsString('Content '.$name, $html);
          $this->assertStringContainsString($code === 'tr' ? '/tr/hedef-0' : '/target-0', $html);
          $this->record(['scenario' => $name, 'locale' => $code, 'sample' => $sample, ...$profile]);
        }
      }
    }
  }

  #[Test]
  public function repeated_localized_links_do_not_repeat_database_work(): void
  {
    [$site] = $this->fixture();
    $profiles = [];
    foreach ([1, 25, 100] as $count) {
      $this->bindRequest('/docs', 'tr');
      [$profile, $html] = $this->profile(function () use ($site, $count): string {
        $urls = [];
        for ($index = 0; $index < $count; $index++) {
          // Resolve through the container on every call, as Blade/model helpers do.
          $urls[] = app(PageRouteResolver::class)->localizedPublicUrl('/target-0?plan=pro#details', 'tr', $site);
          $urls[] = app(PageRouteResolver::class)->localizedPublicUrl('/missing?plan=pro#details', 'tr', $site);
        }

        return implode("\n", $urls);
      });
      $this->assertStringContainsString('/tr/hedef-0?plan=pro#details', $html);
      $this->assertStringContainsString('/missing?plan=pro#details', $html);
      $profiles[$count] = $profile['queries'];
      $this->record(['scenario' => 'repeated-links', 'repetitions' => $count, ...$profile]);
    }
    $this->assertGreaterThan(0, $profiles[1]);
    $this->assertSame($profiles[1], $profiles[25]);
    $this->assertSame($profiles[1], $profiles[100]);
  }

  #[Test]
  public function page_owned_tree_loading_does_not_reload_descendants_at_each_level(): void
  {
    [$site, $locales] = $this->fixture();
    config()->set('cms.visitor_reports.enabled', false);
    config()->set('cms.multisite.unknown_host_fallback', true);
    $slot = SlotType::query()->where('slug', 'main')->firstOrFail();
    $profiles = [];
    foreach ([1, 4, 8] as $depth) {
      $page = $this->page($site, $locales, '/tree-'.$depth, '/tree-'.$depth, 'Tree '.$depth);
      PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slot->id]);
      $parent = null;
      for ($index = 0; $index < $depth; $index++) {
        $parent = Block::query()->create(['page_id' => $page->id, 'parent_id' => $parent?->id, 'type' => 'section', 'slot_type_id' => $slot->id, 'status' => 'published']);
      }
      Block::query()->create(['page_id' => $page->id, 'parent_id' => $parent->id, 'type' => 'plain_text', 'title' => 'Nested leaf', 'content' => 'Visible leaf', 'slot_type_id' => $slot->id, 'status' => 'published']);
      $draft = Block::query()->create(['page_id' => $page->id, 'type' => 'section', 'slot_type_id' => $slot->id, 'status' => 'draft']);
      Block::query()->create(['page_id' => $page->id, 'parent_id' => $draft->id, 'type' => 'plain_text', 'content' => 'Hidden under draft', 'slot_type_id' => $slot->id, 'status' => 'published']);
      $request = $this->bindRequest('/tree-'.$depth, 'en');
      [$profile, $html] = $this->profile(fn () => app(PageController::class)->show($request, 'tree-'.$depth)->render());
      $this->assertSame(1, substr_count($html, 'Visible leaf'));
      $this->assertStringNotContainsString('Hidden under draft', $html);
      $profiles[$depth] = $profile['queries'];
      $this->record(['scenario' => 'page-tree', 'depth' => $depth, ...$profile]);
    }
    $this->assertSame($profiles[1], $profiles[4]);
    $this->assertSame($profiles[1], $profiles[8]);
  }

  #[Test]
  public function protected_and_member_session_responses_keep_their_privacy_headers(): void
  {
    [$site, $locales, $pages] = $this->fixture();
    config()->set('cms.visitor_reports.enabled', false);
    config()->set('cms.multisite.unknown_host_fallback', true);
    $request = $this->bindRequest('/docs', 'en');
    $request->session()->put('webblocks_memberships', ['synthetic' => true]);
    $response = app(PageController::class)->show($request, 'docs');
    $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $this->assertSame('Cookie', $response->headers->get('Vary'));

    DB::table('wbcms_page_access_rules')->insert(['page_id' => $pages['docs']->id, 'package_id' => null]);
    $directory = \Mockery::mock(MemberDirectory::class);
    $directory->shouldReceive('current')->andReturn(new MemberIdentity(1, $site->id, 'Synthetic member', 'member@example.test', true));
    $registry = \Mockery::mock(PluginServiceRegistry::class);
    $registry->shouldReceive('resolve')->andReturn($directory);
    app()->instance(PluginServiceRegistry::class, $registry);
    $response = app(PageController::class)->show($request, 'docs');
    $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $this->assertSame('Cookie', $response->headers->get('Vary'));
    $this->assertSame('noindex, noarchive', $response->headers->get('X-Robots-Tag'));
    $pages['docs']->update(['status' => 'draft']);
    $this->assertNull(app(PageRouteResolver::class)->findPublishedPageByPath($request, '/docs'));
  }

  private function fixture(): array
  {
    $english = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $turkish = Locale::query()->create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $site = Site::query()->create(['name' => 'Synthetic studio', 'handle' => 'performance', 'is_primary' => true]);
    $site->locales()->sync([$english->id => ['is_enabled' => true], $turkish->id => ['is_enabled' => true]]);
    $main = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $sidebar = SlotType::query()->create(['name' => 'Sidebar', 'slug' => 'sidebar', 'status' => 'published', 'sort_order' => 1]);
    $navType = BlockType::query()->create(['name' => 'Sidebar navigation', 'slug' => 'sidebar-navigation', 'is_active' => true]);
    $locales = ['en' => $english, 'tr' => $turkish];
    $pages = [];
    foreach (['home' => 3, 'docs' => 12, 'guides' => 48, 'article' => 8, 'blog' => 6] as $name => $links) {
      $page = $this->page($site, $locales, $name === 'home' ? '/' : '/'.$name, $name === 'home' ? '/' : '/'.$name, 'Page '.$name);
      $pages[$name] = $page;
      PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $main->id]);
      PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $sidebar->id]);
      Block::query()->create(['page_id' => $page->id, 'type' => 'content_header', 'slot_type_id' => $main->id, 'title' => 'Content '.$name, 'subtitle' => 'Plain editorial copy', 'status' => 'published']);
      Block::query()->create(['page_id' => $page->id, 'type' => 'sidebar-navigation', 'block_type_id' => $navType->id, 'slot_type_id' => $sidebar->id, 'settings' => ['menu_key' => 'docs'], 'status' => 'published']);
      for ($index = 0; $index < $links; $index++) {
        Block::query()->create(['page_id' => $page->id, 'type' => 'button_link', 'slot_type_id' => $main->id, 'title' => 'Link '.$index, 'settings' => ['url' => '/target-'.($index % 8)], 'sort_order' => $index + 1, 'status' => 'published']);
      }
    }
    for ($index = 0; $index < 24; $index++) {
      $target = $this->page($site, $locales, '/target-'.$index, '/hedef-'.$index, 'Target '.$index);
      NavigationItem::query()->create(['site_id' => $site->id, 'menu_key' => 'docs', 'title' => 'Target '.$index, 'link_type' => 'page', 'page_id' => $target->id, 'position' => $index, 'visibility' => 'visible']);
    }

    return [$site, $locales, $pages];
  }

  private function page(Site $site, array $locales, string $en, string $tr, string $title): Page
  {
    $page = Page::query()->create(['site_id' => $site->id, 'status' => 'published']);
    foreach ($locales as $code => $locale) {
      $path = $code === 'en' ? $en : $tr;
      $page->translations()->create(['site_id' => $site->id, 'locale_id' => $locale->id, 'name' => $title, 'slug' => trim($path, '/') ?: 'home', 'path' => $path]);
    }

    return $page;
  }

  private function bindRequest(string $path, string $code): Request
  {
    $request = Request::create('http://localhost'.($code === 'tr' ? '/tr' : '').$path);
    $route = new Route('GET', '/{locale?}/{path?}', fn () => null);
    $route->bind($request);
    $route->setParameter('locale', $code === 'en' ? null : $code);
    $request->setRouteResolver(fn () => $route);
    $request->setLaravelSession(session()->driver());
    app()->instance('request', $request);

    return $request;
  }

  private function profile(callable $work): array
  {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $start = hrtime(true);
    try {
      $html = $work();
      $queries = DB::getQueryLog();

      return [[
        'queries' => count($queries),
        'sql_ms' => round(array_sum(array_column($queries, 'time')), 3),
        'render_ms' => round((hrtime(true) - $start) / 1e6, 3),
        'html_sha256' => hash('sha256', $html),
      ], $html];
    } finally {
      DB::disableQueryLog();
      DB::flushQueryLog();
    }
  }

  private function record(array $profile): void
  {
    $path = getenv('CMS_PERFORMANCE_REPORT');
    if (is_string($path) && $path !== '') {
      file_put_contents($path, json_encode($profile, JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
    }
  }
}
