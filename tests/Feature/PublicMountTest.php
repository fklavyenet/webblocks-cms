<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PublicSearchIndex;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\Search\PublicSearchQuery;
use WebBlocks\Cms\Support\Sitemap\SitemapGenerator;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteExportDataBuilder;
use WebBlocks\Cms\Tests\TestCase;

class PublicMountTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.public.mount', ' /wb/ ');
    $app['config']->set('app.url', 'https://mounted.test');
    $app['config']->set('cms.multisite.unknown_host_fallback', false);
  }

  protected function defineRoutes($router): void
  {
    foreach (['/', 'de', 'en', 'up', 'settings', 'robots.txt'] as $path) {
      $router->get($path, fn () => response('host:'.$path));
    }
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function mounted_content_and_generated_urls_share_one_mount_without_changing_identity(): void
  {
    [$site, $english, $german] = $this->site('mounted.test', true);
    $home = $this->page($site, $english, '/');
    $home->translations()->create(['locale_id' => $german->id, 'name' => 'German home', 'slug' => 'home', 'path' => '/']);
    $page = $this->page($site, $english, '/about');
    $page->translations()->create(['locale_id' => $german->id, 'name' => 'German about', 'slug' => 'about', 'path' => '/about']);
    $nested = $this->page($site, $english, '/blog/article');

    foreach (['/wb' => $home, '/wb/de' => $home, '/wb/about' => $page, '/wb/de/about' => $page, '/wb/blog/article' => $nested] as $path => $expected) {
      $this->get('https://mounted.test'.$path)->assertOk()->assertViewHas('page', fn ($actual) => $actual->id === $expected->id);
    }
    foreach (['/', '/de', '/en', '/up', '/settings', '/robots.txt'] as $path) {
      $this->get('https://mounted.test'.$path)->assertOk()->assertSee('host:', false);
    }
    foreach (['/about', '/wb/en/about', '/wb/robots.txt'] as $path) {
      $this->get('https://mounted.test'.$path)->assertNotFound();
    }
    $resolver = app(PageRouteResolver::class);
    $this->assertSame('/wb', $resolver->homePath(null, $site));
    $this->assertSame('/wb/de/search', $resolver->searchPath('de', $site));
    $this->assertSame('/wb/de/search.json', $resolver->searchJsonPath('de', $site));
    $this->assertSame('https://mounted.test/wb/about', $page->publicUrl());
    $this->assertSame('https://mounted.test/wb/de/about', $page->canonicalUrl('de'));
    $this->assertSame('/wb/about', (new NavigationItem(['link_type' => NavigationItem::LINK_PAGE]))->setRelation('page', $page)->resolvedUrl());
    $this->assertSame('/wb/de/about#section', $resolver->localizedPublicUrl('/about#section', 'de', $site));
    $this->assertSame('/wb/de/about', $resolver->localizedPublicUrl('/wb/about', 'de', $site));
    $this->assertSame('https://mounted.test/about', $resolver->localizedPublicUrl('https://mounted.test/about', 'de', $site));
    $this->assertSame('/about', (new NavigationItem(['link_type' => NavigationItem::LINK_CUSTOM_URL, 'url' => '/about']))->resolvedUrl());
    $this->assertSame('/settings', $resolver->localizedPublicUrl('/settings', 'de', $site));
    $this->assertSame('/about', $page->translations()->where('locale_id', $english->id)->value('path'));
    $page->setRelation('currentTranslation', $page->translations()->with('locale')->where('locale_id', $german->id)->firstOrFail());
    $meta = app(PublicPagePresenter::class)->present($page, true, $german)['publicMeta'];
    $this->assertSame('https://mounted.test/wb/de/about', $meta['canonical_url']);
    $this->assertSame($meta['canonical_url'], $meta['og_url']);
    $this->get('https://mounted.test/wb/de/about')->assertSee('hreflang="en" href="https://mounted.test/wb/about"', false);
    $this->get('https://mounted.test/wb/p/about')->assertRedirect('/wb/about');
    $this->get('https://mounted.test/wb/de/p/about')->assertRedirect('/wb/de/about');
    $snapshot = app(PageRevisionManager::class)->snapshotForInspection($page->fresh());
    $this->assertSame(['/about'], array_values(array_unique(array_column($snapshot['translations'], 'path'))));
    $export = app(SiteExportDataBuilder::class)->build($site->fresh(), false, [$page->id]);
    $this->assertSame(['/about'], array_values(array_unique(array_column($export['page_translations'], 'path'))));
    $this->assertSame('contact-messages', Route::getRoutes()->getByName('contact-messages.store')->uri());
  }

  #[Test]
  public function multisite_sitemaps_and_search_results_do_not_reuse_old_mount_urls(): void
  {
    [$first, $english] = $this->site('mounted.test', true);
    [$second, $secondEnglish] = $this->site('second.test');
    $page = $this->page($first, $english, '/about');
    $this->page($second, $secondEnglish, '/other');
    $this->get('https://mounted.test/wb/sitemap.xml')->assertOk()->assertSee('https://mounted.test/wb/about', false)->assertDontSee('second.test');
    $this->get('https://second.test/wb/sitemap.xml')->assertOk()->assertSee('https://second.test/wb/other', false)->assertDontSee('mounted.test');
    app(PublicSearchIndexer::class)->rebuildPage($page);
    $this->get('https://mounted.test/wb/search.json?q=About')->assertOk()->assertJsonPath('results.0.url', '/wb/about');
    config()->set('webblocks-cms.public.mount', 'content');
    $this->assertSame('/content/about', $page->publicPath());
    $generator = app(SitemapGenerator::class);
    $this->assertStringContainsString('https://mounted.test/content/about', $generator->sitemap($first));
    $results = app(PublicSearchQuery::class)->search($first, $english, 'About');
    $this->assertSame('/content/about', $results->first()->url);
  }

  #[Test]
  public function multiword_search_returns_html_and_json_when_terms_are_separated(): void
  {
    [$site, $english] = $this->site('mounted.test', true);
    $page = $this->page($site, $english, '/visitor-support');
    PublicSearchIndex::query()->updateOrCreate(
      ['site_id' => $site->id, 'locale_id' => $english->id, 'page_id' => $page->id],
      ['title' => 'Visitor support', 'url' => '/wb/visitor-support', 'content' => 'Live help and visitor chat', 'indexed_at' => now()],
    );

    $this->get('https://mounted.test/wb/search.json?q=live%20chat')
      ->assertOk()
      ->assertJsonPath('count', 1)
      ->assertJsonPath('results.0.url', '/wb/visitor-support')
      ->assertJsonPath('results.0.excerpt', 'Live help and visitor chat');
    $this->get('https://mounted.test/wb/search?q=live%20chat')
      ->assertOk()
      ->assertSee('Visitor support')
      ->assertSee('Live help and visitor chat');
  }

  private function site(string $domain, bool $primary = false): array
  {
    $english = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $german = Locale::query()->firstOrCreate(['code' => 'de'], ['name' => 'German', 'is_enabled' => true]);
    $site = Site::query()->create(['name' => $domain, 'handle' => str_replace('.', '-', $domain), 'domain' => $domain, 'is_primary' => $primary]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true], $german->id => ['is_enabled' => true]]);

    return [$site, $english, $german];
  }

  private function page(Site $site, Locale $locale, string $path): Page
  {
    $page = Page::query()->create(['site_id' => $site->id, 'status' => Page::STATUS_PUBLISHED]);
    $page->translations()->create(['locale_id' => $locale->id, 'name' => 'About '.$path, 'slug' => $path === '/' ? 'home' : basename($path), 'path' => $path]);

    return $page;
  }
}
