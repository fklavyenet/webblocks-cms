<?php

namespace WebBlocks\Cms\Support\Sitemap;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\CmsModel;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Models\SiteLocale;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;

class SitemapGenerator
{
  public function __construct(
    private readonly PageRouteResolver $pageRouteResolver,
    private readonly SitemapCache $cache,
  ) {}

  public function pageCount(Site $site): int
  {
    return max(1, (int) ceil($this->entries($site)->count() / $this->maxUrls()));
  }

  public function sitemap(Site $site, ?int $page = null): ?string
  {
    $entries = $this->entries($site);
    $pageCount = max(1, (int) ceil($entries->count() / $this->maxUrls()));

    if ($page === null && $pageCount > 1) {
      return $this->index($site, $pageCount);
    }

    $page ??= 1;

    if ($page < 1 || $page > $pageCount) {
      return null;
    }

    return $this->urlset($entries->forPage($page, $this->maxUrls())->values());
  }

  public function sitemapUrl(Site $site): string
  {
    return rtrim($this->pageRouteResolver->homeUrl(null, $site), '/').'/sitemap.xml';
  }

  /** @return Collection<int, array{loc: string, lastmod: string, alternates: array<string, string>}> */
  private function entries(Site $site): Collection
  {
    $version = $this->cache->version((int) $site->id);
    $fingerprint = $this->fingerprint($site);

    return Cache::remember(
      'webblocks-cms:sitemap:site:'.$site->id.':entries:'.$version.':'.$fingerprint,
      now()->addSeconds(max(1, (int) config('webblocks-cms.sitemap.cache_seconds', 3600))),
      fn (): Collection => $this->buildEntries($site),
    );
  }

  /** @return Collection<int, array{loc: string, lastmod: string, alternates: array<string, string>}> */
  private function buildEntries(Site $site): Collection
  {
    $enabledLocales = $site->enabledLocales()->orderByDesc('is_default')->orderBy('code')->get();
    $enabledLocaleIds = $enabledLocales->pluck('id')->map(fn ($id): int => (int) $id)->all();

    $pages = Page::query()
      ->with(['site', 'translations.locale'])
      ->where('site_id', $site->id)
      ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
      ->where('status', Page::STATUS_PUBLISHED)
      ->orderBy('id')
      ->get()
      ->filter(fn (Page $page): bool => $this->isIndexable($page));

    return $pages->flatMap(function (Page $page) use ($site, $enabledLocales, $enabledLocaleIds): array {
      $translations = $page->translations
        ->filter(fn (PageTranslation $translation): bool => in_array((int) $translation->locale_id, $enabledLocaleIds, true));
      $alternates = $enabledLocales->mapWithKeys(function ($locale) use ($page, $site, $translations): array {
        if (! $translations->contains('locale_id', $locale->id)) {
          return [];
        }

        $url = $this->pageRouteResolver->urlFor($page, $locale, $site);

        return $url ? [$locale->code => $url] : [];
      })->all();

      if (count($alternates) > 1) {
        $defaultUrl = collect($alternates)->get($enabledLocales->firstWhere('is_default', true)?->code);

        if ($defaultUrl) {
          $alternates['x-default'] = $defaultUrl;
        }
      }

      return $translations->map(function (PageTranslation $translation) use ($page, $site, $alternates): ?array {
        $url = $this->pageRouteResolver->urlFor($page, $translation->locale, $site);

        if (! $url) {
          return null;
        }

        $updatedAt = $translation->updated_at && $translation->updated_at->greaterThan($page->updated_at)
          ? $translation->updated_at
          : $page->updated_at;

        return [
          'loc' => $url,
          'lastmod' => $updatedAt->toAtomString(),
          'alternates' => $alternates,
        ];
      })->filter()->all();
    })->values();
  }

  private function isIndexable(Page $page): bool
  {
    $settings = is_array($page->settings) ? $page->settings : [];
    $robots = strtolower((string) data_get($settings, 'seo.robots', data_get($settings, 'robots', '')));
    $redirect = data_get($settings, 'redirect.url')
      ?? data_get($settings, 'redirect.to')
      ?? data_get($settings, 'redirect_url')
      ?? data_get($settings, 'redirect_to');

    return ! filter_var(data_get($settings, 'seo.noindex', data_get($settings, 'noindex', false)), FILTER_VALIDATE_BOOL)
      && data_get($settings, 'indexable', true) !== false
      && ! str_contains($robots, 'noindex')
      && ! (is_string($redirect) && trim($redirect) !== '');
  }

  /** @param Collection<int, array{loc: string, lastmod: string, alternates: array<string, string>}> $entries */
  private function urlset(Collection $entries): string
  {
    $body = $entries->map(function (array $entry): string {
      $alternates = collect($entry['alternates'])->map(
        fn (string $href, string $locale): string => '    <xhtml:link rel="alternate" hreflang="'.$this->escape($locale).'" href="'.$this->escape($href).'"/>'
      )->implode("\n");
      $alternateXml = $alternates === '' ? '' : "\n".$alternates;

      return '  <url>'
        ."\n    <loc>".$this->escape($entry['loc']).'</loc>'
        ."\n    <lastmod>".$this->escape($entry['lastmod']).'</lastmod>'
        .$alternateXml
        ."\n  </url>";
    })->implode("\n");

    return $this->document('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">', $body, '</urlset>');
  }

  private function index(Site $site, int $pageCount): string
  {
    $base = rtrim($this->pageRouteResolver->homeUrl(null, $site), '/');
    $body = collect(range(1, $pageCount))->map(
      fn (int $page): string => "  <sitemap>\n    <loc>".$this->escape($base.'/sitemap/'.$page.'.xml')."</loc>\n  </sitemap>"
    )->implode("\n");

    return $this->document('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $body, '</sitemapindex>');
  }

  private function document(string $root, string $body, string $closingRoot): string
  {
    return '<?xml version="1.0" encoding="UTF-8"?>'."\n".$root."\n".($body === '' ? '' : $body."\n").$closingRoot."\n";
  }

  private function escape(string $value): string
  {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
  }

  private function maxUrls(): int
  {
    return max(1, min(50000, (int) config('webblocks-cms.sitemap.max_urls', 50000)));
  }

  private function fingerprint(Site $site): string
  {
    $state = collect([
      Page::class,
      PageTranslation::class,
      SiteDomain::class,
      SiteLocale::class,
    ])->map(function (string $modelClass) use ($site): array {
      /** @var CmsModel $model */
      $model = new $modelClass;
      $query = DB::table($model->getTable())->where('site_id', $site->id);

      return [
        'count' => (clone $query)->count(),
        'updated_at' => (clone $query)->max('updated_at'),
      ];
    })->all();

    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
  }
}
