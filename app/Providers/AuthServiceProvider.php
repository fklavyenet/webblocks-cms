<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;

class AuthServiceProvider extends ServiceProvider
{
  public function boot(): void
  {
    Gate::define('access-admin', fn (User $user) => $user->canAccessAdmin());
    Gate::define('manage-users', fn (User $user) => app(PluginAccessResolver::class)->isSuperAdmin($user));
    Gate::define('access-system', fn (User $user) => app(PluginAccessResolver::class)->canAccessSystem($user));
  }
}
