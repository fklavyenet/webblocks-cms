<?php

namespace WebBlocks\Cms\Policies;

use App\Models\User;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class AutomaticPagePolicy
{
  public function viewAny(User $user): bool
  {
    return app(CmsAuthorization::class)->allows($user, 'cms.automatic-pages.read');
  }

  public function view(User $user, AutomaticPageRule $rule): bool
  {
    return app(CmsAuthorization::class)->allows($user, 'cms.automatic-pages.read', $rule->site_id);
  }

  public function create(User $user, Site $site): bool
  {
    return app(CmsAuthorization::class)->allows($user, 'cms.automatic-pages.configure', $site->id);
  }

  public function activate(User $user, AutomaticPageRule $rule): bool
  {
    return app(CmsAuthorization::class)->allows($user, 'cms.automatic-pages.activate', $rule->site_id);
  }

  public function update(User $user, AutomaticPageRule $rule): bool
  {
    return $this->create($user, $rule->site);
  }
}
