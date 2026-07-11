<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginSettingsDefinition
{
  private string $label = 'Settings';

  private ?string $description = null;

  public function __construct(
    public readonly ?string $routeName = null,
    public readonly ?string $view = null,
    public readonly ?string $schemaClass = null,
  ) {}

  public static function make(?string $routeName = null): self
  {
    return new self($routeName);
  }

  public function label(string $label): self
  {
    $this->label = trim($label);

    if ($this->label === '') {
      $this->label = 'Settings';
    }

    return $this;
  }

  public function labelText(): string
  {
    return $this->label;
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

  public function usesDefaultRoute(): bool
  {
    return $this->routeName === null;
  }

  /**
   * @return array{label: string, description: ?string, route_name: ?string, view: ?string, schema_class: ?string, uses_default_route: bool}
   */
  public function toArray(): array
  {
    return [
      'label' => $this->label,
      'description' => $this->description,
      'route_name' => $this->routeName,
      'view' => $this->view,
      'schema_class' => $this->schemaClass,
      'uses_default_route' => $this->usesDefaultRoute(),
    ];
  }
}
