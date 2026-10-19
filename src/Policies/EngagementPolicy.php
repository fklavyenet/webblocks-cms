<?php

namespace WebBlocks\Cms\Policies;

use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

class EngagementPolicy
{
  public function viewAny($user): bool
  {
    $acl = app(CmsAuthorization::class);

    return $acl->ready() ? $acl->allows($user, 'cms.engagement.read') : (bool) $user?->can('manage-site-operations');
  }

  public function update($user, CommentEntry $comment): bool
  {
    $acl = app(CmsAuthorization::class);
    if ($acl->ready()) {
    return $acl->allows($user, 'cms.engagement.moderate', $comment->site_id);
    }

    return $this->viewAny($user) && ($user->isSuperAdmin() || ($comment->site_id && $user->hasSiteAccess($comment->site_id)));
  }
}
