<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

class PublicSearchIndexingPolicyTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function search_entries_results_and_json_are_noindex_without_marking_ordinary_pages(): void
  {
    $this->withoutMiddleware(EnsureInstalled::class);
    config(['cms.public_rate_limits.search_per_minute' => 1000]);
    $site = Site::create(['name' => 'Search policy studio', 'handle' => 'search-policy', 'domain' => 'localhost', 'is_primary' => true]);
    $locales = collect(['en', 'tr', 'de'])->map(fn ($code) => Locale::firstOrCreate(['code' => $code], [
      'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true,
    ]));
    $site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());
    foreach (['/', '/article'] as $path) {
      $page = Page::create(['site_id' => $site->id, 'status' => 'published']);
      foreach ($locales as $locale) {
        $page->translations()->create(['locale_id' => $locale->id, 'name' => 'Search policy', 'slug' => $path === '/' ? 'home' : 'article', 'path' => $path]);
      }
    }
    foreach (['', '/tr', '/de'] as $prefix) {
      foreach (['', '?q=', '?q=nebula', '?q=nebula&page=2'] as $query) {
        $this->get($prefix.'/search'.$query)->assertRedirect()->assertHeader('X-Robots-Tag', 'noindex');
        $this->getJson($prefix.'/search.json'.$query)->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertJsonStructure(['query', 'count', 'results']);
        $this->get(($prefix ?: '/').'?cms_search=1'.($query === '' ? '' : '&'.substr($query, 1)))
          ->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertSee('<meta name="robots" content="noindex">', false);
      }
      foreach ([$prefix ?: '/', $prefix.'/article', ($prefix ?: '/').'?q=nebula', ($prefix ?: '/').'?cms_search=0'] as $url) {
        $this->get($url)->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertDontSee('<meta name="robots" content="noindex', false);
      }
    }
    $this->get('/robots.txt')->assertOk()->assertDontSee('Disallow: /search', false);
  }
}
