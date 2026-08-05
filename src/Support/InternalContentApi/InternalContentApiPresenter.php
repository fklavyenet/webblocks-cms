<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;

class InternalContentApiPresenter
{
  public function __construct(
    private readonly BlockTypeContractRegistry $contracts,
    private readonly BlockTypeApiAuthoringPolicy $apiAuthoringPolicy,
  ) {}

  public function site(Site $site): array
  {
    return [
      'id' => $site->id,
      'handle' => $site->handle,
      'name' => $site->name,
      'is_primary' => (bool) $site->is_primary,
      'primary_domain' => $site->canonicalDomain(),
      'display_name' => $site->display_name,
      'tagline' => $site->tagline,
      'favicon_media_id' => $site->favicon_media_id,
      'favicon_media' => $site->relationLoaded('faviconMedia') && $site->faviconMedia ? $this->media($site->faviconMedia) : null,
      'social_image_media_id' => $site->social_image_media_id,
      'social_image_media' => $site->relationLoaded('socialImageMedia') && $site->socialImageMedia ? $this->media($site->socialImageMedia) : null,
      'public_theme_preset' => $site->resolvedPublicThemePreset(),
      'custom_head_html' => $site->custom_head_html,
      'seo_title' => $site->seo_title,
      'seo_description' => $site->seo_description,
      'seo_keywords' => $site->seo_keywords,
      'contact_recipient_email' => $site->contact_recipient_email,
      'timezone' => $site->timezone,
      'brand_accent' => $site->brand_accent ?? null,
      'brand_accent_secondary' => $site->brand_accent_secondary ?? null,
      'brand_surface' => $site->brand_surface ?? null,
      'brand_text' => $site->brand_text ?? null,
      'brand_font_heading' => $site->brand_font_heading ?? null,
      'brand_font_body' => $site->brand_font_body ?? null,
      'brand_palette' => $this->brandPalette($site),
      'locales' => $site->relationLoaded('locales')
        ? $site->locales->map(fn (Locale $locale) => $this->locale($locale))->values()->all()
        : [],
    ];
  }

  /**
   * Resolved brand palette tokens so operator tools can preview derived values
   * without reimplementing the derivation.
   *
   * @return array<string, mixed>|null
   */
  private function brandPalette(Site $site): ?array
  {
    $palette = $site->brandPalette();

    if ($palette->isEmpty()) {
      return null;
    }

    return [
      'light' => $palette->lightTokens(),
      'dark' => $palette->darkTokens(),
      'fonts' => $palette->fontTokens(),
      'accent_contrast' => $palette->accentContrast(),
    ];
  }

  public function locale(Locale $locale): array
  {
    return [
      'id' => $locale->id,
      'code' => $locale->code,
      'name' => $locale->name,
      'is_default' => (bool) $locale->is_default,
      'is_enabled' => (bool) $locale->is_enabled,
    ];
  }

  public function pageLayout(PageLayout $layout): array
  {
    return [
      'id' => $layout->id,
      'handle' => $layout->handle,
      'name' => $layout->name,
      'description' => $layout->description,
      'is_active' => (bool) $layout->is_active,
      'is_system' => (bool) $layout->is_system,
      'shell_type' => $layout->shell_type,
      'body_class' => $layout->body_class,
      'slots' => $layout->relationLoaded('layoutSlots')
        ? $layout->layoutSlots->map(fn ($slot) => [
          'id' => $slot->id,
          'slot_type_id' => $slot->slot_type_id,
          'slot_name' => $slot->slot_name,
          'slot_type_slug' => $slot->slotType?->slug,
          'label' => $slot->label,
          'is_required' => (bool) $slot->is_required,
          'is_active' => (bool) $slot->is_active,
          'sort_order' => (int) $slot->sort_order,
        ])->values()->all()
        : [],
    ];
  }

  public function blockType(BlockType $blockType): array
  {
    $contract = $this->contracts->resolve($blockType)->toAuditArray();

    return [
      'id' => $blockType->id,
      'slug' => $blockType->slug,
      'name' => $blockType->name,
      'category' => $blockType->category,
      'status' => $blockType->status,
      'source_type' => $blockType->source_type,
      'is_system' => (bool) $blockType->is_system,
      'is_container' => (bool) $blockType->is_container,
      'sort_order' => (int) $blockType->sort_order,
      'contract' => $contract,
    ] + $this->apiAuthoringPolicy->contractFor($blockType->slug);
  }

  public function page(Page $page, bool $includeBlocks = false): array
  {
    $translations = $page->relationLoaded('translations') ? $page->translations : collect();

    $payload = [
      'id' => $page->id,
      'site_id' => $page->site_id,
      'site' => $page->relationLoaded('site') && $page->site ? $this->site($page->site) : null,
      'status' => $page->status,
      'page_type' => $page->page_type,
      'layout' => $page->publicShellPreset(),
      'title' => $page->name,
      'slug' => $page->slug,
      'source_sync' => $this->sourceSync($page),
      'staged_update' => $this->stagedUpdate($page),
      'translations' => $translations->map(fn ($translation) => $this->pageTranslation($translation))->values()->all(),
      'slots' => $page->relationLoaded('slots')
        ? $page->slots->map(fn (PageSlot $slot) => $this->pageSlot($slot))->values()->all()
        : [],
      'edit_url' => route('admin.pages.edit', $page, absolute: false),
    ];

    if ($includeBlocks) {
      $blocks = $page->relationLoaded('blocks') ? $page->blocks : collect();
      $payload['blocks'] = $this->blockTree($blocks);
    }

    return $payload;
  }

  /**
   * Localized page identity plus the page-level SEO and Open Graph overrides.
   *
   * The SEO fields were missing here for as long as they were unwritable, so a
   * tool could neither set them nor read what a human had set.
   */
  public function pageTranslation(PageTranslation $translation): array
  {
    return [
      'id' => $translation->id,
      'locale_id' => $translation->locale_id,
      'locale' => $translation->locale?->code,
      'name' => $translation->name,
      'slug' => $translation->slug,
      'path' => $translation->path,
      'seo_title' => $translation->seo_title,
      'seo_description' => $translation->seo_description,
      'seo_keywords' => $translation->seo_keywords,
      'og_title' => $translation->og_title,
      'og_description' => $translation->og_description,
      'og_image_media_id' => $translation->og_image_media_id,
    ];
  }

  public function mediaFolder(MediaFolder $folder): array
  {
    return [
      'id' => $folder->id,
      'name' => $folder->name,
      'slug' => $folder->slug,
      'parent_id' => $folder->parent_id,
      'media_count' => $folder->media_count !== null ? (int) $folder->media_count : null,
    ];
  }

  public function pageSlot(PageSlot $slot): array
  {
    return [
      'id' => $slot->id,
      'slot_type_id' => $slot->slot_type_id,
      'slot' => $slot->slotType?->slug,
      'name' => $slot->slotType?->name,
      'source_type' => $slot->runtimeSourceType(),
      'shared_slot_id' => $slot->shared_slot_id,
      'sort_order' => (int) $slot->sort_order,
      'uses_page_owned_blocks' => $slot->usesPageOwnedBlocks(),
    ];
  }

  public function navigationMenu(Site $site, string $handle, Collection $items): array
  {
    return [
      'site_id' => $site->id,
      'site' => $this->site($site),
      'handle' => $handle,
      'label' => NavigationItem::menuOptions()[$handle] ?? str($handle)->headline()->toString(),
      'items' => $this->navigationTree($items),
    ];
  }

  public function navigationItem(NavigationItem $item): array
  {
    return [
      'id' => $item->id,
      'site_id' => $item->site_id,
      'menu_key' => $item->menu_key,
      'parent_id' => $item->parent_id,
      'label' => $item->resolvedLabel(),
      'link_type' => $item->link_type,
      'url' => $item->url,
      'target' => $item->target ?: '_self',
      'sort_order' => (int) $item->position,
      'visibility' => $item->visibility,
    ];
  }

  public function sharedSlot(SharedSlot $sharedSlot, bool $includeBlocks = false): array
  {
    $payload = [
      'id' => $sharedSlot->id,
      'site_id' => $sharedSlot->site_id,
      'site' => $sharedSlot->relationLoaded('site') && $sharedSlot->site ? $this->site($sharedSlot->site) : null,
      'handle' => $sharedSlot->handle,
      'label' => $sharedSlot->name,
      'slot' => $sharedSlot->slot_name,
      'public_shell' => $sharedSlot->public_shell,
      'is_active' => (bool) $sharedSlot->is_active,
    ];

    if ($includeBlocks) {
      $blocks = $sharedSlot->relationLoaded('slotBlocks')
        ? $sharedSlot->slotBlocks->pluck('block')->filter()
        : collect();

      $payload['blocks'] = $this->blockTree($blocks);
    }

    return $payload;
  }

  public function block(Block $block, bool $includeChildren = true): array
  {
    $payload = [
      'id' => $block->id,
      'page_id' => $block->page_id,
      'parent_id' => $block->parent_id,
      'slot_type_id' => $block->slot_type_id,
      'slot' => $block->slotType?->slug ?? $block->slot,
      'block_type_id' => $block->block_type_id,
      'type' => $block->typeSlug(),
      'status' => $block->status,
      'sort_order' => (int) $block->sort_order,
      'settings' => $this->decodeJson($block->settings),
      'variant' => $block->variant,
      'url' => $block->url,
      'media_id' => $block->media_id,
      'media' => $block->relationLoaded('media') && $block->media ? $this->media($block->media) : null,
      'translations' => [
        'text' => $block->relationLoaded('textTranslations') ? $block->textTranslations->values()->all() : [],
        'button' => $block->relationLoaded('buttonTranslations') ? $block->buttonTranslations->values()->all() : [],
        'image' => $block->relationLoaded('imageTranslations') ? $block->imageTranslations->values()->all() : [],
      ],
    ];

    if ($includeChildren) {
      $children = $block->relationLoaded('children') ? $block->children : collect();
      $payload['children'] = $children->map(fn (Block $child) => $this->block($child))->values()->all();
    }

    return $payload;
  }

  public function media(Media $media): array
  {
    return [
      'id' => $media->id,
      'kind' => $media->kind,
      'title' => $media->title,
      'filename' => $media->filename,
      'original_name' => $media->original_name,
      'mime_type' => $media->mime_type,
      'visibility' => $media->visibility,
      'url' => $media->url(),
      'alt_text' => $media->alt_text,
      'caption' => $media->caption,
      'description' => $media->description,
      'width' => $media->width,
      'height' => $media->height,
      'meta_label' => $media->compactMetaLabel(),
      'previewable' => $media->canPreview(),
    ];
  }

  public function blockTree(Collection $blocks): array
  {
    $byParent = $blocks->groupBy(fn (Block $block) => $block->parent_id ?: 0);

    $build = function (int $parentId) use (&$build, $byParent): array {
      return $byParent->get($parentId, collect())
        ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
        ->map(function (Block $block) use ($build): array {
          $payload = $this->block($block, false);
          $payload['children'] = $build((int) $block->id);

          return $payload;
        })
        ->values()
        ->all();
    };

    return $build(0);
  }

  private function navigationTree(Collection $items): array
  {
    $byParent = $items->groupBy(fn (NavigationItem $item) => $item->parent_id ?: 0);

    $build = function (int $parentId) use (&$build, $byParent): array {
      return $byParent->get($parentId, collect())
        ->sortBy([['position', 'asc'], ['id', 'asc']])
        ->map(function (NavigationItem $item) use ($build): array {
          $payload = $this->navigationItem($item);
          $payload['children'] = $build((int) $item->id);

          return $payload;
        })
        ->values()
        ->all();
    };

    return $build(0);
  }

  private function sourceSync(Page $page): ?array
  {
    $settings = is_array($page->settings) ? $page->settings : [];
    $sourceSync = $settings['source_sync'] ?? null;

    if (! is_array($sourceSync)) {
      return null;
    }

    return array_intersect_key($sourceSync, array_flip([
      'type',
      'source_id',
      'source_path',
      'source_sha256',
      'managed_slots',
      'last_synced_at',
    ]));
  }

  private function stagedUpdate(Page $page): ?array
  {
    $settings = is_array($page->settings) ? $page->settings : [];
    $metadata = $settings['staged_update'] ?? null;

    if (! is_array($metadata) || ($metadata['type'] ?? null) !== 'published_page_update') {
      return null;
    }

    return array_intersect_key($metadata, array_flip([
      'type',
      'source_page_id',
      'source_path',
      'source_updated_at',
      'state',
      'managed_slots',
      'created_at',
      'promoted_at',
      'promoted_to_page_id',
    ]));
  }

  private function decodeJson(mixed $value): mixed
  {
    if (is_array($value) || $value === null) {
      return $value;
    }

    if (! is_string($value) || trim($value) === '') {
      return null;
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : null;
  }
}
