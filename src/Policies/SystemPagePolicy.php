<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Http\Request;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class SystemPagePolicy
{
  public function addDefaults(Request $request, Page $page): bool
  {
    return $page->isSystemPage() && $page->status === Page::STATUS_DRAFT
      && $this->allows($request, 'update-draft', (int) $page->site_id);
  }

  public function allows(Request $request, string $permission, int $siteId): bool
  {
    return $request->user() && app(CmsAuthorization::class)->allows($request->user(), 'cms.pages.'.$permission, $siteId);
  }
}
