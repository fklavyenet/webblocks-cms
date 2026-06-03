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

    if ($enabled && class_exists($provider) && method_exists($provider, 'definition') && $this->providerUsableForPath($provider, $path)) {
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
    $definition->menu($this->menuItems($manifest));
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

  /**
   * @param  array<string, mixed>  $manifest
   * @return array<int, PluginMenuItem>
   */
  private function menuItems(array $manifest): array
  {
    $items = $manifest['menu_items'] ?? $manifest['menu'] ?? [];

    if (! is_array($items)) {
      return [];
    }

    $menuItems = [];

    foreach ($items as $item) {
      if (! is_array($item)) {
        continue;
      }

      $key = $item['key'] ?? null;
      $route = $item['route'] ?? null;

      if (! is_string($key) || ! is_string($route)) {
        continue;
      }

      $menuItem = PluginMenuItem::make($key)
        ->route($route)
        ->label((string) ($item['label'] ?? $key));

      if (is_string($item['permission'] ?? null)) {
        $menuItem->permission((string) $item['permission']);
      }

      if (is_string($item['icon'] ?? null)) {
        $menuItem->icon((string) $item['icon']);
      }

      if (is_string($item['group'] ?? null)) {
        $menuItem->group((string) $item['group']);
      }

      if (is_numeric($item['sort'] ?? null)) {
        $menuItem->sort((int) $item['sort']);
      }

      $menuItems[] = $menuItem;
    }

    return $menuItems;
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

    if ($provider !== '' && class_exists($provider) && $this->providerUsableForPath($provider, $path)) {
      return;
    }

    foreach (File::allFiles($source) as $file) {
      $declaredClass = $this->fileDeclaredClass($file->getPathname());

      if ($declaredClass !== null && class_exists($declaredClass)) {
        continue;
      }

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

  private function providerUsableForPath(string $provider, string $path): bool
  {
    if ($provider === '' || ! class_exists($provider)) {
      return false;
    }

    $file = (new \ReflectionClass($provider))->getFileName();

    if (! is_string($file)) {
      return false;
    }

    $base = rtrim(realpath($path) ?: $path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    $loaded = realpath($file) ?: $file;

    if (str_starts_with($loaded, $base)) {
      return true;
    }

    $pluginRoot = rtrim(app(InstalledPluginRepository::class)->rootPath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

    return ! str_starts_with($loaded, $pluginRoot);
  }

  private function fileDeclaredClass(string $file): ?string
  {
    $contents = is_file($file) ? file_get_contents($file) : false;

    if (! is_string($contents)) {
      return null;
    }

    $namespace = preg_match('/\bnamespace\s+([^;]+);/', $contents, $namespaceMatches) === 1
      ? trim($namespaceMatches[1])
      : '';

    if (preg_match('/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $contents, $classMatches) !== 1) {
      return null;
    }

    return $namespace !== ''
      ? $namespace.'\\'.$classMatches[1]
      : $classMatches[1];
  }
}
