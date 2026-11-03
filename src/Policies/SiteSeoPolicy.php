<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Http\Request;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class SiteSeoPolicy
{
  public function access(Request $request, Site $site): bool
  {
    $token = $request->attributes->get('cms_api_token');
    if ($token instanceof CmsApiToken) {
      if ($token->allowed_site_ids !== null && ! in_array((int) $site->id, array_map('intval', $token->allowed_site_ids), true)) {
        return false;
      }
      if (! $token->isPersonal()) {
        return true; // All routes separately require content.read.
      }
      if (! in_array((int) $site->id, $request->attributes->get('cms_api_allowed_site_ids', []), true)) {
        return false;
      }
    }
    $user = $request->user();
    if (! $user || ! $user->is_active) {
      return false;
    }
    foreach (['cms.pages.read', 'cms.navigation.read', 'cms.shared-slots.read'] as $permission) {
      if (! app(CmsAuthorization::class)->allows($user, $permission, $site->id)) {
        return false;
      }
    }

    return true;
  }
}
