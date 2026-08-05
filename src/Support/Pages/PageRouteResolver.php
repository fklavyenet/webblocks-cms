<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Http\Request;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Sites\ResolvedSite;
use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;
use WebBlocks\Cms\Support\Sites\SiteResolver;

class PageRouteResolver
{
  public function __construct(
    private readonly SiteResolver $siteResolver,
    private readonly LocaleResolver $localeResolver,
    private readonly SiteDomainNormalizer $siteDomainNormalizer,
  ) {}

  public function currentSite(?Request $request = null): Site
  {
    return $this->siteResolver->current($request);
  }

  public function resolvedSite(?Request $request = null): ResolvedSite
  {
    return $this->siteResolver->resolve($request);
  }

  public function currentLocale(?Request $request = null): Locale
  {
    $resolvedSite = $this->resolvedSite($request);

    return $this->localeResolver->current($request, $resolvedSite->site);
  }

  public function homePath(?string $localeCode = null, ?Site $site = null): ?string
  {
    $resolvedSite = $site ?? $this->siteResolver->primary();
    $locale = $this->requestedLocale($localeCode, $resolvedSite);

    if (! $locale) {
      return null;
    }

    return $this->applyLocalePrefix('/', $locale);
  }

  public function homeUrl(?string $localeCode = null, ?Site $site = null): string
  {
    $resolvedSite = $site ?? $this->siteResolver->primary();
    $path = $this->homePath($localeCode, $resolvedSite) ?? '/';
    $domain = $this->canonicalDomainFor($resolvedSite);

    if (! $domain) {
      return url($path);
    }

    $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: request()?->getScheme() ?: 'http';

    return $scheme.'://'.$domain.$path;
  }

  public function searchPath(?string $localeCode = null, ?Site $site = null): ?string
  {
    $resolvedSite = $site ?? $this->siteResolver->primary();
    $locale = $this->requestedLocale($localeCode, $resolvedSite);

    if (! $locale) {
      return null;
    }

    return $this->applyLocalePrefix('/search', $locale);
  }

  public function searchJsonPath(?string $localeCode = null, ?Site $site = null): ?string
  {
    $resolvedSite = $site ?? $this->siteResolver->primary();
    $locale = $this->requestedLocale($localeCode, $resolvedSite);

    if (! $locale) {
      return null;
    }

    return $this->applyLocalePrefix('/search.json', $locale);
  }

  public function pathFor(Page $page, Locale|string|null $locale = null, ?Site $site = null): ?string
  {
    $resolvedSite = $site ?? $page->site;
    $resolvedLocale = $this->requestedLocale($locale, $resolvedSite);

    if (! $resolvedSite || ! $resolvedLocale) {
      return null;
    }

    $translation = $this->translationFor($page, $resolvedLocale, $resolvedSite);

    if (! $translation) {
      return null;
    }

    return $this->applyLocalePrefix($this->canonicalPublicPath($translation), $resolvedLocale);
  }

  public function urlFor(Page $page, Locale|string|null $locale = null, ?Site $site = null): ?string
  {
    $resolvedSite = $site ?? $page->site;
    $path = $this->pathFor($page, $locale, $resolvedSite);

    if (! $path) {
      return null;
    }

    $domain = $this->canonicalDomainFor($resolvedSite);

    if (! $domain) {
      return url($path);
    }

    $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: request()?->getScheme() ?: 'http';

    return $scheme.'://'.$domain.$path;
  }

  public function canonicalUrlFor(Page $page, Locale|string|null $locale = null, ?Site $site = null): ?string
  {
    return $this->urlFor($page, $locale, $site);
  }

  public function currentHostUrlFor(Page $page, Locale|string|null $locale = null, ?Request $request = null): ?string
  {
    $request ??= request();
    $resolvedSite = $page->site;
    $path = $this->pathFor($page, $locale, $resolvedSite);

    if (! $path) {
      return null;
    }

    $requestedHost = $this->siteDomainNormalizer->normalize($request?->getHost());
    $host = $requestedHost && $this->siteResolver->activeSiteDomainFor($resolvedSite, $requestedHost)
      ? $requestedHost
      : $this->canonicalDomainFor($resolvedSite);

    if (! $host) {
      return url($path);
    }

    $scheme = $request?->getScheme() ?: (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http');

    return $scheme.'://'.$host.$path;
  }

  public function canonicalDomainFor(?Site $site = null): ?string
  {
    if (! $site) {
      return null;
    }

    return $this->siteResolver->primaryDomainFor($site)?->domain
      ?? $site->domain;
  }

  public function findPublishedPage(Request $request, ?string $slug = null): ?Page
  {
    return $this->findPublishedPageByPath($request, $slug === null ? '/' : PageTranslation::pathFromSlug($slug));
  }

  public function findPublishedPageByPath(Request $request, string $path): ?Page
  {
    $site = $this->resolvedSite($request)->site;
    $localeCode = Locale::normalizeCode((string) $request->route('locale'));
    $locale = $localeCode !== null
      ? $this->localeResolver->enabled($localeCode, $site)
      : $this->localeResolver->default();

    if ($localeCode !== null && (! $locale || $locale->is_default)) {
      return null;
    }

    if (! $locale) {
      return null;
    }

    try {
      $path = PagePath::canonicalize($path);
    } catch (\InvalidArgumentException) {
      return null;
    }

    if (PagePath::isReserved($path)) {
      return null;
    }

    $legacyPath = $path !== '/' && ! str_starts_with($path, '/p/')
      ? '/p'.$path
      : null;

    $translation = PageTranslation::query()
      ->with(['page', 'locale'])
      ->where('site_id', $site->id)
      ->where('locale_id', $locale->id)
      ->where(fn ($query) => $query
        ->where('path', $path)
        ->when($legacyPath, fn ($legacyQuery) => $legacyQuery->orWhere('path', $legacyPath)))
      ->whereHas('page', fn ($query) => $query
        ->where('site_id', $site->id)
        ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
        ->where('status', 'published'))
      ->first();

    if (! $translation) {
      return null;
    }

    $page = $translation->page;
    $page->setRelation('currentTranslation', $translation);
    $page->setRelation('site', $page->site ?? $site);

    return $page;
  }

  public function legacyRedirectPath(Request $request, string $legacyPath): ?string
  {
    $path = '/'.ltrim($legacyPath, '/');
    $page = $this->findPublishedPageByPath($request, $path);

    if (! $page) {
      return null;
    }

    return $this->pathFor($page, $request->route('locale'), $page->site);
  }

  public function translationFor(Page $page, Locale|string|null $locale = null, ?Site $site = null): ?PageTranslation
  {
    $resolvedSite = $site ?? $page->site;
    $resolvedLocale = $this->requestedLocale($locale, $resolvedSite);

    if (! $resolvedLocale) {
      return null;
    }

    if ($resolvedSite && (int) $page->site_id !== (int) $resolvedSite->id) {
      return null;
    }

    $translations = $page->relationLoaded('translations') ? $page->translations : null;

    if ($page->relationLoaded('currentTranslation')) {
      $currentTranslation = $page->getRelation('currentTranslation');

      if ($currentTranslation?->locale_id === $resolvedLocale->id) {
        return $currentTranslation;
      }
    }

    $translation = $translations?->first(fn (PageTranslation $candidate) => $candidate->locale_id === $resolvedLocale->id)
      ?? $page->translations()->where('locale_id', $resolvedLocale->id)->first();

    if ($translation) {
      $page->setRelation('currentTranslation', $translation);
    }

    return $translation;
  }

  public function siteLocale(?string $localeCode = null, ?Site $site = null): Locale
  {
    $resolvedSite = $site ?? $this->siteResolver->primary();
    $locale = $this->requestedLocale($localeCode, $resolvedSite);

    return $locale ?? $this->localeResolver->default();
  }

  private function requestedLocale(Locale|string|null $locale = null, ?Site $site = null): ?Locale
  {
    $defaultLocale = $this->localeResolver->default();

    if ($locale === null) {
      return $site && ! $site->hasEnabledLocale($defaultLocale) ? null : $defaultLocale;
    }

    if ($locale instanceof Locale) {
      return $site && ! $site->hasEnabledLocale($locale) ? null : $locale;
    }

    if (is_string($locale) && Locale::normalizeCode($locale) !== null) {
      if ($site) {
        return $this->localeResolver->enabled($locale, $site);
      }

      return $this->localeResolver->enabled($locale);
    }

    return null;
  }

  private function applyLocalePrefix(string $path, Locale $locale): string
  {
    $normalizedPath = '/'.ltrim($path, '/');

    if ($normalizedPath === '//') {
      $normalizedPath = '/';
    }

    if ($locale->is_default) {
      return $normalizedPath;
    }

    return $normalizedPath === '/'
      ? '/'.$locale->code
      : '/'.$locale->code.$normalizedPath;
  }

  private function canonicalPublicPath(PageTranslation $translation): string
  {
    $path = $translation->path ?: PageTranslation::pathFromSlug($translation->slug);

    if (str_starts_with($path, '/p/')) {
      return '/'.ltrim(substr($path, 3), '/');
    }

    return $path;
  }
}
