<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionQuery;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;
use WebBlocks\Cms\Support\ContentSources\ContentSourceDefinition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceEditor;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRegistry;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRuntime;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\QueryableContentCollectionSourceResolver;

class InternalContentSourceController extends Controller
{
  public function __construct(
    private readonly ContentSourceRegistry $sources,
    private readonly ContentSourceRuntime $runtime,
    private readonly ContentSourceEditor $editor,
  ) {}

  public function index(Request $request): JsonResponse
  {
    $block = $this->block($request);

    if ($request->filled('block_id') && ! $block) {
      return $this->error('block_id', 'The selected block is unavailable in this API scope.');
    }

    $context = $this->context($request, $block);
    $items = collect($this->sources->all())
      ->filter(fn (ContentSourceDefinition $source): bool => $this->runtime->allows($source, $context))
      ->map(fn (ContentSourceDefinition $source): array => $this->source($source, $block, $context))
      ->values()
      ->all();

    return response()->json(['ok' => true, 'sources' => $items, 'warnings' => [], 'errors' => []]);
  }

  public function preview(Request $request, string $source): JsonResponse
  {
    $definition = $this->sources->find($source);
    $block = $this->block($request);

    if (! $definition || ($request->filled('block_id') && ! $block)) {
      return $this->error('source', 'The selected content source is unavailable in this API scope.', 404);
    }

    $context = $this->context($request, $block);

    if (! $this->runtime->allows($definition, $context)) {
      return $this->error('source', 'The selected content source is unavailable in this API scope.', 404);
    }

    try {
      $resolverClass = $definition->resolverClass();
      $resolver = $resolverClass ? app($resolverClass) : null;

      if ($definition->isCollection() && $resolver instanceof ContentCollectionSourceResolver) {
        $limit = min(max((int) $request->input('limit', 3), 1), 5);
        $settings = is_array($request->input('source_settings')) ? $request->input('source_settings') : [];
        $query = new ContentCollectionQuery(
          limit: $limit,
          filterField: $this->optionalString($request->input('filter_field')),
          filterValue: $this->optionalString($request->input('filter_value')),
          sortField: $this->optionalString($request->input('sort_field')),
          sortDirection: $request->input('sort_direction') === 'desc' ? 'desc' : 'asc',
          page: 1,
          perPage: $limit,
        );
        $resolved = $resolver instanceof QueryableContentCollectionSourceResolver
          ? $resolver->queryCollection($query, $settings, $context)->records
          : $resolver->resolveCollection($settings, $context);
        $records = collect($resolved)->filter(fn (mixed $record): bool => is_array($record))->take($limit)
          ->map(fn (array $record): array => $this->record($definition, $record))->values()->all();

        return response()->json(['ok' => true, 'source' => $source, 'records' => $records, 'warnings' => [], 'errors' => []]);
      }

      if (! $definition->isCollection() && $resolver instanceof ContentSourceResolver) {
        $recordKey = trim((string) $request->input('record'));

        if ($recordKey === '' || ! array_key_exists($recordKey, $resolver->options($context))) {
          return $this->error('record', 'Select a record returned by the content-sources endpoint.');
        }

        $record = $resolver->resolve($recordKey, $context);

        return response()->json(['ok' => true, 'source' => $source, 'record_key' => $recordKey, 'record' => is_array($record) ? $this->record($definition, $record) : null, 'warnings' => [], 'errors' => []]);
      }
    } catch (Throwable $exception) {
      report($exception);

      return $this->error('source', 'The content source preview failed.', 422);
    }

    return $this->error('source', 'The content source resolver is unavailable.', 422);
  }

  private function source(ContentSourceDefinition $source, ?Block $block, ContentSourceContext $context): array
  {
    $options = [];

    if (! $source->isCollection() && $source->resolverClass()) {
      try {
        $resolver = app($source->resolverClass());
        $options = $resolver instanceof ContentSourceResolver ? $resolver->options($context) : [];
      } catch (Throwable $exception) {
        report($exception);
      }
    }

    $targets = $block ? collect($this->editor->targets($block))->filter(function (array $types) use ($source): bool {
      return collect($source->fieldDefinitions())->contains(fn (array $field): bool => in_array($field['type'], $types, true));
    })->keys()->values()->all() : [];

    return [
      'handle' => $source->handle(),
      'plugin' => $source->pluginHandle(),
      'label' => $source->labelText(),
      'kind' => $source->isCollection() ? 'collection' : 'entity',
      'fields' => $source->fieldDefinitions(),
      'records' => $options,
      'compatible_binding_targets' => $source->isCollection() ? [] : $targets,
      'compatible_collection_container' => $source->isCollection() && $block ? $this->editor->supportsCollection($block) : null,
      'preview_url' => '/webadmin/api/content-sources/'.rawurlencode($source->handle()).'/preview',
    ];
  }

  private function block(Request $request): ?Block
  {
    if (! $request->filled('block_id')) {
      return null;
    }

    $block = Block::query()->with(['blockType', 'page.site', 'children.blockType'])->find((int) $request->input('block_id'));
    $allowed = $request->attributes->get('cms_api_allowed_site_ids');

    return $block && (! is_array($allowed) || in_array((int) $block->page?->site_id, array_map('intval', $allowed), true)) ? $block : null;
  }

  private function context(Request $request, ?Block $block): ContentSourceContext
  {
    return new ContentSourceContext($block?->renderSite(), $block?->renderPage(), trim((string) $request->input('locale', $block?->renderLocaleCode() ?? 'en')), true, auth()->user());
  }

  private function record(ContentSourceDefinition $source, array $record): array
  {
    return collect($source->fieldDefinitions())->mapWithKeys(fn (array $definition, string $field): array => [$field => data_get($record, $field)])->all();
  }

  private function optionalString(mixed $value): ?string
  {
    $value = trim((string) $value);

    return $value !== '' ? $value : null;
  }

  private function error(string $path, string $message, int $status = 422): JsonResponse
  {
    return response()->json(['ok' => false, 'code' => 'invalid_content_source_preview', 'message' => $message, 'warnings' => [], 'errors' => [['path' => $path, 'message' => $message]]], $status);
  }
}
