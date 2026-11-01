<?php

namespace WebBlocks\Cms\Policies;

use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class PageSeoPolicy
{
  public function check($user, Page $page, ?CmsApiToken $token = null): bool
  {
    if ($page->isSharedSlotSourcePage() || $page->isRevisionRestoreCandidate()) {
      return false;
    }
    if ($token && $token->allowed_site_ids !== null && ! in_array((int) $page->site_id, array_map('intval', $token->allowed_site_ids), true)) {
      return false;
    }
    if ($token && ! $token->isPersonal()) {
      return true; // The route separately requires a valid content.read token.
    }

    return $user && $user->is_active && app(CmsAuthorization::class)->allows($user, 'cms.pages.read', $page->site_id);
  }
}
