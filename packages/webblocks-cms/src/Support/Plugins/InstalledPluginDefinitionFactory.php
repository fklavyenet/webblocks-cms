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
        ->installPath($path)
        ->migrations($this->migrationPaths($manifest));
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

      $name = $this->permissionName($permission);

      if ($name === null) {
        continue;
      }

      $permissions[] = PluginPermission::make($name)
        ->label((string) ($permission['label'] ?? $name))
        ->description($permission['description'] ?? null);
    }

    $definition->permissions($permissions);
    $definition->migrations($this->migrationPaths($manifest));

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

  /**
   * @param  array<string, mixed>  $manifest
   * @return array<int, string>
   */
  private function migrationPaths(array $manifest): array
  {
    $migrations = $manifest['migrations'] ?? [];

    if (is_string($migrations)) {
      $migrations = [$migrations];
    }

    if (! is_array($migrations)) {
      return [];
    }

    return array_values(array_filter($migrations, fn (mixed $path): bool => is_string($path) && trim($path) !== ''));
  }

  /**
   * @param  array<string, mixed>  $permission
   */
  private function permissionName(array $permission): ?string
  {
    $name = $permission['name'] ?? $permission['key'] ?? null;

    if (! is_string($name)) {
      return null;
    }

    $name = trim($name);

    return $name !== '' ? $name : null;
  }

  private function loadPluginSource(string $path, string $provider): void
  {
    $views = $path.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views';

    if (is_dir($views)) {
      $this->registerPluginViews($views);
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

  private function registerPluginViews(string $views): void
  {
    $finder = app('view')->getFinder();

    if (! method_exists($finder, 'getHints') || ! method_exists($finder, 'replaceNamespace')) {
      app('view')->addNamespace(WebBlocksCmsServiceProvider::VIEW_NAMESPACE, $views);

      return;
    }

    $namespace = WebBlocksCmsServiceProvider::VIEW_NAMESPACE;
    $hints = $finder->getHints()[$namespace] ?? [];
    $pluginRoot = rtrim(app(InstalledPluginRepository::class)->rootPath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    $corePaths = [];
    $pluginPaths = [];

    foreach ($hints as $hint) {
      if ($hint === $views) {
        continue;
      }

      if ($this->isPluginViewPath($hint, $pluginRoot)) {
        $pluginPaths[] = $hint;

        continue;
      }

      $corePaths[] = $hint;
    }

    $finder->replaceNamespace($namespace, array_values(array_unique([
      ...$corePaths,
      $views,
      ...$pluginPaths,
    ])));
  }

  private function isPluginViewPath(string $path, string $pluginRoot): bool
  {
    return str_starts_with($path, $pluginRoot)
      || str_contains($path, DIRECTORY_SEPARATOR.'webblocks'.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR)
      || str_contains($path, DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR);
  }
}
