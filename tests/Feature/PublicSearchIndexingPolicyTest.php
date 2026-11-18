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
  public function path_scope_limits_results_before_pagination_and_survives_localized_fallback(): void
  {
    $this->withoutMiddleware(EnsureInstalled::class);
    $site = Site::create(['name' => 'Scope studio', 'handle' => 'scope', 'domain' => 'localhost', 'is_primary' => true]);
    $locales = collect(['en', 'tr'])->map(fn ($code) => Locale::firstOrCreate(['code' => $code], [
      'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true,
    ]));
    $site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());

    $indexed = [
      '/' => 'Home',
      '/guides' => 'Scope sample landing',
      '/guides/copy' => 'Scope sample guide',
      '/guides/nested/steps' => 'Scope sample instructions',
      '/guides-other/blog' => 'Scope sample blog',
    ];
    for ($index = 1; $index <= 9; $index++) {
      $indexed['/guides/extra-'.$index] = 'Scope sample extra '.$index;
    }
    foreach ($indexed + ['/guides/draft' => 'Scope sample draft'] as $path => $title) {
      $page = Page::create(['site_id' => $site->id, 'status' => str_ends_with($path, '/draft') ? 'draft' : 'published']);
      foreach ($locales as $locale) {
        $page->translations()->create([
          'locale_id' => $locale->id,
          'name' => $title.' '.$locale->code,
          'slug' => $path === '/' ? 'home' : basename($path),
          'path' => $path,
        ]);
      }
    }

    $this->getJson('/search.json?q=scope+sample')
      ->assertOk()->assertJsonPath('count', 13);
    $this->getJson('/tr/search.json?q=scope+sample&path_prefix=%2Fguides')
      ->assertOk()->assertJsonPath('count', 11)->assertJsonCount(10, 'results')
      ->assertJsonMissing(['title' => 'Scope sample landing tr'])
      ->assertJsonMissing(['title' => 'Scope sample blog tr']);
    $this->getJson('/tr/search.json?q=scope+sample&path_prefix=%2Fguides&page=2')
      ->assertOk()->assertJsonCount(1, 'results')
      ->assertJsonPath('count', 11);
    $this->getJson('/tr/search.json?q=scope+sample&path_prefix=%2Fguides%2Fnested')
      ->assertOk()->assertJsonPath('results.0.url', '/tr/guides/nested/steps')->assertJsonPath('count', 1);
    $this->get('/tr/search?q=scope+sample&path_prefix=%2Fguides%2Fnested')
      ->assertRedirect('/tr?cms_search=1&q=scope+sample&path_prefix=%2Fguides%2Fnested#site-search');
    $this->get('/tr?cms_search=1&q=scope+sample&path_prefix=%2Fguides%2Fnested')
      ->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->assertSee('Scope sample instructions tr')
      ->assertSee('name="path_prefix" value="/guides/nested"', false)
      ->assertDontSee('Scope sample blog tr');

    foreach (['path_prefix[]=guides', 'path_prefix=%2F', 'path_prefix=%2Fguides%25', 'path_prefix=https%3A%2F%2Fexample.com'] as $invalid) {
      $this->getJson('/search.json?q=scope&'.$invalid)->assertUnprocessable()->assertJsonValidationErrors('path_prefix');
    }
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
