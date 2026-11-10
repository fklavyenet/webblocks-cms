<?php

namespace WebBlocks\Cms\Actions\Plugins;

use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginDatabaseSetup;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDependencyManager;

final class EnablePlugin
{
  public function __construct(
    private readonly InstalledPluginRepository $installed,
    private readonly PluginDatabaseSetup $database,
    private readonly PluginDependencyManager $dependencies,
  ) {}

  public function execute(PluginDefinition $plugin, string $version, bool $repairRecordedMigrations = false): void
  {
    $this->dependencies->assertEnable([
      'handle' => $plugin->handle(),
      'label' => $plugin->labelText(),
      'version' => $version,
      'required_cms_version' => $plugin->requiredCmsVersion(),
      'dependencies' => $plugin->dependencyDefinitions(),
    ]);
    $this->database->run($plugin, repairRecordedMigrations: $repairRecordedMigrations);
    $this->installed->enable($plugin->handle(), $version);
  }
}
