<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Public\PageController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PluginLifecycle;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Catalog\CoreLayoutCatalogSyncer;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginPublicAsset;
use WebBlocks\Cms\Support\Plugins\PluginPublicAssetRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

/** Synthetic fixtures at the live report's paths; never a live-data benchmark. */
class PublicRenderFollowUpPerformanceTest extends TestCase
{
  private string $pluginRoot;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->pluginRoot = sys_get_temp_dir().'/wb-render-follow-up-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->pluginRoot);
    config(['webblocks-plugins.install.root' => $this->pluginRoot, 'cms.visitor_reports.enabled' => false, 'cms.multisite.unknown_host_fallback' => true]);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->pluginRoot);
    parent::tearDown();
  }

  #[Test]
  public function same_en_tr_paths_preserve_output_with_media_plugins_and_managed_layouts(): void
  {
    $en = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $tr = Locale::query()->create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $site = Site::query()->create(['name' => 'Synthetic studio', 'handle' => 'render-fixture', 'is_primary' => true]);
    $site->locales()->sync([$en->id => ['is_enabled' => true], $tr->id => ['is_enabled' => true]]);
    app(CoreLayoutCatalogSyncer::class)->sync();
    $slot = SlotType::query()->where('slug', 'main')->firstOrFail();
    $registry = new PluginRegistry(useLiveConfig: true);
    foreach (range(1, 3) as $index) {
      $handle = 'render-fixture-'.$index;
      config()->set('webblocks-plugins.enabled.'.$handle, true);
      foreach (['', '1.0.0'] as $version) {
        PluginLifecycle::query()->create(['root_hash' => hash('sha256', realpath($this->pluginRoot)), 'handle' => $handle, 'version' => $version, 'enabled_version' => '1.0.0', 'setup_result' => ['status' => 'success'], 'previous_state' => ['version' => '0.9.0']]);
      }
      $registry->register(PluginDefinition::make($handle)->version('1.0.0')->installPath($this->pluginRoot.'/'.$handle)
        ->publicAssets([PluginPublicAsset::cssHead($handle.'.style', '/fixture-'.$index.'.css')]));
    }
    app()->instance(PluginRegistry::class, $registry);
    app()->forgetInstance(PluginPublicAssetRegistry::class);

    $pages = [];
    foreach (['/' => 6, '/docs' => 0, '/guides' => 44, '/docs/editorial-workflow' => 4, '/guides/publish-a-page' => 4, '/blogs' => 6] as $path => $images) {
      $page = Page::query()->create(['site_id' => $site->id, 'status' => 'published', 'settings' => ['public_shell' => 'default']]);
      foreach ([$en, $tr] as $locale) {
        $page->translations()->create(['site_id' => $site->id, 'locale_id' => $locale->id, 'path' => $path, 'slug' => trim($path, '/') ?: 'home', 'name' => $locale->code.' fixture '.$path]);
      }
      PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slot->id]);
      Block::query()->create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'type' => 'plain_text', 'status' => 'published', 'content' => 'Fixture content '.$path]);
      for ($index = 0; $index < $images; $index++) {
        $media = Media::query()->create(['disk' => 'public', 'path' => 'fixtures/image-'.$page->id.'-'.$index.'.svg', 'filename' => 'fixture.svg', 'kind' => 'image', 'mime_type' => 'image/svg+xml', 'visibility' => 'public', 'alt_text' => 'Fixture image '.$index]);
        Block::query()->create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'type' => 'image', 'status' => 'published', 'media_id' => $media->id, 'sort_order' => $index + 1]);
      }
      $pages[$path] = $images;
    }

    foreach ($pages as $path => $images) {
      foreach (['en', 'tr'] as $code) {
        for ($sample = 1; $sample <= 3; $sample++) {
          $publicPath = $code === 'en' ? $path : '/tr'.($path === '/' ? '' : $path);
          $request = Request::create('https://example.test'.$publicPath);
          $route = new Route('GET', $publicPath, []);
          $route->bind($request);
          $route->setParameter('locale', $code === 'en' ? null : $code);
          $request->setRouteResolver(fn () => $route);
          $request->setLaravelSession(app('session')->driver());
          $request->session()->put('_token', str_repeat('p', 40));
          app()->instance('request', $request);
          app()->setLocale($code);
          app()->forgetInstance(PluginBlockCatalog::class);
          DB::flushQueryLog();
          DB::enableQueryLog();
          $start = hrtime(true);
          try {
            $controller = app(PageController::class);
            $view = $path === '/' ? $controller->home($request) : $controller->show($request, ltrim($path, '/'));
            $html = $view->render();
            $queries = DB::getQueryLog();
            $profile = ['path' => $publicPath, 'locale' => $code, 'sample' => $sample, 'queries' => count($queries), 'media_id_queries' => count(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select * from "wbcms_media" where "wbcms_media"."id" ='))), 'sql_ms' => round(array_sum(array_column($queries, 'time')), 3), 'render_ms' => round((hrtime(true) - $start) / 1e6, 3), 'html_sha256' => hash('sha256', $html)];
          } finally {
            DB::disableQueryLog();
          }
          $this->assertSame(0, $profile['media_id_queries']);
          $this->assertSame($code, $view->getData()['publicLocaleCode']);
          $this->assertStringContainsString('Fixture content '.$path, $html);
          $this->assertSame($images, substr_count($html, 'data-wb-public-block-type="image"'));
          $this->assertStringContainsString('/fixture-1.css', $html);
          if ($report = getenv('CMS_PERFORMANCE_REPORT')) {
            file_put_contents($report, json_encode($profile, JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
          }
        }
      }
    }
  }
}
