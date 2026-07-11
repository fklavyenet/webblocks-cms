<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginCommandRegistrar
{
  public function __construct(
    private readonly PluginRegistry $plugins,
  ) {}

  /**
   * @return array<int, class-string>
   */
  public function enabledCommands(): array
  {
    $commands = [];

    foreach ($this->plugins->enabled() as $plugin) {
      foreach ($plugin->commandClasses() as $command) {
        $commands[] = $command;
      }
    }

    return array_values(array_unique($commands));
  }
}
