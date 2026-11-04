<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

class SystemUpdateRecoveryPolicy
{
  public function reconcile(?Authenticatable $user): bool
  {
    return $user !== null && (bool) ($user->is_active ?? true) && $user->can('access-system');
  }
}
