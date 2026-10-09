<?php

namespace WebBlocks\Cms\Actions\Engagement;

use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Policies\EngagementPolicy;

class DeleteComment
{
  public function execute($user, CommentEntry $comment): void
  {
    abort_unless(app(EngagementPolicy::class)->update($user, $comment), 403);
    $comment->delete();
  }
}
