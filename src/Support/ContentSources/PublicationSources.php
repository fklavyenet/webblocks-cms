<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentPublicationSource;

final class PublicationSources
{
  public function __construct(private readonly ContentSourceRegistry $registry, private readonly ContentSourceRuntime $runtime) {}

  public function options(Site $site, ?Authenticatable $actor): array
  {
    $options = [];
    foreach ($this->registry->collections() as $source) {
      if ($configuration = $this->find($source->handle(), $site, $actor)) {
        $options[$source->handle()] = $configuration;
      }
    }

    return $options;
  }

  public function find(string $handle, Site $site, ?Authenticatable $actor, ?Page $page = null, bool $preview = true): ?array
  {
    $source = $this->registry->find($handle);
    $publication = $source?->publication();
    $entity = $publication ? $this->registry->find($publication['entity_source']) : null;
    if (! $source?->isCollection() || ! $source->contract() || ! $publication || ! $entity || $entity->isCollection() || ! $entity->contract()
      || $entity->resolverClass() === null || $entity->pluginHandle() !== $source->pluginHandle() || ! is_a((string) $source->resolverClass(), ContentPublicationSource::class, true)) {
      return null;
    }
    if ($source->contract()['record_key'] !== $entity->contract()['record_key']) {
      return null;
    }
    foreach (['slug_field' => ['text'], 'title_field' => ['text'], 'description_field' => ['text', 'rich_text'], 'image_field' => ['url', 'media']] as $key => $types) {
      $field = $publication[$key];
      if ($field !== null && ! in_array($entity->fieldDefinitions()[$field]['type'] ?? null, $types, true)) {
        return null;
      }
    }
    foreach (ContentSourceContract::languages() as $locale) {
      $context = new ContentSourceContext($site, $page, $locale, $preview, $actor);
      if (! $this->runtime->allows($source, $context) || ! $this->runtime->allows($entity, $context)) {
        return null;
      }
    }

    return compact('source', 'entity', 'publication');
  }
}
