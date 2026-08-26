<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SplFileInfo;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class InstalledPluginDefinitionFactory
{
  /**
   * @param  array<string, mixed>  $manifest
   */
  public function make(array $manifest, string $path, bool $enabled): PluginDefinition
  {
    $provider = (string) ($manifest['provider'] ?? '');
    $handle = (string) ($manifest['handle'] ?? '');

    if ($enabled) {
      $this->loadPluginSource($path, $provider);
    }

    $this->loadPluginTranslations($path, $handle);

    if ($enabled && class_exists($provider) && method_exists($provider, 'definition') && $this->providerUsableForPath($provider, $path)) {
      $definition = $provider::definition();

      $declared = $this->publicAssets($manifest);

      $definition = $definition
        ->source('manual upload')
        ->installPath($path);

      // Only when the manifest declares them. A provider that built its own list in
      // code has said something more specific, and overwriting it with an empty
      // array would silently drop assets that used to work.
      if ($declared !== []) {
        $definition->publicAssets($declared);
      }

      return $definition
        /*
         * Applied to the provider's own definition too. A provider builds its
         * definition in code and has no reason to restate `requires`, which lives in
         * the manifest the installer and the catalog both read.
         */
        ->requires($this->requirements($manifest))
        ->migrations($this->migrationPaths($manifest));
    }

    $definition = PluginDefinition::make((string) $manifest['handle'])
      ->label((string) $manifest['label'])
      ->version((string) ($manifest['version'] ?? ''))
      ->provider($provider)
      ->description($manifest['description'] ?? null)
      ->requiresCms($manifest['required_cms_version'] ?? null)
      ->requires($this->requirements($manifest))
      ->publicAssets($this->publicAssets($manifest))
      ->source('manual upload')
      ->installPath($path);

    $settings = $manifest['settings'] ?? null;

    if (is_array($settings)) {
      $definition
        ->settingsNamespace($settings['namespace'] ?? null)
        ->settings(PluginSettingsDefinition::make($this->settingsRouteName($settings))
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
    $definition->blockTypes($this->blockTypes($manifest));
    $definition->migrations($this->migrationPaths($manifest));

    if ($enabled) {
      $routes = $manifest['routes']['admin'] ?? null;

      if (is_string($routes) && $routes !== '') {
        $definition->adminRoutes($path.DIRECTORY_SEPARATOR.$routes);
      }

      $apiRoutes = $manifest['routes']['api'] ?? null;

      if (is_string($apiRoutes) && $apiRoutes !== '') {
        $definition->apiRoutes($path.DIRECTORY_SEPARATOR.$apiRoutes);
      }

      $publicRoutes = $manifest['routes']['public'] ?? null;

      if (is_string($publicRoutes) && $publicRoutes !== '') {
        $definition->publicRoutes($path.DIRECTORY_SEPARATOR.$publicRoutes);
      }

      $webhookRoutes = $manifest['routes']['webhooks'] ?? null;

      if (is_string($webhookRoutes) && $webhookRoutes !== '') {
        $definition->webhookRoutes($path.DIRECTORY_SEPARATOR.$webhookRoutes);
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

  private function loadPluginTranslations(string $path, string $handle): void
  {
    if ($handle === '' || ! PluginDefinition::isValidHandle($handle)) {
      return;
    }

    $langPath = $path.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'lang';

    if (is_dir($langPath)) {
      app('translator')->addNamespace($handle, $langPath);
    }
  }

  /**
   * @param  array<string, mixed>  $manifest
   * @return array<int, string>
   */
  /**
   * The manifest's `requires` map, defensively.
   *
   * Documented and written by every catalog plugin since the beginning, and until now
   * read by nothing. A manifest is third-party text, so anything that is not a map of
   * strings is ignored rather than trusted.
   *
   * @param  array<string, mixed>  $manifest
   * @return array<string, string>
   */
  private function requirements(array $manifest): array
  {
    $requires = $manifest['requires'] ?? null;

    return is_array($requires) ? $requires : [];
  }

  /**
   * Turn the manifest's `assets` declarations into emittable tags.
   *
   * Each entry names a file inside the plugin's `resources/public`, which
   * `PluginAssetPublisher` copies to `/cms/plugins/{handle}`. The plugin therefore
   * writes a path relative to its own package and never a URL: a plugin that could
   * write its own URL could point the tag at another origin, and every page on the
   * site would load it.
   *
   * @param  array<string, mixed>  $manifest
   * @return list<PluginPublicAsset>
   */
  private function publicAssets(array $manifest): array
  {
    $declared = $manifest['assets'] ?? null;

    if (! is_array($declared)) {
      return [];
    }

    $handle = (string) ($manifest['handle'] ?? '');
    $version = (string) ($manifest['version'] ?? '');
    $assets = [];

    foreach ($declared as $asset) {
      if (! is_array($asset)) {
        continue;
      }

      $key = trim((string) ($asset['handle'] ?? ''));
      $relative = trim((string) ($asset['path'] ?? ''), '/');
      $type = strtolower(trim((string) ($asset['type'] ?? '')));
      $location = strtolower(trim((string) ($asset['location'] ?? PluginPublicAsset::LOCATION_HEAD)));

      /*
       * A path is a path. `..` and a leading slash are the two ways a relative
       * reference stops being one, and a backslash is a separator on the platform
       * that would treat it as such.
       */
      if ($key === '' || $relative === '' || str_contains($relative, '..') || str_contains($relative, '\\')) {
        continue;
      }

      /*
       * The version is the cache-buster. Without it a plugin update ships new CSS
       * to a browser that keeps serving the old file from disk, and the operator
       * sees a bug that does not exist on any other machine.
       */
      $url = '/cms/plugins/'.$handle.'/'.$relative.($version !== '' ? '?v='.rawurlencode($version) : '');

      try {
        $built = match (true) {
          $type === PluginPublicAsset::TYPE_CSS => PluginPublicAsset::cssHead($key, $url),
          $location === PluginPublicAsset::LOCATION_BODY_END => PluginPublicAsset::jsBodyEnd($key, $url),
          default => PluginPublicAsset::jsHead($key, $url),
        };
      } catch (PluginException) {
        // A manifest is third-party text; a malformed asset entry is dropped rather
        // than allowed to break the plugin screen it appears on.
        continue;
      }

      if (! empty($asset['module'])) {
        $built->module();
      }

      if (! empty($asset['async'])) {
        $built->async();
      }

      $assets[] = $built;
    }

    return $assets;
  }

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
   * @param  array<string, mixed>  $settings
   */
  private function settingsRouteName(array $settings): ?string
  {
    $routeName = $settings['route_name'] ?? null;

    if (! is_string($routeName) || trim($routeName) === '') {
      return null;
    }

    return trim($routeName);
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

  /**
   * @param  array<string, mixed>  $manifest
   * @return array<int, PluginBlockTypeDefinition>
   */
  private function blockTypes(array $manifest): array
  {
    $items = $manifest['block_types'] ?? [];

    if (! is_array($items)) {
      return [];
    }

    $blockTypes = [];

    foreach ($items as $item) {
      if (! is_array($item)) {
        continue;
      }

      $handle = $item['handle'] ?? null;

      if (! is_string($handle) || ! PluginBlockTypeDefinition::isValidHandle($handle)) {
        continue;
      }

      $label = $item['label'] ?? null;
      $description = $item['description'] ?? null;
      $blockType = PluginBlockTypeDefinition::make($handle)
        ->label(is_string($label) && trim($label) !== '' ? $label : $handle)
        ->description(is_string($description) ? $description : null);

      if (is_string($item['admin_view'] ?? null)) {
        $blockType->adminView((string) $item['admin_view']);
      }

      if (is_string($item['public_view'] ?? null)) {
        $blockType->publicView((string) $item['public_view']);
      }

      if (is_array($item['metadata'] ?? null)) {
        $blockType->metadata($item['metadata']);
      }

      $translatedFields = $item['translated_fields'] ?? null;

      if (is_array($translatedFields)) {
        $blockType->translatedFields($translatedFields);
      }

      $blockTypes[] = $blockType;
    }

    return $blockTypes;
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

    $files = collect(File::allFiles($source))
      ->sortBy(fn (SplFileInfo $file): string => $this->pluginSourceLoadPriority($file).':'.$file->getPathname())
      ->values();

    foreach ($files as $file) {
      $declaredClass = $this->fileDeclaredClass($file->getPathname());

      if ($declaredClass !== null && $this->declaredSymbolLoaded($declaredClass)) {
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

  private function pluginSourceLoadPriority(SplFileInfo $file): string
  {
    $contents = is_file($file->getPathname()) ? file_get_contents($file->getPathname()) : false;

    if (! is_string($contents)) {
      return '9';
    }

    return match (true) {
      preg_match('/\binterface\s+[A-Za-z_][A-Za-z0-9_]*\b/', $contents) === 1 => '0',
      preg_match('/\btrait\s+[A-Za-z_][A-Za-z0-9_]*\b/', $contents) === 1 => '1',
      preg_match('/\benum\s+[A-Za-z_][A-Za-z0-9_]*\b/', $contents) === 1 => '2',
      preg_match('/\bclass\s+[A-Za-z_][A-Za-z0-9_]*\b/', $contents) === 1 => '3',
      default => '9',
    };
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

    if (preg_match('/\b(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $contents, $classMatches) !== 1) {
      return null;
    }

    return $namespace !== ''
      ? $namespace.'\\'.$classMatches[1]
      : $classMatches[1];
  }

  private function declaredSymbolLoaded(string $symbol): bool
  {
    return class_exists($symbol, false)
      || interface_exists($symbol, false)
      || trait_exists($symbol, false)
      || (function_exists('enum_exists') && enum_exists($symbol, false));
  }
}
