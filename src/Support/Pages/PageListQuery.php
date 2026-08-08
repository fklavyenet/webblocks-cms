<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;

/**
 * Resolves the pages a `page-list` block renders.
 *
 * Five filters are enforced here and are deliberately not exposed as block
 * settings: published status, site scope, a resolvable translation in the
 * render locale, Shared Slot source pages, and the page the block sits on.
 * A list block that leaked drafts, other sites, half-translated rows or
 * internal source pages would be a content incident, not a layout preference.
 */
class PageListQuery
{
  private const DESCRIPTION_LIMIT = 160;

  public function __construct(private readonly PageRouteResolver $routeResolver) {}

  /**
   * @return Collection<int, PageListItem>
   */
  public function itemsFor(Block $block): Collection
  {
    $settings = $block->pageListSettings();
    $site = $block->renderSite();

    if (! $site) {
      return collect();
    }

    $locale = $this->routeResolver->siteLocale($block->renderLocaleCode(), $site);
    $localeId = $locale->id ? (int) $locale->id : null;

    if (! $localeId) {
      return collect();
    }

    $pathPrefix = $this->resolvePathPrefix($block, $settings, $locale, $site);

    /*
     * An unfinished configuration renders nothing. A path-scoped block with no
     * usable prefix, or a type-scoped block with no page type, would otherwise
     * fall through to "every published page on the site".
     */
    if ($settings->scope === PageListSettings::SCOPE_PAGE_TYPE) {
      if ($settings->pageType === null) {
        return collect();
      }
    } elseif ($pathPrefix === null) {
      return collect();
    }

    $currentPageId = $block->renderPageId();

    $pages = $this->baseQuery($site, $localeId, $settings, $pathPrefix, $currentPageId)
      ->with([
        'site',
        'translations' => fn ($query) => $query->where('locale_id', $localeId)->with('ogImageMedia'),
      ])
      ->limit($settings->limit)
      ->get();

    return $pages
      ->map(fn (Page $page): ?PageListItem => $this->toItem($page, $locale, $site, $settings))
      ->filter()
      ->values();
  }

  private function baseQuery(
    Site $site,
    int $localeId,
    PageListSettings $settings,
    ?string $pathPrefix,
    ?int $currentPageId,
  ): Builder {
    $query = Page::query()
      ->where('site_id', $site->id)
      ->where('status', Page::STATUS_PUBLISHED)
      // Shared Slot source pages are internal: never routed, never listed.
      ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
      ->whereHas('translations', function ($translationQuery) use ($localeId, $pathPrefix): void {
        $translationQuery
          ->where('locale_id', $localeId)
          ->whereNotNull('path')
          ->where('path', '!=', '');

        if ($pathPrefix !== null) {
          $escaped = addcslashes($pathPrefix, '%_\\');

          $translationQuery->where(fn ($pathQuery) => $pathQuery
            ->where('path', $pathPrefix)
            ->orWhere('path', 'like', $escaped.'/%'));
        }
      });

    if ($settings->scope === PageListSettings::SCOPE_PAGE_TYPE) {
      $query->whereHas('pageType', fn ($typeQuery) => $typeQuery->where('slug', $settings->pageType));
    }

    if ($settings->excludeCurrent && $currentPageId) {
      $query->where('id', '!=', $currentPageId);
    }

    return $this->applySort($query, $settings, $localeId);
  }

  private function applySort(Builder $query, PageListSettings $settings, int $localeId): Builder
  {
    return match ($settings->sort) {
      PageListSettings::SORT_PUBLISHED_ASC => $query->orderBy('published_at')->orderBy('id'),
      PageListSettings::SORT_TITLE_ASC => $query
        ->orderBy($this->translationColumn('name', $localeId))
        ->orderBy('id'),
      PageListSettings::SORT_PATH_ASC => $query
        ->orderBy($this->translationColumn('path', $localeId))
        ->orderBy('id'),
      default => $query->orderByDesc('published_at')->orderByDesc('id'),
    };
  }

  /**
   * Sorting on a translated column, the same correlated-subquery shape
   * `Page::scopeOrderByDefaultTranslation()` already uses in the admin. It
   * keeps `limit` correct in SQL instead of sorting a truncated result set
   * in PHP.
   */
  private function translationColumn(string $column, int $localeId): Builder
  {
    return PageTranslation::query()
      ->select($column)
      ->whereColumn((new PageTranslation)->qualifyColumn('page_id'), (new Page)->qualifyColumn('id'))
      ->where('locale_id', $localeId)
      ->limit(1);
  }

  private function resolvePathPrefix(
    Block $block,
    PageListSettings $settings,
    Locale $locale,
    Site $site,
  ): ?string {
    if ($settings->scope === PageListSettings::SCOPE_PATH_PREFIX) {
      return $settings->pathPrefix;
    }

    if ($settings->scope !== PageListSettings::SCOPE_SUBTREE_OF_CURRENT) {
      return null;
    }

    $currentPage = $block->renderPage();

    if (! $currentPage) {
      return null;
    }

    $currentPath = $this->routeResolver->translationFor($currentPage, $locale, $site)?->path;

    return PageListSettings::normalizePathPrefix($currentPath);
  }

  private function toItem(Page $page, Locale $locale, Site $site, PageListSettings $settings): ?PageListItem
  {
    $translation = $this->routeResolver->translationFor($page, $locale, $site);
    $url = $this->routeResolver->pathFor($page, $locale, $site);

    if (! $translation || $url === null) {
      return null;
    }

    $title = trim((string) ($translation->name ?: $page->title ?: ''));

    if ($title === '') {
      return null;
    }

    $description = $settings->showDescription ? $this->describe($translation) : null;

    return new PageListItem(
      pageId: (int) $page->id,
      title: $title,
      url: $url,
      description: $description,
      thumbnail: $settings->showThumbnail ? $translation->ogImageMedia : null,
    );
  }

  /**
   * The card description, in fallback order.
   *
   * `list_excerpt` is what an editor wrote for a listing, so it renders whole:
   * cutting a sentence somebody composed for this exact card would be worse
   * than a card one line taller, and the field is capped at 300 characters on
   * the way in. The SEO description is borrowed rather than authored — it is
   * written for a search result — so it is trimmed to a card-sized length.
   */
  private function describe(PageTranslation $translation): ?string
  {
    $excerpt = trim((string) ($translation->list_excerpt ?? ''));

    if ($excerpt !== '') {
      return $excerpt;
    }

    $seoDescription = trim((string) ($translation->seo_description ?? ''));

    return $seoDescription !== '' ? Str::limit($seoDescription, self::DESCRIPTION_LIMIT) : null;
  }
}
