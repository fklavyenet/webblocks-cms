<?php

namespace WebBlocks\Cms\Support\ContentSources;

use WebBlocks\Cms\Models\Block;

class ContentSourceAuthoring
{
  public function __construct(
    private readonly ContentSourceRegistry $sources,
    private readonly ContentSourceEditor $editor,
    private readonly ContentSourceRuntime $runtime,
  ) {}

  /** @param array<string, mixed> $settings */
  public function merge(Block $block, array $settings, mixed $bindings, bool $hasBindings, mixed $collection, bool $hasCollection): array
  {
    if ($hasBindings) {
      if ($bindings === null || $bindings === []) {
        unset($settings['content_bindings']);
      } else {
        $settings['content_bindings'] = $this->bindings($block, $bindings);
      }
    }

    if ($hasCollection) {
      if ($collection === null || $collection === []) {
        unset($settings['content_collection']);
      } else {
        $settings['content_collection'] = $this->collection($block, $collection);
      }
    }

    return $settings;
  }

  private function bindings(Block $block, mixed $input): array
  {
    if (! is_array($input)) {
      throw new ContentSourceAuthoringException('content_bindings', 'Content bindings must be an object or null.');
    }

    $normalized = [];
    $targets = $this->editor->targets($block);

    foreach ($input as $target => $binding) {
      if (! is_string($target) || ! array_key_exists($target, $targets)) {
        throw new ContentSourceAuthoringException('content_bindings.'.(string) $target, 'This block field does not support a content source binding.');
      }

      if (! is_array($binding)) {
        throw new ContentSourceAuthoringException('content_bindings.'.$target, 'A content binding must be an object.');
      }

      $source = trim((string) ($binding['source'] ?? ''));
      $record = trim((string) ($binding['record'] ?? ''));
      $field = trim((string) ($binding['field'] ?? ''));
      $selection = implode('|', [$source, $record, $field]);

      if ($source === '' || $record === '' || $field === '' || ! $this->editor->selectionIsAllowed($block, $target, $selection)) {
        throw new ContentSourceAuthoringException('content_bindings.'.$target, 'Select an accessible source, record, and type-compatible field returned by the content-sources endpoint.');
      }

      $normalized[$target] = compact('source', 'record', 'field');
    }

    return $normalized;
  }

  private function collection(Block $block, mixed $input): array
  {
    if (! is_array($input)) {
      throw new ContentSourceAuthoringException('content_collection', 'Content collection must be an object or null.');
    }

    if (! $this->editor->supportsCollection($block)) {
      throw new ContentSourceAuthoringException('content_collection', 'This block contract does not support a content collection.');
    }

    $sourceHandle = trim((string) ($input['source'] ?? ''));
    $source = $this->sources->find($sourceHandle);
    $context = new ContentSourceContext($block->renderSite(), $block->renderPage(), $block->renderLocaleCode(), true, auth()->user());

    if ($source?->isCollection() !== true || ! $this->runtime->allows($source, $context)) {
      throw new ContentSourceAuthoringException('content_collection.source', 'Select an accessible collection returned by the content-sources endpoint.');
    }

    $templateId = (int) ($input['template_block_id'] ?? 0);
    $template = $block->children->first(fn (Block $child): bool => (int) $child->getKey() === $templateId);

    if (! $template instanceof Block || ! $this->editor->collectionTemplateIsAllowed($block, (string) $template->typeSlug())) {
      throw new ContentSourceAuthoringException('content_collection.template_block_id', 'Select a compatible direct child returned by the block contract.');
    }

    foreach (['filter_field', 'sort_field'] as $key) {
      $field = trim((string) ($input[$key] ?? ''));

      if (! $this->editor->collectionFieldIsAllowed($sourceHandle, $field)) {
        throw new ContentSourceAuthoringException('content_collection.'.$key, 'Select a field declared by this collection source.');
      }
    }

    if (isset($input['source_settings']) && ! is_array($input['source_settings'])) {
      throw new ContentSourceAuthoringException('content_collection.source_settings', 'Source settings must be an object.');
    }

    return [
      'source' => $sourceHandle,
      'template_block_id' => $templateId,
      'limit' => min(max((int) ($input['limit'] ?? 12), 1), 50),
      'filter_field' => trim((string) ($input['filter_field'] ?? '')),
      'filter_value' => mb_strimwidth(trim((string) ($input['filter_value'] ?? '')), 0, 255),
      'sort_field' => trim((string) ($input['sort_field'] ?? '')),
      'sort_direction' => ($input['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
      'paginate' => $block->typeSlug() !== 'slider' && (bool) ($input['paginate'] ?? false),
      'per_page' => min(max((int) ($input['per_page'] ?? 12), 1), 50),
      'empty_behavior' => ($input['empty_behavior'] ?? 'hide_template') === 'keep_template' ? 'keep_template' : 'hide_template',
      'error_behavior' => ($input['error_behavior'] ?? 'keep_template') === 'hide_template' ? 'hide_template' : 'keep_template',
      'source_settings' => $input['source_settings'] ?? [],
    ];
  }
}
