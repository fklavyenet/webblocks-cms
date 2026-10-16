<?php

namespace WebBlocks\Cms\Support\ContentSources;

use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Page;

final class AutomaticPageDesign
{
  public function page(AutomaticPageRule $rule): ?Page
  {
    return Page::query()->visibleInAdmin()->where('site_id', $rule->site_id)
      ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)->find($rule->template_page_id);
  }

  public function connections(Page $page, ContentSourceDefinition $entity): array
  {
    $connections = [];
    foreach ($page->blocks()->with('blockType')->orderBy('id')->get() as $block) {
      foreach ((array) $block->setting('content_bindings', []) as $target => $binding) {
        if (is_array($binding) && ($binding['source'] ?? null) === $entity->handle() && ($binding['record'] ?? '') !== '@item'
          && isset($entity->fieldDefinitions()[$binding['field'] ?? ''])) {
          $connections[] = ['block' => $block, 'target' => $target, 'field' => $binding['field'], 'label' => $entity->fieldDefinitions()[$binding['field']]['label']];
        }
      }
    }

    return $connections;
  }
}
