<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Throwable;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;

class ContentBindingResolver
{
  public function __construct(private readonly ContentSourceRegistry $sources) {}

  public function value(Block $block, string $targetField, mixed $fallback = null): mixed
  {
    $binding = $block->setting('content_bindings.'.$targetField);

    if (! is_array($binding)) {
      return $fallback;
    }

    $source = $this->sources->find(trim((string) ($binding['source'] ?? '')));
    $recordKey = trim((string) ($binding['record'] ?? ''));
    $sourceField = trim((string) ($binding['field'] ?? ''));
    $resolverClass = $source?->resolverClass();

    if ($source === null || $recordKey === '' || ! isset($source->fieldDefinitions()[$sourceField]) || $resolverClass === null) {
      return $binding['fallback'] ?? $fallback;
    }

    try {
      $resolver = app($resolverClass);

      if (! $resolver instanceof ContentSourceResolver) {
        return $binding['fallback'] ?? $fallback;
      }

      $record = $resolver->resolve($recordKey, new ContentSourceContext(
        site: $block->renderSite(),
        page: $block->renderPage(),
        locale: $block->renderLocaleCode(),
        preview: (bool) $block->getAttribute('render_preview'),
      ));
      $value = is_array($record) ? data_get($record, $sourceField) : null;

      return $value !== null && $value !== '' ? $value : ($binding['fallback'] ?? $fallback);
    } catch (Throwable $exception) {
      report($exception);

      return $binding['fallback'] ?? $fallback;
    }
  }
}
