<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class TwoFactorPolicy
{
  public function manage(?Authenticatable $user): bool
  {
    return $user !== null && (bool) ($user->is_active ?? true)
      && method_exists($user, 'canAccessAdmin') && $user->canAccessAdmin();
  }

  public function configure(?Authenticatable $user): bool
  {
    return $this->manage($user) && app(CmsAuthorization::class)->isSuperAdmin($user);
  }
}
