<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

class PublicSitemapTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('app.url', 'https://primary.test');
    $app['config']->set('cms.multisite.unknown_host_fallback', false);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function an_empty_site_returns_a_valid_empty_urlset(): void
  {
    $site = $this->site('empty.test', true);

    $response = $this->get('https://'.$site->domain.'/sitemap.xml');

    $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', false);
    $response->assertDontSee('<url>', false);
  }

  #[Test]
  public function a_single_published_page_is_listed_and_non_public_pages_are_excluded(): void
  {
    $site = $this->site('one.test', true);
    $this->page($site, 'published', Page::STATUS_PUBLISHED);
    $this->page($site, 'draft', Page::STATUS_DRAFT);
    $this->page($site, 'review', Page::STATUS_IN_REVIEW);
    $this->page($site, 'archived', Page::STATUS_ARCHIVED);
    $this->page($site, 'noindex', Page::STATUS_PUBLISHED, ['seo' => ['noindex' => true]]);
    $this->page($site, 'redirected', Page::STATUS_PUBLISHED, ['redirect_url' => '/published']);

    $response = $this->get('https://one.test/sitemap.xml');

    $response->assertOk()->assertSee('<loc>https://one.test/published</loc>', false);
    foreach (['draft', 'review', 'archived', 'noindex', 'redirected'] as $slug) {
      $response->assertDontSee('https://one.test/'.$slug, false);
    }
  }

  #[Test]
  public function request_host_selects_the_site_and_localized_urls_share_renderer_alternates(): void
  {
    $first = $this->site('first.test', true);
    $second = $this->site('second.test');
    $turkish = Locale::query()->create(['code' => 'tr', 'name' => 'Türkçe', 'is_default' => false, 'is_enabled' => true]);
    $first->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
    $page = $this->page($first, 'about', Page::STATUS_PUBLISHED);
    $page->translations()->create([
      'locale_id' => $turkish->id,
      'name' => 'Hakkımızda',
      'slug' => 'hakkimizda',
      'path' => '/hakkimizda',
    ]);
    $this->page($second, 'private-to-second', Page::STATUS_PUBLISHED);

    $response = $this->get('https://first.test/sitemap.xml');

    $response->assertOk()
      ->assertSee('<loc>https://first.test/about</loc>', false)
      ->assertSee('<loc>https://first.test/tr/hakkimizda</loc>', false)
      ->assertSee('hreflang="en" href="https://first.test/about"', false)
      ->assertSee('hreflang="tr" href="https://first.test/tr/hakkimizda"', false)
      ->assertSee('hreflang="x-default" href="https://first.test/about"', false)
      ->assertDontSee('private-to-second', false);
  }

  #[Test]
  public function robots_declares_the_resolved_sites_canonical_sitemap(): void
  {
    $this->site('robots.test', true);

    $this->get('https://robots.test/robots.txt')
      ->assertOk()
      ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
      ->assertSee("User-agent: *\nAllow: /\nSitemap: https://robots.test/sitemap.xml", false);
  }

  #[Test]
  public function large_sitemaps_are_paginated_and_page_changes_invalidate_cached_output(): void
  {
    config()->set('webblocks-cms.sitemap.max_urls', 1);
    $site = $this->site('large.test', true);
    $page = $this->page($site, 'first', Page::STATUS_PUBLISHED);
    $this->page($site, 'second', Page::STATUS_PUBLISHED);

    $this->get('https://large.test/sitemap.xml')
      ->assertOk()
      ->assertSee('<sitemapindex', false)
      ->assertSee('https://large.test/sitemap/1.xml', false)
      ->assertSee('https://large.test/sitemap/2.xml', false);

    $this->get('https://large.test/sitemap/1.xml')
      ->assertOk()
      ->assertSee('https://large.test/first', false)
      ->assertDontSee('https://large.test/second', false);

    $page->translations()->firstOrFail()->update(['slug' => 'renamed', 'path' => '/renamed']);

    $this->get('https://large.test/sitemap/1.xml')
      ->assertOk()
      ->assertSee('https://large.test/renamed', false)
      ->assertDontSee('https://large.test/first', false);

    $this->get('https://large.test/sitemap/3.xml')->assertNotFound();
  }

  private function site(string $domain, bool $primary = false): Site
  {
    $english = Locale::query()->firstOrCreate(
      ['code' => 'en'],
      ['name' => 'English', 'is_default' => true, 'is_enabled' => true],
    );
    $site = Site::query()->create([
      'name' => $domain,
      'handle' => str_replace('.', '-', $domain),
      'domain' => $domain,
      'is_primary' => $primary,
    ]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true]]);

    return $site->fresh();
  }

  private function page(Site $site, string $slug, string $status, ?array $settings = null): Page
  {
    $english = Locale::query()->where('code', 'en')->firstOrFail();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'status' => $status,
      'settings' => $settings,
    ]);
    $page->translations()->create([
      'locale_id' => $english->id,
      'name' => ucfirst($slug),
      'slug' => $slug,
      'path' => '/'.$slug,
    ]);

    return $page->fresh();
  }
}
