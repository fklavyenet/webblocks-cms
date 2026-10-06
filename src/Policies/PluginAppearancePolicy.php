<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;

class PluginAppearancePolicy
{
  public function update(?Authenticatable $user, ?PluginDefinition $plugin): bool
  {
    return $user !== null && $plugin !== null && $user->can('access-system');
  }
}
