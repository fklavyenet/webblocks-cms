<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;

/** An in-memory editable tree; validation and preview never persist it. */
final class DynamicContentDesign
{
  public function blocks(Page $page): Collection
  {
    $blocks = $page->blocks()->with(['blockType', 'slotType', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'blockAssets.galleryItemTranslations'])->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');
    foreach ($blocks as $block) {
      $block->setRelation('page', $page);
      $block->setRelation('parent', $blocks->get($block->parent_id));
      $block->setRelation('children', $blocks->where('parent_id', $block->id)->values());
    }

    return $blocks;
  }

  public function fingerprint(Page $page): string
  {
    $blocks = $this->blocks($page);

    return hash('sha256', serialize([$page->updated_at?->toISOString(), $page->settings,
      $blocks->map(fn ($block) => [$block->portableAttributes(), $block->textTranslations->map->getAttributes()->all(), $block->buttonTranslations->map->getAttributes()->all(), $block->imageTranslations->map->getAttributes()->all()])->all(),
    ]));
  }

  public function branch(Block $block): Collection
  {
    $result = collect();
    $walk = function (Block $node, int $depth = 0) use (&$walk, &$result): void {
      if ($depth > 32 || $result->has($node->id)) {
        return;
      }
      $result->put($node->id, $node);
      foreach ($node->children as $child) {
        $walk($child, $depth + 1);
      }
    };
    $walk($block);

    return $result;
  }

  public function label(Block $block): string
  {
    $parts = [];
    $seen = [];
    for ($node = $block; $node && ! isset($seen[$node->id]) && count($parts) < 32; $node = $node->parent) {
      $seen[$node->id] = true;
      array_unshift($parts, ($node->layoutAdminName() ?: $node->typeName()).' #'.$node->id);
    }

    return implode(' → ', $parts);
  }

  public function mappingBlocks(Block $block, ?int $template = null): Collection
  {
    $editor = app(ContentSourceEditor::class);
    $root = $template ? $block->children->firstWhere('id', $template) : $block;

    return $root ? $this->branch($root)->filter(fn ($node) => $editor->targets($node) !== []) : collect();
  }
}
