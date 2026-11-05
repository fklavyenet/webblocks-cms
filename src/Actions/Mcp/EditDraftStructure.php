<?php

namespace WebBlocks\Cms\Actions\Mcp;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Policies\McpPagePolicy;
use WebBlocks\Cms\Support\Formatting\SafeRichTextRenderer;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Mcp\McpBlockFields;
use WebBlocks\Cms\Support\Mcp\McpPages;
use WebBlocks\Cms\Support\Mcp\McpToolException;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;

final class EditDraftStructure
{
  public const ADD_TYPES = ['header', 'plain_text', 'rich-text', 'hero', 'content_header', 'alert', 'cta', 'container', 'stack'];

  private const MOVE_TYPES = [
    ...self::ADD_TYPES, 'image', 'quote', 'code', 'table', 'button', 'button_link', 'section', 'split', 'cluster', 'grid',
  ];

  public function __construct(
    private readonly McpPages $pages,
    private readonly McpPagePolicy $policy,
    private readonly InternalContentPlanService $plans,
    private readonly InternalContentApiOperations $blocks,
    private readonly McpBlockFields $fields,
    private readonly SafeRichTextRenderer $richText,
    private readonly PageRevisionManager $revisions,
  ) {}

  public function read(int $siteId, array $input): array
  {
    [$page, $draft] = $this->target($siteId, $input['page_id']);
    $locale = $this->pages->locale($draft ?? $page, $input['locale']);
    $version = $this->version($page, $draft, $locale);
    $slots = $this->slots($draft ?? $page);
    $managed = $this->managed($page, $draft, $slots);
    $editable = $this->pages->staticBlocks($draft ?? $page);
    $blocks = ($draft ?? $page)->blocks->whereIn('slot_type_id', $slots->keys())->sortBy('id')->values();
    $number = $input['page'] ?? 1;

    return [
      'page_id' => $page->id,
      'draft_id' => $draft?->id,
      'draft_status' => $draft?->status,
      'version' => $version,
      'slots' => $slots->map(fn (PageSlot $slot) => [
        'name' => $slot->slotType->slug,
        'managed' => in_array($slot->slotType->slug, $managed, true),
      ])->values()->all(),
      'add_types' => BlockType::query()->where('status', 'published')->whereIn('slug', self::ADD_TYPES)->orderBy('slug')->pluck('slug')->all(),
      'blocks' => $blocks->forPage($number, 50)->map(fn (Block $block) => [
        'block_id' => $block->id,
        'type' => $block->typeSlug(),
        'slot' => $slots->get($block->slot_type_id)->slotType->slug,
        'parent_id' => $block->parent_id,
        'sort_order' => $block->sort_order,
        'static' => $editable->has($block->id),
        'move_type_supported' => in_array($block->typeSlug(), self::MOVE_TYPES, true),
      ])->values()->all(),
      'next_page' => $number < McpPages::MAX_PAGE_NUMBER && $blocks->count() > $number * 50 ? $number + 1 : null,
    ];
  }

  public function handle(User $user, int $siteId, array $input): array
  {
    $localizedText = $input['operation'] === 'add' && ! empty($input['text'])
      && $input['locale'] !== app(LocaleResolver::class)->default()->code;
    if (! $this->policy->writeLocale($user, $siteId, $localizedText)) {
      throw new McpToolException('forbidden');
    }

    return DB::transaction(function () use ($user, $siteId, $input, $localizedText): array {
      [$page, $draft] = $this->target($siteId, $input['page_id'], lock: true);
      $this->pages->lockBlocks($page, $draft);
      $locale = $this->pages->locale($draft ?? $page, $input['locale']);
      $fingerprint = $this->pages->fingerprint($page);
      if (($draft && $draft->status !== Page::STATUS_DRAFT) || ! hash_equals($this->version($page, $draft, $locale, $fingerprint), $input['version'])) {
        throw new McpToolException('conflict');
      }
      if ($page->isPublished() && $draft && ! $this->pages->hasVerifiedBase($draft, $fingerprint)) {
        throw new McpToolException('unverified_base');
      }
      if ($this->pages->hasHumanOnlyBlocks($page, $draft)) {
        throw new McpToolException('block_unavailable');
      }

      if ($input['operation'] === 'prepare') {
        $slots = $this->slots($page)->map(fn (PageSlot $slot) => $slot->slotType->slug)->all();
        if ($draft || array_diff($input['slots'], $slots) !== []) {
          throw new McpToolException('structure_slot_unavailable');
        }
        $draft = $this->plans->prepareAuthoringDraft($page, $user, $input['slots'], 'mcp_draft_prepared');

        return $this->result($page, $draft, $locale);
      }
      if (! $draft) {
        throw new McpToolException('draft_missing');
      }
      if (! $this->policy->editDraft($user, $draft, $localizedText)) {
        throw new McpToolException('forbidden');
      }

      $slots = $this->slots($draft);
      $managed = $this->managed($page, $draft, $slots);
      $slot = $slots->first(fn (PageSlot $slot) => $slot->slotType->slug === $input['slot']);
      if (! $slot || ! in_array($input['slot'], $managed, true)) {
        throw new McpToolException('structure_slot_unavailable');
      }

      $all = $draft->blocks->keyBy('id');
      $editable = $this->pages->staticBlocks($draft);
      $parent = isset($input['parent_id']) ? $editable->get($input['parent_id']) : null;
      if (isset($input['parent_id']) && (! $parent || $parent->slot_type_id !== $slot->slot_type_id)) {
        throw new McpToolException('invalid_block_placement');
      }

      $changedIds = match ($input['operation']) {
        'add' => [$this->add($draft, $slot, $parent, $all, $locale, $input)->id],
        'move' => [$this->move($slot, $parent, $all, $editable, $slots, $managed, $input['block_id'])],
        'reorder' => $this->reorder($slot, $parent, $all, $editable, $input['block_ids']),
      };

      $draft->forceFill(['updated_by_user_id' => $user->id])->touch();
      $this->revisions->capture($draft, $user, event: 'mcp_structure_'.$input['operation']);

      return $this->result($page, $draft, $locale) + ['block_ids' => $changedIds];
    }, attempts: 3);
  }

  private function target(int $siteId, int $id, bool $lock = false): array
  {
    $page = Page::query()->where('site_id', $siteId)->whereKey($id)
      ->whereIn('status', [Page::STATUS_DRAFT, Page::STATUS_PUBLISHED])
      ->whereNotIn('page_type', [Page::TYPE_SHARED_SLOT_SOURCE, Page::TYPE_SYSTEM])
      ->when($lock, fn ($query) => $query->lockForUpdate())->first();
    if (! $page || $page->isRevisionRestoreCandidate() || $page->setting('staged_update') !== null) {
      throw new McpToolException('page_missing');
    }

    return [$page, $page->isPublished() ? $this->pages->draft($page, lock: $lock) : $page];
  }

  private function slots(Page $page): Collection
  {
    $page->loadMissing('slots.slotType');

    return $page->slots->filter(fn (PageSlot $slot) => $slot->slotType?->status === 'published' && $slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_PAGE)
      ->keyBy('slot_type_id');
  }

  private function managed(Page $page, ?Page $draft, Collection $slots): array
  {
    return $page->isPublished()
      ? ($draft?->setting('staged_update.managed_slots', []) ?? [])
      : $slots->map(fn (PageSlot $slot) => $slot->slotType->slug)->values()->all();
  }

  private function version(Page $page, ?Page $draft, Locale $locale, ?string $fingerprint = null): string
  {
    return $this->pages->version($page, $fingerprint ?? $this->pages->fingerprint($page), $page->isPublished() ? $draft : null, $locale);
  }

  private function result(Page $page, Page $draft, Locale $locale): array
  {
    return $this->pages->previewLink($page, $draft, $locale) + ['version' => $this->version($page, $draft, $locale)];
  }

  private function siblings(Collection $blocks, PageSlot $slot, ?Block $parent): Collection
  {
    return $blocks->where('slot_type_id', $slot->slot_type_id)->whereStrict('parent_id', $parent?->id);
  }

  private function assertPlacement(?Block $parent, string $type): void
  {
    if ($parent && (! $parent->canAcceptMoreChildren() || ! $parent->canAcceptChildType($type))) {
      throw new McpToolException('invalid_block_placement');
    }
  }

  private function add(Page $draft, PageSlot $slot, ?Block $parent, Collection $all, Locale $locale, array $input): Block
  {
    $type = $input['type'];
    $text = $input['text'] ?? [];
    if (array_diff(array_keys($text), $this->fields->textFields($type)) !== []) {
      throw new McpToolException('invalid_request');
    }
    if ($type === 'rich-text' && isset($text['content'])) {
      $text['content'] = $this->richText->sanitize($text['content']);
    }
    $this->assertPlacement($parent, $type);

    $errors = [];
    $warnings = [];
    $normalized = $this->blocks->normalizeBlock(
      ['type' => $type, 'translations' => $text],
      'block', $parent?->blockType, $errors, $warnings,
      allowEmptyWrapper: in_array($type, ['container', 'stack'], true),
    );
    if (! $normalized || $errors !== []) {
      throw new McpToolException('invalid_block_placement');
    }

    $order = ($this->siblings($all, $slot, $parent)->max('sort_order') ?? -1) + 1;

    return $this->blocks->createPageSlotBlock($draft, $slot->slotType, $normalized, $locale->code, $parent, $order);
  }

  private function move(PageSlot $slot, ?Block $parent, Collection $all, Collection $editable, Collection $slots, array $managed, int $id): int
  {
    $block = $editable->get($id);
    $origin = $block ? $slots->get($block->slot_type_id) : null;
    if (! $origin || ! in_array($origin->slotType->slug, $managed, true)) {
      throw new McpToolException('structure_slot_unavailable');
    }
    if ($block->slot_type_id === $slot->slot_type_id && $block->parent_id === $parent?->id) {
      return $id;
    }

    // Restrict moves to types that do not require a particular ancestor.
    // Validate the full subtree so a move cannot detach dynamic content.
    $subtree = collect([$block]);
    $visited = [];
    $children = $all->groupBy('parent_id');
    for ($i = 0; $i < $subtree->count(); $i++) {
      $node = $subtree[$i];
      if (isset($visited[$node->id]) || $node->id === $parent?->id || ! $editable->has($node->id)
        || ! in_array($node->typeSlug(), self::MOVE_TYPES, true) || $node->slot_type_id !== $origin->slot_type_id) {
        throw new McpToolException('invalid_block_placement');
      }
      $visited[$node->id] = true;
      foreach ($children->get($node->id, []) as $child) {
        $subtree->push($child);
      }
    }
    if ($all->get($block->parent_id)?->typeSlug() === 'split') {
      throw new McpToolException('invalid_block_placement');
    }
    $this->assertPlacement($parent, $block->typeSlug());

    $order = ($this->siblings($all, $slot, $parent)->max('sort_order') ?? -1) + 1;
    $block->update(['parent_id' => $parent?->id, 'slot_type_id' => $slot->slot_type_id, 'sort_order' => $order]);
    foreach ($subtree->skip(1) as $child) {
      if ($child->slot_type_id !== $slot->slot_type_id) {
        $child->update(['slot_type_id' => $slot->slot_type_id]);
      }
    }

    return $id;
  }

  private function reorder(PageSlot $slot, ?Block $parent, Collection $all, Collection $editable, array $ids): array
  {
    $ids = array_map('intval', $ids);
    $siblings = $this->siblings($all, $slot, $parent);
    if ($siblings->count() !== count($ids)
      || array_diff($ids, $siblings->intersectByKeys($editable)->keys()->all()) !== []) {
      throw new McpToolException('invalid_block_order');
    }

    foreach ($ids as $order => $id) {
      $block = $siblings->get($id);
      if ($block->sort_order !== $order) {
        $block->update(['sort_order' => $order]);
      }
    }

    return $ids;
  }
}
