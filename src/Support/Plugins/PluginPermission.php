<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginPermission
{
  /** @var list<string> */
  private array $roles = ['super_admin'];

  private string $label = '';

  private ?string $description = null;

  private function __construct(
    private readonly string $name,
  ) {
    if (! preg_match('/^[a-z0-9][a-z0-9-]*(\.[a-z0-9][a-z0-9-]*)+$/', $name)) {
      throw new PluginException("Plugin permission [{$name}] must use handle-prefixed dot notation.");
    }
  }

  public static function make(string $name): self
  {
    return new self($name);
  }

  public function name(): string
  {
    return $this->name;
  }

  public function label(string $label): self
  {
    $this->label = trim($label);

    return $this;
  }

  public function labelText(): string
  {
    return $this->label !== '' ? $this->label : $this->name;
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

  /**
   * Declare which CMS roles receive this permission by default.
   *
   * Super admins always retain access to enabled plugin permissions. Plugins
   * must opt site-scoped roles in explicitly so an upgrade cannot silently
   * broaden an existing plugin's admin surface.
   *
   * @param  list<string>  $roles
   */
  public function roles(array $roles): self
  {
    $allowed = ['super_admin', 'site_admin', 'editor'];
    $roles = array_values(array_unique(array_filter(
      array_map(static fn (mixed $role): string => is_string($role) ? trim($role) : '', $roles),
      static fn (string $role): bool => in_array($role, $allowed, true),
    )));

    $this->roles = array_values(array_unique(['super_admin', ...$roles]));

    return $this;
  }

  /** @return list<string> */
  public function roleNames(): array
  {
    return $this->roles;
  }

  /**
   * @return array{name: string, label: string, description: ?string, roles: list<string>}
   */
  public function toArray(): array
  {
    return [
      'name' => $this->name,
      'label' => $this->labelText(),
      'description' => $this->description,
      'roles' => $this->roles,
    ];
  }
}
