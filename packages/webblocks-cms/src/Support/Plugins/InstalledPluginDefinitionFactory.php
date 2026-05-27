<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class InstalledPluginDefinitionFactory
{
  /**
   * @param  array<string, mixed>  $manifest
   */
  public function make(array $manifest, string $path, bool $enabled): PluginDefinition
  {
    $provider = (string) ($manifest['provider'] ?? '');

    if ($enabled) {
      $this->loadPluginSource($path, $provider);
    }

    if ($enabled && class_exists($provider) && method_exists($provider, 'definition')) {
      $definition = $provider::definition();

      return $definition
        ->source('manual upload')
        ->installPath($path);
    }

    $definition = PluginDefinition::make((string) $manifest['handle'])
      ->label((string) $manifest['label'])
      ->version((string) ($manifest['version'] ?? ''))
      ->provider($provider)
      ->description($manifest['description'] ?? null)
      ->requiresCms($manifest['required_cms_version'] ?? null)
      ->source('manual upload')
      ->installPath($path);

    $settings = $manifest['settings'] ?? null;

    if (is_array($settings)) {
      $definition
        ->settingsNamespace($settings['namespace'] ?? null)
        ->settings(PluginSettingsDefinition::make()
          ->label((string) ($settings['label'] ?? $manifest['label'].' Settings'))
          ->description($settings['description'] ?? null));
    }

    $permissions = [];

    foreach (($manifest['permissions'] ?? []) as $permission) {
      if (! is_array($permission)) {
        continue;
      }

      $permissions[] = PluginPermission::make((string) $permission['name'])
        ->label((string) ($permission['label'] ?? $permission['name']))
        ->description($permission['description'] ?? null);
    }

    $definition->permissions($permissions);

    if ($enabled) {
      $routes = $manifest['routes']['admin'] ?? null;

      if (is_string($routes) && $routes !== '') {
        $definition->adminRoutes($path.DIRECTORY_SEPARATOR.$routes);
      }

      $commands = array_values(array_filter($manifest['commands'] ?? [], function (mixed $command): bool {
        return is_string($command) && class_exists($command) && is_subclass_of($command, Command::class);
      }));

      $definition->commands($commands);

      $health = $manifest['health'] ?? null;
      $definition->health(is_string($health) && class_exists($health) ? $health : null);
    }

    return $definition;
  }

  private function loadPluginSource(string $path, string $provider): void
  {
    $views = $path.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views';

    if (is_dir($views)) {
      app('view')->addNamespace(WebBlocksCmsServiceProvider::VIEW_NAMESPACE, $views);
    }

    $source = $path.DIRECTORY_SEPARATOR.'src';

    if (! is_dir($source)) {
      return;
    }

    if ($provider !== '' && class_exists($provider)) {
      return;
    }

    foreach (File::allFiles($source) as $file) {
      if ($file->getExtension() === 'php') {
        require_once $file->getPathname();
      }
    }
  }
}
