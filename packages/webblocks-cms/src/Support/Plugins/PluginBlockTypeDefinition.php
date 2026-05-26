<?php

namespace WebBlocks\Cms\Support\Plugins;

use WebBlocks\Cms\Support\Plugins\Contracts\PluginBlockExtension;

class PluginBlockTypeDefinition implements PluginBlockExtension
{
  private string $pluginHandle = '';

  private string $label = '';

  private ?string $description = null;

  private ?string $adminView = null;

  private ?string $publicView = null;

  /**
   * @param  array<string, mixed>  $metadata
   */
  private function __construct(
    private readonly string $handle,
    private array $metadata = [],
  ) {
    if (! self::isValidHandle($handle)) {
      throw PluginException::invalidBlockHandle($handle);
    }
  }

  /**
   * Plugin block handles are intentionally namespaced and cannot match core
   * block slugs such as "hero" or "gallery".
   */
  public static function isValidHandle(string $handle): bool
  {
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]*(?:-[a-z0-9]+)*::[a-z0-9][a-z0-9-]*(?:-[a-z0-9]+)*$/', $handle);
  }

  public static function make(string $handle): self
  {
    return new self($handle);
  }

  public function forPlugin(string $pluginHandle): self
  {
    $this->pluginHandle = $pluginHandle;

    return $this;
  }

  public function pluginHandle(): string
  {
    return $this->pluginHandle;
  }

  public function handle(): string
  {
    return $this->handle;
  }

  public function label(string $label): self
  {
    $this->label = trim($label);

    if ($this->label === '') {
      throw new PluginException("Plugin block [{$this->handle}] must define a label.");
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

  public function adminView(?string $view): self
  {
    $view = is_string($view) ? trim($view) : null;
    $this->adminView = $view !== '' ? $view : null;

    return $this;
  }

  public function adminViewName(): ?string
  {
    return $this->adminView;
  }

  public function publicView(?string $view): self
  {
    $view = is_string($view) ? trim($view) : null;
    $this->publicView = $view !== '' ? $view : null;

    return $this;
  }

  public function publicViewName(): ?string
  {
    return $this->publicView;
  }

  /**
   * @param  array<string, mixed>  $metadata
   */
  public function metadata(array $metadata): self
  {
    $this->metadata = $metadata;

    return $this;
  }

  /**
   * @return array<string, mixed>
   */
  public function metadataValues(): array
  {
    return $this->metadata;
  }
}
