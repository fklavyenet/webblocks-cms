<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;

final class LogFormatPolicy
{
  public function update(?Authenticatable $user): bool
  {
    return $user !== null && (bool) ($user->is_active ?? true)
      && app(PluginAccessResolver::class)->isSuperAdmin($user);
  }
}
