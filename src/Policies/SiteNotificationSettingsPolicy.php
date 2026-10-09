<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Http\Request;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class SiteNotificationSettingsPolicy
{
  public function access(Request $request, Site $site): bool
  {
    $token = $request->attributes->get('cms_api_token');
    if ($token instanceof CmsApiToken) {
      if ($token->allowed_site_ids !== null && ! in_array((int) $site->id, array_map('intval', $token->allowed_site_ids), true)) {
        return false;
      }

      return ! $token->isPersonal() || in_array((int) $site->id, $request->attributes->get('cms_api_allowed_site_ids', []), true);
    }
    $authorization = app(AdminAuthorization::class);

    return $request->user() && ($request->isMethod('GET')
      ? $authorization->canViewSiteSettings($request->user(), $site)
      : $authorization->canMutateSiteSettings($request->user(), $site));
  }
}
