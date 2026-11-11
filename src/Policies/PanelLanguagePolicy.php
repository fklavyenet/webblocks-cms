<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;

final class PanelLanguagePolicy
{
  public function manage(?Authenticatable $user): bool
  {
    return $user !== null && (bool) ($user->is_active ?? true)
      && app(PluginAccessResolver::class)->isSuperAdmin($user);
  }

  public function allowsToken(?CmsApiToken $token): bool
  {
    return $token !== null && ($token->token_type ?? 'system') === 'system'
      && $token->allowed_site_ids === null && ! $token->isExpired() && ! $token->isRevoked()
      && $this->manage($token->creator);
  }
}
