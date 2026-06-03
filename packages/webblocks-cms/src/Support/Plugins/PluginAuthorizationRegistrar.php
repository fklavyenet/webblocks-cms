<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\Gate;

class PluginAuthorizationRegistrar
{
  private bool $registeredBeforeHook = false;

  public function __construct(
    private readonly PluginPermissionRegistry $permissions,
    private readonly PluginAccessResolver $access,
  ) {}

  public function register(): void
  {
    if (! $this->registeredBeforeHook) {
      Gate::before(function ($user, string $ability): ?bool {
        if (! $this->isActivePluginPermission($ability)) {
          return null;
        }

        return $this->access->canAccessPluginPermission($user, $ability) ? true : null;
      });

      $this->registeredBeforeHook = true;
    }

    foreach ($this->activePermissionNames() as $permission) {
      if (Gate::has($permission)) {
        continue;
      }

      Gate::define($permission, fn ($user): bool => $this->access->canAccessPluginPermission($user, $permission));
    }
  }

  /**
   * @return array<int, string>
   */
  public function activePermissionNames(): array
  {
    $permissions = [];

    foreach (app(PluginRegistry::class)->permissions(enabledOnly: true) as $pluginPermissions) {
      foreach ($pluginPermissions as $permission) {
        $permissions[] = $permission->name();
      }
    }

    return array_values(array_unique($permissions));
  }

  private function isActivePluginPermission(string $ability): bool
  {
    return in_array($ability, $this->activePermissionNames(), true);
  }
}
