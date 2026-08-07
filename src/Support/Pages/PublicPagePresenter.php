<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\PublicRendering\SlotWrapperResolver;

class PublicPagePresenter
{
  public function __construct(
    private readonly BlockTranslationResolver $blockTranslationResolver,
    private readonly PageAssetRenderer $pageAssetRenderer,
    private readonly PublicSharedSlotResolver $publicSharedSlotResolver,
    private readonly SlotWrapperResolver $slotWrapperResolver,
    private readonly PageRouteResolver $pageRouteResolver,
  ) {}

  /**
   * A caller that renders outside the public locale-prefixed route has no route
   * locale for the resolvers to read, so it must say which locale it is
   * rendering. Passing null keeps the public route's behaviour of resolving the
   * locale from the request.
   */
  public function present(Page $page, bool $preview = false, Locale|string|null $locale = null): array
  {
    $topLevelBlocks = $page->blocks
      ->whereNull('parent_id')
      ->when(! $preview, fn (Collection $blocks) => $blocks->where('status', 'published'))
      ->sortBy(fn (Block $block) => sprintf('%010d-%010d', (int) $block->sort_order, (int) $block->id))
      ->values();

    $translatedTopLevelBlocks = $this->blockTranslationResolver
      ->resolveCollection($topLevelBlocks, $locale, site: $page->site)
      ->values();

    $slots = $page->slots
      ->sortBy(fn (PageSlot $slot) => sprintf('%010d-%010d', (int) $slot->sort_order, (int) $slot->id))
      ->map(fn (PageSlot $slot) => $this->presentSlot($slot, $translatedTopLevelBlocks, $preview, $locale))
      ->values();

    $slots = $this->orderSlotsForLayout($page, $slots);

    return [
      'page' => $page,
      'slots' => $slots,
      'headPageAssets' => $this->pageAssetRenderer->headAssetsFor($page),
      'bodyEndPageAssets' => $this->pageAssetRenderer->bodyEndAssetsFor($page),
      'publicMeta' => $this->publicMeta($page),
      'publicLocaleCode' => $page->currentTranslation?->locale?->code,
      'publicBodyClass' => collect([
        'wb-public-body',
        $this->pageBodyClass($page),
        $this->stagedSourcePageBodyClass($page),
        app(PageLayoutManager::class)->bodyClassForHandle($page->publicShellPreset()),
      ])
        ->filter()
        ->flatMap(fn (string $classes) => preg_split('/\s+/', $classes, -1, PREG_SPLIT_NO_EMPTY) ?: [])
        ->unique()
        ->implode(' '),
    ];
  }

  private function pageBodyClass(Page $page): string
  {
    $slug = (string) ($page->currentTranslation?->slug ?? '');
    $slug = Str::slug($slug);
    $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)) ?? '';
    $slug = preg_replace('/-+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return 'wb-page-'.($slug !== '' ? $slug : 'home');
  }

  private function stagedSourcePageBodyClass(Page $page): ?string
  {
    $metadata = is_array($page->settings ?? null) ? ($page->settings['staged_update'] ?? null) : null;

    if (! is_array($metadata) || ($metadata['type'] ?? null) !== 'published_page_update') {
      return null;
    }

    $sourcePageId = $metadata['source_page_id'] ?? null;

    if (! is_numeric($sourcePageId) || (int) $sourcePageId === (int) $page->id) {
      return null;
    }

    $sourcePage = Page::query()
      ->whereKey((int) $sourcePageId)
      ->where('site_id', $page->site_id)
      ->with(['translations.locale'])
      ->first();

    if (! $sourcePage) {
      return null;
    }

    $localeId = $page->currentTranslation?->locale_id ?? Page::defaultLocaleId();
    $sourceTranslation = $sourcePage->translationForLocale($localeId) ?? $sourcePage->defaultTranslation();

    if ($sourceTranslation) {
      $sourcePage->setRelation('currentTranslation', $sourceTranslation);
    }

    return $this->pageBodyClass($sourcePage);
  }

  public function publicMeta(?Page $page = null): array
  {
    $site = $page?->site;
    $translation = $page?->currentTranslation;
    $siteName = $site?->publicDisplayName() ?? $site?->name ?? config('app.name');
    $siteLabel = $this->siteLabel($site);
    $siteSeoTitle = trim((string) ($site?->seo_title ?? ''));
    $siteSeoDescription = trim((string) ($site?->seo_description ?? ''));
    $siteSeoKeywords = trim((string) ($site?->seo_keywords ?? ''));
    $pageTitle = $this->trimmed($translation?->name);
    $seoTitle = $this->trimmed($translation?->seo_title);
    $pageLabel = $seoTitle ?? $pageTitle;
    $seoDescription = $this->trimmed($translation?->seo_description);
    $seoKeywords = $this->trimmed($translation?->seo_keywords);
    $ogTitle = $this->trimmed($translation?->og_title);
    $ogDescription = $this->trimmed($translation?->og_description);
    $ogImageMedia = $translation?->ogImage ?? $site?->socialImageAsset;
    $ogImage = $this->trimmed($ogImageMedia?->transformUrl('social'));
    $title = $this->composeSiteFirstTitle($siteLabel, $pageLabel)
      ?? config('app.name');
    $resolvedOgTitle = $ogTitle
      ?? $this->composeSiteFirstTitle($siteLabel, $pageLabel)
      ?? config('app.name');

    return [
      'site_name' => $siteName,
      'site_tagline' => trim((string) ($site?->tagline ?? config('app.slogan'))),
      'site_label' => $siteLabel,
      'title' => $title,
      'canonical_url' => $page ? $this->pageRouteResolver->canonicalUrlFor($page, $translation?->locale?->code, $site) : null,
      'meta_description' => $seoDescription ?? $siteSeoDescription,
      'meta_keywords' => $seoKeywords ?? $siteSeoKeywords,
      'favicon_url' => $this->trimmed($site?->faviconAsset?->url()),
      'og_title' => $resolvedOgTitle,
      'og_description' => $ogDescription
        ?? $seoDescription
        ?? $siteSeoDescription,
      'og_image' => $ogImage,
      'og_url' => $page ? $this->pageRouteResolver->canonicalUrlFor($page, $translation?->locale?->code, $site) : null,
      'og_site_name' => $siteName,
    ];
  }

  public function publicSiteLabel(?Page $page = null): ?string
  {
    return $this->siteLabel($page?->site);
  }

  private function presentSlot(PageSlot $slot, Collection $topLevelBlocks, bool $preview, Locale|string|null $locale = null): array
  {
    $page = $slot->page ?? $slot->page()->firstOrFail();
    $slug = $slot->slotType?->slug ?? 'main';
    $blocks = $this->applyRenderContext($this->resolveSlotBlocks($slot, $topLevelBlocks, $locale), $page, $slug, $preview);
    $wrapper = $this->slotWrapperResolver->resolve($page, $slot);

    if ($promotedNavbar = $this->promotedHeaderNavbar($slug, $blocks, $wrapper)) {
      $blocks = $promotedNavbar->children;
      $wrapper = $this->promoteWrapperToNavbar($wrapper, $promotedNavbar);
    }

    return [
      'slug' => $slug,
      'name' => $slot->slotType?->name ?? str($slug)->headline()->toString(),
      'wrapper' => [
        'preset' => $wrapper['preset'],
        'element' => $wrapper['element'],
        'attributes' => $wrapper['attributes'],
        'before_html' => $wrapper['before_html'] ?? null,
        'start_html' => $wrapper['start_html'] ?? null,
        'end_html' => $wrapper['end_html'] ?? null,
        'after_html' => $wrapper['after_html'] ?? null,
      ],
      'blocks' => $blocks,
    ];
  }

  private function promotedHeaderNavbar(string $slug, Collection $blocks, array $wrapper): ?Block
  {
    if ($slug !== 'header' || ($wrapper['preset'] ?? null) !== 'default' || $blocks->count() !== 1) {
      return null;
    }

    $block = $blocks->first();

    return $block instanceof Block && $block->typeSlug() === 'sticky-navbar' ? $block : null;
  }

  private function promoteWrapperToNavbar(array $wrapper, Block $navbar): array
  {
    $attributes = $wrapper['attributes'] ?? [];
    $existingClasses = trim((string) ($attributes['class'] ?? ''));
    $navbarClasses = collect(['wb-navbar', $navbar->navbarPositionClass()])->filter()->implode(' ');
    $classes = collect(explode(' ', $existingClasses.' '.$navbarClasses))
      ->map(fn (string $class) => trim($class))
      ->filter()
      ->unique()
      ->implode(' ');

    $attributes['class'] = $classes;
    $attributes['data-wb-public-block-type'] = $navbar->publicBlockTypeAttribute();

    return array_merge($wrapper, [
      'preset' => 'navbar',
      'element' => 'nav',
      'attributes' => $attributes,
    ]);
  }

  private function orderSlotsForLayout(Page $page, Collection $slots): Collection
  {
    $orderedSlugs = app(PageLayoutManager::class)->orderedSlotSlugsForHandle($page->publicShellPreset());

    if ($orderedSlugs === []) {
      return $slots->values();
    }

    $positions = array_flip($orderedSlugs);

    return $slots
      ->sortBy(fn (array $slot) => sprintf('%010d-%s', $positions[$slot['slug'] ?? ''] ?? 9999, $slot['slug'] ?? ''))
      ->values();
  }

  private function resolveSlotBlocks(PageSlot $slot, Collection $topLevelBlocks, Locale|string|null $locale = null): Collection
  {
    if ($slot->usesPageOwnedBlocks()) {
      return $topLevelBlocks->where('slot_type_id', $slot->slot_type_id)->values();
    }

    if (PageSlot::normalizeRuntimeSourceType($slot->source_type) === PageSlot::SOURCE_TYPE_SHARED_SLOT) {
      return $this->publicSharedSlotResolver->resolve($slot, $locale);
    }

    return collect();
  }

  private function applyRenderContext(Collection $blocks, Page $page, string $slotSlug, bool $preview = false): Collection
  {
    return $blocks
      ->map(function (Block $block) use ($page, $slotSlug, $preview) {
        $block->setRelation('renderPage', $page);
        $block->setAttribute('render_locale_code', $page->currentTranslation?->locale?->code);
        $block->setAttribute('render_slot_slug', $slotSlug);
        $block->setAttribute('render_preview', $preview);

        if ($block->relationLoaded('children')) {
          $children = $block->getRelation('children');

          if ($children instanceof Collection) {
            $block->setRelation('children', $this->applyRenderContext($children, $page, $slotSlug, $preview));
          }
        }

        return $block;
      })
      ->values();
  }

  private function trimmed(?string $value): ?string
  {
    $value = trim((string) $value);

    return $value !== '' ? $value : null;
  }

  private function siteLabel(mixed $site): ?string
  {
    return $this->trimmed($site?->display_name)
      ?? $this->trimmed($site?->seo_title)
      ?? $this->trimmed($site?->name);
  }

  private function composeSiteFirstTitle(?string $siteLabel, ?string $pageLabel): ?string
  {
    if ($siteLabel !== null && $pageLabel !== null) {
      $siteNormalized = Str::lower($siteLabel);
      $pageNormalized = Str::lower($pageLabel);

      if ($siteNormalized === $pageNormalized || Str::contains($pageNormalized, $siteNormalized)) {
        return $pageLabel;
      }

      return $siteLabel.' · '.$pageLabel;
    }

    return $siteLabel ?? $pageLabel;
  }
}
