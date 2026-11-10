<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

class PanelNotificationPolicy
{
  public function view($user): bool
  {
    $acl = app(CmsAuthorization::class);

    return $acl->ready() ? $acl->allows($user, 'cms.messages.read') : (bool) $user?->can('manage-site-operations');
  }

  /** @return list<int> */
  public function pluginSiteIds(Authenticatable $user): array
  {
    if (! method_exists($user, 'canAccessAdmin') || ! $user->canAccessAdmin()) {
      return [];
    }

    return app(CmsAuthorization::class)->siteIds($user)->map(fn ($id): int => (int) $id)->values()->all();
  }
}
