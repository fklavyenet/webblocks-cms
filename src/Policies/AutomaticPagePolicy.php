<?php

namespace WebBlocks\Cms\Policies;

use App\Models\User;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

final class AutomaticPagePolicy
{
  public function viewAny(User $user): bool
  {
    return $user->can('access-admin');
  }

  public function view(User $user, AutomaticPageRule $rule): bool
  {
    return $this->create($user, $rule->site);
  }

  public function create(User $user, Site $site): bool
  {
    return $this->viewAny($user) && app(AdminAuthorization::class)->canViewSiteSettings($user, $site);
  }

  public function activate(User $user, AutomaticPageRule $rule): bool
  {
    return $this->viewAny($user) && app(AdminAuthorization::class)->canMutateSiteSettings($user, $rule->site);
  }

  public function update(User $user, AutomaticPageRule $rule): bool
  {
    return $this->view($user, $rule);
  }
}
