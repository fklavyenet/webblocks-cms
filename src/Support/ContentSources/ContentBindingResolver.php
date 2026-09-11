<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Throwable;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;

class ContentBindingResolver
{
  public function __construct(
    private readonly ContentSourceRegistry $sources,
    private readonly ContentSourceRuntime $runtime,
  ) {}

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

    if ($source === null || $recordKey === '' || ! isset($source->fieldDefinitions()[$sourceField])) {
      return $binding['fallback'] ?? $fallback;
    }

    if ($recordKey === '@item') {
      $item = $block->getAttribute('content_source_item');
      $value = is_array($item) ? data_get($item, $sourceField) : null;

      return $value !== null && $value !== '' ? $value : ($binding['fallback'] ?? $fallback);
    }

    if ($resolverClass === null) {
      return $binding['fallback'] ?? $fallback;
    }

    $context = new ContentSourceContext(
      site: $block->renderSite(),
      page: $block->renderPage(),
      locale: $block->renderLocaleCode(),
      preview: (bool) $block->getAttribute('render_preview'),
      actor: auth()->user(),
    );

    if (! $this->runtime->allows($source, $context)) {
      return $binding['fallback'] ?? $fallback;
    }

    try {
      $resolver = app($resolverClass);

      if (! $resolver instanceof ContentSourceResolver) {
        return $binding['fallback'] ?? $fallback;
      }

      $record = $this->runtime->remember(
        $source,
        $context,
        ['record' => $recordKey],
        fn () => $resolver->resolve($recordKey, $context),
      );
      $value = is_array($record) ? data_get($record, $sourceField) : null;

      return $value !== null && $value !== '' ? $value : ($binding['fallback'] ?? $fallback);
    } catch (Throwable $exception) {
      report($exception);

      return $binding['fallback'] ?? $fallback;
    }
  }
}
