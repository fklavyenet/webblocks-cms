<?php

namespace WebBlocks\Cms\Actions\Engagement;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Policies\EngagementPolicy;

class ModerateComments
{
  public function execute($user, array $ids, string $status): void
  {
    DB::transaction(function () use ($user, $ids, $status): void {
      $comments = CommentEntry::query()->whereIn('id', $ids)->lockForUpdate()->get();
      abort_unless($comments->count() === count(array_unique($ids)), 403);
      foreach ($comments as $comment) {
        abort_unless(app(EngagementPolicy::class)->update($user, $comment), 403);
      }
      foreach ($comments as $comment) {
        if ($comment->status === $status) {
          continue;
        }
        $comment->update(['status' => $status, 'approved_at' => $status === 'approved' ? now() : null, 'approved_by_user_id' => $status === 'approved' ? $user->id : null]);
      }
    });
  }
}
