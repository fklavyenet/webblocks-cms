<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginDefinition
{
  /**
   * Reserved first URI segment for every plugin-owned public route. Reserving
   * one segment for all plugins keeps visitor endpoints from colliding with
   * page slugs, and mirrors `/webadmin/plugins/{handle}` on the admin side.
   */
  public const PUBLIC_ROUTE_SEGMENT = 'plugins';

  private string $label = '';

  private ?string $version = null;

  private ?string $providerClass = null;

  private ?string $description = null;

  private ?string $requiredCmsVersion = null;

  private string $source = 'core';

  private ?string $installPath = null;

  private ?PluginSettingsDefinition $settings = null;

  private ?string $settingsNamespace = null;

  private ?string $databasePrefix = null;

  /** @var array<int, string|callable> */
  private array $adminRoutes = [];

  /** @var array<int, string|callable> */
  private array $apiRoutes = [];

  /** @var array<int, string|callable> */
  private array $publicRoutes = [];

  /** @var array<string, mixed> */
  private array $apiDiscovery = [];

  /** @var array<string, mixed> */
  private array $apiCapabilities = [];

  /** @var array<int, class-string> */
  private array $commands = [];

  /** @var array<int, string> */
  private array $migrations = [];

  /** @var callable|string|null */
  private mixed $healthReporter = null;

  /** @var array<string, PluginMenuItem> */
  private array $menuItems = [];

  /** @var array<string, PluginPermission> */
  private array $permissions = [];

  /** @var array<string, PluginDashboardWidget> */
  private array $dashboardWidgets = [];

  /** @var array<string, PluginSystemCard> */
  private array $systemCards = [];

  /** @var array<string, PluginBlockTypeDefinition> */
  private array $blockTypes = [];

  /** @var array<string, PluginBlockPackDefinition> */
  private array $blockPacks = [];

  /** @var array<string, PluginPublicAsset> */
  private array $publicAssets = [];

  private function __construct(
    private readonly string $handle,
  ) {
    if (! self::isValidHandle($handle)) {
      throw PluginException::invalidHandle($handle);
    }
  }

  public function __clone()
  {
    foreach ($this->menuItems as $key => $item) {
      $this->menuItems[$key] = clone $item;
    }

    foreach ($this->permissions as $key => $permission) {
      $this->permissions[$key] = clone $permission;
    }

    if ($this->settings !== null) {
      $this->settings = clone $this->settings;
    }

    foreach ($this->dashboardWidgets as $key => $widget) {
      $this->dashboardWidgets[$key] = clone $widget;
    }

    foreach ($this->systemCards as $key => $card) {
      $this->systemCards[$key] = clone $card;
    }

    foreach ($this->blockTypes as $key => $blockType) {
      $this->blockTypes[$key] = clone $blockType;
    }

    foreach ($this->blockPacks as $key => $blockPack) {
      $this->blockPacks[$key] = clone $blockPack;
    }

    foreach ($this->publicAssets as $key => $asset) {
      $this->publicAssets[$key] = clone $asset;
    }
  }

  public static function make(string $handle): self
  {
    return new self($handle);
  }

  public static function isValidHandle(string $handle): bool
  {
    return (bool) preg_match('/^[a-z0-9][a-z0-9]*(?:-[a-z0-9]+)*$/', $handle);
  }

  public static function routeNameSegmentForHandle(string $handle): string
  {
    if (! self::isValidHandle($handle)) {
      throw PluginException::invalidHandle($handle);
    }

    return str_replace('-', '_', $handle);
  }

  public function handle(): string
  {
    return $this->handle;
  }

  public function routeNamePrefix(): string
  {
    return 'webblocks.plugins.'.self::routeNameSegmentForHandle($this->handle);
  }

  public function adminRoutePrefix(): string
  {
    return '/webadmin/plugins/'.$this->handle;
  }

  public function publicRoutePrefix(): string
  {
    return '/'.self::PUBLIC_ROUTE_SEGMENT.'/'.$this->handle;
  }

  public function publicRouteNamePrefix(): string
  {
    return $this->routeNamePrefix().'.public';
  }

  public function label(string $label): self
  {
    $this->label = trim($label);

    if ($this->label === '') {
      throw PluginException::invalidLabel($this->handle);
    }

    return $this;
  }

  public function labelText(): string
  {
    if ($this->label === '') {
      throw PluginException::invalidLabel($this->handle);
    }

    return $this->label;
  }

  public function version(?string $version): self
  {
    $version = is_string($version) ? trim($version) : null;

    if ($version === '') {
      $version = null;
    }

    if ($version !== null && ! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
      throw PluginException::invalidVersion($this->handle, $version);
    }

    $this->version = $version;

    return $this;
  }

  public function versionText(): ?string
  {
    return $this->version;
  }

  public function provider(?string $providerClass): self
  {
    $providerClass = is_string($providerClass) ? trim($providerClass) : null;
    $this->providerClass = $providerClass !== '' ? $providerClass : null;

    return $this;
  }

  public function providerClass(): ?string
  {
    return $this->providerClass;
  }

  public function description(?string $description): self
  {
    $description = is_string($description) ? trim($description) : null;
    $this->description = $description !== '' ? $description : null;

    return $this;
  }

  public function descriptionText(): ?string
  {
    return $this->description;
  }

  public function requiresCms(?string $versionConstraint): self
  {
    $versionConstraint = is_string($versionConstraint) ? trim($versionConstraint) : null;
    $this->requiredCmsVersion = $versionConstraint !== '' ? $versionConstraint : null;

    return $this;
  }

  public function requiredCmsVersion(): ?string
  {
    return $this->requiredCmsVersion;
  }

  public function source(string $source): self
  {
    $source = trim($source);
    $this->source = $source !== '' ? $source : 'core';

    return $this;
  }

  public function sourceText(): string
  {
    return $this->source;
  }

  public function installPath(?string $path): self
  {
    $path = is_string($path) ? trim($path) : null;
    $this->installPath = $path !== '' ? $path : null;

    return $this;
  }

  public function installPathValue(): ?string
  {
    return $this->installPath;
  }

  public function settingsNamespace(?string $namespace): self
  {
    $namespace = is_string($namespace) ? trim($namespace) : null;

    if ($namespace === '') {
      $namespace = null;
    }

    if ($namespace !== null && ! preg_match('/^[a-z0-9][a-z0-9_]*$/', $namespace)) {
      throw PluginException::invalidSettingsNamespace($namespace);
    }

    $this->settingsNamespace = $namespace;

    return $this;
  }

  public function settingsNamespaceValue(): string
  {
    return $this->settingsNamespace ?? str_replace('-', '_', $this->handle);
  }

  public function databasePrefix(?string $prefix): self
  {
    $prefix = is_string($prefix) ? trim($prefix) : null;

    if ($prefix === '') {
      $prefix = null;
    }

    if ($prefix !== null && ! preg_match('/^[a-z0-9][a-z0-9_]*_$/', $prefix)) {
      throw PluginException::invalidDatabasePrefix($prefix);
    }

    $this->databasePrefix = $prefix;

    return $this;
  }

  public function databasePrefixValue(): string
  {
    return $this->databasePrefix ?? str_replace('-', '_', $this->handle).'_';
  }

  /**
   * @param  array<int, PluginMenuItem>  $items
   */
  public function menu(array $items): self
  {
    $menuItems = [];

    foreach ($items as $item) {
      if (! $item instanceof PluginMenuItem) {
        throw new PluginException('Plugin menu entries must be PluginMenuItem instances.');
      }

      if (isset($menuItems[$item->key()])) {
        throw PluginException::duplicateMenuItem($this->handle, $item->key());
      }

      $permission = $item->permissionName();

      if ($permission !== null && ! str_starts_with($permission, $this->handle.'.')) {
        throw PluginException::invalidPermissionPrefix($this->handle, $permission);
      }

      $menuItems[$item->key()] = $item;
    }

    $this->menuItems = $menuItems;

    return $this;
  }

  /**
   * @return array<string, PluginMenuItem>
   */
  public function menuItems(): array
  {
    return $this->menuItems;
  }

  /**
   * @param  array<int, PluginPermission>  $permissions
   */
  public function permissions(array $permissions): self
  {
    $indexed = [];

    foreach ($permissions as $permission) {
      if (! $permission instanceof PluginPermission) {
        throw new PluginException('Plugin permissions must be PluginPermission instances.');
      }

      if (! str_starts_with($permission->name(), $this->handle.'.')) {
        throw PluginException::invalidPermissionPrefix($this->handle, $permission->name());
      }

      $indexed[$permission->name()] = $permission;
    }

    $this->permissions = $indexed;

    return $this;
  }

  /**
   * @return array<string, PluginPermission>
   */
  public function permissionsList(): array
  {
    return $this->permissions;
  }

  public function settings(?PluginSettingsDefinition $settings): self
  {
    $this->settings = $settings;

    return $this;
  }

  public function settingsDefinition(): ?PluginSettingsDefinition
  {
    return $this->settings;
  }

  public function adminRoutes(string|callable $routes): self
  {
    if (is_string($routes) && trim($routes) === '') {
      throw new PluginException("Plugin [{$this->handle}] admin route file cannot be empty.");
    }

    $this->adminRoutes[] = is_string($routes) ? trim($routes) : $routes;

    return $this;
  }

  /**
   * @return array<int, string|callable>
   */
  public function adminRouteDefinitions(): array
  {
    return $this->adminRoutes;
  }

  /**
   * Register a plugin-owned internal API route file (or callable). These are
   * mounted under the CMS internal API group (`/webadmin/api`, token auth) so a
   * plugin can expose the same capabilities to AI agents that its admin panel
   * exposes to humans, without the CMS hardcoding the plugin's endpoints.
   */
  public function apiRoutes(string|callable $routes): self
  {
    if (is_string($routes) && trim($routes) === '') {
      throw new PluginException("Plugin [{$this->handle}] api route file cannot be empty.");
    }

    $this->apiRoutes[] = is_string($routes) ? trim($routes) : $routes;

    return $this;
  }

  /**
   * @return array<int, string|callable>
   */
  public function apiRouteDefinitions(): array
  {
    return $this->apiRoutes;
  }

  /**
   * Register a plugin-owned public route file (or callable). These are mounted
   * under `/plugins/{handle}` on the public site so a plugin can own a visitor
   * surface — a form submit, a JSON lookup a block's script calls — without the
   * CMS hardcoding the endpoint the way the commerce bridge is hardcoded today.
   *
   * The prefix is a reserved first segment, so plugin endpoints never shadow a
   * page slug, and the registrar forces `web`, `install.required` and a rate
   * limiter onto the group. CSRF stays on: these are browser forms, not the
   * bearer-token clients `apiRoutes()` serves.
   */
  public function publicRoutes(string|callable $routes): self
  {
    if (is_string($routes) && trim($routes) === '') {
      throw new PluginException("Plugin [{$this->handle}] public route file cannot be empty.");
    }

    $this->publicRoutes[] = is_string($routes) ? trim($routes) : $routes;

    return $this;
  }

  /**
   * @return array<int, string|callable>
   */
  public function publicRouteDefinitions(): array
  {
    return $this->publicRoutes;
  }

  /**
   * Describe the plugin's internal API to the discovery endpoint so AI agents
   * can self-discover plugin-owned endpoints. Recognized keys:
   *   - resources: array<string, string> name => path (merged into `_links`)
   *   - openapi_paths: array<string, mixed> OpenAPI path items (merged into the schema)
   *   - guidance: list<string> usage hints (appended to recommended_next_steps)
   * Only enabled plugins contribute, so discovery reflects enabled state.
   *
   * @param  array<string, mixed>  $discovery
   */
  public function apiDiscovery(array $discovery): self
  {
    $this->apiDiscovery = $discovery;

    return $this;
  }

  /**
   * @return array<string, mixed>
   */
  public function apiDiscoveryDefinition(): array
  {
    return $this->apiDiscovery;
  }

  /**
   * Declare the internal API capabilities this plugin's endpoints require, so a
   * shared CMS API token can be granted them without the CMS core hardcoding
   * plugin capabilities. Only enabled plugins contribute. Shape:
   *   - capabilities: array<string, string> name => human label
   *   - group: array{key: string, label: string, description?: string} optional
   *     token-UI preset grouping for these capabilities
   *
   * @param  array<string, mixed>  $capabilities
   */
  public function apiCapabilities(array $capabilities): self
  {
    $this->apiCapabilities = $capabilities;

    return $this;
  }

  /**
   * @return array<string, mixed>
   */
  public function apiCapabilityDefinition(): array
  {
    return $this->apiCapabilities;
  }

  /**
   * @param  array<int, class-string>  $commands
   */
  public function commands(array $commands): self
  {
    $cleanCommands = [];

    foreach ($commands as $command) {
      if (! is_string($command) || trim($command) === '') {
        throw new PluginException("Plugin [{$this->handle}] commands must be class-string values.");
      }

      $cleanCommands[] = trim($command);
    }

    $this->commands = array_values(array_unique($cleanCommands));

    return $this;
  }

  /**
   * @return array<int, class-string>
   */
  public function commandClasses(): array
  {
    return $this->commands;
  }

  /**
   * @param  array<int, string>  $paths
   */
  public function migrations(array $paths): self
  {
    $this->migrations = array_values(array_filter(array_map(
      fn (string $path): string => trim($path),
      $paths
    )));

    return $this;
  }

  /**
   * @return array<int, string>
   */
  public function migrationPaths(): array
  {
    return $this->migrations;
  }

  public function health(callable|string|null $reporter): self
  {
    if (is_string($reporter) && trim($reporter) === '') {
      $reporter = null;
    }

    $this->healthReporter = is_string($reporter) ? trim($reporter) : $reporter;

    return $this;
  }

  /**
   * @return callable|string|null
   */
  public function healthReporter(): mixed
  {
    return $this->healthReporter;
  }

  /**
   * @param  array<int, PluginDashboardWidget>  $widgets
   */
  public function dashboardWidgets(array $widgets): self
  {
    $indexed = [];

    foreach ($widgets as $widget) {
      if (! $widget instanceof PluginDashboardWidget) {
        throw new PluginException('Plugin dashboard widgets must be PluginDashboardWidget instances.');
      }

      if (! str_starts_with($widget->key(), $this->handle.'.')) {
        throw PluginException::invalidExtensionOwnership($this->handle, $widget->key());
      }

      if ($widget->permissionName() !== null && ! str_starts_with($widget->permissionName(), $this->handle.'.')) {
        throw PluginException::invalidPermissionPrefix($this->handle, $widget->permissionName());
      }

      if (isset($indexed[$widget->key()])) {
        throw PluginException::duplicateExtensionKey($widget->key());
      }

      $indexed[$widget->key()] = $widget->forPlugin($this->handle);
    }

    $this->dashboardWidgets = $indexed;

    return $this;
  }

  /**
   * @return array<string, PluginDashboardWidget>
   */
  public function dashboardWidgetDefinitions(): array
  {
    return array_map(fn (PluginDashboardWidget $widget): PluginDashboardWidget => clone $widget, $this->dashboardWidgets);
  }

  /**
   * @param  array<int, PluginSystemCard>  $cards
   */
  public function systemCards(array $cards): self
  {
    $indexed = [];

    foreach ($cards as $card) {
      if (! $card instanceof PluginSystemCard) {
        throw new PluginException('Plugin system cards must be PluginSystemCard instances.');
      }

      if (! str_starts_with($card->key(), $this->handle.'.')) {
        throw PluginException::invalidExtensionOwnership($this->handle, $card->key());
      }

      if ($card->permissionName() !== null && ! str_starts_with($card->permissionName(), $this->handle.'.')) {
        throw PluginException::invalidPermissionPrefix($this->handle, $card->permissionName());
      }

      if (isset($indexed[$card->key()])) {
        throw PluginException::duplicateExtensionKey($card->key());
      }

      $indexed[$card->key()] = $card->forPlugin($this->handle);
    }

    $this->systemCards = $indexed;

    return $this;
  }

  /**
   * @return array<string, PluginSystemCard>
   */
  public function systemCardDefinitions(): array
  {
    return array_map(fn (PluginSystemCard $card): PluginSystemCard => clone $card, $this->systemCards);
  }

  /**
   * @param  array<int, PluginBlockTypeDefinition>  $blockTypes
   */
  public function blockTypes(array $blockTypes): self
  {
    $indexed = [];

    foreach ($blockTypes as $blockType) {
      if (! $blockType instanceof PluginBlockTypeDefinition) {
        throw new PluginException('Plugin block types must be PluginBlockTypeDefinition instances.');
      }

      $this->assertPluginBlockOwnership($blockType->handle());

      if (isset($indexed[$blockType->handle()])) {
        throw PluginException::duplicateBlockHandle($blockType->handle());
      }

      $indexed[$blockType->handle()] = $blockType->forPlugin($this->handle);
    }

    $this->blockTypes = $indexed;

    return $this;
  }

  /**
   * @return array<string, PluginBlockTypeDefinition>
   */
  public function blockTypeDefinitions(): array
  {
    return array_map(fn (PluginBlockTypeDefinition $blockType): PluginBlockTypeDefinition => clone $blockType, $this->blockTypes);
  }

  /**
   * @param  array<int, PluginBlockPackDefinition>  $blockPacks
   */
  public function blockPacks(array $blockPacks): self
  {
    $indexed = [];

    foreach ($blockPacks as $blockPack) {
      if (! $blockPack instanceof PluginBlockPackDefinition) {
        throw new PluginException('Plugin block packs must be PluginBlockPackDefinition instances.');
      }

      if ($blockPack->namespace() !== $this->handle && ! str_starts_with($blockPack->namespace(), $this->handle.'-')) {
        throw PluginException::invalidNamespace($blockPack->namespace());
      }

      if (isset($indexed[$blockPack->namespace()])) {
        throw PluginException::invalidNamespace($blockPack->namespace());
      }

      foreach ($blockPack->blockTypeDefinitions() as $blockType) {
        $this->assertPluginBlockOwnership($blockType->handle());
      }

      $indexed[$blockPack->namespace()] = $blockPack->forPlugin($this->handle);
    }

    $this->blockPacks = $indexed;

    return $this;
  }

  /**
   * @return array<string, PluginBlockPackDefinition>
   */
  public function blockPackDefinitions(): array
  {
    return array_map(fn (PluginBlockPackDefinition $blockPack): PluginBlockPackDefinition => clone $blockPack, $this->blockPacks);
  }

  /**
   * @param  array<int, PluginPublicAsset>  $assets
   */
  public function publicAssets(array $assets): self
  {
    $indexed = [];

    foreach ($assets as $asset) {
      if (! $asset instanceof PluginPublicAsset) {
        throw new PluginException('Plugin public assets must be PluginPublicAsset instances.');
      }

      if (! str_starts_with($asset->handle(), $this->handle.'.')) {
        throw PluginException::invalidExtensionOwnership($this->handle, $asset->handle());
      }

      if (isset($indexed[$asset->handle()])) {
        throw PluginException::duplicateAssetHandle($asset->handle());
      }

      $indexed[$asset->handle()] = $asset->forPlugin($this->handle);
    }

    $this->publicAssets = $indexed;

    return $this;
  }

  /**
   * @return array<string, PluginPublicAsset>
   */
  public function publicAssetDefinitions(): array
  {
    return array_map(fn (PluginPublicAsset $asset): PluginPublicAsset => clone $asset, $this->publicAssets);
  }

  /**
   * @return array<string, mixed>
   */
  public function toArray(bool $enabled = false): array
  {
    return [
      'handle' => $this->handle,
      'label' => $this->labelText(),
      'version' => $this->version,
      'provider' => $this->providerClass,
      'description' => $this->description,
      'required_cms_version' => $this->requiredCmsVersion,
      'source' => $this->source,
      'install_path' => $this->installPath,
      'settings_namespace' => $this->settingsNamespaceValue(),
      'database_prefix' => $this->databasePrefixValue(),
      'route_name_prefix' => $this->routeNamePrefix(),
      'admin_route_prefix' => $this->adminRoutePrefix(),
      'public_route_prefix' => $this->publicRoutePrefix(),
      'enabled' => $enabled,
      'menu_items_count' => count($this->menuItems),
      'permissions_count' => count($this->permissions),
      'admin_routes_count' => count($this->adminRoutes),
      'api_routes_count' => count($this->apiRoutes),
      'public_routes_count' => count($this->publicRoutes),
      'commands_count' => count($this->commands),
      'migrations_count' => count($this->migrations),
      'dashboard_widgets_count' => count($this->dashboardWidgets),
      'system_cards_count' => count($this->systemCards),
      'block_types_count' => count($this->blockTypes),
      'block_packs_count' => count($this->blockPacks),
      'public_assets_count' => count($this->publicAssets),
      'has_settings' => $this->settings !== null,
    ];
  }

  private function assertPluginBlockOwnership(string $blockHandle): void
  {
    if (! str_starts_with($blockHandle, $this->handle.'::')) {
      throw PluginException::invalidBlockOwnership($this->handle, $blockHandle);
    }
  }
}
