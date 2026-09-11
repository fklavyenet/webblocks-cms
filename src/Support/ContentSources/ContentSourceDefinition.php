<?php

namespace WebBlocks\Cms\Support\ContentSources;

use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
use WebBlocks\Cms\Support\Plugins\PluginException;

class ContentSourceDefinition
{
  private string $pluginHandle = '';

  private string $label = '';

  /** @var class-string<ContentSourceResolver>|null */
  private ?string $resolver = null;

  /** @var array<string, array{type: string, label: string}> */
  private array $fields = [];

  private function __construct(private readonly string $handle)
  {
    if (! self::isValidHandle($handle)) {
      throw new PluginException("Content source handle [{$handle}] must be namespaced like plugin-handle::source-name.");
    }
  }

  public static function entity(string $handle): self
  {
    return new self($handle);
  }

  public static function isValidHandle(string $handle): bool
  {
    return preg_match('/^[a-z0-9][a-z0-9-]*::[a-z0-9][a-z0-9-]*$/', $handle) === 1;
  }

  public function forPlugin(string $pluginHandle): self
  {
    $this->pluginHandle = $pluginHandle;

    return $this;
  }

  public function handle(): string
  {
    return $this->handle;
  }

  public function pluginHandle(): string
  {
    return $this->pluginHandle;
  }

  public function label(string $label): self
  {
    $this->label = trim($label);

    return $this;
  }

  public function labelText(): string
  {
    return $this->label !== '' ? $this->label : $this->handle;
  }

  /** @param class-string<ContentSourceResolver> $resolver */
  public function resolver(string $resolver): self
  {
    if (! is_a($resolver, ContentSourceResolver::class, true)) {
      throw new PluginException("Content source resolver [{$resolver}] must implement ".ContentSourceResolver::class.'.');
    }

    $this->resolver = $resolver;

    return $this;
  }

  /** @return class-string<ContentSourceResolver>|null */
  public function resolverClass(): ?string
  {
    return $this->resolver;
  }

  /**
   * @param  array<string, string|array{type: string, label?: string}>  $fields
   */
  public function fields(array $fields): self
  {
    $normalized = [];

    foreach ($fields as $name => $definition) {
      if (! is_string($name) || preg_match('/^[a-z][a-z0-9_.]*$/', $name) !== 1) {
        continue;
      }

      $type = is_string($definition) ? $definition : ($definition['type'] ?? null);
      $label = is_array($definition) ? ($definition['label'] ?? $name) : $name;

      if (! is_string($type) || ! in_array($type, ['text', 'rich_text', 'url', 'media'], true)) {
        continue;
      }

      $normalized[$name] = ['type' => $type, 'label' => trim((string) $label) ?: $name];
    }

    $this->fields = $normalized;

    return $this;
  }

  /** @return array<string, array{type: string, label: string}> */
  public function fieldDefinitions(): array
  {
    return $this->fields;
  }
}
