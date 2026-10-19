<?php

namespace WebBlocks\Cms\Policies;

use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class CmsNavigationPolicy
{
  public function visible($user, string $route): bool
  {
    $acl = app(CmsAuthorization::class);
    if (! $acl->ready() || $acl->isSuperAdmin($user)) {
    return true;
    }
    if ($route === 'admin.site-assets.index') {
    return $acl->allows($user, 'cms.legacy.site-assets.read');
    }
    $group = app(CmsResourcePolicy::class)->group(preg_replace('/^admin\./', '', $route));

    return $group === null || $acl->allows($user, 'cms.'.$group.'.read');
  }
}
