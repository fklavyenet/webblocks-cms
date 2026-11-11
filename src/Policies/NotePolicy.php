<?php

namespace WebBlocks\Cms\Policies;

use App\Models\User;
use WebBlocks\Cms\Models\Note;

final class NotePolicy
{
  public function access(?User $user): bool
  {
    return $user?->canAccessAdmin() === true;
  }

  public function administer(User $user): bool
  {
    return $this->access($user) && $user->isSuperAdmin();
  }

  public function view(User $user, Note $note): bool
  {
    return $this->access($user)
      && (! $note->is_private || $this->owns($user, $note) || (int) $note->recipient_id === (int) $user->id);
  }

  public function update(User $user, Note $note): bool
  {
    return $this->access($user) && ($this->owns($user, $note) || $user->isSuperAdmin());
  }

  public function delete(User $user, Note $note): bool
  {
    return $this->access($user) && $this->owns($user, $note);
  }

  private function owns(User $user, Note $note): bool
  {
    return (int) $note->author_id === (int) $user->id;
  }
}
