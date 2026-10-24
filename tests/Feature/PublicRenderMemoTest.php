<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteVariable;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Memberships\MemberDirectory;
use WebBlocks\Cms\Support\Memberships\MemberIdentity;
use WebBlocks\Cms\Support\Navigation\NavigationTree;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Pages\PublicPageAccess;
use WebBlocks\Cms\Support\Plugins\PluginServiceRegistry;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Support\Sites\SiteTokenRenderer;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;

class PublicRenderMemoTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function locale_queries_are_shared_across_consumers_and_cache_missing_codes(): void
  {
    [$site, $en, $tr] = $this->fixture();
    app()->instance('request', Request::create('/'));
    app(LocaleResolver::class)->default();
    app(LocaleResolver::class)->enabled('tr', $site);
    $this->assertSame(0, $this->queries(function () use ($site, $tr): void {
      for ($index = 0; $index < 100; $index++) {
        $this->assertSame('en', app(LocaleResolver::class)->default()->code);
        $this->assertSame('en', app(SystemSettings::class)->defaultLocaleCode());
        $this->assertSame($tr->id, app(LocaleResolver::class)->enabled(' TR ', $site)->id);
        $this->assertNull(app(LocaleResolver::class)->enabled('missing', $site));
        $this->assertTrue($site->hasEnabledLocale($tr));
        $this->assertFalse($site->hasEnabledLocale('missing'));
      }
    }));
  }

  #[Test]
  public function sites_requests_and_worker_scopes_are_isolated_even_for_an_existing_helper(): void
  {
    [$site, $en, $tr] = $this->fixture();
    $other = Site::query()->create(['name' => 'Other', 'handle' => 'other']);
    $other->locales()->sync([$en->id => ['is_enabled' => true]]);
    $resolver = app(LocaleResolver::class);
    $this->assertNotNull($resolver->enabled('tr', $site));
    $this->assertNull($resolver->enabled('tr', $other));

    $first = app(RequestMemo::class);
    $this->assertSame('first', $first->remember('isolation', [], fn () => 'first'));
    app()->instance('request', Request::create('/next'));
    $this->assertSame('next', $first->remember('isolation', [], fn () => 'next'));
    $this->assertGreaterThan(0, $this->queries(fn () => $resolver->enabled('tr', $site)));

    app()->forgetScopedInstances();
    $this->assertNotSame($first, app(RequestMemo::class));
    $this->assertGreaterThan(0, $this->queries(fn () => $resolver->enabled('tr', $site)));
  }

  #[Test]
  public function raw_and_pivot_writes_invalidate_positive_and_negative_results(): void
  {
    [$site, $en, $tr] = $this->fixture();
    $this->assertNotNull(app(LocaleResolver::class)->enabled('tr', $site));
    $site->locales()->updateExistingPivot($tr->id, ['is_enabled' => false]);
    $this->assertFalse($site->hasEnabledLocale($tr));
    $this->assertNull(app(LocaleResolver::class)->enabled('tr', $site));
    $site->locales()->updateExistingPivot($tr->id, ['is_enabled' => true]);
    $this->assertNotNull(app(LocaleResolver::class)->enabled('tr', $site));
    DB::table('wbcms_locales')->where('id', $en->id)->update(['is_default' => false]);
    DB::table('wbcms_locales')->where('id', $tr->id)->update(['is_default' => true]);
    $this->assertSame('tr', app(SystemSettings::class)->defaultLocaleCode());
    $this->assertSame('tr', app(LocaleResolver::class)->default()->code);
  }

  #[Test]
  public function rolled_back_values_cannot_survive_in_the_memo(): void
  {
    [$site, $en, $tr] = $this->fixture();
    $this->assertNotNull(app(LocaleResolver::class)->enabled('tr', $site));
    DB::beginTransaction();
    $site->locales()->updateExistingPivot($tr->id, ['is_enabled' => false]);
    $this->assertNull(app(LocaleResolver::class)->enabled('tr', $site));
    DB::rollBack();
    $this->assertNotNull(app(LocaleResolver::class)->enabled('tr', $site));
  }

  #[Test]
  public function database_events_invalidate_the_current_worker_container_not_its_boot_application(): void
  {
    $this->fixture();
    $original = app();
    $originalMemo = app(RequestMemo::class);
    $originalMemo->put('worker', [], 'original');
    $sandbox = clone $original;
    $sandbox->forgetScopedInstances();
    Container::setInstance($sandbox);
    try {
      $sandboxMemo = app(RequestMemo::class);
      $this->assertNotSame($originalMemo, $sandboxMemo);
      $sandboxMemo->put('worker', [], 'sandbox');
      DB::table('wbcms_locales')->where('code', 'tr')->update(['name' => 'Updated']);
      $this->assertFalse($sandboxMemo->has('worker', []));
      $this->assertTrue($originalMemo->has('worker', []));
    } finally {
      Container::setInstance($original);
    }
  }

  #[Test]
  public function plain_copy_never_reads_variables_and_empty_results_are_reused(): void
  {
    [$site] = $this->fixture();
    $this->assertSame(0, $this->queries(function () use ($site): void {
      foreach (['Plain copy', '{{ site. }}', '{{ site.UPPER }}', '{{ other.name }}', '', null] as $copy) {
        $this->assertSame($copy, app(SiteTokenRenderer::class)->render($copy, $site));
      }
    }));
    app(SiteTokenRenderer::class)->render('{{ site.name }}', $site);
    $this->assertSame(0, $this->queries(function () use ($site): void {
      for ($index = 0; $index < 50; $index++) {
        $this->assertSame('{{ site.name }}', app(SiteTokenRenderer::class)->render('{{ site.name }}', $site));
      }
    }));
  }

  #[Test]
  public function tokens_keep_escaping_unknown_and_disabled_values_and_site_isolation(): void
  {
    [$site] = $this->fixture();
    $other = Site::query()->create(['name' => 'Other', 'handle' => 'other']);
    $variable = SiteVariable::query()->create(['site_id' => $site->id, 'key' => 'name', 'label' => 'Name', 'value' => '<Studio & Co>', 'is_enabled' => true]);
    SiteVariable::query()->create(['site_id' => $site->id, 'key' => 'hidden', 'label' => 'Hidden', 'value' => 'Private', 'is_enabled' => false]);
    SiteVariable::query()->create(['site_id' => $other->id, 'key' => 'name', 'label' => 'Name', 'value' => 'Other studio', 'is_enabled' => true]);
    $this->assertSame('&lt;Studio &amp; Co&gt; {{ site.hidden }} {{ site.unknown }}', app(SiteTokenRenderer::class)->render('{{ site.name }} {{ site.hidden }} {{ site.unknown }}', $site, true));
    $this->assertSame('<Studio & Co>', app(SiteTokenRenderer::class)->render('{{ site.name }}', $site));
    $this->assertSame('Other studio', app(SiteTokenRenderer::class)->render('{{ site.name }}', $other));
    $variable->update(['value' => 'Changed']);
    $this->assertSame('Changed', app(SiteTokenRenderer::class)->render('{{ site.name }}', $site));
  }

  #[Test]
  public function localized_link_results_respect_site_mount_suffix_and_write_changes(): void
  {
    [$site, $en, $tr] = $this->fixture();
    $page = $this->page($site, $en, $tr);
    $routes = app(PageRouteResolver::class);
    $this->assertSame('/tr/hedef?q=1#one', $routes->localizedPublicUrl('/target?q=1#one', 'tr', $site));
    $this->assertSame('/target?q=2#two', $routes->localizedPublicUrl('/tr/hedef?q=2#two', 'en', $site));
    config()->set('webblocks-cms.public.mount', '/content');
    $this->assertSame('/content/tr/hedef?q=1#one', $routes->localizedPublicUrl('/target?q=1#one', 'tr', $site));
    $this->assertSame('/content/target', $routes->localizedPublicUrl('/content/tr/hedef', 'en', $site));
    config()->set('webblocks-cms.public.mount', '');
    $other = Site::query()->create(['name' => 'Other', 'handle' => 'other']);
    $other->locales()->sync([$en->id => ['is_enabled' => true], $tr->id => ['is_enabled' => true]]);
    $this->assertSame('/target', $routes->localizedPublicUrl('/target', 'tr', $other));
    $page->translations()->where('locale_id', $tr->id)->update(['path' => '/yeni']);
    $this->assertSame('/tr/yeni', $routes->localizedPublicUrl('/target', 'tr', $site));
    $page->update(['status' => 'draft']);
    $this->assertSame('/target', $routes->localizedPublicUrl('/target', 'tr', $site));
  }

  #[Test]
  public function access_rules_refresh_after_raw_writes_and_do_not_cache_authorization(): void
  {
    [$site, $en, $tr] = $this->fixture();
    $page = $this->page($site, $en, $tr);
    $access = app(PublicPageAccess::class);
    $this->assertFalse($access->protected($page));
    $this->assertTrue($access->allows($page, request()));
    DB::table('wbcms_page_access_rules')->insert(['page_id' => $page->id, 'package_id' => null]);
    $this->assertTrue($access->protected($page));
    $this->assertFalse($access->allows($page, request()));
    DB::table('wbcms_page_access_rules')->where('page_id', $page->id)->delete();
    $this->assertFalse($access->protected($page));
    $this->assertTrue($access->allows($page, request()));
  }

  #[Test]
  public function member_verification_and_package_access_are_rechecked_for_each_decision(): void
  {
    [$site, $en, $tr] = $this->fixture();
    $page = $this->page($site, $en, $tr);
    DB::table('wbcms_page_access_rules')->insert(['page_id' => $page->id, 'package_id' => 7]);
    $directory = new class implements MemberDirectory
    {
      public bool $package = true;

      public function current(Request $request, int $siteId): ?MemberIdentity
      {
        return $request->session()->get('test.member')
          ? new MemberIdentity(1, $siteId, 'Synthetic member', 'member@example.test', (bool) $request->session()->get('test.verified'))
          : null;
      }

      public function hasPackage(MemberIdentity $member, int $packageId): bool
      {
        return $this->package && $packageId === 7;
      }

      public function packages(int $siteId): array
      {
        return [];
      }
    };
    $registry = \Mockery::mock(PluginServiceRegistry::class);
    $registry->shouldReceive('resolve')->with('webblocks-memberships', MemberDirectory::class, '^0.1 || ^0.2')->andReturn($directory);
    app()->instance(PluginServiceRegistry::class, $registry);
    $request = Request::create('/protected');
    $request->setLaravelSession(session()->driver());
    $access = app(PublicPageAccess::class);
    $this->assertFalse($access->allows($page, $request));
    $request->session()->put('test.member', true);
    $this->assertFalse($access->allows($page, $request));
    $request->session()->put('test.verified', true);
    $this->assertTrue($access->allows($page, $request));
    $directory->package = false;
    $this->assertFalse($access->allows($page, $request));
    $directory->package = true;
    $request->session()->forget('test.member');
    $this->assertFalse($access->allows($page, $request));
  }

  #[Test]
  public function batched_unique_link_targets_and_missing_paths_do_not_query_per_link(): void
  {
    [$site, $en, $tr] = $this->fixture();
    $urls = [];
    for ($index = 0; $index < 20; $index++) {
      $this->page($site, $en, $tr, (string) $index);
      $urls[] = '/target'.$index;
    }
    $urls[] = '/absent';
    $routes = app(PageRouteResolver::class);
    $batchCounts = [];
    foreach ([1, 20] as $count) {
      app()->instance('request', Request::create('/batch-'.$count));
      $batchCounts[$count] = $this->queries(fn () => $routes->primeLocalizedUrls(array_slice($urls, 0, $count), $site));
    }
    $this->assertSame($batchCounts[1], $batchCounts[20]);
    $routes->primeLocalizedUrls($urls, $site);
    $this->assertSame(0, $this->queries(function () use ($urls, $routes, $site): void {
      foreach ($urls as $url) {
        $localized = $routes->localizedPublicUrl($url, 'tr', $site);
        $this->assertSame($url === '/absent' ? $url : str_replace('/target', '/tr/hedef', $url), $localized);
      }
    }));
  }

  #[Test]
  public function navigation_page_and_access_metadata_are_loaded_in_batches(): void
  {
    [$site, $en, $tr] = $this->fixture();
    for ($index = 0; $index < 20; $index++) {
      $page = $this->page($site, $en, $tr, (string) $index);
      foreach ($index === 0 ? ['small', 'large'] : ['large'] as $menu) {
        NavigationItem::query()->create(['site_id' => $site->id, 'menu_key' => $menu, 'title' => 'Target '.$index, 'link_type' => 'page', 'page_id' => $page->id, 'position' => $index]);
      }
    }
    $counts = [];
    foreach (['small', 'large'] as $menu) {
      app()->instance('request', Request::create('/menu-'.$menu));
      $counts[$menu] = $this->queries(function () use ($site, $menu): void {
        foreach (app(NavigationTree::class)->buildMenuTree($menu, $site) as $item) {
          $this->assertFalse(app(PublicPageAccess::class)->protected($item->page));
          $this->assertNotNull($item->resolvedUrl());
          $this->assertNotSame('', $item->resolvedTitle());
        }
      });
    }
    $this->assertSame($counts['small'], $counts['large']);
  }

  private function fixture(): array
  {
    $en = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $tr = Locale::query()->create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $site = Site::query()->create(['name' => 'Synthetic studio', 'handle' => 'memo', 'is_primary' => true]);
    $site->locales()->sync([$en->id => ['is_enabled' => true], $tr->id => ['is_enabled' => true]]);

    return [$site, $en, $tr];
  }

  private function page(Site $site, Locale $en, Locale $tr, string $suffix = ''): Page
  {
    $page = Page::query()->create(['site_id' => $site->id, 'status' => 'published']);
    foreach ([[$en, 'target'], [$tr, 'hedef']] as [$locale, $slug]) {
      $page->translations()->create(['site_id' => $site->id, 'locale_id' => $locale->id, 'name' => 'Target', 'slug' => $slug.$suffix, 'path' => '/'.$slug.$suffix]);
    }

    return $page;
  }

  private function queries(callable $work): int
  {
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
      $work();

      return count(DB::getQueryLog());
    } finally {
      DB::disableQueryLog();
      DB::flushQueryLog();
    }
  }
}
