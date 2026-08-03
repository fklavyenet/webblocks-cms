<?php

namespace WebBlocks\Cms\Support\Search;

use Illuminate\Support\Collection;
use Throwable;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PublicSearchIndex;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Pages\PublicSharedSlotResolver;

class PublicSearchIndexer
{
  /**
   * Depth of nested deferrals; the reactive refresh path is off while above 0.
   *
   * Every block, translation and slot save reindexes the whole page it belongs
   * to. That is right for an editor changing one block, and quadratic for a
   * bulk writer: an import of N blocks spread over P pages walks every page's
   * full block tree N times. Bulk writers defer instead and rebuild once.
   */
  private static int $deferred = 0;

  /**
   * Depth of nested coalescing scopes; refresh targets queue up while above 0.
   *
   * Deferring assumes the caller knows what to rebuild afterwards, which only a
   * bulk writer does. One editor save does not know: it writes a block row and
   * up to four translation rows, and each of those save hooks asks for the same
   * reindex. Coalescing keeps the hooks' answer — they are the ones that know
   * which pages are affected — and collapses the repeats into one pass at the
   * end of the request.
   */
  private static int $coalescing = 0;

  /**
   * Pages awaiting a coalesced rebuild, as page id => locale id, where a null
   * locale means every searchable locale on that page.
   *
   * @var array<int, int|null>
   */
  private static array $pendingPages = [];

  /**
   * Shared slot ids awaiting a coalesced rebuild, expanded to the pages that
   * use them at flush time rather than now — the slot assignments are still
   * being rewritten while the scope is open.
   *
   * @var array<int, int>
   */
  private static array $pendingSharedSlots = [];

  public function __construct(
    private readonly PublicSearchSchema $schema,
    private readonly SearchablePageResolver $searchablePages,
    private readonly SearchTextNormalizer $normalizer,
    private readonly BlockSearchTextExtractorRegistry $extractors,
    private readonly BlockTranslationResolver $blockTranslationResolver,
    private readonly PublicSharedSlotResolver $publicSharedSlotResolver,
  ) {}

  public function rebuild(?Site $site = null, ?Locale $locale = null, ?Page $page = null): PublicSearchRebuildResult
  {
    $result = new PublicSearchRebuildResult;

    if (! $this->schema->tableExists()) {
      return $result;
    }

    if ($page) {
      return $this->rebuildPage($page, $locale);
    }

    $this->clearScope($site, $locale);

    $this->searchablePages->query($site)
      ->orderBy('id')
      ->get()
      ->each(function (Page $candidate) use ($locale, $result): void {
        $this->searchablePages->loadForIndexing($candidate);
        $candidateLocales = $this->searchablePages->candidateLocales($candidate, $locale);
        $locales = $this->searchablePages->searchableLocales($candidate, $locale);

        $skippedLocales = max(0, $candidateLocales->count() - $locales->count());

        if ($skippedLocales > 0) {
          $result->addSkipped($skippedLocales);
        }

        if ($locales->isEmpty()) {
          return;
        }

        foreach ($locales as $resolvedLocale) {
          $this->indexPageLocale($candidate, $resolvedLocale);
          $result->addIndexed();
        }
      });

    return $result;
  }

  public function rebuildPage(Page|int $page, ?Locale $locale = null): PublicSearchRebuildResult
  {
    $result = new PublicSearchRebuildResult;
    $page = $page instanceof Page ? $page : Page::query()->find($page);

    if (! $page || ! $this->schema->tableExists()) {
      return $result;
    }

    $this->deletePage($page, $locale);

    if (! $this->searchablePages->shouldIndex($page)) {
      return $result->addSkipped();
    }

    $this->searchablePages->loadForIndexing($page);
    $candidateLocales = $this->searchablePages->candidateLocales($page, $locale);
    $locales = $this->searchablePages->searchableLocales($page, $locale);

    $skippedLocales = max(0, $candidateLocales->count() - $locales->count());

    if ($skippedLocales > 0) {
      $result->addSkipped($skippedLocales);
    }

    if ($locales->isEmpty()) {
      return $result;
    }

    foreach ($locales as $resolvedLocale) {
      $this->indexPageLocale($page, $resolvedLocale);
      $result->addIndexed();
    }

    return $result;
  }

  /**
   * Run a bulk write with the save-hook reindex path switched off.
   *
   * Only the reactive refresh* methods are suspended. An explicit rebuild()
   * still indexes, so the caller can — and must — rebuild once when it is done;
   * otherwise the pages it wrote stay out of the index.
   *
   * @template TReturn
   *
   * @param  callable():TReturn  $callback
   * @return TReturn
   */
  public static function deferring(callable $callback): mixed
  {
    self::$deferred++;

    try {
      return $callback();
    } finally {
      self::$deferred--;
    }
  }

  public static function isDeferred(): bool
  {
    return self::$deferred > 0;
  }

  /**
   * Run a write with the reactive reindexes collected instead of repeated.
   *
   * Unlike deferring(), this needs nothing from the caller afterwards: the save
   * hooks still name their targets, and the outermost scope rebuilds each one
   * exactly once. Safe to wrap around a transaction — the flush happens after
   * the callback returns, so it reads committed rows.
   *
   * @template TReturn
   *
   * @param  callable():TReturn  $callback
   * @return TReturn
   */
  public static function coalescing(callable $callback): mixed
  {
    $indexer = app(self::class);
    $indexer->beginCoalescing();

    try {
      $result = $callback();
    } catch (Throwable $exception) {
      // The write did not land, so neither did anything worth reindexing.
      $indexer->abandonCoalescing();

      throw $exception;
    }

    $indexer->endCoalescing();

    return $result;
  }

  public static function isCoalescing(): bool
  {
    return self::$coalescing > 0;
  }

  public function beginCoalescing(): void
  {
    self::$coalescing++;
  }

  /**
   * Close one coalescing scope, rebuilding the collected targets at the last.
   */
  public function endCoalescing(): void
  {
    if (self::$coalescing === 0) {
      return;
    }

    if (--self::$coalescing > 0) {
      return;
    }

    $this->flushPending();
  }

  /**
   * Close one coalescing scope and throw away what it collected.
   */
  public function abandonCoalescing(): void
  {
    if (self::$coalescing === 0) {
      return;
    }

    if (--self::$coalescing === 0) {
      self::discardPending();
    }
  }

  public function refreshPage(Page|int $page, ?Locale $locale = null): void
  {
    if (self::$deferred > 0) {
      return;
    }

    if (self::$coalescing > 0) {
      self::queuePage($page instanceof Page ? (int) $page->id : (int) $page, $locale?->id);

      return;
    }

    $this->rebuildPage($page, $locale);
  }

  public function deletePage(Page|int $page, ?Locale $locale = null): void
  {
    if (! $this->schema->tableExists()) {
      return;
    }

    $pageId = $page instanceof Page ? $page->id : (int) $page;

    PublicSearchIndex::query()
      ->where('page_id', $pageId)
      ->when($locale, fn ($query) => $query->where('locale_id', $locale->id))
      ->delete();
  }

  public function refreshSharedSlot(SharedSlot|int $sharedSlot): void
  {
    if (self::$deferred > 0 || ! $this->schema->tableExists()) {
      return;
    }

    $sharedSlotId = $sharedSlot instanceof SharedSlot ? (int) $sharedSlot->id : (int) $sharedSlot;

    if (self::$coalescing > 0) {
      self::$pendingSharedSlots[$sharedSlotId] = $sharedSlotId;

      return;
    }

    $this->pagesUsingSharedSlot($sharedSlotId)
      ->each(fn (int $pageId) => $this->refreshPage($pageId));
  }

  /**
   * Rebuild every target collected while coalescing, each page once.
   */
  private function flushPending(): void
  {
    $pages = self::$pendingPages;
    $sharedSlots = self::$pendingSharedSlots;
    self::discardPending();

    if (! $this->schema->tableExists()) {
      return;
    }

    foreach ($sharedSlots as $sharedSlotId) {
      foreach ($this->pagesUsingSharedSlot($sharedSlotId) as $pageId) {
        // Shared slot content is not locale-scoped, so it supersedes any
        // single-locale entry already queued for the same page.
        $pages[$pageId] = null;
      }
    }

    if ($pages === []) {
      return;
    }

    $localeIds = array_values(array_unique(array_filter($pages)));
    $locales = $localeIds === []
      ? collect()
      : Locale::query()->whereKey($localeIds)->get()->keyBy('id');

    foreach ($pages as $pageId => $localeId) {
      $this->rebuildPage($pageId, $localeId === null ? null : $locales->get($localeId));
    }
  }

  private static function queuePage(int $pageId, ?int $localeId): void
  {
    if ($pageId <= 0) {
      return;
    }

    if (! array_key_exists($pageId, self::$pendingPages)) {
      self::$pendingPages[$pageId] = $localeId;

      return;
    }

    // A page-wide refresh subsumes a single-locale one, and two different
    // locales on one page are only both satisfied by rebuilding all of them.
    if (self::$pendingPages[$pageId] !== $localeId) {
      self::$pendingPages[$pageId] = null;
    }
  }

  private static function discardPending(): void
  {
    self::$pendingPages = [];
    self::$pendingSharedSlots = [];
  }

  /**
   * @return Collection<int, int>
   */
  private function pagesUsingSharedSlot(int $sharedSlotId): Collection
  {
    return Page::query()
      ->where('status', Page::STATUS_PUBLISHED)
      ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
      ->whereHas('slots', fn ($query) => $query->where('shared_slot_id', $sharedSlotId))
      ->pluck('id')
      ->unique()
      ->values();
  }

  private function clearScope(?Site $site = null, ?Locale $locale = null): void
  {
    PublicSearchIndex::query()
      ->when($site, fn ($query) => $query->where('site_id', $site->id))
      ->when($locale, fn ($query) => $query->where('locale_id', $locale->id))
      ->delete();
  }

  private function indexPageLocale(Page $page, Locale $locale): void
  {
    $translation = $page->translationForLocale($locale);
    $url = $page->publicPath($locale->code);

    if (! $translation || ! $url) {
      return;
    }

    $content = $this->buildContent($page, $locale);
    $title = $this->normalizer->normalize($translation->name);

    PublicSearchIndex::query()->updateOrCreate(
      [
        'page_id' => $page->id,
        'locale_id' => $locale->id,
      ],
      [
        'site_id' => $page->site_id,
        'title' => $title,
        'excerpt' => $this->normalizer->excerpt($content),
        'url' => $url,
        'content' => $content,
        'indexed_at' => now(),
      ],
    );
  }

  private function buildContent(Page $page, Locale $locale): string
  {
    $page->setRelation('currentTranslation', $page->translationForLocale($locale));

    $slotContent = $page->slots
      ->sortBy(fn (PageSlot $slot) => sprintf('%010d-%010d', (int) $slot->sort_order, (int) $slot->id))
      ->map(function (PageSlot $slot) use ($page, $locale) {
        if ($slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_DISABLED) {
          return '';
        }

        if ($slot->usesPageOwnedBlocks()) {
          $blocks = $page->blocks
            ->where('slot_type_id', $slot->slot_type_id)
            ->whereNull('parent_id')
            ->where('status', 'published')
            ->values();

          return $this->extractBlockTree(
            $this->blockTranslationResolver->resolveCollection($blocks, $locale, $page->site),
          );
        }

        if ($slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_SHARED_SLOT) {
          return $this->extractBlockTree(
            $this->publicSharedSlotResolver->resolve($slot, $locale),
          );
        }

        return '';
      })
      ->all();

    return $this->normalizer->join([
      $page->currentTranslation?->name,
      $page->currentTranslation?->slug,
      $page->currentTranslation?->path,
      $slotContent,
    ]);
  }

  private function extractBlockTree(Collection $blocks): string
  {
    return $blocks
      ->map(function (Block $block) {
        $children = $block->children instanceof Collection
          ? $this->extractBlockTree($block->children)
          : '';

        return $this->normalizer->join([
          $this->extractors->extract($block),
          $children,
        ]);
      })
      ->implode(' ');
  }
}
