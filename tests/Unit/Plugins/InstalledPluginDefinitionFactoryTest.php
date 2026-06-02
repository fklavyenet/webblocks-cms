<?php

namespace Tests\Unit\Plugins;

use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;

class InstalledPluginDefinitionFactoryTest extends TestCase
{
  public function test_catalog_manifest_permission_keys_and_string_migrations_are_supported(): void
  {
    $definition = app(InstalledPluginDefinitionFactory::class)->make([
      'handle' => 'webblocks-redirect-manager',
      'label' => 'WebBlocks Redirect Manager',
      'version' => '0.1.1',
      'provider' => 'WebBlocks\\RedirectManager\\RedirectManagerServiceProvider',
      'required_cms_version' => '^1.32',
      'permissions' => [
        [
          'key' => 'webblocks-redirect-manager.view',
          'label' => 'View redirects',
        ],
      ],
      'migrations' => 'database/migrations',
    ], storage_path('framework/testing/plugins/webblocks-redirect-manager/0.1.1'), false);

    $permissions = $definition->permissionsList();

    $this->assertArrayHasKey('webblocks-redirect-manager.view', $permissions);
    $this->assertSame('View redirects', $permissions['webblocks-redirect-manager.view']->labelText());
    $this->assertSame(['database/migrations'], $definition->migrationPaths());
  }
}
