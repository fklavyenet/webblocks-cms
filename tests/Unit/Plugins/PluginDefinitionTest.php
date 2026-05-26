<?php

namespace Tests\Unit\Plugins;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginException;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPermission;

class PluginDefinitionTest extends TestCase
{
  #[Test]
  public function valid_plugin_definition_exposes_manifest_metadata(): void
  {
    $plugin = PluginDefinition::make('webblocks-ui-manager')
      ->label('WebBlocks UI Manager')
      ->version('0.1.0')
      ->description('Publishes WebBlocks UI release artifacts to first-party CDN.')
      ->provider('Vendor\\WebBlocksUiManager\\ServiceProvider')
      ->requiresCms('^1.33')
      ->menu([
        PluginMenuItem::make('releases')
          ->label('Releases')
          ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
          ->icon('wb-icon-box')
          ->permission('webblocks-ui-manager.view'),
      ])
      ->permissions([
        PluginPermission::make('webblocks-ui-manager.view')->label('View WebBlocks UI releases'),
        PluginPermission::make('webblocks-ui-manager.publish')->label('Publish WebBlocks UI releases'),
      ]);

    $this->assertSame('webblocks-ui-manager', $plugin->handle());
    $this->assertSame('WebBlocks UI Manager', $plugin->labelText());
    $this->assertSame('0.1.0', $plugin->versionText());
    $this->assertSame('webblocks.plugins.webblocks_ui_manager', $plugin->routeNamePrefix());
    $this->assertSame('/webadmin/plugins/webblocks-ui-manager', $plugin->adminRoutePrefix());
    $this->assertCount(1, $plugin->menuItems());
    $this->assertCount(2, $plugin->permissionsList());
  }

  #[Test]
  public function invalid_handle_is_rejected(): void
  {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('must be kebab-case');

    PluginDefinition::make('WebBlocks Ui Manager');
  }

  #[Test]
  public function invalid_version_is_rejected(): void
  {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('must be semver-like');

    PluginDefinition::make('sample-plugin')
      ->label('Sample Plugin')
      ->version('one');
  }

  #[Test]
  public function duplicate_menu_keys_are_rejected(): void
  {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('already defines menu item [releases]');

    PluginDefinition::make('webblocks-ui-manager')
      ->label('WebBlocks UI Manager')
      ->menu([
        PluginMenuItem::make('releases')->permission('webblocks-ui-manager.view'),
        PluginMenuItem::make('releases')->permission('webblocks-ui-manager.publish'),
      ]);
  }

  #[Test]
  public function permission_names_must_start_with_plugin_handle(): void
  {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('must start with [webblocks-ui-manager.]');

    PluginDefinition::make('webblocks-ui-manager')
      ->label('WebBlocks UI Manager')
      ->permissions([
        PluginPermission::make('other.view')->label('Other View'),
      ]);
  }

  #[Test]
  public function menu_item_permission_names_must_start_with_plugin_handle(): void
  {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('must start with [webblocks-ui-manager.]');

    PluginDefinition::make('webblocks-ui-manager')
      ->label('WebBlocks UI Manager')
      ->menu([
        PluginMenuItem::make('releases')->permission('other.view'),
      ]);
  }

  #[Test]
  public function route_name_segment_normalizes_handle_dashes_to_underscores(): void
  {
    $this->assertSame(
      'webblocks_ui_manager',
      PluginDefinition::routeNameSegmentForHandle('webblocks-ui-manager')
    );
  }
}
