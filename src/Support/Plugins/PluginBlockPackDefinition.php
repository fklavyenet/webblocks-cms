<?php

namespace WebBlocks\Cms\Support\Plugins;

class PluginBlockPackDefinition
{
  private string $pluginHandle = '';

  private string $label = '';

  private ?string $description = null;

  /** @var array<string, PluginBlockTypeDefinition> */
  private array $blockTypes = [];

  private function __construct(
    private readonly string $namespace,
  ) {
    if (! PluginDefinition::isValidHandle($namespace)) {
      throw PluginException::invalidNamespace($namespace);
    }
  }

  public static function make(string $namespace): self
  {
    return new self($namespace);
  }

  public function forPlugin(string $pluginHandle): self
  {
    $this->pluginHandle = $pluginHandle;

    foreach ($this->blockTypes as $handle => $blockType) {
      $this->blockTypes[$handle] = $blockType->forPlugin($pluginHandle);
    }

    return $this;
  }

  public function pluginHandle(): string
  {
    return $this->pluginHandle;
  }

  public function namespace(): string
  {
    return $this->namespace;
  }

  public function label(string $label): self
  {
    $this->label = trim($label);

    if ($this->label === '') {
      throw new PluginException("Plugin block pack [{$this->namespace}] must define a label.");
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

  /**
   * @param  array<int, PluginBlockTypeDefinition>  $blockTypes
   */
  public function blockTypes(array $blockTypes): self
  {
    $indexed = [];

    foreach ($blockTypes as $blockType) {
      if (! $blockType instanceof PluginBlockTypeDefinition) {
        throw new PluginException('Plugin block packs must contain PluginBlockTypeDefinition instances.');
      }

      if (isset($indexed[$blockType->handle()])) {
        throw PluginException::duplicateBlockHandle($blockType->handle());
      }

      $indexed[$blockType->handle()] = $blockType->forPlugin($this->pluginHandle);
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
}
