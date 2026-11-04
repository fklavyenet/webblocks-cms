<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Http\Request;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class SystemPagePolicy
{
  public function allows(Request $request, string $permission, int $siteId): bool
  {
    return $request->user() && app(CmsAuthorization::class)->allows($request->user(), 'cms.pages.'.$permission, $siteId);
  }
}
