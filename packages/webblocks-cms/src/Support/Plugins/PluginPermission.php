<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginPermission
{
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
   * @return array{name: string, label: string, description: ?string}
   */
  public function toArray(): array
  {
    return [
      'name' => $this->name,
      'label' => $this->labelText(),
      'description' => $this->description,
    ];
  }
}
