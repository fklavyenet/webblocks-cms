<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Auth\Access\AuthorizationException;
use UnexpectedValueException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\QueryableContentCollectionSourceResolver;

/** Shared query semantics for public rendering and explicit authoring previews. */
final class ContentCollectionLoader
{
  public function __construct(private readonly ContentSourceRegistry $sources, private readonly ContentSourceRuntime $runtime) {}

  public function load(Block $block, array $configuration, bool $preview = false): ContentCollectionResult
  {
    $source = $this->sources->find((string) ($configuration['source'] ?? ''));
    if (! $source?->isCollection() || ! $source->resolverClass()) {
      throw new UnexpectedValueException('Source unavailable.');
    }
    $context = new ContentSourceContext($block->renderSite(), $block->renderPage(), $block->renderLocaleCode() ?? app()->getLocale(), $preview, auth()->user());
    if (! $this->runtime->allows($source, $context)) {
      throw new AuthorizationException;
    }
    $resolver = app($source->resolverClass());
    if (! $resolver instanceof ContentCollectionSourceResolver) {
      throw new UnexpectedValueException('Collection resolver unavailable.');
    }
    $limit = min(max((int) ($configuration['limit'] ?? 12), 1), 50);
    $paginate = ($configuration['paginate'] ?? false) === true && $block->typeSlug() !== 'slider';
    $perPage = $paginate ? min(max((int) ($configuration['per_page'] ?? 12), 1), 50) : null;
    $page = $paginate ? max((int) request()->query('wb_collection_'.$block->getKey().'_page', 1), 1) : 1;
    $query = new ContentCollectionQuery(
      limit: $limit,
      filterField: ($value = trim((string) ($configuration['filter_field'] ?? ''))) !== '' ? $value : null,
      filterValue: ($value = trim((string) ($configuration['filter_value'] ?? ''))) !== '' ? $value : null,
      sortField: ($value = trim((string) ($configuration['sort_field'] ?? ''))) !== '' ? $value : null,
      sortDirection: ($configuration['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
      page: $page,
      perPage: $perPage,
    );
    $settings = is_array($configuration['source_settings'] ?? null) ? $configuration['source_settings'] : [];
    if ($resolver instanceof QueryableContentCollectionSourceResolver) {
      $result = $this->runtime->remember($source, $context, ['query' => (array) $query, 'settings' => $settings], fn () => $resolver->queryCollection($query, $settings, $context));
      if (! $result instanceof ContentCollectionResult) {
        throw new UnexpectedValueException('Invalid collection result.');
      }

      return new ContentCollectionResult(ContentSourceContract::records($source, $result->records, $context), $result->total, $result->currentPage, $result->perPage);
    }
    $resolved = $this->runtime->remember($source, $context, ['settings' => $settings], fn () => collect($resolver->resolveCollection($settings, $context))->filter(fn ($record) => is_array($record))->all());
    $records = collect(ContentSourceContract::records($source, $resolved, $context));
    if ($query->filterField !== null && $query->filterValue !== null) {
      $records = $records->filter(fn ($record) => mb_strtolower(trim((string) data_get($record, $query->filterField))) === mb_strtolower($query->filterValue));
    }
    if ($query->sortField !== null) {
      $records = $records->sortBy(fn ($record) => data_get($record, $query->sortField), SORT_NATURAL | SORT_FLAG_CASE, $query->sortDirection === 'desc');
    }
    $records = $records->take($limit)->values();
    $total = $records->count();
    $page = $paginate ? min($page, max((int) ceil($total / $perPage), 1)) : 1;

    return new ContentCollectionResult(($paginate ? $records->forPage($page, $perPage) : $records)->values()->all(), $total, $page, $perPage ?? max($total, 1));
  }
}
