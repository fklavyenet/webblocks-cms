<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Models\CmsApiToken;

class SystemHealthPolicy
{
  public function view(?Authenticatable $user): bool
  {
    return $user !== null && (bool) ($user->is_active ?? true) && $user->can('access-system');
  }

  public function readApi(?CmsApiToken $token): bool
  {
    return $token !== null && $token->token_type === 'system' && $token->allowed_site_ids === null
      && ! $token->isRevoked() && ! $token->isExpired() && $this->view($token->creator);
  }
}
