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
      $this->assertLoopChange($block, is_array($collection) ? $collection : []);
      if ($collection === null || $collection === []) {
        unset($settings['content_collection']);
      } else {
        $settings['content_collection'] = $this->collection($block, $collection);
      }
    }

    return $settings;
  }

  public function assertPlacement(Block $block): void
  {
    if (! $block->exists || ((int) $block->getOriginal('parent_id') === (int) $block->parent_id
      && (int) $block->getOriginal('page_id') === (int) $block->page_id
      && (int) $block->getOriginal('block_type_id') === (int) $block->block_type_id)) {
      return;
    }
    $previousParent = Block::query()->find($block->getOriginal('parent_id'));
    if ($previousParent && (int) $previousParent->setting('content_collection.template_block_id') === (int) $block->id
      && ((int) $previousParent->id !== (int) $block->parent_id || (int) $previousParent->page_id !== (int) $block->page_id)) {
      throw new ContentSourceAuthoringException('parent_id', 'Reassign the repeated template before moving this block.', 'orphaned');
    }
    $walk = function (Block $node, int $depth = 0) use (&$walk): void {
      if ($depth > 32) {
        throw new ContentSourceAuthoringException('parent_id', 'Invalid block tree.');
      }
      foreach ((array) $node->setting('content_bindings', []) as $binding) {
        if (($binding['record'] ?? '') === '@item' && $this->editor->collectionSourceFor($node)?->handle() !== ($binding['source'] ?? '')) {
          throw new ContentSourceAuthoringException('parent_id', 'Moving this block would orphan current-record mappings.', 'orphaned');
        }
      }
      foreach ($node->children as $child) {
        $child->setRelation('parent', $node);
        $walk($child, $depth + 1);
      }
    };
    $walk($block);
  }

  public function assertLoopChange(Block $block, array $configuration): void
  {
    if (! $block->exists || ($block->setting('content_collection.source', '') === ($configuration['source'] ?? '')
      && (int) $block->setting('content_collection.template_block_id') === (int) ($configuration['template_block_id'] ?? 0))) {
      return;
    }
    $seen = [];
    $tree = function (Block $parent, int $depth = 0) use (&$tree, &$seen): void {
      if ($depth > 32 || isset($seen[$parent->id])) {
        throw new ContentSourceAuthoringException('content_collection', 'Invalid block tree.');
      }
      $seen[$parent->id] = true;
      $parent->loadMissing('children');
      foreach ($parent->children as $child) {
        $child->setRelation('parent', $parent);
        $tree($child, $depth + 1);
      }
    };
    $tree($block);
    $original = $block->decodedSettings();
    $settings = $original;
    $settings['content_collection'] = $configuration;
    $block->settings = $settings;
    try {
      $check = function (Block $parent) use (&$check): void {
        foreach ($parent->children as $child) {
          foreach ((array) $child->setting('content_bindings', []) as $binding) {
            if (($binding['record'] ?? '') === '@item'
              && $this->editor->collectionSourceFor($child)?->handle() !== ($binding['source'] ?? '')) {
              throw new ContentSourceAuthoringException('content_collection.template_block_id', 'Changing this loop would orphan current-record mappings. Remove or reassign the affected mappings first.', 'orphaned');
            }
          }
          $check($child);
        }
      };
      $check($block);
    } finally {
      $block->settings = $original;
    }
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
        throw new ContentSourceAuthoringException('content_bindings.'.$target, 'Select an accessible source, record, and type-compatible field returned by the content-sources endpoint.', 'invalid_binding');
      }

      $normalized[$target] = array_replace((array) $block->setting('content_bindings.'.$target, []), compact('source', 'record', 'field'));
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

    if (! $this->editor->collectionSelectionIsAllowed($block, $sourceHandle) || ! $this->runtime->allows($source, $context)) {
      throw new ContentSourceAuthoringException('content_collection.source', 'Select an accessible collection returned by the content-sources endpoint.');
    }

    $templateId = (int) ($input['template_block_id'] ?? 0);
    $template = $block->children->first(fn (Block $child): bool => (int) $child->getKey() === $templateId);

    if (! $template instanceof Block || ! $this->editor->collectionTemplateIsAllowed($block, (string) $template->typeSlug())) {
      throw new ContentSourceAuthoringException('content_collection.template_block_id', 'Select a compatible direct child returned by the block contract.', 'invalid_template');
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
