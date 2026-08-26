<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use WebBlocks\Cms\Support\Applications\ApplicationDefinition;
use WebBlocks\Cms\Support\Applications\ApplicationRegistry;
use WebBlocks\Cms\Support\Blocks\BlockTranslationRegistry;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Pages\PageListItem;
use WebBlocks\Cms\Support\Pages\PageListQuery;
use WebBlocks\Cms\Support\Pages\PageListSettings;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeDefinition;
use WebBlocks\Cms\Support\Search\ReindexesPublicSearch;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class Block extends CmsModel
{
  use HasFactory;
  use ReindexesPublicSearch;

  protected $fillable = [
    'page_id',
    'parent_id',
    'type',
    'block_type_id',
    'source_type',
    'slot',
    'slot_type_id',
    'sort_order',
    'title',
    'subtitle',
    'content',
    'url',
    'media_id',
    'asset_id',
    'variant',
    'meta',
    'settings',
    'status',
    'is_system',
  ];

  protected function casts(): array
  {
    return [
      'is_system' => 'boolean',
    ];
  }

  protected function mediaId(): Attribute
  {
    return Attribute::make(
      get: fn ($value, array $attributes) => $value ?? ($attributes['asset_id'] ?? null),
      set: fn ($value) => ['media_id' => $value],
    );
  }

  protected function assetId(): Attribute
  {
    return Attribute::make(
      get: fn ($value, array $attributes) => $value ?? ($attributes['media_id'] ?? null),
      set: fn ($value) => ['media_id' => $value],
    );
  }

  protected static function booted(): void
  {
    static::saving(function (self $block): void {
      if ($block->block_type_id) {
        $resolvedBlockType = BlockType::query()->find($block->block_type_id);

        $block->type = $resolvedBlockType?->slug ?? $block->type;
        $block->source_type = $resolvedBlockType?->source_type ?? $block->source_type ?? 'static';
      }

      if ($block->slot_type_id) {
        $block->slot = SlotType::query()
          ->whereKey($block->slot_type_id)
          ->value('slug') ?? $block->slot;
      }
    });

    static::saved(function (self $block): void {
      static::refreshSearchForBlock($block->fresh('page'));
    });

    static::deleted(function (self $block): void {
      static::refreshSearchForBlock($block);
    });
  }

  public function page(): BelongsTo
  {
    return $this->belongsTo(Page::class)->with('translations');
  }

  public function blockType(): BelongsTo
  {
    return $this->belongsTo(BlockType::class);
  }

  public function slotType(): BelongsTo
  {
    return $this->belongsTo(SlotType::class);
  }

  public function parent(): BelongsTo
  {
    return $this->belongsTo(self::class, 'parent_id');
  }

  public function media(): BelongsTo
  {
    return $this->belongsTo(Media::class, 'media_id');
  }

  public function asset(): BelongsTo
  {
    return $this->media();
  }

  public function children(): HasMany
  {
    return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
  }

  public function publishedChildren(): HasMany
  {
    return $this->children()->where('status', 'published');
  }

  public function blockMedia(): HasMany
  {
    return $this->hasMany(BlockMedia::class)->orderBy('position');
  }

  public function blockAssets(): HasMany
  {
    return $this->blockMedia();
  }

  public function sharedSlotAssignments(): HasMany
  {
    return $this->hasMany(SharedSlotBlock::class)->orderBy('sort_order')->orderBy('id');
  }

  public function textTranslations(): HasMany
  {
    return $this->hasMany(BlockTextTranslation::class);
  }

  public function buttonTranslations(): HasMany
  {
    return $this->hasMany(BlockButtonTranslation::class);
  }

  public function imageTranslations(): HasMany
  {
    return $this->hasMany(BlockImageTranslation::class);
  }

  public function contactFormTranslations(): HasMany
  {
    return $this->hasMany(BlockContactFormTranslation::class);
  }

  public function pluginTranslations(): HasMany
  {
    return $this->hasMany(BlockPluginTranslation::class);
  }

  public function galleryItemTranslations(): HasManyThrough
  {
    return $this->hasManyThrough(
      BlockGalleryItemTranslation::class,
      BlockMedia::class,
      'block_id',
      'block_media_id',
      'id',
      'id',
    );
  }

  public function typeSlug(): ?string
  {
    return $this->blockType?->slug ?? $this->type;
  }

  public function translationFamily(): ?string
  {
    return app(BlockTranslationRegistry::class)->familyFor($this);
  }

  public function supportsTranslations(): bool
  {
    return $this->translationFamily() !== null;
  }

  public function translationStatus(?Locale $locale = null): array
  {
    return app(BlockTranslationResolver::class)->statusFor($this, $locale);
  }

  public function typeName(): string
  {
    return $this->blockType?->name ?? str($this->typeSlug() ?: 'block')->replace('-', ' ')->title()->toString();
  }

  public function slotName(): string
  {
    return $this->slotType?->name ?? str($this->slot ?: 'slot')->replace('-', ' ')->title()->toString();
  }

  public function editorLabel(): string
  {
    $title = $this->stringValueOrNull($this->title) ?? $this->translatedTextFieldValue('title');
    $content = $this->stringValueOrNull($this->content) ?? $this->translatedTextFieldValue('content');

    if (in_array($this->typeSlug(), ['section', 'container', 'cluster', 'grid', 'slider', 'slide', 'sticky-navbar'], true)) {
      $layoutName = $this->layoutAdminName();

      return $layoutName !== null
        ? $this->typeName().($this->typeSlug() === 'cluster' ? ' — ' : ' -- ').$layoutName
        : $this->typeName();
    }

    if (in_array($this->typeSlug(), ['plain_text', 'rich-text'], true)) {
      return $content ?? $this->typeName();
    }

    return $title ?? $this->typeName();
  }

  public function editorSummary(): ?string
  {
    if (in_array($this->typeSlug(), ['section', 'container', 'cluster', 'grid', 'slider', 'slide', 'sticky-navbar'], true)) {
      $childCount = $this->children->count();

      if ($childCount > 0) {
        $itemLabel = match ($this->typeSlug()) {
          'slider' => 'slide',
          'slide' => 'child block',
          default => 'child block',
        };

        return $childCount.' '.Str::plural($itemLabel, $childCount);
      }

      return 'Layout wrapper';
    }

    if (in_array($this->typeSlug(), ['card', 'card_header', 'card_body', 'card_footer'], true) && $this->children->isNotEmpty()) {
      $childCount = $this->children->count();

      return $childCount.' '.Str::plural('child block', $childCount);
    }

    if (in_array($this->typeSlug(), ['navigation-auto', 'menu', 'navbar-navigation'], true)) {
      return 'Location: '.str($this->navigationLocation())->headline();
    }

    if ($this->typeSlug() === 'page-list') {
      $settings = $this->pageListSettings();

      $scopeSummary = match ($settings->scope) {
        PageListSettings::SCOPE_PATH_PREFIX => $settings->pathPrefix ?? 'No path prefix',
        PageListSettings::SCOPE_SUBTREE_OF_CURRENT => 'Pages below this one',
        default => $settings->pageType
          ? str($settings->pageType)->headline().' pages'
          : 'No page type',
      };

      return $scopeSummary.', up to '.$settings->limit;
    }

    if ($this->typeSlug() === 'application') {
      return $this->applicationDefinition()?->name
        ?? ((string) $this->setting('application_handle', '') ?: 'Unconfigured application');
    }

    if ($this->typeSlug() === 'stat-card') {
      return $this->stringValueOrNull($this->title)
        ?? $this->translatedTextFieldValue('title')
        ?? $this->stringValueOrNull($this->subtitle)
        ?? $this->translatedTextFieldValue('subtitle')
        ?? $this->stringValueOrNull($this->content, true)
        ?? $this->translatedTextFieldValue('content', true);
    }

    if (in_array($this->typeSlug(), ['columns', 'feature-grid', 'link-list', 'cta'], true)) {
      $childCount = $this->children->count();

      if ($childCount > 0) {
        $itemLabel = match ($this->typeSlug()) {
          'link-list' => 'link list item',
          'cta' => 'action',
          'feature-grid' => 'feature item',
          default => 'column item',
        };

        return $childCount.' '.$itemLabel.($childCount === 1 ? '' : 's');
      }
    }

    $summary = collect([
      $this->typeSlug() === 'header' ? $this->stringValueOrNull($this->variant) : null,
      $this->stringValueOrNull($this->subtitle) ?? $this->translatedTextFieldValue('subtitle'),
      $this->stringValueOrNull($this->content, true) ?? $this->translatedTextFieldValue('content', true),
      $this->stringValueOrNull($this->url),
      $this->stringValueOrNull($this->variant),
    ])->first(fn ($value) => $value !== null);

    return $summary !== null ? (string) $summary : null;
  }

  public function parentCandidateLabel(): string
  {
    $detail = match ($this->typeSlug()) {
      'card' => $this->parentCandidateDetail($this->title),
      'section', 'container', 'cluster', 'grid', 'slider', 'slide', 'sticky-navbar' => $this->parentCandidateDetail($this->layoutAdminName()),
      default => $this->parentCandidateDetail($this->editorLabel()),
    };

    return $detail !== null
      ? $this->typeName().': '.$detail
      : $this->typeName();
  }

  public function layoutAdminName(): ?string
  {
    if (! in_array($this->typeSlug(), ['section', 'container', 'cluster', 'grid', 'slider', 'slide', 'sticky-navbar'], true)) {
      return null;
    }

    $name = trim((string) $this->setting('layout_name', ''));

    return $name !== '' ? $name : null;
  }

  private function parentCandidateDetail(?string $value): ?string
  {
    $resolved = str((string) ($value ?? ''))
      ->squish()
      ->trim();

    if ($resolved->isEmpty()) {
      return null;
    }

    return $this->truncateParentCandidateDetail($resolved)->toString();
  }

  private function truncateParentCandidateDetail(Stringable $value): Stringable
  {
    return $value->length() > 80
      ? $value->limit(80)
      : $value;
  }

  public function stringValueOrNull(mixed $value, bool $stripTags = false): ?string
  {
    if ($value === null) {
      return null;
    }

    $resolved = $stripTags ? strip_tags((string) $value) : (string) $value;
    $resolved = str($resolved)->squish()->toString();

    return $resolved !== '' ? $resolved : null;
  }

  public function headerAnchor(): ?string
  {
    if ($this->typeSlug() !== 'header') {
      return null;
    }

    $anchor = $this->stringValueOrNull($this->setting('anchor'))
      ?? $this->stringValueOrNull($this->url);

    if ($anchor === null) {
      return null;
    }

    $anchor = ltrim($anchor, '#');

    return preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_:.]*$/', $anchor) === 1
      ? $anchor
      : null;
  }

  public function tocEligibleHeadingText(): ?string
  {
    if ($this->typeSlug() !== 'header') {
      return null;
    }

    return $this->stringValueOrNull($this->title)
      ?? $this->stringValueOrNull($this->content)
      ?? $this->translatedTextFieldValue('title')
      ?? $this->translatedTextFieldValue('content');
  }

  /**
   * A table of contents describes the slot it lives in, not the page. Scoping
   * to $this->slot_type_id keeps a TOC in `sidebar` from listing headings that
   * are actually in `main`, and the reverse.
   *
   * Blocks in the same slot can be nested under different section/container
   * parents, whose sort_order values are independently numbered from each
   * other (see PageSlotBlockRequest-style creation: sort_order is scoped per
   * page+slot+parent). A flat sort by (sort_order, id) across those parents
   * does not reconstruct reading order, so headings are collected by walking
   * the slot's block tree in document order instead: each parent's own
   * children first, each level sorted by (sort_order, id).
   *
   * Shared-Slot-hosted content is out of scope: its block tree lives on a
   * separate hidden source page, never on $this->renderPage()->blocks, so a
   * TOC placed inside a Shared Slot finds nothing here rather than the wrong
   * page's headings.
   */
  public function publicTocHeadingBlocks(?string $localeCode = null): Collection
  {
    $page = $this->renderPage();

    if (! $page || $this->slot_type_id === null) {
      return collect();
    }

    $slotBlocks = $page->relationLoaded('blocks')
      ? $page->blocks
      : $page->blocks()->where('status', 'published')->orderBy('sort_order')->orderBy('id')->get();

    $slotBlocks = $slotBlocks->where('slot_type_id', $this->slot_type_id)->values();

    $resolver = app(BlockTranslationResolver::class);
    $translatedBlocks = $resolver->resolveCollection($slotBlocks, $localeCode ?? $this->renderLocaleCode());

    $childrenByParent = $translatedBlocks->groupBy(fn (Block $block) => $block->parent_id ?? 0);
    $sortKey = fn (Block $block) => sprintf('%010d-%010d', (int) $block->sort_order, (int) $block->id);

    $headings = collect();

    $walk = function (?int $parentId) use (&$walk, $childrenByParent, $sortKey, &$headings): void {
      $children = ($childrenByParent->get($parentId ?? 0) ?? collect())->sortBy($sortKey)->values();

      foreach ($children as $child) {
        if ($child->id !== $this->id
          && $child->typeSlug() === 'header'
          && in_array($child->variant, ['h2', 'h3'], true)
          && $child->headerAnchor() !== null
        ) {
          $headings->push($child);
        }

        $walk($child->id);
      }
    };

    $walk(null);

    return $headings->values();
  }

  public function pageListSettings(): PageListSettings
  {
    return PageListSettings::fromBlock($this);
  }

  /**
   * The pages this `page-list` block renders, already reduced to the render
   * locale. Derived at render time from a page query, never from child blocks.
   *
   * @return Collection<int, PageListItem>
   */
  public function pageListItems(): Collection
  {
    return app(PageListQuery::class)->itemsFor($this);
  }

  public function translatedTextFieldValue(string $field, bool $stripTags = false): ?string
  {
    if ($this->translationFamily() !== 'text') {
      return null;
    }

    $translations = $this->relationLoaded('textTranslations')
      ? $this->getRelation('textTranslations')
      : $this->textTranslations()->get();
    $defaultLocaleId = app(LocaleResolver::class)->default()->id;
    $translation = $translations->firstWhere('locale_id', $defaultLocaleId) ?? $translations->first();

    return $this->stringValueOrNull($translation?->{$field}, $stripTags);
  }

  public function metaItems(): Collection
  {
    $raw = $this->meta;

    if (is_array($raw)) {
      return collect($raw)
        ->map(fn ($item) => trim((string) $item))
        ->filter()
        ->values();
    }

    if (! is_string($raw) || trim($raw) === '') {
      return collect();
    }

    $decoded = json_decode($raw, true);

    if (! is_array($decoded)) {
      return collect();
    }

    return collect($decoded)
      ->map(fn ($item) => trim((string) $item))
      ->filter()
      ->values();
  }

  public function canAcceptChildren(): bool
  {
    $slug = $this->typeSlug();

    if ($slug === null) {
      return false;
    }

    if (in_array($slug, ['code', 'table', 'quote', 'toc', 'html'], true)) {
      return false;
    }

    if ((bool) ($this->blockType?->is_container ?? false)) {
      return true;
    }

    return in_array($slug, ['section', 'container', 'stack', 'split', 'cluster', 'grid', 'card', 'card_header', 'card_body', 'card_footer', 'hero', 'slider', 'slide', 'columns', 'feature-grid', 'cta', 'sticky-navbar', 'sidebar-navigation', 'sidebar-nav-group'], true);
  }

  public function canAcceptMoreChildren(): bool
  {
    if (! $this->canAcceptChildren()) {
      return false;
    }

    if ($this->typeSlug() !== 'split' || ! $this->exists) {
      return true;
    }

    $childCount = $this->relationLoaded('children')
      ? $this->children->count()
      : $this->children()->count();

    return $childCount < 2;
  }

  public function allowedChildTypeSlugs(): ?array
  {
    return match ($this->typeSlug()) {
      'hero', 'cta' => ['button_link', 'button'],
      'slider' => ['slide'],
      'columns' => ['column_item'],
      'feature-grid' => ['feature-item', 'column_item'],
      'card' => ['card_header', 'card_body', 'card_footer'],
      'sticky-navbar' => ['container', 'cluster', 'header', 'plain_text', 'rich-text', 'button_link', 'navbar-brand', 'navbar-navigation', 'header-actions', 'search-form'],
      'link-list' => ['link-list-item'],
      'sidebar-navigation' => ['sidebar-nav-item', 'sidebar-nav-group'],
      'sidebar-nav-group' => ['sidebar-nav-item'],
      default => null,
    };
  }

  public function linkListItemUrl(): ?string
  {
    return self::safePublicUrl($this->url);
  }

  public static function safePublicUrl(mixed $value): ?string
  {
    $url = trim((string) $value);

    if ($url === '' || preg_match('/\s/', $url) === 1) {
      return null;
    }

    if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $url) === 1) {
      $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));

      if (in_array($scheme, ['http', 'https'], true)) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
      }

      if ($scheme === 'mailto') {
        return substr($url, strlen('mailto:')) !== '' ? $url : null;
      }

      if ($scheme === 'tel') {
        return substr($url, strlen('tel:')) !== '' ? $url : null;
      }

      return null;
    }

    if (str_starts_with($url, '/')) {
      return str_starts_with($url, '//') ? null : $url;
    }

    if (str_starts_with($url, '#')) {
      return strlen($url) > 1 ? $url : null;
    }

    if (preg_match('/^[A-Za-z0-9._\/-]+(?:\?[A-Za-z0-9._~!$&\'()*+,;=:@%\/-]*)?(?:#[A-Za-z0-9._~!$&\'()*+,;=:@%\/-]*)?$/', $url) !== 1) {
      return null;
    }

    return str_starts_with($url, '//') ? null : $url;
  }

  public function canAcceptChildType(?string $childTypeSlug): bool
  {
    if (! $this->canAcceptChildren() || ! is_string($childTypeSlug) || $childTypeSlug === '') {
      return false;
    }

    if (in_array($childTypeSlug, ['navbar-brand', 'navbar-navigation'], true) && ! $this->hasNavbarAncestorOrSelf()) {
      return false;
    }

    if (in_array($childTypeSlug, ['card_header', 'card_body', 'card_footer'], true)) {
      return $this->typeSlug() === 'card';
    }

    if (in_array($this->typeSlug(), ['card_header', 'card_body', 'card_footer'], true) && in_array($childTypeSlug, ['card_header', 'card_body', 'card_footer'], true)) {
      return false;
    }

    $allowedChildTypeSlugs = $this->allowedChildTypeSlugs();

    return $allowedChildTypeSlugs === null
      || in_array($childTypeSlug, $allowedChildTypeSlugs, true);
  }

  public function hasNavbarAncestorOrSelf(): bool
  {
    $cursor = $this;

    while ($cursor) {
      if ($cursor->isNavbar()) {
        return true;
      }

      $cursor = $cursor->parent;
    }

    return false;
  }

  public function appearanceSetting(string $key): ?string
  {
    $value = trim((string) $this->setting($key, ''));

    return $value !== '' ? $value : null;
  }

  public function headerAlignmentClass(): ?string
  {
    return match ($this->appearanceSetting('alignment')) {
      'left' => 'wb-text-left',
      'center' => 'wb-text-center',
      'right' => 'wb-text-right',
      default => null,
    };
  }

  public function contentHeaderAlignmentClass(): ?string
  {
    return match ($this->appearanceSetting('alignment')) {
      'left' => 'wb-text-left',
      'center' => 'wb-text-center',
      'right' => 'wb-text-right',
      default => null,
    };
  }

  public function publicContentIconSlug(): ?string
  {
    $icon = trim((string) $this->setting('icon_slug', ''));

    return $icon !== '' ? $icon : null;
  }

  public function publicIconTone(): ?string
  {
    $tone = trim((string) $this->setting('icon_tone', ''));

    return $tone !== '' ? $tone : null;
  }

  public function publicBadgeLabel(): ?string
  {
    return $this->stringValueOrNull($this->eyebrow ?? null)
      ?? $this->translatedTextFieldValue('eyebrow');
  }

  public function publicBadgeTone(): ?string
  {
    $tone = trim((string) $this->setting('badge_tone', ''));

    return $tone !== '' ? $tone : null;
  }

  public function buttonLinkVariantClass(): string
  {
    return match ($this->variant) {
      'secondary' => 'wb-btn wb-btn-secondary',
      default => 'wb-btn wb-btn-primary',
    };
  }

  public function buttonLinkUrl(): ?string
  {
    return self::safePublicUrl($this->setting('url', ''));
  }

  public function buttonLinkTarget(): string
  {
    return $this->setting('target') === '_blank' ? '_blank' : '_self';
  }

  public function plainTextAlignmentClass(): ?string
  {
    return match ($this->appearanceSetting('alignment')) {
      'left' => 'wb-text-left',
      'center' => 'wb-text-center',
      'right' => 'wb-text-right',
      default => null,
    };
  }

  public function sectionSpacingClass(): ?string
  {
    return match ($this->appearanceSetting('spacing')) {
      'sm' => 'wb-section-sm',
      'lg' => 'wb-section-lg',
      default => null,
    };
  }

  public function containerWidthClass(): ?string
  {
    return match ($this->appearanceSetting('width')) {
      'sm' => 'wb-container-sm',
      'md' => 'wb-container-md',
      'lg' => 'wb-container-lg',
      'xl' => 'wb-container-xl',
      'full' => 'wb-container-full',
      default => null,
    };
  }

  public function containerFlow(): string
  {
    return $this->setting('flow') === 'stack' ? 'stack' : 'none';
  }

  public function containerFlowClass(): ?string
  {
    return $this->containerFlow() === 'stack' ? 'wb-stack' : null;
  }

  public function stackSpacingClass(): ?string
  {
    return match ($this->appearanceSetting('spacing')) {
      '1', '2', '3', '4', '6', '8' => 'wb-stack-'.$this->appearanceSetting('spacing'),
      default => null,
    };
  }

  public function splitGapClass(): ?string
  {
    return match ($this->appearanceSetting('gap')) {
      '0', '1', '2', '3', '4', '6', '8' => 'wb-gap-'.$this->appearanceSetting('gap'),
      default => null,
    };
  }

  public function splitAlignClass(): ?string
  {
    return match ($this->appearanceSetting('items_alignment')) {
      'start' => 'wb-items-start',
      'end' => 'wb-items-end',
      'stretch' => 'wb-items-stretch',
      default => null,
    };
  }

  public function splitWidthClass(): ?string
  {
    return $this->appearanceSetting('width') === 'full' ? 'wb-w-full' : null;
  }

  public function clusterGapClass(): ?string
  {
    return match ($this->clusterGap()) {
      'none' => 'wb-gap-0',
      'xs' => 'wb-gap-1',
      'sm', '2' => 'wb-cluster-2',
      'md', '4' => 'wb-cluster-4',
      'lg', '6' => 'wb-cluster-6',
      default => null,
    };
  }

  public function clusterGap(): string
  {
    return match ($this->appearanceSetting('gap')) {
      'none', 'xs', 'sm', 'md', 'lg' => $this->appearanceSetting('gap'),
      '2' => 'sm',
      '4' => 'md',
      '6' => 'lg',
      default => 'default',
    };
  }

  public function clusterAlignmentClass(): ?string
  {
    return match ($this->clusterJustify()) {
      'center' => 'wb-cluster-center',
      'end' => 'wb-cluster-end',
      'between' => 'wb-cluster-between',
      default => null,
    };
  }

  public function clusterJustify(): string
  {
    return match ($this->appearanceSetting('alignment')) {
      'center', 'end', 'between' => $this->appearanceSetting('alignment'),
      default => 'start',
    };
  }

  public function clusterAlignClass(): ?string
  {
    return match ($this->clusterAlign()) {
      'start' => 'wb-items-start',
      'end' => 'wb-items-end',
      'stretch' => 'wb-items-stretch',
      default => null,
    };
  }

  public function clusterAlign(): string
  {
    return match ($this->appearanceSetting('items_alignment')) {
      'start', 'center', 'end', 'stretch' => $this->appearanceSetting('items_alignment'),
      default => 'center',
    };
  }

  public function clusterWrapClass(): ?string
  {
    return match ($this->clusterWrap()) {
      'nowrap' => 'wb-flex-nowrap',
      default => null,
    };
  }

  public function clusterWrap(): string
  {
    return $this->appearanceSetting('wrap') === 'nowrap' ? 'nowrap' : 'wrap';
  }

  public function clusterWidthClass(): ?string
  {
    return $this->clusterWidth() === 'full' ? 'wb-w-full' : null;
  }

  public function clusterWidth(): string
  {
    return $this->appearanceSetting('width') === 'full' ? 'full' : 'auto';
  }

  public function gridColumnsClass(): string
  {
    return match ($this->appearanceSetting('columns')) {
      '2' => 'wb-grid-2',
      '4' => 'wb-grid-4',
      default => 'wb-grid-3',
    };
  }

  public function gridGapClass(): ?string
  {
    return match ($this->appearanceSetting('gap')) {
      '3' => 'wb-gap-3',
      '4' => 'wb-gap-4',
      '6' => 'wb-gap-6',
      default => null,
    };
  }

  public function gridAlternatesMediaTextSections(): bool
  {
    return (bool) $this->setting('alternate_media_text_sections', false);
  }

  public function gridAlternateStart(): string
  {
    return $this->appearanceSetting('alternate_start') === 'text_left'
      ? 'text_left'
      : 'media_left';
  }

  public function gridMediaTextSequenceIndex(): int
  {
    if (! $this->gridAlternatesMediaTextSections()) {
      return 0;
    }

    $index = $this->mediaTextGridSequenceSiblings()
      ->filter(fn (self $sibling): bool => $sibling->gridAlternatesMediaTextSections())
      ->values()
      ->search(fn (self $sibling): bool => (int) $sibling->getKey() === (int) $this->getKey());

    return is_int($index) ? $index : 0;
  }

  public function gridMediaTextSequenceMediaLeft(int $sectionIndex): bool
  {
    $startsWithMedia = $this->gridMediaTextSequenceStartsWithMedia();
    $sequenceIndex = $this->gridMediaTextSequenceIndex() + $sectionIndex;

    return $sequenceIndex % 2 === 0
      ? $startsWithMedia
      : ! $startsWithMedia;
  }

  public function gridSectionMediaLeft(int $sectionIndex): bool
  {
    $startsWithMedia = $this->gridAlternateStart() !== 'text_left';

    return $sectionIndex % 2 === 0
      ? $startsWithMedia
      : ! $startsWithMedia;
  }

  public function isMediaTextVisualBlock(): bool
  {
    return in_array($this->typeSlug(), ['slider', 'image', 'gallery', 'video'], true)
      || $this->publicBackgroundMediaUrl() !== null;
  }

  private function mediaTextGridSequenceSiblings(): Collection
  {
    if ($this->parent_id !== null) {
      if ($this->relationLoaded('parent') && $this->parent?->relationLoaded('children')) {
        return $this->parent->children
          ->where('status', $this->status)
          ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
          ->values();
      }

      return self::query()
        ->where('parent_id', $this->parent_id)
        ->where('status', $this->status)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();
    }

    return self::query()
      ->where('page_id', $this->page_id)
      ->whereNull('parent_id')
      ->where('slot', $this->slot)
      ->where('status', $this->status)
      ->orderBy('sort_order')
      ->orderBy('id')
      ->get();
  }

  private function gridMediaTextSequenceStartsWithMedia(): bool
  {
    $firstGrid = $this->mediaTextGridSequenceSiblings()
      ->first(fn (self $sibling): bool => $sibling->gridAlternatesMediaTextSections());

    return ($firstGrid?->gridAlternateStart() ?? $this->gridAlternateStart()) !== 'text_left';
  }

  public function hasMediaTextVisualContent(): bool
  {
    if ($this->isMediaTextVisualBlock()) {
      return true;
    }

    return $this->children->contains(
      fn (Block $child): bool => $child->hasMediaTextVisualContent()
    );
  }

  public function hasMediaTextLayoutContent(): bool
  {
    return $this->hasMediaTextVisualContent()
      || $this->children->isNotEmpty()
      || in_array($this->typeSlug(), ['section', 'container', 'cluster', 'card'], true);
  }

  public function sliderHeightClass(): string
  {
    return match ($this->appearanceSetting('height')) {
      'auto' => 'wb-slider-height-auto',
      'viewport' => 'wb-slider-height-viewport',
      'large' => 'wb-slider-height-lg',
      'medium' => 'wb-slider-height-md',
      'small' => 'wb-slider-height-sm',
      default => 'wb-slider-height-fill',
    };
  }

  public function sliderAspectRatioClass(): ?string
  {
    return match ($this->appearanceSetting('aspect_ratio')) {
      '16/9' => 'wb-slider-ratio-video',
      '4/3' => 'wb-slider-ratio-standard',
      '1/1' => 'wb-slider-ratio-square',
      default => null,
    };
  }

  public function sliderBooleanSetting(string $key, bool $default): bool
  {
    $value = $this->setting($key);

    if (is_bool($value)) {
      return $value;
    }

    if (is_numeric($value)) {
      return (bool) $value;
    }

    if (is_string($value)) {
      return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true)
        ? true
        : (in_array(strtolower($value), ['0', 'false', 'no', 'off'], true) ? false : $default);
    }

    return $default;
  }

  public function sliderIntervalMs(): int
  {
    $interval = (int) $this->setting('interval_ms', 6000);

    return min(max($interval, 1000), 30000);
  }

  public function sliderOverlayClass(): ?string
  {
    return self::sliderOverlayClassFor($this->appearanceSetting('overlay'));
  }

  public function sliderContentPositionClass(): string
  {
    return match ($this->appearanceSetting('content_position')) {
      'top-left' => 'wb-slider-content-top-start',
      'top-center' => 'wb-slider-content-top-center',
      'top-right' => 'wb-slider-content-top-end',
      'bottom-left' => 'wb-slider-content-bottom-start',
      'bottom-center' => 'wb-slider-content-bottom-center',
      'bottom-right' => 'wb-slider-content-bottom-end',
      default => 'wb-slider-content-center',
    };
  }

  public function sliderContentWidthClass(): string
  {
    return match ($this->appearanceSetting('content_width')) {
      'narrow' => 'wb-slider-content-sm',
      'wide' => 'wb-slider-content-lg',
      'full' => 'wb-slider-content-full',
      default => 'wb-slider-content-md',
    };
  }

  public function sliderTextColorClass(): ?string
  {
    return match ($this->appearanceSetting('text_color')) {
      'light' => 'wb-slider-text-light',
      'dark' => 'wb-slider-text-dark',
      default => null,
    };
  }

  public function sliderBackgroundFitClass(): ?string
  {
    return $this->appearanceSetting('background_fit') === 'contain'
      ? 'wb-slide-media-contain'
      : null;
  }

  public function sliderMinHeightStyle(): ?string
  {
    $height = trim((string) $this->setting('min_height', ''));

    if ($height === '' || preg_match('/^\d{2,4}(px|vh|svh|dvh)$/', $height) !== 1) {
      return null;
    }

    return '--wb-slider-min-height: '.$height.';';
  }

  public function publicBackgroundMediaPositionStyle(): string
  {
    return 'object-position: '.$this->backgroundPosition().';';
  }

  public function cardUrl(): ?string
  {
    return self::safePublicUrl($this->setting('url', ''));
  }

  public function cardTarget(): string
  {
    return $this->setting('target') === '_blank' ? '_blank' : '_self';
  }

  public function cardVariant(): string
  {
    return match ($this->setting('variant')) {
      'promo' => 'promo',
      default => 'default',
    };
  }

  public function cardImagePosition(): string
  {
    return match ($this->setting('image_position')) {
      'middle' => 'middle',
      'bottom' => 'bottom',
      default => 'top',
    };
  }

  public function cardImageAlign(): string
  {
    return match ($this->setting('image_align')) {
      'start' => 'start',
      'end' => 'end',
      'stretch' => 'stretch',
      default => 'center',
    };
  }

  public function cardImageAspect(): string
  {
    return match ($this->setting('image_aspect')) {
      'square' => 'square',
      'wide' => 'wide',
      'portrait' => 'portrait',
      default => 'auto',
    };
  }

  public function isPromoCard(): bool
  {
    return $this->cardVariant() === 'promo';
  }

  public function alertVariant(): string
  {
    return match ($this->setting('variant')) {
      'success' => 'success',
      'warning' => 'warning',
      'danger' => 'danger',
      default => 'info',
    };
  }

  public function alertVariantClass(): string
  {
    return 'wb-alert-'.$this->alertVariant();
  }

  public function slotPreviewLabel(): string
  {
    $label = $this->typeName();

    if (in_array($this->typeSlug(), ['navigation-auto', 'menu', 'navbar-navigation'], true)) {
      return $label.' ('.str($this->navigationLocation())->headline().')';
    }

    $childCount = $this->children->count();

    if ($childCount > 0) {
      return $label.' ('.$childCount.' '.Str::plural('item', $childCount).')';
    }

    return $label;
  }

  public function isColumnContainer(): bool
  {
    return $this->typeSlug() === 'columns';
  }

  public function isColumnItem(): bool
  {
    return $this->typeSlug() === 'column_item';
  }

  public function isLinkList(): bool
  {
    return $this->typeSlug() === 'link-list';
  }

  public function isNavbar(): bool
  {
    return $this->typeSlug() === 'sticky-navbar';
  }

  public function isSidebarBrand(): bool
  {
    return $this->typeSlug() === 'sidebar-brand';
  }

  public function isSidebarNavigation(): bool
  {
    return $this->typeSlug() === 'sidebar-navigation';
  }

  public function isSidebarNavItem(): bool
  {
    return $this->typeSlug() === 'sidebar-nav-item';
  }

  public function isSidebarNavGroup(): bool
  {
    return $this->typeSlug() === 'sidebar-nav-group';
  }

  public function isSidebarFooter(): bool
  {
    return $this->typeSlug() === 'sidebar-footer';
  }

  public function isLinkListItem(): bool
  {
    return $this->typeSlug() === 'link-list-item';
  }

  public function isFeatureGrid(): bool
  {
    return $this->typeSlug() === 'feature-grid';
  }

  public function isFeatureItem(): bool
  {
    return $this->typeSlug() === 'feature-item';
  }

  public function isBuilderManagedChild(): bool
  {
    return $this->isColumnItem() || $this->isLinkListItem() || $this->isFeatureItem();
  }

  public function adminFormView(): string
  {
    $pluginView = $this->pluginBlockView(fn (PluginBlockTypeDefinition $definition): ?string => $definition->adminViewName());

    if ($pluginView !== null) {
      return $pluginView;
    }

    $packageView = WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.types.'.$this->typeSlug();

    if (View::exists($packageView)) {
      return $packageView;
    }

    $legacyView = 'admin.blocks.types.'.$this->typeSlug();

    if (View::exists($legacyView)) {
      return $legacyView;
    }

    return $this->fallbackAdminFormView('fallback');
  }

  public function publicRenderView(): string
  {
    $pluginView = $this->pluginBlockView(fn (PluginBlockTypeDefinition $definition): ?string => $definition->publicViewName());

    if ($pluginView !== null) {
      return $pluginView;
    }

    $view = WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.'.$this->typeSlug();

    if (View::exists($view)) {
      return $view;
    }

    $legacyView = 'pages.partials.blocks.'.$this->typeSlug();

    if (View::exists($legacyView)) {
      return $legacyView;
    }

    return App::environment('production')
      ? $this->fallbackPublicRenderView('fallback')
      : $this->fallbackPublicRenderView('missing-renderer');
  }

  public function adminFormSupported(): bool
  {
    return self::supportsAdminForm($this->typeSlug());
  }

  public function publicRenderSupported(): bool
  {
    return self::supportsPublicRender($this->typeSlug());
  }

  public function publicBlockTypeAttribute(): string
  {
    return str_replace('_', '-', (string) $this->typeSlug());
  }

  public function renderPage(): ?Page
  {
    if ($this->relationLoaded('renderPage')) {
      return $this->getRelation('renderPage');
    }

    return $this->page;
  }

  public function renderPageId(): ?int
  {
    $pageId = $this->renderPage()?->id ?? $this->page_id;

    return $pageId ? (int) $pageId : null;
  }

  public function renderSite(): ?Site
  {
    return $this->renderPage()?->site;
  }

  public function renderLocaleCode(): ?string
  {
    $localeCode = $this->renderPage()?->currentTranslation?->locale?->code
      ?? $this->getAttribute('render_locale_code')
      ?? $this->getAttribute('resolved_locale_code');

    return is_string($localeCode) && trim($localeCode) !== '' ? $localeCode : null;
  }

  public function renderSlotSlug(): string
  {
    $slotSlug = trim((string) $this->getAttribute('render_slot_slug'));

    if ($slotSlug !== '') {
      return $slotSlug;
    }

    $ownedSlotSlug = trim((string) $this->slot);

    return $ownedSlotSlug !== '' ? $ownedSlotSlug : 'main';
  }

  public function ownsPublicRoot(): bool
  {
    return in_array($this->typeSlug(), [
      'header',
      'section',
      'container',
      'stack',
      'split',
      'grid',
      'cluster',
      'card',
      'card_header',
      'card_body',
      'card_footer',
      'content_header',
      'hero',
      'slider',
      'slide',
      'columns',
      'cta',
      'image',
      'gallery',
      'download',
      'file',
      'video',
      'audio',
      'rating',
      'comments',
      'sticky-navbar',
      'application',
    ], true);
  }

  public function settingsText(): ?string
  {
    if (is_array($this->decodedSettings())) {
      return json_encode($this->decodedSettings(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    return $this->settings;
  }

  public function supportsBackgroundMedia(): bool
  {
    return in_array($this->typeSlug(), ['hero', 'section', 'card', 'cta', 'content_header', 'slide'], true);
  }

  public function publicBackgroundMediaUrl(): ?string
  {
    if (! $this->supportsBackgroundMedia() || ! $this->media?->isImage()) {
      return null;
    }

    return $this->media->url();
  }

  public function publicBackgroundMediaClass(): ?string
  {
    if ($this->publicBackgroundMediaUrl() === null) {
      return null;
    }

    $overlay = $this->backgroundOverlay();

    return $overlay === 'soft'
      ? 'wb-background-media'
      : 'wb-background-media wb-background-media--overlay-'.$overlay;
  }

  /**
   * A slide's own background overlay, expressed in the slider pattern's scale.
   *
   * Slides do not use the wb-background-media primitive — their image is a real
   * <img class="wb-slide-media">, and the darkening comes from .wb-slide::after
   * painting var(--wb-slider-overlay). The wb-slider-overlay-* classes only set
   * that custom property, so one on the slide overrides the slider's choice for
   * that slide alone.
   *
   * Returns null when the setting is absent, which is how the slider keeps
   * control: BlockRequest stores this key only for a non-default choice, so an
   * untouched slide inherits the slider's overlay instead of overriding it with
   * a default of its own.
   *
   * The admin field's four levels each map to their own class since WebBlocks
   * UI 2.22.0 added `wb-slider-overlay-medium`.
   */
  public function slideBackgroundOverlayClass(): ?string
  {
    $overlay = $this->setting('background_overlay');

    if (! is_string($overlay)) {
      return null;
    }

    return self::sliderOverlayClassFor($overlay);
  }

  /**
   * The single mapping from an overlay setting value to the slider pattern's
   * scrim class, shared by the slider root and by individual slides.
   *
   * These used to be two separate match arms that had drifted apart: the slide
   * arm collapsed `medium` onto strong, the slider arm had no `medium` case at
   * all and dropped it to no overlay. The same word therefore produced three
   * different results depending on where it was set. WebBlocks UI 2.22.0 ships
   * a real `wb-slider-overlay-medium`, so every level now has its own class and
   * there is one place to change when the scale grows again.
   *
   * `dark` is a legacy alias the slider root accepted before the scale was
   * named; it is kept so stored content keeps rendering.
   */
  private static function sliderOverlayClassFor(?string $overlay): ?string
  {
    return match (trim((string) $overlay)) {
      'none' => 'wb-slider-overlay-none',
      'soft' => 'wb-slider-overlay-soft',
      'medium' => 'wb-slider-overlay-medium',
      'dark', 'strong' => 'wb-slider-overlay-strong',
      default => null,
    };
  }

  public function publicBackgroundMediaStyle(): ?string
  {
    $url = $this->publicBackgroundMediaUrl();

    if ($url === null) {
      return null;
    }

    $escapedUrl = str_replace(['\\', '\''], ['\\\\', '\\\''], $url);

    return "--wb-background-media-image: url('".$escapedUrl."'); --wb-background-media-position: ".$this->backgroundPosition().';';
  }

  public function setting(string $key, mixed $default = null): mixed
  {
    return data_get($this->decodedSettings(), $key, $default);
  }

  public function applicationDefinition(): ?ApplicationDefinition
  {
    if ($this->typeSlug() !== 'application') {
      return null;
    }

    return app(ApplicationRegistry::class)->find(trim((string) $this->setting('application_handle', '')));
  }

  public function readyApplicationDefinition(): ?ApplicationDefinition
  {
    $definition = $this->applicationDefinition();

    return $definition?->isReady() ? $definition : null;
  }

  public function applicationSettings(): array
  {
    $settings = $this->setting('application_settings', []);

    return is_array($settings) ? $settings : [];
  }

  private function backgroundPosition(): string
  {
    $position = trim((string) $this->setting('background_position', 'center'));

    return in_array($position, ['center', 'top', 'bottom', 'left', 'right'], true) ? $position : 'center';
  }

  private function backgroundOverlay(): string
  {
    $overlay = trim((string) $this->setting('background_overlay', 'soft'));

    return in_array($overlay, ['none', 'soft', 'medium', 'strong'], true) ? $overlay : 'soft';
  }

  public function navigationMenuKey(): string
  {
    $configured = (string) ($this->setting('menu_key') ?? $this->setting('location') ?? '');

    if (in_array($configured, NavigationItem::menuKeys(), true)) {
      return $configured;
    }

    return ($this->subtitle === 'footer' || $this->slot === 'footer')
      ? NavigationItem::MENU_FOOTER
      : NavigationItem::MENU_PRIMARY;
  }

  public function navigationLocation(): string
  {
    return $this->navigationMenuKey();
  }

  public function stickyNavbarMenuKey(): string
  {
    return $this->navigationMenuKey();
  }

  public function stickyNavbarMode(): string
  {
    $mode = trim((string) $this->setting('sticky_mode', 'sticky'));

    return in_array($mode, ['sticky', 'fixed', 'static'], true) ? $mode : 'sticky';
  }

  public function navbarPosition(): string
  {
    return $this->stickyNavbarMode();
  }

  public function navbarPositionClass(): ?string
  {
    return match ($this->navbarPosition()) {
      'static' => 'wb-navbar--static',
      'fixed' => 'wb-fixed',
      default => 'wb-sticky',
    };
  }

  public function navbarBrandUrl(): ?string
  {
    return self::safePublicUrl($this->setting('url', ''));
  }

  public function navbarBrandTarget(): string
  {
    return $this->setting('target') === '_blank' ? '_blank' : '_self';
  }

  public function navbarBrandAriaLabel(): ?string
  {
    return $this->stringValueOrNull($this->setting('aria_label'));
  }

  public function navbarBrandAccessibleLabel(): ?string
  {
    return $this->navbarBrandAriaLabel()
      ?? $this->stringValueOrNull($this->title)
      ?? $this->translatedTextFieldValue('title')
      ?? $this->siteAccessibleLabelFallback();
  }

  public function navbarNavigationMenuKey(): string
  {
    return $this->navigationMenuKey();
  }

  public function navbarNavigationActiveIndicator(): string
  {
    $value = trim((string) $this->setting('active_indicator', 'underline'));

    return in_array($value, ['underline', 'pill', 'dot', 'background', 'none'], true) ? $value : 'underline';
  }

  public function navbarNavigationActiveMatching(): string
  {
    $value = trim((string) $this->setting('active_matching', 'path'));

    return in_array($value, ['path', 'section', 'current-page', 'exact', 'off'], true) ? $value : 'path';
  }

  public function isNavbarBrand(): bool
  {
    return $this->typeSlug() === 'navbar-brand';
  }

  public function isNavbarNavigation(): bool
  {
    return $this->typeSlug() === 'navbar-navigation';
  }

  public function sidebarLinkUrl(): ?string
  {
    return self::safePublicUrl($this->setting('url', ''))
      ?? self::safePublicUrl($this->url);
  }

  public function sidebarLinkTarget(): string
  {
    return $this->setting('target') === '_blank' ? '_blank' : '_self';
  }

  public function sidebarBrandAriaLabel(): ?string
  {
    return $this->stringValueOrNull($this->setting('aria_label'));
  }

  public function sidebarBrandAccessibleLabel(): ?string
  {
    return $this->sidebarBrandAriaLabel()
      ?? $this->stringValueOrNull($this->title)
      ?? $this->translatedTextFieldValue('title')
      ?? $this->siteAccessibleLabelFallback();
  }

  public function sidebarNavigationMenuKey(): ?string
  {
    $menuKey = trim((string) $this->setting('menu_key', ''));

    return in_array($menuKey, NavigationItem::menuKeys(), true) ? $menuKey : null;
  }

  public function sidebarNavigationShowIcons(): bool
  {
    return (bool) $this->setting('show_icons', true);
  }

  public function sidebarNavigationActiveMatching(): string
  {
    $value = trim((string) $this->setting('active_matching', 'path'));

    return in_array($value, ['path', 'current-page', 'exact'], true) ? $value : 'path';
  }

  public function sidebarNavItemIcon(): ?string
  {
    $icon = trim((string) $this->setting('icon', ''));

    return $icon !== '' ? $icon : null;
  }

  public function sidebarNavItemActiveMode(): string
  {
    $value = trim((string) $this->setting('active_mode', 'path'));

    return in_array($value, ['exact', 'path', 'current-page', 'manual'], true) ? $value : 'path';
  }

  public function sidebarNavItemManualActive(): bool
  {
    return (bool) $this->setting('manual_active', false);
  }

  public function sidebarNavGroupInitiallyOpen(): bool
  {
    return (bool) $this->setting('initially_open', false);
  }

  public function sidebarNavResolvedLabel(): ?string
  {
    return $this->stringValueOrNull($this->title) ?? $this->translatedTextFieldValue('title');
  }

  public function sidebarNavResolvedPath(): ?string
  {
    $href = $this->sidebarLinkUrl();

    if ($href === null || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) === 1 || str_starts_with($href, '#')) {
      return null;
    }

    $path = '/'.ltrim(parse_url($href, PHP_URL_PATH) ?? '', '/');

    return $path === '/' ? '/' : rtrim($path, '/');
  }

  public function sidebarNavItemIsActive(): bool
  {
    $href = $this->sidebarLinkUrl();
    $hrefPath = $this->sidebarNavResolvedPath();
    $currentPath = '/'.ltrim(request()->path(), '/');
    $currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');

    return match ($this->sidebarNavItemActiveMode()) {
      'exact' => $href !== null && url()->current() === url($href),
      'current-page' => $hrefPath !== null && request()->routeIs('pages.show') && $hrefPath === $currentPath,
      'manual' => $this->sidebarNavItemManualActive(),
      default => $hrefPath !== null && $hrefPath === $currentPath,
    };
  }

  private function siteAccessibleLabelFallback(): string
  {
    return $this->stringValueOrNull($this->renderSite()?->display_name)
      ?? $this->stringValueOrNull($this->renderSite()?->seo_title)
      ?? $this->stringValueOrNull($this->renderSite()?->name)
      ?? WebBlocks::name();
  }

  public function sidebarFooterVariantClass(): string
  {
    return match ($this->setting('variant')) {
      'success' => 'wb-callout-success',
      'warning' => 'wb-callout-warning',
      'danger' => 'wb-callout-danger',
      default => 'wb-callout-info',
    };
  }

  public function galleryMediaIds(): array
  {
    return $this->blockMedia
      ->where('role', 'gallery_item')
      ->sortBy('position')
      ->pluck('media_id')
      ->map(fn ($id) => (int) $id)
      ->values()
      ->all();
  }

  public function galleryAssetIds(): array
  {
    return $this->galleryMediaIds();
  }

  /**
   * Settings exposed to core and plugin renderers as a defensive array.
   *
   * Database-backed blocks store JSON, while translated render clones and tests
   * may already carry an array. A plugin view must not need to duplicate those
   * storage details or fail when an API-authored block has null settings.
   *
   * @return array<string, mixed>
   */
  public function decodedSettings(): array
  {
    if (is_array($this->settings)) {
      return $this->settings;
    }

    if (! is_string($this->settings) || trim($this->settings) === '') {
      return [];
    }

    $decoded = json_decode($this->settings, true);

    return is_array($decoded) ? $decoded : [];
  }

  public function galleryMedia(): Collection
  {
    $galleryItems = $this->galleryItems();

    if ($galleryItems->isEmpty()) {
      return collect();
    }

    $mediaIds = $galleryItems->pluck('media_id')
      ->map(fn ($id) => (int) $id)
      ->values()
      ->all();

    return Media::query()
      ->whereIn('id', $mediaIds)
      ->get()
      ->sortBy(fn (Media $media) => array_search($media->id, $mediaIds, true))
      ->values();
  }

  public function galleryItems(): Collection
  {
    return $this->blockMedia
      ->where('role', 'gallery_item')
      ->sortBy('position')
      ->values();
  }

  public function resolvedGalleryItemTranslation(?BlockMedia $item): ?BlockGalleryItemTranslation
  {
    if (! $item) {
      return null;
    }

    $translations = $item->relationLoaded('galleryItemTranslations')
      ? $item->galleryItemTranslations
      : $item->galleryItemTranslations()->get();

    $resolvedLocaleCode = $this->getAttribute('resolved_locale_code');
    $resolvedLocaleId = is_string($resolvedLocaleCode) && $resolvedLocaleCode !== ''
      ? Locale::query()->where('code', $resolvedLocaleCode)->value('id')
      : null;
    $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

    return ($resolvedLocaleId ? $translations->firstWhere('locale_id', $resolvedLocaleId) : null)
      ?? ($defaultLocaleId ? $translations->firstWhere('locale_id', $defaultLocaleId) : null)
      ?? $translations->first();
  }

  public function galleryVariant(): string
  {
    return match ($this->appearanceSetting('variant')) {
      'masonry', 'masonary' => 'masonry',
      'collage' => 'collage',
      default => 'grid',
    };
  }

  public function galleryColumns(): string
  {
    return match ($this->appearanceSetting('columns')) {
      '2', '3', '4', '5' => $this->appearanceSetting('columns'),
      default => '3',
    };
  }

  public function galleryGap(): string
  {
    return match ($this->appearanceSetting('gap')) {
      'none', 'sm', 'md', 'lg' => $this->appearanceSetting('gap'),
      default => 'md',
    };
  }

  public function galleryAspectRatio(): string
  {
    return match ($this->appearanceSetting('aspect_ratio')) {
      'square', '4:3', '16:9', 'portrait' => $this->appearanceSetting('aspect_ratio'),
      default => 'auto',
    };
  }

  public function galleryCaptionsMode(): string
  {
    return match ($this->appearanceSetting('captions_mode')) {
      'hidden', 'overlay', 'on-hover' => $this->appearanceSetting('captions_mode'),
      default => 'below',
    };
  }

  public function galleryOverlayMode(): string
  {
    return match ($this->appearanceSetting('overlay_mode')) {
      'none', 'solid' => $this->appearanceSetting('overlay_mode'),
      default => 'gradient',
    };
  }

  public function galleryLightboxEnabled(): bool
  {
    return (bool) $this->setting('lightbox_enabled', true);
  }

  public function galleryViewerTitle(): ?string
  {
    return $this->stringValueOrNull($this->setting('viewer_title'));
  }

  public function galleryAssets(): Collection
  {
    return $this->galleryMedia();
  }

  public function attachmentMedia(): ?Media
  {
    $structured = $this->blockMedia
      ->where('role', 'attachment')
      ->sortBy('position')
      ->first()?->media;

    return $structured ?: $this->media;
  }

  public function attachmentAsset(): ?Media
  {
    return $this->attachmentMedia();
  }

  public function downloadMedia(): ?Media
  {
    if ($this->typeSlug() === 'download') {
      return $this->media;
    }

    return null;
  }

  public function downloadAsset(): ?Media
  {
    return $this->downloadMedia();
  }

  public static function supportsAdminForm(?string $slug): bool
  {
    return $slug !== null && (
      View::exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.types.'.$slug)
      || View::exists('admin.blocks.types.'.$slug)
      || View::exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.types.fallback')
      || View::exists('admin.blocks.types.fallback')
    );
  }

  /**
   * Resolve the view an enabled plugin declared for this block type.
   *
   * Plugin block views already resolve by convention, because installed plugin
   * view paths are appended to the CMS view namespace and the catalog slug for
   * `appointments::form` is `appointments-form`. That only works if the plugin
   * mirrors the CMS directory layout and guesses the filename, and it leaves
   * the `admin_view`/`public_view` a plugin declares in its manifest dead. A
   * declared view wins here; everything else falls through to the convention.
   *
   * @param  callable(PluginBlockTypeDefinition): ?string  $viewName
   */
  private function pluginBlockView(callable $viewName): ?string
  {
    $slug = $this->typeSlug();

    if ($slug === null) {
      return null;
    }

    $definition = app(PluginBlockCatalog::class)->enabledDefinitionForCatalogSlug($slug);

    if ($definition === null) {
      return null;
    }

    $view = $viewName($definition);

    return is_string($view) && $view !== '' && View::exists($view) ? $view : null;
  }

  private function fallbackAdminFormView(string $slug): string
  {
    $packageView = WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.types.'.$slug;

    if (View::exists($packageView)) {
      return $packageView;
    }

    return 'admin.blocks.types.'.$slug;
  }

  public static function supportsPublicRender(?string $slug): bool
  {
    return $slug !== null && (
      View::exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.'.$slug)
      || View::exists('pages.partials.blocks.'.$slug)
      || View::exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.fallback')
      || View::exists('pages.partials.blocks.fallback')
    );
  }

  private function fallbackPublicRenderView(string $slug): string
  {
    $packageView = WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::pages.partials.blocks.'.$slug;

    if (View::exists($packageView)) {
      return $packageView;
    }

    return 'pages.partials.blocks.'.$slug;
  }
}
