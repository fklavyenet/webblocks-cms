<?php

namespace WebBlocks\Cms\Support\Blocks;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;

/** Stable targets independent of heading copy, locale and container order. */
final class HeaderAnchors
{
  public function forBlock(Block $block): ?string
  {
    if (! $block->exists) {
      return $block->explicitHeaderAnchor();
    }

    $anchors = app(RequestMemo::class)->remember('header-anchors', [$block->page_id], function () use ($block): array {
      $page = $block->renderPage();
      $blocks = $page?->id === $block->page_id && $page->relationLoaded('blocks')
        ? $page->blocks
        : Block::query()->where('page_id', $block->page_id)->with('blockType')->get();

      return $this->allocate($blocks);
    });

    return $anchors[$block->id] ?? $block->explicitHeaderAnchor() ?? 'wb-heading-'.$block->id;
  }

  public function allocate(Collection $blocks): array
  {
    $headers = $blocks->filter(fn (Block $block) => $block->typeSlug() === 'header')->sortBy('id');
    $reserved = [];
    $anchors = [];
    foreach ($headers as $header) {
      $anchor = $header->explicitHeaderAnchor();
      if ($anchor !== null && ! isset($reserved[$anchor])) {
        $reserved[$anchor] = true;
        $anchors[$header->id] = $anchor;
      }
    }
    foreach ($headers as $header) {
      if (isset($anchors[$header->id])) {
        continue;
      }
      $base = 'wb-heading-'.$header->id;
      $anchor = $base;
      for ($suffix = 2; isset($reserved[$anchor]); $suffix++) {
        $anchor = $base.'-'.$suffix;
      }
      $reserved[$anchor] = true;
      $anchors[$header->id] = $anchor;
    }

    return $anchors;
  }
}
