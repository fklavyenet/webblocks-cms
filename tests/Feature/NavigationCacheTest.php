<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Consent\UpdateConsentSettings;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Navigation\NavigationCache;
use WebBlocks\Cms\Support\Navigation\NavigationSnapshot;
use WebBlocks\Cms\Support\Navigation\NavigationTree;
use WebBlocks\Cms\Tests\TestCase;

class NavigationCacheTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function public_renderers_share_menu_reads_with_identical_output_on_new_requests(): void
  {
    $f = $this->fixture();
    $this->request();
    $hashes = [];
    $counts = [];
    foreach ($f['blocks'] as $slug => $block) {
      [$html, $counts[$slug]] = $this->measure($block, 'menu-'.$slug.'-cold');
      $this->assertStringContainsString('First', $html);
      $this->assertStringContainsString('Second', $html);
      $hashes[$slug] = hash('sha256', $html);
    }
    if (! getenv('CMS_PROFILE_BASELINE')) {
      $this->assertSame([1, 0, 0], array_values($counts));
    }
    $this->request();
    foreach ($f['blocks'] as $slug => $block) {
      [$html, $queries] = $this->measure($block, 'menu-'.$slug.'-warm');
      $this->assertSame($hashes[$slug], hash('sha256', $html));
      if (! getenv('CMS_PROFILE_BASELINE')) {
        $this->assertSame(0, $queries);
      }
    }
  }

  #[Test]
  public function serialized_data_keeps_locale_mount_and_active_page_context_live(): void
  {
    $f = $this->fixture();
    $directory = sys_get_temp_dir().'/wb-menu-cache-'.bin2hex(random_bytes(8));
    config(['cache.default' => 'menu_test', 'cache.stores.menu_test' => ['driver' => 'file', 'path' => $directory]]);
    try {
      $this->request();
      $html = $this->render($f['blocks']['navbar-navigation']);
      $this->assertSame(['/one'], $this->activeLinks($html));
      $this->request('tr', '/tr/iki');
      Cache::purge('menu_test');
      $block = clone $f['blocks']['navbar-navigation'];
      $block->setRelation('renderPage', $f['two']->fresh('site'));
      [$html, $queries] = $this->measure($block, 'menu-tr-second');
      $this->assertSame(0, $queries);
      $this->assertStringContainsString('Birinci', $html);
      $this->assertSame(['/tr/iki'], $this->activeLinks($html));
      config()->set('webblocks-cms.public.mount', '/content');
      $this->request('tr', '/content/tr/iki');
      $html = $this->render($block);
      $this->assertStringContainsString('href="/content/tr/bir"', $html);
      $this->assertSame(['/content/tr/iki'], $this->activeLinks($html));
    } finally {
      File::deleteDirectory($directory);
    }
  }

  #[Test]
  public function rendered_tree_mutations_do_not_poison_other_consumers_or_requests(): void
  {
    $f = $this->fixture();
    $group = NavigationItem::create(['site_id' => $f['site']->id, 'menu_key' => 'primary', 'title' => 'Group', 'link_type' => 'group', 'position' => 0]);
    $f['first']->update(['parent_id' => $group->id]);
    $first = $this->tree($f['site']);
    $first->first()->setRelation('children', collect());
    $first->first()->title = 'Changed by one renderer';
    $first->last()->page->setAttribute('status', 'draft');
    $next = $this->tree($f['site']);
    $this->assertSame('Group', $next->first()->title);
    $this->assertCount(1, $next->first()->children);
    $this->assertSame('published', $next->last()->page->status);
    $this->request();
    $this->assertCount(1, $this->tree($f['site'])->first()->children);
  }

  #[Test]
  public function consent_destinations_are_resolved_live_even_when_menu_data_is_reused(): void
  {
    $f = $this->fixture();
    NavigationItem::create(['site_id' => $f['site']->id, 'menu_key' => 'primary', 'link_type' => 'cookie_settings', 'position' => 3]);
    $block = $f['blocks']['navigation-auto'];
    $this->assertStringContainsString('/privacy-consent/preferences', $this->render($block));
    $generation = DB::table(NavigationCache::TABLE)->value('generation');
    app(UpdateConsentSettings::class)->settings($f['site'], ['enabled' => false]);
    $this->request();
    [$html, $queries] = $this->measure($block, 'menu-consent-disabled');
    $this->assertSame(0, $queries);
    $this->assertStringNotContainsString('/privacy-consent/preferences', $html);
    $this->assertSame($generation, DB::table(NavigationCache::TABLE)->value('generation'));
    app(UpdateConsentSettings::class)->settings($f['site'], ['enabled' => true]);
    $this->request('tr');
    [$html, $queries] = $this->measure($block, 'menu-consent-enabled-tr');
    $this->assertSame(0, $queries);
    $this->assertStringContainsString('/tr/privacy-consent/preferences', $html);
  }

  #[Test]
  public function navigation_mutations_reorder_translate_hide_add_and_delete_cached_items(): void
  {
    $f = $this->fixture();
    $this->assertSame(['First', 'Second'], $this->tree($f['site'])->pluck('title')->all());
    DB::table('wbcms_navigation_items')->where('id', $f['second']->id)->update(['position' => 0]);
    $this->assertSame(['Second', 'First'], $this->tree($f['site'])->pluck('title')->all());
    $f['first']->update(['title' => 'Renamed']);
    $this->assertSame('Renamed', $this->tree($f['site'])->last()->title);
    $f['first']->translations()->update(['title' => 'Yeni']);
    $this->request('tr');
    $this->assertSame('Yeni', $this->tree($f['site'])->last()->resolvedTitle());
    $f['first']->update(['visibility' => 'hidden']);
    $this->assertStringNotContainsString('Yeni', $this->render($f['blocks']['navigation-auto']));
    $this->assertStringNotContainsString('Yeni', serialize(app(NavigationSnapshot::class)->encode($this->tree($f['site']))));
    $f['second']->delete();
    $this->request();
    $this->assertCount(0, $this->tree($f['site']));
    $f['first']->delete();
    $this->assertCount(0, $this->tree($f['site']));
    NavigationItem::create(['site_id' => $f['site']->id, 'menu_key' => 'primary', 'title' => 'New link', 'link_type' => 'custom_url', 'url' => '/new']);
    $this->assertSame(['New link'], $this->tree($f['site'])->pluck('title')->all());
  }

  #[Test]
  public function page_mutations_refresh_publication_paths_access_and_cached_private_data_is_absent(): void
  {
    $f = $this->fixture();
    $this->tree($f['site']);
    $f['one']->translations()->where('locale_id', $f['en']->id)->update(['path' => '/renamed']);
    $this->assertSame('/renamed', $this->tree($f['site'])->first()->resolvedUrl());
    $f['one']->update(['status' => 'draft']);
    $this->request();
    $this->assertSame(['Second'], $this->tree($f['site'])->pluck('title')->all());
    $this->assertCount(2, app(NavigationTree::class)->buildMenuTree('primary', $f['site']), 'The administration still sees draft-linked records.');
    $snapshot = app(NavigationSnapshot::class)->encode($this->tree($f['site']));
    $this->assertStringNotContainsString('First', serialize($snapshot));
    $f['one']->update(['status' => 'published']);
    $this->assertCount(2, $this->tree($f['site']));
    $f['one']->update(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE]);
    $this->assertSame(['Second'], $this->tree($f['site'])->pluck('title')->all());
    $f['one']->update(['page_type' => Page::TYPE_DEFAULT]);
    DB::table('wbcms_page_access_rules')->insert(['page_id' => $f['one']->id, 'package_id' => null]);
    $this->request();
    $this->assertSame(['Second'], $this->tree($f['site'])->pluck('title')->all());
    DB::table('wbcms_page_access_rules')->where('page_id', $f['one']->id)->delete();
    $this->assertCount(2, $this->tree($f['site']));
    $f['one']->delete();
    $this->assertSame(['Second'], $this->tree($f['site'])->pluck('title')->all());
  }

  #[Test]
  public function sites_menu_keys_and_missing_site_context_are_isolated(): void
  {
    $f = $this->fixture();
    $other = Site::create(['name' => 'Other synthetic site', 'handle' => 'other']);
    NavigationItem::create(['site_id' => $other->id, 'menu_key' => 'primary', 'title' => 'Foreign', 'link_type' => 'custom_url', 'url' => '/foreign']);
    NavigationItem::create(['site_id' => $f['site']->id, 'menu_key' => 'footer', 'title' => 'Footer', 'link_type' => 'custom_url', 'url' => '/footer']);
    $this->assertSame(['First', 'Second'], $this->tree($f['site'])->pluck('title')->all());
    $this->assertSame(['Foreign'], $this->tree($other)->pluck('title')->all());
    $this->assertSame(['Footer'], app(NavigationTree::class)->buildPublicMenuTree('footer', $f['site'])->pluck('title')->all());
    $this->assertCount(0, app(NavigationTree::class)->buildPublicMenuTree('primary'));
    DB::table('wbcms_navigation_items')->where('id', $f['first']->id)->update(['site_id' => $other->id]);
    $this->assertSame(['Foreign'], $this->tree($other)->pluck('title')->all(), 'A corrupt cross-site page reference cannot enter public cache.');
  }

  #[Test]
  public function transactions_rollback_and_overlapping_fills_preserve_freshness(): void
  {
    $f = $this->fixture();
    $this->tree($f['site']);
    $generation = DB::table(NavigationCache::TABLE)->value('generation');
    DB::beginTransaction();
    $f['first']->update(['title' => 'Uncommitted']);
    $this->assertSame('Uncommitted', $this->tree($f['site'])->first()->title);
    DB::rollBack();
    $this->assertSame($generation, DB::table(NavigationCache::TABLE)->value('generation'));
    $this->request();
    $this->assertSame('First', $this->tree($f['site'])->first()->title);
    Cache::flush();
    $this->request();
    $armed = true;
    DB::listen(function (QueryExecuted $event) use (&$armed, $f): void {
      if ($armed && str_starts_with($event->sql, 'select * from "wbcms_navigation_items"')) {
        $armed = false;
        DB::table('wbcms_navigation_items')->where('id', $f['first']->id)->update(['title' => 'Concurrent change']);
      }
    });
    $this->assertSame('First', $this->tree($f['site'])->first()->title);
    $this->assertSame('Concurrent change', $this->tree($f['site'])->first()->title);
    $this->request();
    $this->assertSame('Concurrent change', $this->tree($f['site'])->first()->title);
  }

  #[Test]
  public function cache_outages_and_recovery_cannot_revive_old_results(): void
  {
    $f = $this->fixture();
    $this->tree($f['site']);
    $manager = Cache::getFacadeRoot();
    $failing = \Mockery::mock(CacheManager::class);
    $failing->shouldReceive('get')->andThrow(new \RuntimeException('Synthetic outage'));
    $failing->shouldReceive('put')->andThrow(new \RuntimeException('Synthetic outage'));
    Cache::swap($failing);
    try {
      DB::table('wbcms_navigation_items')->where('id', $f['first']->id)->update(['title' => 'During outage']);
      $this->request();
      $this->assertSame('During outage', $this->tree($f['site'])->first()->title);
    } finally {
      Cache::swap($manager);
    }
    $this->request();
    $this->assertSame('During outage', $this->tree($f['site'])->first()->title);
  }

  #[Test]
  public function empty_results_expiry_disable_switch_and_upgrade_migration_are_supported(): void
  {
    $f = $this->fixture();
    NavigationItem::query()->delete();
    config()->set('webblocks-cms.navigation.cache_seconds', 2);
    [, $cold] = $this->measure($f['blocks']['navigation-auto'], 'empty-cold');
    $this->assertSame(1, $cold);
    $this->request();
    [, $warm] = $this->measure($f['blocks']['navigation-auto'], 'empty-warm');
    $this->assertSame(0, $warm);
    $this->travel(3)->seconds();
    $this->request();
    [, $expired] = $this->measure($f['blocks']['navigation-auto'], 'expired');
    $this->assertSame(1, $expired);
    config()->set('webblocks-cms.navigation.cache_seconds', 0);
    $this->request();
    [, $disabled] = $this->measure($f['blocks']['navigation-auto'], 'disabled');
    $this->assertSame(1, $disabled);
    $update = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_31_130000_create_navigation_cache_versions.php';
    $before = DB::table(NavigationCache::TABLE)->value('generation');
    $update->up();
    $this->assertSame($before, DB::table(NavigationCache::TABLE)->value('generation'));
    $update->down();
    $this->request();
    $this->assertCount(0, $this->tree($f['site']));
    $update->up();
    $this->assertSame(1, DB::table(NavigationCache::TABLE)->count());
  }

  private function fixture(): array
  {
    $en = Locale::create(['code' => 'en', 'name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $tr = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $site = Site::create(['name' => 'Synthetic navigation', 'handle' => 'menu-fixture', 'is_primary' => true]);
    $site->locales()->sync([$en->id => ['is_enabled' => true], $tr->id => ['is_enabled' => true]]);
    $one = Page::create(['site_id' => $site->id, 'status' => 'published']);
    $two = Page::create(['site_id' => $site->id, 'status' => 'published']);
    foreach ([[$one, 'one', 'bir'], [$two, 'second', 'iki']] as [$page, $path, $trPath]) {
      $page->translations()->create(['site_id' => $site->id, 'locale_id' => $en->id, 'path' => '/'.$path, 'slug' => $path, 'name' => ucfirst($path)]);
      $page->translations()->create(['site_id' => $site->id, 'locale_id' => $tr->id, 'path' => '/'.$trPath, 'slug' => $trPath, 'name' => ucfirst($trPath)]);
    }
    $first = NavigationItem::create(['site_id' => $site->id, 'menu_key' => 'primary', 'link_type' => 'page', 'page_id' => $one->id, 'title' => 'First', 'position' => 1]);
    $first->translations()->create(['locale_id' => $tr->id, 'title' => 'Birinci']);
    $second = NavigationItem::create(['site_id' => $site->id, 'menu_key' => 'primary', 'link_type' => 'page', 'page_id' => $two->id, 'title' => 'Second', 'position' => 2]);
    $blocks = [];
    foreach (['navbar-navigation', 'sidebar-navigation', 'navigation-auto'] as $slug) {
      $type = BlockType::firstOrCreate(['slug' => $slug], ['name' => $slug, 'is_active' => true]);
      $blocks[$slug] = Block::create(['page_id' => $one->id, 'block_type_id' => $type->id, 'type' => $slug, 'status' => 'published', 'settings' => ['menu_key' => 'primary']])->fresh(['blockType', 'page.site']);
    }
    $this->request();

    return compact('site', 'en', 'tr', 'one', 'two', 'first', 'second', 'blocks');
  }

  private function tree(Site $site)
  {
    return app(NavigationTree::class)->buildPublicMenuTree('primary', $site);
  }

  private function request(?string $locale = null, string $path = '/one'): void
  {
    $request = Request::create($path);
    $route = new Route('GET', '/{locale?}', []);
    $route->bind($request);
    $route->setParameter('locale', $locale);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);
    app()->forgetScopedInstances();
  }

  private function render(Block $block): string
  {
    return view('webblocks-cms::pages.partials.blocks.'.$block->type, ['block' => $block])->render();
  }

  private function activeLinks(string $html): array
  {
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    return array_values(array_unique(array_map(fn ($node) => $node->value, iterator_to_array($xpath->query('//*[@aria-current="page"]/@href')))));
  }

  private function measure(Block $block, string $scenario): array
  {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $start = hrtime(true);
    try {
      $html = $this->render($block);
      $queries = DB::getQueryLog();
    } finally {
      DB::disableQueryLog();
    }
    $menuQueries = count(array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select * from "wbcms_navigation_items"')));
    if ($report = getenv('CMS_PERFORMANCE_REPORT')) {
      file_put_contents($report, json_encode(['scenario' => $scenario, 'queries' => count($queries), 'navigation_queries' => $menuQueries, 'render_ms' => round((hrtime(true) - $start) / 1e6, 3), 'html_sha256' => hash('sha256', $html)], JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
    }

    return [$html, $menuQueries];
  }
}
