<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Support\Plugins\Contracts\PluginAdminExtension;

class PluginDashboardWidget implements PluginAdminExtension
{
  private string $pluginHandle = '';

  private string $title = '';

  private ?string $description = null;

  private ?string $value = null;

  private ?string $url = null;

  private ?string $permission = null;

  private int $sortOrder = 100;

  private ?string $view = null;

  private mixed $dataResolver = null;

  private array $viewData = [];

  private bool $summary = false;

  protected function __construct(
    private readonly string $key,
  ) {
    if (! self::isValidKey($key)) {
      throw PluginException::invalidExtensionKey($key);
    }
  }

  public static function make(string $key): static
  {
    return new static($key);
  }

  public function view(string $view): self
  {
    $this->view = $view;

    return $this;
  }

  public function viewName(): ?string
  {
    return $this->view;
  }

  public function resolveUsing(callable|string $resolver): self
  {
    $this->dataResolver = $resolver;

    return $this;
  }

  public function resolve(?Authenticatable $user): ?self
  {
    $resolved = clone $this;
    if ($this->dataResolver !== null) {
      $resolver = is_string($this->dataResolver) && ! is_callable($this->dataResolver) ? app($this->dataResolver) : $this->dataResolver;
      $data = $resolver($user);
      if ($data === null) {
        return null;
      }
      if (! is_array($data)) {
        throw new PluginException('Plugin admin view data must be an array or null.');
      }
      $resolved->viewData = $data;
    }

    return $resolved;
  }

  public function viewData(): array
  {
    return $this->viewData;
  }

  public function summary(bool $summary = true): self
  {
    $this->summary = $summary;

    return $this;
  }

  public function isSummary(): bool
  {
    return $this->summary;
  }

  public static function isValidKey(string $key): bool
  {
    return (bool) preg_match('/^[a-z0-9][a-z0-9-]*(?:\.[a-z0-9][a-z0-9-]*)+$/', $key);
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
      throw new PluginException("Plugin dashboard widget [{$this->key}] must define a title.");
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

  public function value(string|int|null $value): self
  {
    $value = is_string($value) ? trim($value) : $value;
    $this->value = $value === null || $value === '' ? null : (string) $value;

    return $this;
  }

  public function valueText(): ?string
  {
    return $this->value;
  }

  public function url(?string $url): self
  {
    $url = is_string($url) ? trim($url) : null;
    $this->url = $url !== '' ? $url : null;

    return $this;
  }

  public function urlValue(): ?string
  {
    return $this->url;
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
