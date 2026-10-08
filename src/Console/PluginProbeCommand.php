<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Throwable;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginApiRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginBootProbe;
use WebBlocks\Cms\Support\Plugins\PluginCompatibility;
use WebBlocks\Cms\Support\Plugins\PluginPublicRouteRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;

class PluginProbeCommand extends Command
{
  protected $signature = 'cms:plugin-probe {handle} {version}';

  protected $description = 'Validate one installed plugin in a fresh process before activation';

  public function handle(InstalledPluginRepository $plugins, InstalledPluginDefinitionFactory $factory): int
  {
    try {
      $installed = $plugins->findVersion((string) $this->argument('handle'), (string) $this->argument('version'));
      if ($installed === null) {
        return self::FAILURE;
      }
      $prototype = $factory->make($installed['manifest'], $installed['path'], false);
      app(PluginCompatibility::class)->assertCompatible($prototype);
      $registry = new PluginRegistry([$prototype->handle() => true], respectRecoveryMode: false);
      $registry->register($prototype);
      app()->instance(PluginRegistry::class, $registry);
      $plugin = $factory->make($installed['manifest'], $installed['path'], true);
      if ($plugin->handle() !== $prototype->handle() || $plugin->versionText() !== $prototype->versionText()) {
        return self::FAILURE;
      }
      $registry = new PluginRegistry([$plugin->handle() => true], respectRecoveryMode: false);
      $registry->register($plugin);
      app()->instance(PluginRegistry::class, $registry);
      $provider = $plugin->providerClass();
      if (! $provider || ! class_exists($provider)) {
        return self::FAILURE;
      }
      if (is_subclass_of($provider, ServiceProvider::class)) {
        app()->register($provider);
      }
      app(PluginRouteRegistrar::class)->registerAdminRoutesFor($plugin);
      app(PluginApiRouteRegistrar::class)->registerApiRoutesFor($plugin);
      app(PluginPublicRouteRegistrar::class)->registerPublicRoutesFor($plugin);
      foreach ($plugin->commandClasses() as $command) {
        app($command);
      }
      $this->line(PluginBootProbe::SUCCESS);

      return self::SUCCESS;
    } catch (Throwable) {
      return self::FAILURE;
    }
  }
}
