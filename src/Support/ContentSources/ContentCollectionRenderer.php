<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Support\Collection;
use Throwable;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;

class ContentCollectionRenderer
{
  public function __construct(private readonly ContentSourceRegistry $sources) {}

  /** @return Collection<int, Block> */
  public function sliderSlides(Block $slider): Collection
  {
    $slides = $slider->children
      ->filter(fn (Block $child): bool => $child->typeSlug() === 'slide')
      ->values();
    $configuration = $slider->setting('content_collection');

    if (! is_array($configuration)) {
      return $slides;
    }

    $source = $this->sources->find(trim((string) ($configuration['source'] ?? '')));
    $templateId = (int) ($configuration['template_block_id'] ?? 0);
    $template = $slides->first(fn (Block $slide): bool => (int) $slide->id === $templateId);
    $resolverClass = $source?->resolverClass();

    if ($source?->isCollection() !== true || ! $template instanceof Block || $resolverClass === null) {
      return $slides;
    }

    try {
      $resolver = app($resolverClass);

      if (! $resolver instanceof ContentCollectionSourceResolver) {
        return $slides;
      }

      $limit = min(max((int) ($configuration['limit'] ?? 12), 1), 50);
      $records = collect($resolver->resolveCollection(
        settings: is_array($configuration['source_settings'] ?? null) ? $configuration['source_settings'] : [],
        context: new ContentSourceContext(
          site: $slider->renderSite(),
          page: $slider->renderPage(),
          locale: $slider->renderLocaleCode(),
          preview: (bool) $slider->getAttribute('render_preview'),
        ),
      ))->filter(fn (mixed $record): bool => is_array($record))->take($limit)->values();
    } catch (Throwable $exception) {
      report($exception);

      return $slides;
    }

    return $slides->flatMap(function (Block $slide) use ($template, $records, $slider): array {
      if ((int) $slide->id !== (int) $template->id) {
        return [$slide];
      }

      return $records
        ->map(fn (array $record): Block => $this->cloneTreeForItem($template, $record, $slider))
        ->all();
    })->values();
  }

  private function cloneTreeForItem(Block $block, array $record, ?Block $parent = null): Block
  {
    $clone = clone $block;
    $clone->setAttribute('content_source_item', $record);

    if ($parent instanceof Block) {
      $clone->setRelation('parent', $parent);
    }

    $children = $block->relationLoaded('children') ? $block->getRelation('children') : collect();
    $clone->setRelation('children', $children
      ->map(fn (Block $child): Block => $this->cloneTreeForItem($child, $record, $clone))
      ->values());

    return $clone;
  }
}
