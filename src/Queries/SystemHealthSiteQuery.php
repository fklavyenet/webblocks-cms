<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\PublicSearchIndex;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Search\SearchablePageResolver;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\System\SystemHealthStatus as Health;

class SystemHealthSiteQuery
{
  public function __construct(private readonly LocaleResolver $locales, private readonly SearchablePageResolver $searchablePages) {}

  public function rows(): array
  {
    $sites = Site::query()->with(['locales', 'siteDomains'])->orderBy('name')->get();
    try {
      $default = $this->locales->default();
    } catch (Throwable) {
      $default = null;
    }

    return $sites->map(function (Site $site) use ($default): array {
      $checks = [];
      $route = 'admin.sites.edit';
      $args = ['site' => $site->id];
      $enabled = $site->locales->filter(fn (Locale $locale) => $locale->is_enabled && $locale->pivot->is_enabled);
      $hasDefault = $default && $enabled->contains('id', $default->id);
      $checks[] = Health::check('locales', $hasDefault ? 'healthy' : 'warning', $hasDefault ? 'locales_ready' : 'locales_review', $route, [], $args + ['tab' => 'locales']);

      $primaryDomains = $site->siteDomains->where('is_primary', true);
      $domainReady = $primaryDomains->count() <= 1 && ($primaryDomains->isEmpty() || $primaryDomains->first()->isActive());
      $hasAddress = trim((string) $site->canonicalDomain()) !== '' || ($site->is_primary && filter_var(config('app.url'), FILTER_VALIDATE_URL));
      $checks[] = Health::check('domains', $domainReady && $hasAddress ? 'healthy' : 'warning', $domainReady && $hasAddress ? 'domains_ready' : 'domains_review', 'admin.sites.domains.index', [], $args);

      $pages = Page::query()->visibleInAdmin()->where('site_id', $site->id)->with('translations')->get();
      $home = $hasDefault && $pages->contains(fn (Page $page) => $page->isPublished() && $page->translations->contains(fn ($translation) => (int) $translation->locale_id === (int) $default->id && ($translation->path ?: PageTranslation::pathFromSlug($translation->slug)) === '/'));
      $checks[] = Health::check('homepage', $home ? 'healthy' : 'warning', $home ? 'homepage_ready' : 'homepage_review', 'admin.pages.index', [], ['site_id' => $site->id]);

      try {
        $search = $this->search($site);
      } catch (Throwable) {
        $search = Health::check('search', 'unknown', 'check_unavailable', 'admin.system.search.index');
      }

      $historyBytes = 0;
      foreach (['wbcms_page_revisions', 'wbcms_shared_slot_revisions'] as $table) {
        if (Schema::hasTable($table)) {
          $historyBytes += (int) DB::table($table)->where('site_id', $site->id)->selectRaw('COALESCE(SUM(LENGTH(snapshot)), 0) as bytes')->value('bytes');
        }
      }

      return ['history_bytes' => $historyBytes, 'id' => (int) $site->id, 'name' => $site->name, 'pages' => $pages->count(), 'published_pages' => $pages->where('status', Page::STATUS_PUBLISHED)->count(), 'scheduler_required' => app(SchedulerHealth::class)->requiredForSite($site), 'checks' => $checks, 'search' => $search];
    })->all();
  }

  private function search(Site $site): array
  {
    $route = 'admin.system.search.index';
    if (! Schema::hasTable((new PublicSearchIndex)->getTable())) {
      return Health::check('search', 'unknown', 'search_unavailable', $route);
    }

    $expected = [];
    $this->searchablePages->query($site)->with(['site.locales', 'translations'])->each(function (Page $page) use (&$expected): void {
      foreach ($this->searchablePages->searchableLocales($page) as $locale) {
        $expected[$page->id.':'.$locale->id] = true;
      }
    });
    $actual = PublicSearchIndex::query()->where('site_id', $site->id)->get(['page_id', 'locale_id']);
    $actualKeys = $actual->mapWithKeys(fn ($row) => [$row->page_id.':'.$row->locale_id => true])->all();
    $missing = count(array_diff_key($expected, $actualKeys));
    $extra = count(array_diff_key($actualKeys, $expected)) + $actual->count() - count($actualKeys);

    return Health::check('search', $missing + $extra > 0 ? 'warning' : ($expected === [] ? 'not_applicable' : 'healthy'), $missing + $extra > 0 ? 'search_review' : ($expected === [] ? 'search_empty' : 'search_ready'), $route, ['missing' => $missing, 'extra' => $extra, 'count' => count($expected)]);
  }
}
