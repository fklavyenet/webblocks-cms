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
        $this->applyStatus($comment, $status, $user->id);
      }
    });
  }

  /** Apply a status after the calling channel authorizes the operation. */
  public function applyStatus(CommentEntry $comment, string $status, ?int $actorId, bool $recordRepeat = false, bool $recordOtherStatuses = false): void
  {
    $approved = $status === 'approved';
    if ($comment->status === $status && ($approved || ! $recordRepeat)) {
      return;
    }

    $comment->update([
      'status' => $status,
      'approved_at' => $approved ? now() : null,
      'approved_by_user_id' => $approved || $recordOtherStatuses ? $actorId : null,
    ]);
  }
}
