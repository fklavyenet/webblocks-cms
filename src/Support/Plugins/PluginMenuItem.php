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
