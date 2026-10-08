<?php

namespace WebBlocks\Cms\Policies\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;

class PluginRecoveryPolicy
{
  public function manage(?Authenticatable $user): bool
  {
    return $user !== null && method_exists($user, 'canAccessAdmin') && $user->canAccessAdmin()
      && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
  }
}
