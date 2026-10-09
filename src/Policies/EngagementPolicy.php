<?php

namespace WebBlocks\Cms\Policies;

use WebBlocks\Cms\Models\CommentEntry;

class EngagementPolicy
{
  public function viewAny($user): bool
  {
    return (bool) $user?->can('manage-site-operations');
  }

  public function update($user, CommentEntry $comment): bool
  {
    return $this->viewAny($user) && ($user->isSuperAdmin() || ($comment->site_id && $user->hasSiteAccess($comment->site_id)));
  }
}
