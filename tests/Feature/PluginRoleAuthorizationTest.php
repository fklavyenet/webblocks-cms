<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class PluginRoleAuthorizationTest extends TestCase
{
  public function test_plugin_permissions_remain_super_admin_only_by_default(): void
  {
    $permission = PluginPermission::make('appointments.view');
    $registry = $this->registryWith($permission);
    $resolver = app(PluginAccessResolver::class);

    $this->assertTrue($resolver->canAccessPluginPermission($this->user('super_admin'), 'appointments.view', $registry));
    $this->assertFalse($resolver->canAccessPluginPermission($this->user('site_admin'), 'appointments.view', $registry));
    $this->assertSame(['super_admin'], $permission->roleNames());
  }

  public function test_plugin_can_explicitly_grant_a_permission_to_site_admins(): void
  {
    $permission = PluginPermission::make('appointments.manage')->roles(['site_admin']);
    $registry = $this->registryWith($permission);
    $resolver = app(PluginAccessResolver::class);

    $this->assertTrue($resolver->canAccessPluginPermission($this->user('super_admin'), 'appointments.manage', $registry));
    $this->assertTrue($resolver->canAccessPluginPermission($this->user('site_admin'), 'appointments.manage', $registry));
    $this->assertFalse($resolver->canAccessPluginPermission($this->user('editor'), 'appointments.manage', $registry));
    $this->assertSame(['super_admin', 'site_admin'], $permission->toArray()['roles']);
  }

  public function test_unknown_roles_are_ignored_instead_of_broadening_access(): void
  {
    $permission = PluginPermission::make('appointments.manage')->roles(['site_admin', 'owner', 123]);

    $this->assertSame(['super_admin', 'site_admin'], $permission->roleNames());
  }

  public function test_manifest_only_plugins_load_explicit_role_grants(): void
  {
    $plugin = app(InstalledPluginDefinitionFactory::class)->make([
      'handle' => 'appointments',
      'label' => 'Appointments',
      'version' => '1.0.0',
      'provider' => '',
      'permissions' => [[
        'key' => 'appointments.view',
        'label' => 'View appointments',
        'roles' => ['site_admin'],
      ]],
    ], sys_get_temp_dir().'/missing-appointments-plugin', false);

    $this->assertSame(
      ['super_admin', 'site_admin'],
      $plugin->permissionsList()['appointments.view']->roleNames(),
    );
  }

  private function registryWith(PluginPermission $permission): PluginRegistry
  {
    $plugin = PluginDefinition::make('appointments')
      ->label('Appointments')
      ->version('1.0.0')
      ->permissions([$permission]);
    $registry = new PluginRegistry(['appointments' => true]);
    $registry->register($plugin);

    return $registry;
  }

  private function user(string $role): AuthenticatableUser
  {
    $user = new AuthenticatableUser;
    $user->role = $role;
    $user->is_admin = $role === 'super_admin';

    return $user;
  }
}
