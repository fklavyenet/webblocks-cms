<?php

namespace WebBlocks\Cms\Policies;

use WebBlocks\Cms\Models\CmsApiToken;

class SystemUpdateApiPolicy
{
  public function allows(?CmsApiToken $token): bool
  {
    // Personal and site-scoped credentials never carry installation authority.
    return $token !== null
      && $token->token_type === 'system'
      && $token->allowed_site_ids === null
      && ! $token->isRevoked()
      && ! $token->isExpired()
      && $token->creator !== null
      && (bool) ($token->creator->is_active ?? true)
      && $token->creator->can('access-system');
  }
}
