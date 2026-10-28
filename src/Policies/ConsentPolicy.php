<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Http\Request;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;

final class ConsentPolicy
{
  public function access(Request $request, Site $site, bool $write = false): bool
  {
    $token = $request->attributes->get('cms_api_token');
    if ($token instanceof CmsApiToken) {
      return app(CmsApiTokenCapabilities::class)->has($token, $write ? 'consent.write' : 'consent.read')
        && ($token->allowed_site_ids === null || in_array((int) $site->id, array_map('intval', $token->allowed_site_ids), true))
        && (! $token->isPersonal() || in_array((int) $site->id, $request->attributes->get('cms_api_allowed_site_ids', []), true));
    }

    return app(CmsAuthorization::class)->allows($request->user(), 'cms.consent.'.($write ? 'update' : 'read'), $site->id);
  }

  public function code(Request $request): bool
  {
    $token = $request->attributes->get('cms_api_token');
    if ($token instanceof CmsApiToken) {
      return ! $token->isPersonal() && app(CmsApiTokenCapabilities::class)->has($token, 'consent.code.write');
    }

    return app(CmsAuthorization::class)->isSuperAdmin($request->user());
  }
}
