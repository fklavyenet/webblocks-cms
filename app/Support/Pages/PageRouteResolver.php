<?php

namespace App\Support\Pages;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Support\Locales\LocaleResolver;
use App\Support\Sites\ResolvedSite;
use App\Support\Sites\SiteResolver;
use Illuminate\Http\Request;

class PageRouteResolver
{
    public function __construct(
        private readonly SiteResolver $siteResolver,
        private readonly LocaleResolver $localeResolver,
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

        return $this->applyLocalePrefix($translation->path, $resolvedLocale);
    }

    public function urlFor(Page $page, Locale|string|null $locale = null, ?Site $site = null): ?string
    {
        $resolvedSite = $site ?? $page->site;
        $path = $this->pathFor($page, $locale, $resolvedSite);

        if (! $path) {
            return null;
        }

        if (! $resolvedSite?->domain) {
            return url($path);
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: request()?->getScheme() ?: 'http';

        return $scheme.'://'.$resolvedSite->domain.$path;
    }

    public function findPublishedPage(Request $request, ?string $slug = null): ?Page
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

        $path = $slug === null ? '/' : PageTranslation::pathFromSlug($slug);

        $translation = PageTranslation::query()
            ->with(['page', 'locale'])
            ->where('site_id', $site->id)
            ->where('locale_id', $locale->id)
            ->where(function ($query) use ($path, $slug) {
                $query->where('path', $path);

                if ($slug !== null) {
                    $query->orWhere('slug', $slug);
                }
            })
            ->whereHas('page', fn ($query) => $query
                ->where('site_id', $site->id)
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

        $translation = $translations
            ? $translations->first(fn (PageTranslation $candidate) => $candidate->locale_id === $resolvedLocale->id)
            : $page->translations()->where('locale_id', $resolvedLocale->id)->first();

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
}
