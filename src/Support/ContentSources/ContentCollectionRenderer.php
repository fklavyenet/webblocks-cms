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
    return $this->children($slider)
      ->filter(fn (Block $child): bool => $child->typeSlug() === 'slide')
      ->values();
  }

  /** @return Collection<int, Block> */
  public function children(Block $container): Collection
  {
    $children = $container->children->values();
    $configuration = $container->setting('content_collection');

    if (! is_array($configuration)) {
      return $children;
    }

    $source = $this->sources->find(trim((string) ($configuration['source'] ?? '')));
    $templateId = (int) ($configuration['template_block_id'] ?? 0);
    $template = $children->first(fn (Block $child): bool => (int) $child->id === $templateId);
    $resolverClass = $source?->resolverClass();

    if ($source?->isCollection() !== true || ! $template instanceof Block || $resolverClass === null) {
      return $children;
    }

    try {
      $resolver = app($resolverClass);

      if (! $resolver instanceof ContentCollectionSourceResolver) {
        return $children;
      }

      $limit = min(max((int) ($configuration['limit'] ?? 12), 1), 50);
      $records = collect($resolver->resolveCollection(
        settings: is_array($configuration['source_settings'] ?? null) ? $configuration['source_settings'] : [],
        context: new ContentSourceContext(
          site: $container->renderSite(),
          page: $container->renderPage(),
          locale: $container->renderLocaleCode(),
          preview: (bool) $container->getAttribute('render_preview'),
        ),
      ))->filter(fn (mixed $record): bool => is_array($record));

      $filterField = trim((string) ($configuration['filter_field'] ?? ''));
      $filterValue = trim((string) ($configuration['filter_value'] ?? ''));

      if ($filterField !== '' && $filterValue !== '') {
        $records = $records->filter(
          fn (array $record): bool => mb_strtolower(trim((string) data_get($record, $filterField))) === mb_strtolower($filterValue)
        );
      }

      $sortField = trim((string) ($configuration['sort_field'] ?? ''));

      if ($sortField !== '') {
        $descending = ($configuration['sort_direction'] ?? 'asc') === 'desc';
        $records = $records->sortBy(
          fn (array $record): mixed => data_get($record, $sortField),
          SORT_NATURAL | SORT_FLAG_CASE,
          $descending,
        );
      }

      $records = $records->take($limit)->values();

      if (($configuration['paginate'] ?? false) === true && $container->typeSlug() !== 'slider') {
        $perPage = min(max((int) ($configuration['per_page'] ?? 12), 1), 50);
        $pageParameter = 'wb_collection_'.$container->getKey().'_page';
        $lastPage = max((int) ceil($records->count() / $perPage), 1);
        $currentPage = min(max((int) request()->query($pageParameter, 1), 1), $lastPage);
        $container->setAttribute('content_source_pagination', [
          'current_page' => $currentPage,
          'last_page' => $lastPage,
          'previous_url' => $currentPage > 1 ? request()->fullUrlWithQuery([$pageParameter => $currentPage - 1]) : null,
          'next_url' => $currentPage < $lastPage ? request()->fullUrlWithQuery([$pageParameter => $currentPage + 1]) : null,
        ]);
        $records = $records->forPage($currentPage, $perPage)->values();
      } else {
        $container->setAttribute('content_source_pagination', null);
      }
    } catch (Throwable $exception) {
      report($exception);

      return $children;
    }

    return $children->flatMap(function (Block $child) use ($template, $records, $container): array {
      if ((int) $child->id !== (int) $template->id) {
        return [$child];
      }

      return $records
        ->map(fn (array $record): Block => $this->cloneTreeForItem($template, $record, $container))
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
