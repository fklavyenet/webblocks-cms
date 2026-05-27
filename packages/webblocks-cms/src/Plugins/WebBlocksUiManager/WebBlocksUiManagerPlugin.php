<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager;

use WebBlocks\Cms\Plugins\WebBlocksUiManager\Console\PrepareWebBlocksUiReleaseCommand;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Console\PublishWebBlocksUiReleaseCommand;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiManagerHealth;
use WebBlocks\Cms\Support\Plugins\PluginDashboardWidget;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginSettingsDefinition;
use WebBlocks\Cms\Support\Plugins\PluginSystemCard;

class WebBlocksUiManagerPlugin
{
  public const HANDLE = 'webblocks-ui-manager';

  public static function definition(): PluginDefinition
  {
    return PluginDefinition::make(self::HANDLE)
      ->label('WebBlocks UI Manager')
      ->version('0.1.0')
      ->provider(self::class)
      ->description('Tracks WebBlocks UI release artifacts and first-party CDN preparation metadata.')
      ->requiresCms('^1.32')
      ->settingsNamespace('webblocks_ui_manager')
      ->databasePrefix('webblocks_ui_manager_')
      ->permissions([
        PluginPermission::make('webblocks-ui-manager.view')
          ->label('View WebBlocks UI releases')
          ->description('View release metadata, artifact manifests, and plugin status.'),
        PluginPermission::make('webblocks-ui-manager.manage')
          ->label('Manage WebBlocks UI release metadata')
          ->description('Create and edit release metadata before artifact preparation.'),
        PluginPermission::make('webblocks-ui-manager.publish')
          ->label('Prepare WebBlocks UI release artifacts')
          ->description('Run safe local preparation for release artifacts and manifests.'),
      ])
      ->menu([
        PluginMenuItem::make('webblocks-ui-releases')
          ->label('WebBlocks UI Releases')
          ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
          ->icon('wb-icon-package')
          ->permission('webblocks-ui-manager.view')
          ->group('System')
          ->sort(80),
      ])
      ->adminRoutes(__DIR__.'/../../../routes/plugins/webblocks-ui-manager.php')
      ->commands([
        PrepareWebBlocksUiReleaseCommand::class,
        PublishWebBlocksUiReleaseCommand::class,
      ])
      ->settings(
        PluginSettingsDefinition::make()
          ->label('WebBlocks UI Manager Settings')
          ->description('Default CDN path: public/cdn/webblocks-ui/{version}. Configure local artifact paths through the plugin release metadata and dry-run command options.')
      )
      ->dashboardWidgets([
        PluginDashboardWidget::make('webblocks-ui-manager.release-readiness')
          ->title('WebBlocks UI Release Readiness')
          ->description('Tracks local release metadata and prepared artifact manifests.')
          ->permission('webblocks-ui-manager.view')
          ->sort(80),
      ])
      ->systemCards([
        PluginSystemCard::make('webblocks-ui-manager.cdn-foundation')
          ->title('WebBlocks UI CDN Foundation')
          ->description('First-party release metadata and artifact manifest preparation are hosted by a plugin, not CMS core.')
          ->url('/webadmin/plugins/webblocks-ui-manager/releases', 'Open Releases')
          ->permission('webblocks-ui-manager.view')
          ->sort(80),
      ])
      ->health(WebBlocksUiManagerHealth::class);
  }
}
