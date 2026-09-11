<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Throwable;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\QueryableContentCollectionSourceResolver;

class ContentSourceEditor
{
  public function __construct(
    private readonly ContentSourceRegistry $sources,
    private readonly ContentSourceRuntime $runtime,
  ) {}

  /**
   * @return array<int, array{value: string, label: string}>
   */
  public function choices(Block $block, array $acceptedTypes): array
  {
    $choices = [];
    $context = new ContentSourceContext(
      site: $block->page?->site,
      page: $block->page,
      locale: $block->renderLocaleCode(),
      preview: true,
      actor: auth()->user(),
    );

    $collectionSource = $this->collectionSourceFor($block);

    if ($collectionSource instanceof ContentSourceDefinition) {
      foreach ($collectionSource->fieldDefinitions() as $field => $definition) {
        if (in_array($definition['type'], $acceptedTypes, true)) {
          $choices[] = [
            'value' => implode('|', [$collectionSource->handle(), '@item', $field]),
            'label' => $collectionSource->labelText().' / '.__('webblocks-cms::admin.block_form.content_source_current_item').' / '.$definition['label'],
          ];
        }
      }
    }

    foreach ($this->sources->all() as $source) {
      if ($source->isCollection()) {
        continue;
      }
      $resolverClass = $source->resolverClass();

      if ($resolverClass === null) {
        continue;
      }

      if (! $this->runtime->allows($source, $context)) {
        continue;
      }

      try {
        $resolver = app($resolverClass);
        $records = $resolver instanceof ContentSourceResolver ? $resolver->options($context) : [];
      } catch (Throwable $exception) {
        report($exception);
        $records = [];
      }

      foreach ($records as $recordKey => $recordLabel) {
        foreach ($source->fieldDefinitions() as $field => $definition) {
          if (! in_array($definition['type'], $acceptedTypes, true)) {
            continue;
          }

          $choices[] = [
            'value' => implode('|', [$source->handle(), $recordKey, $field]),
            'label' => $source->labelText().' / '.$recordLabel.' / '.$definition['label'],
          ];
        }
      }
    }

    return $choices;
  }

  /** @return array<string, list<string>> */
  public function targets(Block $block): array
  {
    return match ($block->typeSlug()) {
      'header' => ['title' => ['text']],
      'plain_text' => ['content' => ['text']],
      'rich-text' => ['content' => ['text', 'rich_text']],
      'image' => [
        'image_source' => ['media', 'url'],
        'title' => ['text'],
        'subtitle' => ['text'],
        'url' => ['url'],
      ],
      'button', 'button_link' => ['title' => ['text'], 'url' => ['url']],
      'link-list-item' => [
        'title' => ['text'],
        'subtitle' => ['text'],
        'content' => ['text', 'rich_text'],
        'url' => ['url'],
      ],
      default => [],
    };
  }

  public function supports(Block $block): bool
  {
    $hasBindings = collect((array) $block->setting('content_bindings', []))
      ->contains(fn (mixed $binding): bool => is_array($binding) && trim((string) ($binding['source'] ?? '')) !== '');

    if (in_array($block->typeSlug(), ['slider', 'grid', 'stack'], true)) {
      return $this->sources->collections() !== [] || is_array($block->setting('content_collection'));
    }

    if ($hasBindings) {
      return true;
    }

    foreach ($this->targets($block) as $acceptedTypes) {
      if ($this->choices($block, $acceptedTypes) !== []) {
        return true;
      }
    }

    return false;
  }

  public function selectionIsAllowed(Block $block, string $target, string $selection): bool
  {
    $acceptedTypes = $this->targets($block)[$target] ?? null;

    if ($acceptedTypes === null) {
      return false;
    }

    return collect($this->choices($block, $acceptedTypes))->contains(
      fn (array $choice): bool => hash_equals($choice['value'], $selection)
    );
  }

  public function selectedValue(Block $block, string $target): string
  {
    $binding = $block->setting('content_bindings.'.$target, []);

    if (! is_array($binding)) {
      return '';
    }

    return implode('|', [
      (string) ($binding['source'] ?? ''),
      (string) ($binding['record'] ?? ''),
      (string) ($binding['field'] ?? ''),
    ]);
  }

  /** @return array<string, string> */
  public function collectionChoices(): array
  {
    return array_map(
      fn (ContentSourceDefinition $source): string => $source->labelText(),
      $this->sources->collections(),
    );
  }

  /** @return array<string, string> */
  public function collectionFieldChoices(string $handle): array
  {
    $source = $this->sources->find($handle);

    if ($source?->isCollection() !== true) {
      return [];
    }

    return array_map(
      fn (array $definition): string => $definition['label'],
      $source->fieldDefinitions(),
    );
  }

  public function collectionFieldIsAllowed(string $handle, string $field): bool
  {
    return $field === '' || array_key_exists($field, $this->collectionFieldChoices($handle));
  }

  /** @return array<int, array<string, string>> */
  public function collectionPreview(Block $block, string $handle, int $limit = 3): array
  {
    $source = $this->sources->find($handle);
    $resolverClass = $source?->resolverClass();

    if ($source?->isCollection() !== true || $resolverClass === null) {
      return [];
    }

    $context = new ContentSourceContext(
      site: $block->page?->site,
      page: $block->page,
      locale: $block->renderLocaleCode(),
      preview: true,
      actor: auth()->user(),
    );

    if (! $this->runtime->allows($source, $context)) {
      return [];
    }

    try {
      $resolver = app($resolverClass);

      if (! $resolver instanceof ContentCollectionSourceResolver) {
        return [];
      }

      $previewLimit = min(max($limit, 1), 5);
      $records = $resolver instanceof QueryableContentCollectionSourceResolver
        ? collect($resolver->queryCollection(new ContentCollectionQuery(
          limit: $previewLimit,
          filterField: null,
          filterValue: null,
          sortField: null,
          sortDirection: 'asc',
          page: 1,
          perPage: $previewLimit,
        ), [], $context)->records)
        : collect($resolver->resolveCollection([], $context));

      return $records
        ->filter(fn (mixed $record): bool => is_array($record))
        ->take($previewLimit)
        ->map(function (array $record) use ($source): array {
          return collect($source->fieldDefinitions())->mapWithKeys(function (array $definition, string $field) use ($record): array {
            $value = data_get($record, $field);
            $text = is_scalar($value) ? strip_tags((string) $value) : '';

            return [$definition['label'] => mb_strimwidth($text, 0, 100, '…')];
          })->all();
        })
        ->values()
        ->all();
    } catch (Throwable $exception) {
      report($exception);

      return [];
    }
  }

  /** @return list<array{key: string, params: array<string, string>}> */
  public function warnings(Block $block): array
  {
    $warnings = [];

    foreach ((array) $block->setting('content_bindings', []) as $target => $binding) {
      if (! is_array($binding)) {
        continue;
      }

      $handle = (string) ($binding['source'] ?? '');
      $field = (string) ($binding['field'] ?? '');
      $source = $this->sources->find($handle);

      if ($source === null) {
        $warnings[] = ['key' => 'content_source_warning_missing_source', 'params' => ['target' => (string) $target, 'source' => $handle]];
      } elseif (! isset($source->fieldDefinitions()[$field])) {
        $warnings[] = ['key' => 'content_source_warning_missing_field', 'params' => ['target' => (string) $target, 'field' => $field, 'source' => $handle]];
      }
    }

    $collection = $block->setting('content_collection');

    if (is_array($collection)) {
      $handle = (string) ($collection['source'] ?? '');

      if ($this->sources->find($handle)?->isCollection() !== true) {
        $warnings[] = ['key' => 'content_source_warning_missing_collection', 'params' => ['source' => $handle]];
      }
    }

    return $warnings;
  }

  private function collectionSourceFor(Block $block): ?ContentSourceDefinition
  {
    $current = $block;

    for ($depth = 0; $depth < 8; $depth++) {
      if (in_array($current->typeSlug(), ['slider', 'grid', 'stack'], true)) {
        $handle = trim((string) $current->setting('content_collection.source', ''));

        return $this->sources->find($handle);
      }

      $parent = $current->relationLoaded('parent') ? $current->getRelation('parent') : $current->parent;

      if (! $parent instanceof Block) {
        return null;
      }

      $current = $parent;
    }

    return null;
  }
}
