<?php

namespace WebBlocks\Cms\Support\Plugins;

use WebBlocks\Cms\Support\Plugins\Contracts\PluginAdminExtension;

class PluginSystemCard implements PluginAdminExtension
{
  private string $pluginHandle = '';

  private string $title = '';

  private ?string $description = null;

  private ?string $url = null;

  private ?string $linkLabel = null;

  private ?string $permission = null;

  private int $sortOrder = 100;

  private function __construct(
    private readonly string $key,
  ) {
    if (! PluginDashboardWidget::isValidKey($key)) {
      throw PluginException::invalidExtensionKey($key);
    }
  }

  public static function make(string $key): self
  {
    return new self($key);
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

  public function key(): string
  {
    return $this->key;
  }

  public function title(string $title): self
  {
    $this->title = trim($title);

    if ($this->title === '') {
      throw new PluginException("Plugin system card [{$this->key}] must define a title.");
    }

    return $this;
  }

  public function titleText(): string
  {
    return $this->title;
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

  public function url(?string $url, ?string $label = null): self
  {
    $url = is_string($url) ? trim($url) : null;
    $label = is_string($label) ? trim($label) : null;
    $this->url = $url !== '' ? $url : null;
    $this->linkLabel = $label !== '' ? $label : null;

    return $this;
  }

  public function urlValue(): ?string
  {
    return $this->url;
  }

  public function linkLabel(): ?string
  {
    return $this->linkLabel;
  }

  public function permission(?string $permission): self
  {
    $permission = is_string($permission) ? trim($permission) : null;
    $this->permission = $permission !== '' ? $permission : null;

    return $this;
  }

  public function permissionName(): ?string
  {
    return $this->permission;
  }

  public function sort(int $sortOrder): self
  {
    $this->sortOrder = $sortOrder;

    return $this;
  }

  public function sortOrder(): int
  {
    return $this->sortOrder;
  }
}
