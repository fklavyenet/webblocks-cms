<?php

namespace WebBlocks\Cms\Support\ContentSources;

use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceAccessPolicy;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\QueryableContentCollectionSourceResolver;
use WebBlocks\Cms\Support\Plugins\PluginException;

class ContentSourceDefinition
{
  private string $pluginHandle = '';

  private string $label = '';

  /** @var class-string<ContentSourceResolver|ContentCollectionSourceResolver>|null */
  private ?string $resolver = null;

  /** @var array<string, array{type: string, label: string}> */
  private array $fields = [];

  /** @var class-string<ContentSourceAccessPolicy>|null */
  private ?string $accessPolicy = null;

  private int $cacheSeconds = 0;

  private ?array $standard = null;

  private function __construct(private readonly string $handle, private readonly string $kind)
  {
    if (! self::isValidHandle($handle)) {
      throw new PluginException("Content source handle [{$handle}] must be namespaced like plugin-handle::source-name.");
    }
  }

  public static function entity(string $handle): self
  {
    return new self($handle, 'entity');
  }

  public static function collection(string $handle): self
  {
    return new self($handle, 'collection');
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

  /** @param class-string<ContentSourceResolver|ContentCollectionSourceResolver> $resolver */
  public function resolver(string $resolver): self
  {
    $contract = $this->isCollection() ? ContentCollectionSourceResolver::class : ContentSourceResolver::class;

    if (! is_a($resolver, $contract, true)) {
      throw new PluginException("Content source resolver [{$resolver}] must implement {$contract}.");
    }

    $this->resolver = $resolver;

    return $this;
  }

  /** @return class-string<ContentSourceResolver|ContentCollectionSourceResolver>|null */
  public function resolverClass(): ?string
  {
    return $this->resolver;
  }

  public function isCollection(): bool
  {
    return $this->kind === 'collection';
  }

  /** @param class-string<ContentSourceAccessPolicy> $policy */
  public function accessPolicy(string $policy): self
  {
    if (! is_a($policy, ContentSourceAccessPolicy::class, true)) {
      throw new PluginException("Content source access policy [{$policy}] must implement ".ContentSourceAccessPolicy::class.'.');
    }

    $this->accessPolicy = $policy;

    return $this;
  }

  /** @return class-string<ContentSourceAccessPolicy>|null */
  public function accessPolicyClass(): ?string
  {
    return $this->accessPolicy;
  }

  public function cacheFor(int $seconds): self
  {
    $this->cacheSeconds = min(max($seconds, 0), 86400);

    return $this;
  }

  public function cacheSeconds(): int
  {
    return $this->cacheSeconds;
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

  /** Declare standard 1.0. Existing unversioned providers remain readable as legacy. */
  public function standard(array $locales, string $description, string $origin, string $recordKey): self
  {
    $this->standard = ContentSourceContract::definition($locales, $description, $origin, $recordKey);

    return $this;
  }

  public function capabilities(): array
  {
    return ['entity' => ! $this->isCollection(), 'collection' => $this->isCollection(),
      'filter_sort_pagination' => $this->isCollection(),
      'query_execution' => $this->isCollection() && $this->resolver !== null && is_subclass_of($this->resolver, QueryableContentCollectionSourceResolver::class) ? 'provider' : 'cms',
      'cache_seconds' => $this->cacheSeconds];
  }

  public function contract(): ?array
  {
    return $this->standard;
  }

  /** @return array<string, array{type: string, label: string}> */
  public function fieldDefinitions(): array
  {
    return $this->fields;
  }
}
