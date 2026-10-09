<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Models\ContentRating;
use WebBlocks\Cms\Support\Engagement\EngagementVisitor;

class PublicEngagementQuery
{
  public function pageScoped(Block $block): bool
  {
    return $block->setting('data_scope', 'block') === 'page';
  }

  public function comments(Block $block): Builder
  {
    return $this->scope(CommentEntry::query(), $block)->where('status', 'approved');
  }

  public function ratings(Block $block): Builder
  {
    return $this->scope(ContentRating::query(), $block)->where('status', 'active')->where('rating_max', 5);
  }

  public function visitorRating(Block $block, Request $request): ?ContentRating
  {
    if (! $request->hasSession() || ! $request->session()->has('webblocks.engagement.visitor_id')) {
      return null;
    }
    $siteId = (int) $block->page?->site_id;
    $visitor = app(EngagementVisitor::class);
    $pageScoped = $this->pageScoped($block);
    $hash = $pageScoped ? $visitor->pageHash($request, $siteId, $block->page_id) : $visitor->visitorHash($request, $siteId, $block->id);
    $hashes = [$hash];
    if ($pageScoped) {
      foreach (ContentRating::query()->where('site_id', $siteId)->where('page_id', $block->page_id)->whereNotNull('block_id')->distinct()->pluck('block_id') as $blockId) {
        $hashes[] = $visitor->visitorHash($request, $siteId, (int) $blockId);
      }
    }

    return $this->scope(ContentRating::query(), $block)->whereIn('visitor_hash', $hashes)
      ->orderByRaw('case when visitor_hash = ? then 0 else 1 end', [$hash])->orderByDesc('updated_at')->first();
  }

  private function scope(Builder $query, Block $block): Builder
  {
    // The persisted owning page is authoritative; request input and preview
    // render context cannot select another site's public feedback.
    return $query->where('site_id', $block->page?->site_id)
      ->where('page_id', $block->page_id)
      ->when(! $this->pageScoped($block), fn (Builder $query) => $query->where('block_id', $block->id));
  }
}
