<?php

namespace WebBlocks\Cms\Support\Mcp;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Pages\PagePath;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;

final class McpPages
{
  public function __construct(
    private readonly InternalContentPlanService $plans,
    private readonly PageRevisionManager $revisions,
    private readonly BlockTranslationResolver $translations,
    private readonly McpConfiguration $config,
    private readonly McpBlockFields $fields,
  ) {}

  public function page(int $siteId, array $input, bool $lock = false): Page
  {
    $query = Page::query()->where('site_id', $siteId)->where('status', Page::STATUS_PUBLISHED);
    if (isset($input['page_id'])) {
      $query->whereKey($input['page_id']);
    } else {
      try {
        $path = PagePath::canonicalize($input['path']);
      } catch (\InvalidArgumentException) {
        throw new McpToolException('page_missing');
      }
      $query->whereHas('translations', fn ($translations) => $translations->where('path', $path)
        ->whereHas('locale', fn ($locale) => $locale->where('code', $input['locale'])));
    }

    $page = $query->when($lock, fn ($query) => $query->lockForUpdate())->first();
    if (! $page || $page->isSharedSlotSourcePage() || $page->isSystemPage()) {
      throw new McpToolException('page_missing');
    }

    if (! $lock) {
      $this->assertEditableType($page);
    }

    return $page;
  }

  public function assertEditableType(Page $page): void
  {
    if ($page->isRevisionRestoreCandidate()) {
      throw new McpToolException('page_missing');
    }
  }

  public function locale(Page $page, string $code): Locale
  {
    $locale = $page->availableSiteLocales()->firstWhere('code', $code);
    if (! $locale || ! $page->translationForLocale($locale)) {
      throw new McpToolException('page_missing');
    }

    return $locale;
  }

  public function draft(Page $source, bool $lock = false): ?Page
  {
    $drafts = $this->plans->stagedDrafts($source)
      ->whereIn('status', [Page::STATUS_DRAFT, Page::STATUS_IN_REVIEW])
      ->orderBy('id')->limit(2)->when($lock, fn ($query) => $query->lockForUpdate())->get();
    if ($drafts->count() > 1) {
      throw new McpToolException('conflict');
    }

    return $drafts->first();
  }

  public function fingerprint(Page $page): string
  {
    return SnapshotContent::hash($this->revisions->snapshotForInspection($page));
  }

  public function version(Page $source, string $sourceFingerprint, ?Page $draft, Locale $locale): string
  {
    return SnapshotContent::hash([
      'source_id' => $source->id,
      'site_id' => $source->site_id,
      'locale_id' => $locale->id,
      'default_locale_id' => app(LocaleResolver::class)->default()->id,
      'source' => $sourceFingerprint,
      'draft_id' => $draft?->id,
      'draft' => $draft ? $this->fingerprint($draft) : null,
    ]);
  }

  public function hasVerifiedBase(Page $draft, string $sourceFingerprint): bool
  {
    return DB::table('wbcms_staged_page_bases')->where('page_id', $draft->id)->value('source_fingerprint') === $sourceFingerprint;
  }

  public function read(int $siteId, array $input): array
  {
    $source = $this->page($siteId, $input);
    $locale = $this->locale($source, $input['locale']);
    $translation = $source->translationForLocale($locale);
    $draft = $this->draft($source);
    $sourceFingerprint = $this->fingerprint($source);
    $version = $this->version($source, $sourceFingerprint, $draft, $locale);
    $sourceHeadings = $this->headings($source);
    $draftHeadings = $draft ? $this->headings($draft)->keyBy('revision_key') : collect();

    return [
      'site_id' => $siteId,
      'page_id' => $source->id,
      'locale' => $locale->code,
      'name' => $translation->name,
      'path' => $translation->path,
      'version' => $version,
      'draft' => $draft ? ['id' => $draft->id, 'status' => $draft->status, 'source_verified' => $this->hasVerifiedBase($draft, $sourceFingerprint)] : null,
      'headings' => $sourceHeadings->map(function (Block $block) use ($draftHeadings, $locale, $source, $draft): array {
        $copy = $block->revision_key ? $draftHeadings->get($block->revision_key) : null;

        return [
          'block_id' => $block->id,
          'type' => $block->typeSlug(),
          'slot' => $block->slotType?->slug,
          'title' => $this->title($source, $block, $locale),
          'draft_title' => $copy ? $this->title($draft, $copy, $locale) : null,
        ];
      })->values()->all(),
    ];
  }

  public function content(int $siteId, array $input): array
  {
    $source = $this->page($siteId, $input);
    $locale = $this->locale($source, $input['locale']);
    $draft = $this->draft($source);
    $fingerprint = $this->fingerprint($source);
    $defaultLocale = app(LocaleResolver::class)->default()->id === $locale->id;
    $managed = $draft?->setting('staged_update.managed_slots', []);
    $draftBlocks = $draft ? $this->staticBlocks($draft)->groupBy('revision_key') : collect();
    $blocks = $this->staticBlocks($source)->filter($this->fields->supports(...))->sortBy('id')->values();
    $page = $input['page'] ?? 1;

    return [
      'page_id' => $source->id,
      'locale' => $locale->code,
      'version' => $this->version($source, $fingerprint, $draft, $locale),
      'draft' => $draft ? ['id' => $draft->id, 'status' => $draft->status, 'source_verified' => $this->hasVerifiedBase($draft, $fingerprint), 'managed_slots' => $managed] : null,
      'blocks' => $blocks->forPage($page, 10)->map(function (Block $block) use ($draftBlocks, $locale, $defaultLocale, $managed): array {
        $slot = $block->slotType?->slug;
        $matches = $draftBlocks->get($block->revision_key, collect())->filter(fn (Block $copy) => $copy->slot_type_id === $block->slot_type_id && $copy->typeSlug() === $block->typeSlug());
        $draftBlock = $matches->count() === 1 && in_array($slot, $managed ?? [], true) ? $matches->first() : null;

        return [
          'block_id' => $block->id,
          'type' => $block->typeSlug(),
          'slot' => $slot,
          'text' => (object) $this->fields->values($block, $locale),
          'draft_text' => $draftBlock ? (object) $this->fields->values($draftBlock, $locale) : null,
          'accepts_media_id' => $defaultLocale && $this->fields->acceptsImage($block),
        ];
      })->values()->all(),
      'next_page' => $blocks->count() > $page * 10 ? $page + 1 : null,
    ];
  }

  public function preview(int $siteId, array $input): array
  {
    $source = $this->page($siteId, $input);
    $draft = $this->draft($source) ?? throw new McpToolException('draft_missing');

    return $this->previewLink($source, $draft, $this->locale($draft, $input['locale']));
  }

  public function previewLink(Page $source, Page $draft, Locale $locale): array
  {
    return [
      'page_id' => $source->id,
      'draft_id' => $draft->id,
      'locale' => $locale->code,
      'preview_url' => $this->config->origin().route('admin.pages.preview', ['page' => $draft, 'locale' => $locale->code], absolute: false),
    ];
  }

  /** @return Collection<int, Block> */
  public function headings(Page $page): Collection
  {
    return $this->staticBlocks($page)->filter(fn (Block $block) => in_array($block->typeSlug(), ['header', 'hero', 'content_header'], true));
  }

  /** @return Collection<int, Block> */
  public function staticBlocks(Page $page): Collection
  {
    $page->loadMissing(['slots', 'blocks.blockType', 'blocks.slotType']);
    $slots = $page->slots->filter(fn (PageSlot $slot) => $slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_PAGE)->keyBy('slot_type_id');
    $blocks = $page->blocks->keyBy('id');

    return $blocks->filter(function (Block $block) use ($slots, $blocks, $page): bool {
      if (! $block->revision_key || ! $slots->has($block->slot_type_id)) {
        return false;
      }

      // A collection may render the same child with different content for each row.
      $seen = [];
      for ($node = $block; $node; $node = $blocks->get($node->parent_id)) {
        if (isset($seen[$node->id]) || $node->setting('content_collection') !== null
          || ($page->isPublished() && $node->status !== 'published')) {
          return false;
        }
        $seen[$node->id] = true;
      }

      return ! $block->setting('content_bindings');
    });
  }

  private function title(Page $page, Block $block, Locale $locale): string
  {
    $resolved = $this->translations->resolve($block, $locale, $page->site);

    return (string) ($resolved->title ?: ($block->typeSlug() === 'hero' ? $block->setting('headline', $block->setting('title', '')) : ''));
  }
}
