<?php

namespace WebBlocks\Cms\Policies;

use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class CmsRolePolicy
{
  public function manage($user): bool
  {
    return (bool) ($user->is_active ?? false) && app(CmsAuthorization::class)->isSuperAdmin($user);
  }

  public function update($user, CmsRole $role): bool
  {
    return $this->manage($user) && ! $role->is_protected;
  }
}
