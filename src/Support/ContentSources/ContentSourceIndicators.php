<?php

namespace WebBlocks\Cms\Support\ContentSources;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

/** Uses local configuration only; opening a tree must never call a provider API. */
final class ContentSourceIndicators
{
  public function __construct(private readonly ContentSourceRegistry $sources) {}

  public function labels(Block $block): array
  {
    $translator = app(CmsTranslator::class);
    $locale = app(AdminLocaleResolver::class)->locale();
    $text = fn ($key, $replace = []) => $translator->get('dynamic-content.'.$key, $locale, $replace);
    $items = [];
    if (is_string($source = $block->setting('content_collection.source')) && $source !== '') {
      $items[] = $text('loop_label', ['source' => $this->sources->find($source)?->labelText() ?? $source]);
      $items[] = $text('template_label', ['id' => (string) $block->setting('content_collection.template_block_id')]);
    }
    if ($block->relationLoaded('parent') && $block->parent && (int) $block->parent->setting('content_collection.template_block_id') === (int) $block->id) {
      $items[] = $text('repeated_template', ['id' => $block->parent->id]);
    }
    foreach ((array) $block->setting('content_bindings', []) as $target => $binding) {
      if (! is_array($binding) || ! is_string($binding['source'] ?? null) || ! is_string($binding['field'] ?? null) || ! is_string($binding['record'] ?? null)) {
        continue;
      }
      $source = $this->sources->find((string) ($binding['source'] ?? ''));
      $context = ($binding['record'] ?? '') === '@item' ? $text('current_item') : (string) ($binding['record'] ?? '');
      $field = $source?->fieldDefinitions()[$binding['field'] ?? '']['label'] ?? ($binding['field'] ?? '');
      $targetKey = $block->typeSlug() === 'image' ? match ($target) {
      'title' => 'caption', 'subtitle' => 'alt_text', default => $target
      } : $target;
      $targetLabel = $text('target_'.$targetKey);
      $items[] = $targetLabel.' ← '.($source?->labelText() ?? ($binding['source'] ?? '')).' / '.$context.' / '.$field;
    }
    if ($items === [] && $block->relationLoaded('children') && $this->hasDynamicDescendant($block)) {
      $items[] = $text('dynamic_descendants');
    }

    return $items;
  }

  private function hasDynamicDescendant(Block $block, int $depth = 0, array &$seen = []): bool
  {
    $identity = $block->exists ? 'id:'.$block->id : 'object:'.spl_object_id($block);
    if (isset($seen[$identity]) || $depth > 32 || ! $block->relationLoaded('children')) {
      return false;
    }
    $seen[$identity] = true;
    foreach ($block->children as $child) {
      if ($child->setting('content_collection.source') || $child->setting('content_bindings') || $this->hasDynamicDescendant($child, $depth + 1, $seen)) {
        return true;
      }
    }

    return false;
  }
}
