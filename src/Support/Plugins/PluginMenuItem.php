<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginMenuItem
{
  private string $label = '';

  private ?string $route = null;

  private ?string $icon = null;

  private ?string $permission = null;

  private ?string $group = null;

  private int $sort = 100;

  private function __construct(
    private readonly string $key,
  ) {
    if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $key)) {
      throw new PluginException("Plugin menu item key [{$key}] must be kebab-case.");
    }
  }

  public static function make(string $key): self
  {
    return new self($key);
  }

  public function key(): string
  {
    return $this->key;
  }

  public function label(string $label): self
  {
    $this->label = trim($label);

    return $this;
  }

  public function labelText(): string
  {
    return $this->label !== '' ? $this->label : $this->key;
  }

  public function route(string $route): self
  {
    $this->route = trim($route);

    return $this;
  }

  public function routeName(): ?string
  {
    return $this->route;
  }

  public function icon(string $icon): self
  {
    $this->icon = trim($icon);

    return $this;
  }

  public function iconClass(): ?string
  {
    return $this->icon;
  }

  public function permission(string $permission): self
  {
    $this->permission = trim($permission);

    return $this;
  }

  public function permissionName(): ?string
  {
    return $this->permission;
  }

  /**
   * Which sidebar section this item renders under.
   *
   * This is an exact-match label, not a picklist: the admin layout gives every
   * distinct string its own heading, and only merges items whose group string
   * is byte-for-byte (slug-insensitively) identical. Passing one of the
   * documented shared buckets ("System", "Tools", "Integrations") deliberately
   * shares that heading with whatever else is already in it; passing anything
   * else — including, and normally, your own plugin's name — gets your plugin
   * a dedicated section with no core-side registration required.
   *
   * Two unrelated plugins that both reach for the same generic-sounding label
   * (e.g. "Content") will silently share one heading, because that is exactly
   * what identical strings mean here. If your plugin is its own product
   * surface rather than a small utility that belongs in a shared bucket, pass
   * its name, not a shared bucket's.
   */
  public function group(string $group): self
  {
    $this->group = trim($group);

    return $this;
  }

  public function groupName(): ?string
  {
    return $this->group;
  }

  public function sort(int $sort): self
  {
    $this->sort = $sort;

    return $this;
  }

  public function sortOrder(): int
  {
    return $this->sort;
  }

  /**
   * @return array{key: string, label: string, route: ?string, icon: ?string, permission: ?string, group: ?string, sort: int}
   */
  public function toArray(): array
  {
    return [
      'key' => $this->key,
      'label' => $this->labelText(),
      'route' => $this->route,
      'icon' => $this->icon,
      'permission' => $this->permission,
      'group' => $this->group,
      'sort' => $this->sort,
    ];
  }
}
