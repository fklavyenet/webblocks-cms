<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;

/** Cheap list affordances; the selected site's full report and delete guards remain authoritative. */
class SiteDeletionAvailabilityQuery
{
  public function deletableIds(Collection $sites, int $siteCount): array
  {
    if ($siteCount <= 1) {
      return [];
    }
    $candidates = $sites->reject(fn (Site $site) => $site->is_primary)->pluck('id')->all();
    if ($candidates === []) {
      return [];
    }
    $messages = (new ContactMessage)->getTable();
    $pages = (new Page)->getTable();
    $blocks = (new Block)->getTable();
    $pageReferences = ContactMessage::query()
      ->join($pages.' as pages', 'pages.id', '=', $messages.'.page_id')
      ->whereIn('pages.site_id', $candidates)->distinct()->pluck('pages.site_id');
    $blockReferences = ContactMessage::query()
      ->join($blocks.' as blocks', 'blocks.id', '=', $messages.'.block_id')
      ->join($pages.' as pages', 'pages.id', '=', 'blocks.page_id')
      ->whereIn('pages.site_id', $candidates)->distinct()->pluck('pages.site_id');

    return collect($candidates)->diff($pageReferences->concat($blockReferences))->values()->all();
  }
}
