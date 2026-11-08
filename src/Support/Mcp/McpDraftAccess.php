<?php

namespace WebBlocks\Cms\Support\Mcp;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Page;

final class McpDraftAccess
{
  public function candidates(array $siteIds): Builder
  {
    return Page::query()->whereIn('site_id', $siteIds)->where('status', Page::STATUS_DRAFT)
      ->whereNotIn('page_type', [Page::TYPE_SHARED_SLOT_SOURCE, Page::TYPE_SYSTEM])
      ->whereRuntimeSettingNull('revision_restore_candidate');
  }

  public function allows(?array $grant, ?Page $draft): bool
  {
    return ! $draft || ! ($grant['drafts_created_only'] ?? false)
      || DB::table('wbcms_mcp_grant_drafts')->where('grant_id', $grant['id'])->where('page_id', $draft->id)->exists();
  }

  public function assertAllowed(?array $grant, ?Page $draft): void
  {
    if (! $this->allows($grant, $draft)) {
      throw new McpToolException('draft_access_denied');
    }
  }

  public function recordCreated(?array $grant, Page $draft): void
  {
    if ($grant['drafts_created_only'] ?? false) {
      DB::table('wbcms_mcp_grant_drafts')->insert(['grant_id' => $grant['id'], 'page_id' => $draft->id]);
    }
  }
}
