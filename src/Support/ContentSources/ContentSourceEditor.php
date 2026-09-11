<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Throwable;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;

class ContentSourceEditor
{
  public function __construct(private readonly ContentSourceRegistry $sources) {}

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
    );

    foreach ($this->sources->all() as $source) {
      $resolverClass = $source->resolverClass();

      if ($resolverClass === null) {
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
      default => [],
    };
  }

  public function supports(Block $block): bool
  {
    return $this->targets($block) !== [] && $this->sources->all() !== [];
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
}
