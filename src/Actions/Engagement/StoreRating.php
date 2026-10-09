<?php

namespace WebBlocks\Cms\Actions\Engagement;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ContentRating;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Queries\PublicEngagementQuery;
use WebBlocks\Cms\Support\Engagement\EngagementVisitor;

class StoreRating
{
  public function execute(Block $block, Request $request, int $value, string $sourceUrl): void
  {
    DB::transaction(function () use ($block, $request, $value, $sourceUrl): void {
      $page = Page::query()->lockForUpdate()->findOrFail($block->page_id);
      $visitor = app(EngagementVisitor::class);
      $siteId = (int) $page->site_id;
      $pageScoped = app(PublicEngagementQuery::class)->pageScoped($block);
      $hash = $pageScoped ? $visitor->pageHash($request, $siteId, $page->id) : $visitor->visitorHash($request, $siteId, $block->id);
      // The owning page lock serializes votes across multiple page-scoped blocks.
      $existing = app(PublicEngagementQuery::class)->visitorRating($block, $request);
      if ($existing && ! (bool) $block->setting('allow_change', true)) {
        return;
      }
      ($existing ?? new ContentRating)->fill([
        'site_id' => $siteId, 'page_id' => $page->id, 'block_id' => $block->id,
        'visitor_hash' => $hash, 'rating_value' => $value, 'rating_max' => 5, 'status' => 'active',
        'source_url' => $sourceUrl, 'ip_hash' => $visitor->ipHash($request->ip()), 'user_agent' => $request->userAgent(),
      ])->save();
    });
  }
}
