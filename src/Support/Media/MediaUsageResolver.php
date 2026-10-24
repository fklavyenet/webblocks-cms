<?php

namespace WebBlocks\Cms\Support\Media;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Blocks\MobileBlockMedia;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class MediaUsageResolver
{
  public function resolve(Media $media, $actor = null): Collection
  {
    return $this->resolveMany(collect([$media]), $actor)->get($media->id, collect());
  }

  /** @return Collection<int, Collection<int, array<string, mixed>>> */
  public function resolveMany(Collection $media, $actor = null): Collection
  {
    $ids = $media->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
    if ($ids->isEmpty()) {
      return collect();
    }
    $mediaIds = $ids->all();
    $groups = $this->blockUsages($mediaIds)
      ->concat($this->galleryUsages($mediaIds))
      ->concat($this->attachmentUsages($mediaIds))
      ->concat($this->mobileImageUsages($mediaIds))
      ->concat($this->siteBrandingUsages($mediaIds))
      ->concat($this->pageSeoUsages($mediaIds))
      ->groupBy('_media_id');

    return $ids->mapWithKeys(fn (int $id) => [$id => $this->visibleUsages($groups->get($id, collect()), $actor)]);
  }

  private function visibleUsages(Collection $usages, $actor): Collection
  {
    return $usages->filter(function (array $usage) use ($actor): bool {
      $acl = app(CmsAuthorization::class);

      return ! $actor || ! $acl->ready() || $acl->isSuperAdmin($actor) || $acl->usesLegacyRoles($actor)
        || (isset($usage['_site_id']) && $acl->allows($actor, 'cms.media.read', $usage['_site_id']));
    })->map(function (array $usage): array {
      unset($usage['_site_id'], $usage['_media_id']);

      return $usage;
    })->values();
  }

  public function count(Media $media): int
  {
    return $this->resolve($media)->count();
  }

  public function isUsed(Media $media): bool
  {
    return $this->count($media) > 0;
  }

  private function blockUsages(array $mediaIds): Collection
  {
    return Block::query()
      ->with(['page.translations', 'blockType'])
      ->whereIn('media_id', $mediaIds)
      ->orderBy('id')
      ->get()
      ->map(function (Block $block) {
        return [
          'type' => 'Block',
          '_media_id' => $block->media_id,
          '_site_id' => $block?->page?->site_id,
          'context' => $block->typeSlug() === 'download' ? 'Download block' : $block->typeName(),
          'label' => $block->title ?: $block->typeName(),
          'admin_url' => route('admin.blocks.edit', $block),
          'page_title' => $block->page?->title,
        ];
      });
  }

  private function galleryUsages(array $mediaIds): Collection
  {
    return BlockMedia::query()
      ->with(['block.page.translations', 'block.blockType'])
      ->whereIn('media_id', $mediaIds)
      ->where('role', 'gallery_item')
      ->orderBy('id')
      ->get()
      ->map(function (BlockMedia $blockMedia) {
        $block = $blockMedia->block;

        return [
          'type' => 'Block',
          '_media_id' => $blockMedia->media_id,
          '_site_id' => $block?->page?->site_id,
          'context' => 'Gallery block',
          'label' => $block?->title ?: $block?->typeName(),
          'admin_url' => $block ? route('admin.blocks.edit', $block) : null,
          'page_title' => $block?->page?->title,
        ];
      });
  }

  private function attachmentUsages(array $mediaIds): Collection
  {
    return BlockMedia::query()
      ->with(['block.page.translations', 'block.blockType'])
      ->whereIn('media_id', $mediaIds)
      ->where('role', 'attachment')
      ->orderBy('id')
      ->get()
      ->map(function (BlockMedia $blockMedia) {
        $block = $blockMedia->block;

        return [
          'type' => 'Block',
          '_media_id' => $blockMedia->media_id,
          '_site_id' => $block?->page?->site_id,
          'context' => 'Button attachment',
          'label' => $block?->title ?: $block?->typeName(),
          'admin_url' => $block ? route('admin.blocks.edit', $block) : null,
          'page_title' => $block?->page?->title,
        ];
      });
  }

  private function mobileImageUsages(array $mediaIds): Collection
  {
    $locale = app(AdminLocaleResolver::class)->locale();
    $context = app(CmsTranslator::class)->get('admin.blocks.mobile_media.title', $locale);

    return BlockMedia::query()
      ->with(['block.page.translations', 'block.blockType'])
      ->whereIn('media_id', $mediaIds)
      ->where('role', MobileBlockMedia::ROLE)
      ->orderBy('id')
      ->get()
      ->map(function (BlockMedia $blockMedia) use ($context): array {
        $block = $blockMedia->block;

        return [
          'type' => 'Block',
          '_media_id' => $blockMedia->media_id,
          '_site_id' => $block?->page?->site_id,
          'context' => $context,
          'label' => $block?->title ?: $block?->typeName(),
          'admin_url' => $block ? route('admin.blocks.edit', $block) : null,
          'page_title' => $block?->page?->title,
        ];
      });
  }

  private function siteBrandingUsages(array $mediaIds): Collection
  {
    return Site::query()
      ->where(function ($query) use ($mediaIds): void {
        $query->whereIn('favicon_media_id', $mediaIds)
          ->orWhereIn('social_image_media_id', $mediaIds);
      })
      ->orderBy('id')
      ->get()
      ->flatMap(function (Site $site) use ($mediaIds): array {
        $references = [['id' => (int) $site->favicon_media_id, 'context' => 'Site favicon']];
        if ((int) $site->social_image_media_id !== (int) $site->favicon_media_id) {
          $references[] = ['id' => (int) $site->social_image_media_id, 'context' => 'Site social image'];
        }

        return collect($references)->filter(fn (array $reference) => in_array($reference['id'], $mediaIds, true))
          ->map(fn (array $reference) => [
            'type' => 'Site',
            '_media_id' => $reference['id'],
            '_site_id' => $site->id,
            'context' => $reference['context'],
            'label' => $site->name,
            'admin_url' => route('admin.sites.edit', $site),
            'page_title' => null,
          ])->all();
      });
  }

  private function pageSeoUsages(array $mediaIds): Collection
  {
    return PageTranslation::query()
      ->with(['page.translations', 'locale'])
      ->whereIn('og_image_media_id', $mediaIds)
      ->orderBy('id')
      ->get()
      ->map(function (PageTranslation $translation) {
        $page = $translation->page;

        return [
          'type' => 'Page translation',
          '_media_id' => $translation->og_image_media_id,
          '_site_id' => $page?->site_id,
          'context' => 'SEO social image',
          'label' => $translation->name ?: $page?->title,
          'admin_url' => $page ? route('admin.pages.translations.edit', [$page, $translation]) : null,
          'page_title' => $page?->title,
        ];
      });
  }
}
