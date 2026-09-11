<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Support\Collection;
use Throwable;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\QueryableContentCollectionSourceResolver;

class ContentCollectionRenderer
{
  public function __construct(
    private readonly ContentSourceRegistry $sources,
    private readonly ContentSourceRuntime $runtime,
    private readonly ContentSourceEditor $editor,
  ) {}

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

    if (! $this->editor->supportsCollection($container)
      || $source?->isCollection() !== true
      || ! $template instanceof Block
      || ! $this->editor->collectionTemplateIsAllowed($container, (string) $template->typeSlug())
      || $resolverClass === null) {
      return $children;
    }

    try {
      $resolver = app($resolverClass);

      if (! $resolver instanceof ContentCollectionSourceResolver) {
        return $children;
      }

      $limit = min(max((int) ($configuration['limit'] ?? 12), 1), 50);
      $paginate = ($configuration['paginate'] ?? false) === true && $container->typeSlug() !== 'slider';
      $perPage = $paginate ? min(max((int) ($configuration['per_page'] ?? 12), 1), 50) : null;
      $pageParameter = 'wb_collection_'.$container->getKey().'_page';
      $page = $paginate ? max((int) request()->query($pageParameter, 1), 1) : 1;
      $query = new ContentCollectionQuery(
        limit: $limit,
        filterField: ($value = trim((string) ($configuration['filter_field'] ?? ''))) !== '' ? $value : null,
        filterValue: ($value = trim((string) ($configuration['filter_value'] ?? ''))) !== '' ? $value : null,
        sortField: ($value = trim((string) ($configuration['sort_field'] ?? ''))) !== '' ? $value : null,
        sortDirection: ($configuration['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        page: $page,
        perPage: $perPage,
      );
      $context = new ContentSourceContext(
        site: $container->renderSite(),
        page: $container->renderPage(),
        locale: $container->renderLocaleCode(),
        preview: (bool) $container->getAttribute('render_preview'),
        actor: auth()->user(),
      );

      if (! $this->runtime->allows($source, $context)) {
        return $children;
      }

      $settings = is_array($configuration['source_settings'] ?? null) ? $configuration['source_settings'] : [];

      if ($resolver instanceof QueryableContentCollectionSourceResolver) {
        $result = $this->runtime->remember(
          $source,
          $context,
          ['query' => (array) $query, 'settings' => $settings],
          fn () => $resolver->queryCollection($query, $settings, $context),
        );

        if (! $result instanceof ContentCollectionResult) {
          return $children;
        }
        $records = collect($result->records)->filter(fn (mixed $record): bool => is_array($record))->values();
        $this->setPagination($container, $pageParameter, $result->currentPage, $result->perPage, $result->total);

        return $this->replaceTemplate($children, $template, $records, $container, $configuration);
      }

      $resolved = $this->runtime->remember(
        $source,
        $context,
        ['settings' => $settings],
        fn () => collect($resolver->resolveCollection($settings, $context))->filter(fn (mixed $record): bool => is_array($record))->all(),
      );
      $records = collect($resolved);

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

      if ($paginate && $perPage !== null) {
        $lastPage = max((int) ceil($records->count() / $perPage), 1);
        $currentPage = min($page, $lastPage);
        $this->setPagination($container, $pageParameter, $currentPage, $perPage, $records->count());
        $records = $records->forPage($currentPage, $perPage)->values();
      } else {
        $container->setAttribute('content_source_pagination', null);
      }
    } catch (Throwable $exception) {
      report($exception);

      return ($configuration['error_behavior'] ?? 'keep_template') === 'hide_template'
        ? $this->replaceTemplate($children, $template, collect(), $container, [...$configuration, 'empty_behavior' => 'hide_template'])
        : $children;
    }

    return $this->replaceTemplate($children, $template, $records, $container, $configuration);
  }

  private function replaceTemplate(Collection $children, Block $template, Collection $records, Block $container, array $configuration): Collection
  {
    if ($records->isEmpty() && ($configuration['empty_behavior'] ?? 'hide_template') === 'keep_template') {
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

  private function setPagination(Block $container, string $parameter, int $page, int $perPage, int $total): void
  {
    $lastPage = max((int) ceil($total / max($perPage, 1)), 1);
    $page = min(max($page, 1), $lastPage);
    $container->setAttribute('content_source_pagination', [
      'current_page' => $page,
      'last_page' => $lastPage,
      'previous_url' => $page > 1 ? request()->fullUrlWithQuery([$parameter => $page - 1]) : null,
      'next_url' => $page < $lastPage ? request()->fullUrlWithQuery([$parameter => $page + 1]) : null,
    ]);
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
