<?php

namespace WebBlocks\Cms\Policies;

use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class ListingViewPolicy
{
  public function listing($user, string $listing): bool
  {
    return in_array($listing, ['pages', 'media'], true) && $user && $user->is_active
      && app(CmsAuthorization::class)->allows($user, 'cms.'.$listing.'.read');
  }

  public function owned($user, object $view, string $listing): bool
  {
    return $this->listing($user, $listing) && (int) $view->user_id === (int) $user->getAuthIdentifier() && $view->listing === $listing;
  }
}
