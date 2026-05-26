<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginDefinition
{
  private string $label = '';

  private ?string $version = null;

  private ?string $providerClass = null;

  private ?string $description = null;

  private ?string $requiredCmsVersion = null;

  private ?PluginSettingsDefinition $settings = null;

  /** @var array<string, PluginMenuItem> */
  private array $menuItems = [];

  /** @var array<string, PluginPermission> */
  private array $permissions = [];

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
      'route_name_prefix' => $this->routeNamePrefix(),
      'admin_route_prefix' => $this->adminRoutePrefix(),
      'enabled' => $enabled,
      'menu_items_count' => count($this->menuItems),
      'permissions_count' => count($this->permissions),
    ];
  }
}
