<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Models\SystemBackup;

final class SystemBackupPolicy
{
  public function manageRetention(Authenticatable $user, ?SystemBackup $backup = null): bool
  {
    return $user->can('access-system');
  }
}
