<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;

final class PublicThemePolicy
{
  public function managePackages(?Authenticatable $user): bool
  {
    return $user !== null && (bool) ($user->is_active ?? true)
      && app(PluginAccessResolver::class)->isSuperAdmin($user);
  }

  public function managePackagesWithToken(?CmsApiToken $token): bool
  {
    return $token !== null && ! $token->isPersonal() && $token->allowed_site_ids === null
      && ! $token->isExpired() && ! $token->isRevoked() && $this->managePackages($token->creator);
  }

  public function manageSite(Request $request, Site $site, bool $preview = false): bool
  {
    $token = $request->attributes->get('cms_api_token');
    if ($token instanceof CmsApiToken && ! $token->isPersonal()) {
      return $token->allowed_site_ids === null || in_array((int) $site->id, $token->allowed_site_ids, true);
    }

    $user = $request->user();
    $access = app(CmsAuthorization::class);

    return $user !== null && $access->allows($user, 'cms.site-settings.update', $site->id)
      && (! $preview || $access->allows($user, 'cms.pages.read', $site->id));
  }
}
