<?php

namespace WebBlocks\Cms\Policies;

use WebBlocks\Cms\Support\Access\CmsAuthorization;

class PanelNotificationPolicy
{
  public function view($user): bool
  {
    $acl = app(CmsAuthorization::class);

    return $acl->ready() ? $acl->allows($user, 'cms.messages.read') : (bool) $user?->can('manage-site-operations');
  }
}
