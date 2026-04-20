<?php

namespace App\Support\Pages;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Support\Locales\LocaleResolver;
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

    public function currentLocale(?Request $request = null): Locale
    {
        return $this->localeResolver->current($request);
    }

    public function homePath(?string $localeCode = null, ?Site $site = null): string
    {
        $locale = $this->siteLocale($localeCode, $site);

        return $this->applyLocalePrefix('/', $locale);
    }

    public function pathFor(Page $page, Locale|string|null $locale = null, ?Site $site = null): string
    {
        $translation = $this->translationFor($page, $locale, $site);
        $resolvedLocale = $this->resolveLocale($locale, $site ?? $page->site);
        $basePath = $translation?->path ?? PageTranslation::pathFromSlug((string) $page->getRawOriginal('slug'));

        return $this->applyLocalePrefix($basePath, $resolvedLocale);
    }

    public function urlFor(Page $page, Locale|string|null $locale = null, ?Site $site = null): string
    {
        return url($this->pathFor($page, $locale, $site));
    }

    public function findPublishedPage(Request $request, ?string $slug = null): ?Page
    {
        $site = $this->currentSite($request);
        $localeCode = trim((string) $request->route('locale'));
        $locale = $this->siteLocale($localeCode !== '' ? $localeCode : null, $site);

        if ($localeCode !== '' && $locale->is_default) {
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
            ->whereHas('page', fn ($query) => $query->where('status', 'published'))
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
        $resolvedLocale = $this->resolveLocale($locale, $resolvedSite);
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

        if (! $translation && ! $resolvedLocale->is_default) {
            $defaultLocale = $this->localeResolver->default();
            $translation = $translations
                ? $translations->first(fn (PageTranslation $candidate) => $candidate->locale_id === $defaultLocale->id)
                : $page->translations()->where('locale_id', $defaultLocale->id)->first();
        }

        if ($translation) {
            $page->setRelation('currentTranslation', $translation);
        }

        return $translation;
    }

    private function siteLocale(?string $localeCode = null, ?Site $site = null): Locale
    {
        $resolvedSite = $site ?? $this->siteResolver->primary();
        $locale = $this->resolveLocale($localeCode, $resolvedSite);

        $enabledOnSite = $resolvedSite->locales()
            ->where('locales.id', $locale->id)
            ->wherePivot('is_enabled', true)
            ->exists();

        return $enabledOnSite ? $locale : $this->localeResolver->default();
    }

    private function resolveLocale(Locale|string|null $locale = null, ?Site $site = null): Locale
    {
        if ($locale instanceof Locale) {
            return $locale;
        }

        if (is_string($locale) && $locale !== '') {
            return $this->localeResolver->enabled($locale) ?? $this->localeResolver->default();
        }

        $resolvedLocale = $this->localeResolver->current();

        if (! $site) {
            return $resolvedLocale;
        }

        $enabledOnSite = $site->locales()
            ->where('locales.id', $resolvedLocale->id)
            ->wherePivot('is_enabled', true)
            ->exists();

        return $enabledOnSite ? $resolvedLocale : $this->localeResolver->default();
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
