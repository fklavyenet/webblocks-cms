<?php

namespace WebBlocks\Cms\Support\Media;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;

class MediaUsageResolver
{
  public function resolve(Media $media): Collection
  {
    return $this->blockUsages($media)
      ->concat($this->galleryUsages($media))
      ->concat($this->attachmentUsages($media))
      ->concat($this->siteBrandingUsages($media))
      ->concat($this->pageSeoUsages($media))
      ->values();
  }

  public function count(Media $media): int
  {
    return $this->resolve($media)->count();
  }

  public function isUsed(Media $media): bool
  {
    return $this->count($media) > 0;
  }

  private function blockUsages(Media $media): Collection
  {
    return Block::query()
      ->with(['page', 'blockType'])
      ->where('media_id', $media->id)
      ->get()
      ->map(function (Block $block) {
        return [
          'type' => 'Block',
          'context' => $block->typeSlug() === 'download' ? 'Download block' : $block->typeName(),
          'label' => $block->title ?: $block->typeName(),
          'admin_url' => route('admin.blocks.edit', $block),
          'page_title' => $block->page?->title,
        ];
      });
  }

  private function galleryUsages(Media $media): Collection
  {
    return BlockMedia::query()
      ->with(['block.page', 'block.blockType'])
      ->where('media_id', $media->id)
      ->where('role', 'gallery_item')
      ->get()
      ->map(function (BlockMedia $blockMedia) {
        $block = $blockMedia->block;

        return [
          'type' => 'Block',
          'context' => 'Gallery block',
          'label' => $block?->title ?: $block?->typeName(),
          'admin_url' => $block ? route('admin.blocks.edit', $block) : null,
          'page_title' => $block?->page?->title,
        ];
      });
  }

  private function attachmentUsages(Media $media): Collection
  {
    return BlockMedia::query()
      ->with(['block.page', 'block.blockType'])
      ->where('media_id', $media->id)
      ->where('role', 'attachment')
      ->get()
      ->map(function (BlockMedia $blockMedia) {
        $block = $blockMedia->block;

        return [
          'type' => 'Block',
          'context' => 'Button attachment',
          'label' => $block?->title ?: $block?->typeName(),
          'admin_url' => $block ? route('admin.blocks.edit', $block) : null,
          'page_title' => $block?->page?->title,
        ];
      });
  }

  private function siteBrandingUsages(Media $media): Collection
  {
    return Site::query()
      ->where(function ($query) use ($media): void {
        $query->where('favicon_media_id', $media->id)
          ->orWhere('social_image_media_id', $media->id);
      })
      ->get()
      ->map(function (Site $site) use ($media) {
        $context = (int) $site->favicon_media_id === (int) $media->id
          ? 'Site favicon'
          : 'Site social image';

        return [
          'type' => 'Site',
          'context' => $context,
          'label' => $site->name,
          'admin_url' => route('admin.sites.edit', $site),
          'page_title' => null,
        ];
      });
  }

  private function pageSeoUsages(Media $media): Collection
  {
    return PageTranslation::query()
      ->with(['page', 'locale'])
      ->where('og_image_media_id', $media->id)
      ->get()
      ->map(function (PageTranslation $translation) {
        $page = $translation->page;

        return [
          'type' => 'Page translation',
          'context' => 'SEO social image',
          'label' => $translation->name ?: $page?->title,
          'admin_url' => $page ? route('admin.pages.translations.edit', [$page, $translation]) : null,
          'page_title' => $page?->title,
        ];
      });
  }
}
