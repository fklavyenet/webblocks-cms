<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Throwable;
use WebBlocks\Cms\Models\Block;

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
      $this->runtime->previewIssue('invalid');

      return $children;
    }

    try {
      $result = app(ContentCollectionLoader::class)->load($container, $configuration, (bool) $container->getAttribute('render_preview'));
      $records = collect($result->records);
      if (($configuration['paginate'] ?? false) === true && $container->typeSlug() !== 'slider') {
        $this->setPagination($container, 'wb_collection_'.$container->getKey().'_page', $result->currentPage, $result->perPage, $result->total);
      } else {
        $container->setAttribute('content_source_pagination', null);
      }
    } catch (AuthorizationException) {
      return $children;
    } catch (Throwable $exception) {
      $this->runtime->previewIssue('unavailable');
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
