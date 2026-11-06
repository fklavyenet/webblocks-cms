<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;

final class McpPageQuery
{
  public function publishedPages(Builder $query, int $siteId): Builder
  {
    return $query->where('site_id', $siteId)->where('status', Page::STATUS_PUBLISHED)
      ->whereNotIn('page_type', [Page::TYPE_SHARED_SLOT_SOURCE, Page::TYPE_SYSTEM]);
  }

  public function search(int $siteId, int $localeId, string $term, int $pageNumber): Paginator
  {
    $pattern = '%'.strtr(trim($term), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

    return PageTranslation::query()
      ->where('site_id', $siteId)->where('locale_id', $localeId)
      ->whereHas('page', fn (Builder $page) => $this->publishedPages($page, $siteId)
        ->whereRuntimeSettingNull('revision_restore_candidate'))
      ->where(fn (Builder $translation) => $translation->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
        ->orWhereRaw("path LIKE ? ESCAPE '!'", [$pattern]))
      ->orderBy('page_id')
      ->simplePaginate(20, ['page_id', 'name', 'path'], 'page', $pageNumber);
  }
}
