<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;

class InternalContentApiPresenter
{
  public function __construct(
    private readonly BlockTypeContractRegistry $contracts,
  ) {}

  public function site(Site $site): array
  {
    return [
      'id' => $site->id,
      'handle' => $site->handle,
      'name' => $site->name,
      'is_primary' => (bool) $site->is_primary,
      'primary_domain' => $site->canonicalDomain(),
      'locales' => $site->relationLoaded('locales')
        ? $site->locales->map(fn (Locale $locale) => $this->locale($locale))->values()->all()
        : [],
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
    ];
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
      'translations' => $translations->map(fn ($translation) => [
        'id' => $translation->id,
        'locale_id' => $translation->locale_id,
        'locale' => $translation->locale?->code,
        'name' => $translation->name,
        'slug' => $translation->slug,
        'path' => $translation->path,
      ])->values()->all(),
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

  public function pageSlot(PageSlot $slot): array
  {
    return [
      'id' => $slot->id,
      'slot_type_id' => $slot->slot_type_id,
      'slot' => $slot->slotType?->slug,
      'name' => $slot->slotType?->name,
      'source_type' => $slot->runtimeSourceType(),
      'sort_order' => (int) $slot->sort_order,
      'uses_page_owned_blocks' => $slot->usesPageOwnedBlocks(),
    ];
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
