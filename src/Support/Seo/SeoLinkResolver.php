<?php

namespace WebBlocks\Cms\Support\Seo;

use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Support\Pages\PagePath;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Pages\PublicMount;
use WebBlocks\Cms\Support\Pages\PublicPageAccess;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;

/** Native identity only: no HTTP, redirects, plugin queries or provider record discovery. */
final class SeoLinkResolver
{
  public function __construct(private readonly PageRouteResolver $routes, private readonly PublicMount $mount) {}

  public function resolve(Site $site, string $sourceUrl, string $locale, string $href): array
  {
    return app(RequestMemo::class)->remember('seo.link', [$site->getConnectionName(), $site->id, $sourceUrl, $locale, $href], fn () => $this->inspect($site, $sourceUrl, $locale, $href));
  }

  private function inspect(Site $site, string $sourceUrl, string $locale, string $href): array
  {
    $result = ['state' => 'outside_scope', 'target_page_id' => null, 'target_locale' => null, 'suggested_url' => null];
    if ($href === '') {
      return $result;
    }
    $parts = parse_url($href);
    if ($parts === false || (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true))) {
      return $result;
    }
    if (isset($parts['host'])) {
      $hosts = app(RequestMemo::class)->remember('seo.hosts', [$site->id], fn () => SiteDomain::query()->where('site_id', $site->id)->active()->pluck('domain')->push($site->domain)->push(parse_url($this->routes->homeUrl(null, $site), PHP_URL_HOST))->filter()->map('strtolower'));
      if (! $hosts->contains(strtolower($parts['host']))) {
        return [...$result, 'state' => 'external'];
      }
      $expectedPort = parse_url($this->routes->homeUrl(null, $site), PHP_URL_PORT);
      if (isset($parts['port']) && $parts['port'] !== ($expectedPort ?? (strtolower($parts['scheme'] ?? 'https') === 'http' ? 80 : 443))) {
        return $result;
      }
    }
    $path = $parts['path'] ?? '/';
    if (! str_starts_with($path, '/')) {
      $path = substr((string) parse_url($sourceUrl, PHP_URL_PATH), 0, strrpos((string) parse_url($sourceUrl, PHP_URL_PATH), '/') + 1).$path;
    }
    $segments = [];
    foreach (explode('/', $path) as $segment) {
      if ($segment === '..') {
        array_pop($segments);
      } elseif ($segment !== '' && $segment !== '.') {
        $segments[] = $segment;
      }
    }
    $mountedPath = '/'.implode('/', $segments);
    $path = $this->mount->unmount($mountedPath);
    if ($path === null) {
      return $result;
    }
    $locales = app(RequestMemo::class)->remember('seo.locales', [$site->id], fn () => $site->enabledLocales()->get());
    $targetLocale = $locales->firstWhere('is_default', true) ?? $locales->first();
    $segments = explode('/', trim($path, '/'));
    $prefixed = $locales->firstWhere('code', $segments[0]);
    if ($prefixed && ! $prefixed->is_default) {
      $targetLocale = $prefixed;
      $path = '/'.implode('/', array_slice($segments, 1));
    }
    if (! $targetLocale) {
      return $result;
    }
    try {
      $path = PagePath::canonicalize($path);
    } catch (\InvalidArgumentException) {
      return $result;
    }
    if (PagePath::isReserved($path)) {
      return $result;
    }
    // Active automatic prefixes own the route even if a retained native row exists.
    if (app(RequestMemo::class)->remember('seo.automatic-rules', [$site->id], fn () => AutomaticPageRule::query()->where('site_id', $site->id)->where('status', 'active')->get())->contains(fn ($rule) => str_starts_with($path, $rule->base_path.'/'))) {
      return [...$result, 'state' => 'automatic_not_checked', 'target_locale' => $targetLocale->code];
    }
    $paths = array_unique([$path, '/p'.($path === '/' ? '' : $path), str_starts_with($path, '/p/') ? substr($path, 2) : $path]);
    $translation = PageTranslation::query()->with(['page.translations.locale', 'locale'])
      ->where('site_id', $site->id)->where('locale_id', $targetLocale->id)->whereIn('path', $paths)
      ->whereHas('page', fn ($q) => $q->where('site_id', $site->id)->whereNotIn('page_type', [Page::TYPE_SHARED_SLOT_SOURCE, Page::TYPE_SYSTEM]))
      ->orderByRaw('case when path = ? then 0 else 1 end', [$path])->first();
    if (! $translation || $translation->page->isRevisionRestoreCandidate()) {
      return [...$result, 'state' => 'missing_page', 'target_locale' => $targetLocale->code];
    }
    $target = $translation->page;
    $target->setRelation('site', $site);
    $result['target_page_id'] = (int) $target->id;
    $result['target_locale'] = $targetLocale->code;
    if ($target->status !== Page::STATUS_PUBLISHED) {
      return [...$result, 'state' => 'unpublished_target'];
    }
    if (app(PublicPageAccess::class)->protected($target)) {
      return [...$result, 'state' => 'protected_target'];
    }
    $canonical = $this->routes->pathFor($target, $targetLocale, $site);
    if ($locale !== $targetLocale->code) {
      return [...$result, 'state' => 'wrong_locale', 'suggested_url' => $this->routes->pathFor($target, $locale, $site)];
    }
    if ($canonical !== $mountedPath || (isset($parts['host']) && strtolower($parts['host']) !== strtolower((string) $this->routes->canonicalDomainFor($site)))) {
      return [...$result, 'state' => 'old_url', 'suggested_url' => $this->routes->urlFor($target, $locale, $site)];
    }

    return [...$result, 'state' => 'ok'];
  }
}
