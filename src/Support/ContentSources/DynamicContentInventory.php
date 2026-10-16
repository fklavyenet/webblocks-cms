<?php

namespace WebBlocks\Cms\Support\ContentSources;

use App\Models\User;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlotBlock;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

/** A read model over existing SQL bindings. Does not resolve remote records. */
final class DynamicContentInventory
{
  public function __construct(private readonly AdminAuthorization $authorization, private readonly PluginRegistry $plugins, private readonly ContentSourceRuntime $runtime) {}

  public function build(User $actor, ?int $siteId = null): array
  {
    $sites = $this->authorization->scopeSitesForUser(Site::query(), $actor)->orderBy('name')->get();
    if ($siteId !== null) {
      abort_unless($sites->contains('id', $siteId), 403);
    }
    $allowedSites = $siteId === null ? $sites->modelKeys() : [$siteId];
    $definitions = [];
    $active = [];
    $providerLabels = [];
    foreach ($this->plugins->all() as $plugin) {
      foreach ($plugin->contentSourceDefinitions() as $source) {
        $definitions[$source->handle()] = $source;
        $providerLabels[$source->handle()] = $plugin->labelText();
        $active[$source->handle()] = $this->plugins->isActive($plugin->handle());
      }
    }
    $blocks = $this->authorization->scopeBlocksForUser(Block::query(), $actor)
      ->whereHas('page', fn ($q) => $q->whereIn('site_id', $allowedSites)->whereRuntimeSettingNull('revision_restore_candidate'))
      ->whereIn('id', function ($q) {
        $q->select('d.block_id')->from('wbcms_setting_documents as d')->whereNotNull('d.block_id')
          ->where(function ($q) {
            $q->whereExists(fn ($q) => $q->selectRaw('1')->from('wbcms_block_content_bindings as b')->whereColumn('b.document_id', 'd.id'))
              ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('wbcms_block_content_collections as c')->whereColumn('c.document_id', 'd.id'));
          });
      })
      ->with(['blockType', 'children', 'page.site', 'page.translations.locale', 'slotType', 'parent.parent.parent.parent.parent.parent.parent.parent'])
      ->orderBy('id')->get();
    $ancestorIds = $blocks->flatMap(fn ($block) => collect($this->ancestors($block))->pluck('id'))->unique();
    $assignments = SharedSlotBlock::query()->with('sharedSlot')->whereIn('block_id', $ancestorIds)
      ->whereHas('sharedSlot', fn ($q) => $q->whereIn('site_id', $allowedSites))->get()->keyBy('block_id');
    $slots = PageSlot::query()->whereIn('page_id', $blocks->pluck('page_id'))->get()->keyBy(fn ($slot) => $slot->page_id.':'.$slot->slot_type_id);
    $rows = collect();
    foreach ($blocks as $block) {
      $chain = $this->ancestors($block);
      $shared = collect($chain)->map(fn ($ancestor) => $assignments->get($ancestor->id)?->sharedSlot)->filter()->first();
      $slot = $slots->get($block->page_id.':'.$block->slot_type_id);
      $edit = $shared ? route('admin.shared-slots.blocks.edit', ['shared_slot' => $shared->id, 'edit' => $block->id])
        : ($slot ? route('admin.pages.slots.blocks', ['page' => $block->page_id, 'slot' => $slot->id, 'edit' => $block->id]) : route('admin.blocks.edit', $block));
      $base = ['block' => $block, 'site' => $block->page->site, 'owner' => $shared?->name ?? $block->page->defaultTranslation()?->name ?? $block->page->slug,
        'shared' => $shared !== null, 'path' => implode(' → ', array_reverse(array_map(fn ($b) => $b->typeName().' #'.$b->id, $chain))),
        'edit_url' => $edit, 'status' => $shared ? ($shared->is_active ? 'published' : 'draft') : $block->page->status, 'block_status' => $block->status];
      $collection = $block->setting('content_collection');
      if (is_array($collection) && is_string($collection['source'] ?? null) && $collection['source'] !== '') {
        $rows->push($base + ['kind' => 'loop', 'template_valid' => $block->children->contains('id', (int) ($collection['template_block_id'] ?? 0)), 'source' => $collection['source'], 'target' => null, 'field' => null, 'record' => null, 'configuration' => $collection]);
      }
      foreach ((array) $block->setting('content_bindings', []) as $target => $binding) {
        if (is_array($binding) && is_string($binding['source'] ?? null) && $binding['source'] !== '') {
          $loop = collect($chain)->first(fn ($b) => $b->setting('content_collection.source') === $binding['source']
            && collect($chain)->contains('id', (int) $b->setting('content_collection.template_block_id')));
          $rows->push($base + ['kind' => 'binding', 'source' => $binding['source'], 'target' => $target,
            'field' => $binding['field'] ?? '', 'target_label_key' => $block->typeSlug() === 'image' ? match ($target) {
            'title' => 'caption', 'subtitle' => 'alt_text', default => $target
            } : $target, 'record' => $binding['record'] ?? '', 'loop_id' => $loop?->id, 'configuration' => null]);
        }
      }
    }
    $catalog = collect();
    $handles = array_unique([...array_keys($definitions), ...$rows->pluck('source')->all()]);
    foreach ($handles as $handle) {
      $source = $definitions[$handle] ?? null;
      $usages = $rows->where('source', $handle);
      $contexts = $sites->whereIn('id', $allowedSites)->map(fn ($site) => new ContentSourceContext($site, null, 'en', true, $actor));
      $visible = $source && ($contexts->contains(fn ($context) => $this->runtime->allows($source, $context))
        || $usages->contains(fn ($row) => $this->runtime->allows($source, new ContentSourceContext($row['site'], $row['block']->page, 'en', true, $actor))));
      if ($usages->isEmpty() && ! $visible) {
        continue;
      }
      $catalog->put($handle, ['handle' => $handle, 'definition' => $visible ? $source : null, 'label' => $visible ? $source->labelText() : $handle,
        'plugin' => $source?->pluginHandle(), 'provider_label' => $providerLabels[$handle] ?? null, 'kind' => $source?->isCollection() ? 'collection' : 'entity',
        'state' => ! $source ? 'missing' : (! ($active[$handle] ?? false) ? 'disabled' : (! $visible ? 'restricted' : ($source->contract() ? 'declared' : 'legacy'))),
        'capabilities' => $visible ? $source->capabilities() : null,
        'fields' => $visible ? $source->fieldDefinitions() : [], 'contract' => $visible ? $source->contract() : null, 'usages' => $usages->values(),
        'blocks_count' => $usages->pluck('block.id')->unique()->count(), 'loops_count' => $usages->where('kind', 'loop')->count()]);
    }

    return ['sites' => $sites, 'siteId' => $siteId, 'sources' => $catalog, 'usages' => $rows];
  }

  private function ancestors(Block $block): array
  {
    $result = [];
    $seen = [];
    for ($current = $block; $current && $current->page_id === $block->page_id && ! isset($seen[$current->id]) && count($result) < 32; $current = $current->parent) {
      $seen[$current->id] = true;
      $result[] = $current;
    }

    return $result;
  }
}
