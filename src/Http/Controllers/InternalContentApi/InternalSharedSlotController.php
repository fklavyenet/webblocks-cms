<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\BlockDeletionManager;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotRevisionManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;

class InternalSharedSlotController extends Controller
{
  public function __construct(
    private readonly InternalContentApiOperations $operations,
    private readonly InternalContentApiPresenter $presenter,
    private readonly SharedSlotSourcePageManager $sourcePages,
    private readonly BlockDeletionManager $blockDeletionManager,
    private readonly SharedSlotRevisionManager $revisionManager,
    private readonly BlockTypeApiAuthoringPolicy $apiAuthoringPolicy,
    private readonly CmsApiTokenCapabilities $capabilities,
  ) {}

  public function index(Request $request): JsonResponse
  {
    $site = $this->siteFromRequest($request);
    $sharedSlots = SharedSlot::query()
      ->with(['site', 'slotBlocks.block.blockType', 'slotBlocks.block.slotType'])
      ->when($site, fn ($query) => $query->where('site_id', $site->id))
      ->orderBy('name')
      ->get()
      ->map(fn (SharedSlot $sharedSlot) => $this->presenter->sharedSlot($sharedSlot))
      ->values();

    return $this->ok(['shared_slots' => $sharedSlots]);
  }

  public function show(SharedSlot $sharedSlot): JsonResponse
  {
    $sharedSlot->load(['site', 'slotBlocks.block.blockType', 'slotBlocks.block.slotType']);

    return $this->ok(['shared_slot' => $this->presenter->sharedSlot($sharedSlot, true)]);
  }

  public function store(Request $request): JsonResponse
  {
    $errors = [];
    $warnings = [];
    $site = $this->siteFromRequest($request);
    $locale = $this->operations->resolveLocale($request->input('locale', 'en'), $site ?? Site::primary(), 'shared_slot.locale', $errors);
    $normalized = $this->operations->normalizeSharedSlot($request->json()->all(), $site, 'shared_slot', $errors, $warnings);

    if ($errors !== [] || ! $normalized || ! $locale) {
      return $this->validationError($errors);
    }

    $sharedSlot = $this->operations->createSharedSlot($normalized, $locale->code);

    return response()->json([
      'ok' => true,
      'shared_slot' => $this->presenter->sharedSlot($sharedSlot->fresh(['site', 'slotBlocks.block.blockType', 'slotBlocks.block.slotType']), true),
      'writes' => [['type' => 'shared_slot', 'id' => $sharedSlot->id]],
      'warnings' => $warnings,
      'errors' => [],
    ], 201);
  }

  public function storeBlock(Request $request, SharedSlot $sharedSlot): JsonResponse
  {
    $errors = [];
    $warnings = [];
    $site = $sharedSlot->site ?? $sharedSlot->site()->first();
    $locale = $this->operations->resolveLocale($request->input('locale', 'en'), $site, 'block.locale', $errors);
    $parent = $this->parentBlockFromRequest($request, $sharedSlot, $errors);
    $blockPayload = $request->json()->all();
    unset($blockPayload['parent_id'], $blockPayload['parent_block_id']);
    $normalized = $this->operations->normalizeBlock($blockPayload, 'block', $parent?->blockType, $errors, $warnings);

    if ($errors !== [] || ! $normalized || ! $locale) {
      return $this->validationError($errors);
    }

    $block = DB::transaction(function () use ($sharedSlot, $normalized, $locale, $parent) {
      $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
      $sortOrder = (int) $sourcePage->blocks()->where('parent_id', $parent?->id)->max('sort_order') + 1;
      $block = $this->operations->createSharedSlotBlock($sharedSlot, $normalized, $locale->code, $parent, $sortOrder);
      $this->sourcePages->rebuildAssignments($sharedSlot);

      return $block;
    });

    return response()->json([
      'ok' => true,
      'block' => $this->presenter->block($block->fresh(['blockType', 'slotType', 'textTranslations', 'buttonTranslations', 'imageTranslations'])),
      'writes' => [['type' => 'shared_slot_block', 'id' => $block->id]],
      'warnings' => $warnings,
      'errors' => [],
    ], 201);
  }

  private function parentBlockFromRequest(Request $request, SharedSlot $sharedSlot, array &$errors): ?Block
  {
    $parentId = $request->input('parent_id', $request->input('parent_block_id'));

    if ($parentId === null || $parentId === '') {
      return null;
    }

    if (! is_numeric($parentId) || (int) $parentId < 1) {
      $errors[] = ['path' => 'block.parent_id', 'message' => 'Parent block id must be a positive integer.'];

      return null;
    }

    $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
    $parent = Block::query()
      ->with('blockType')
      ->whereKey((int) $parentId)
      ->where('page_id', $sourcePage->id)
      ->first();

    if (! $parent) {
      $errors[] = ['path' => 'block.parent_id', 'message' => 'Parent block must belong to this Shared Slot source tree.'];

      return null;
    }

    if (! $parent->canAcceptChildren()) {
      $errors[] = ['path' => 'block.parent_id', 'message' => 'Parent block cannot accept child blocks.'];

      return null;
    }

    return $parent;
  }

  public function assignToPageSlot(Request $request, Page $page, string $slot): JsonResponse
  {
    $errors = [];
    $sharedSlotValue = $request->input('shared_slot', $request->input('shared_slot_id'));
    $sharedSlot = SharedSlot::query()
      ->when(is_numeric($sharedSlotValue), fn ($query) => $query->whereKey((int) $sharedSlotValue), fn ($query) => $query->where('handle', trim((string) $sharedSlotValue))->where('site_id', $page->site_id))
      ->first();

    if (! $sharedSlot) {
      return $this->validationError([
        ['path' => 'page_slot.shared_slot', 'message' => 'Shared Slot must resolve for the page site.'],
      ]);
    }

    if ($this->apiAuthoringPolicy->scopeHasHumanOnlyBlock($this->sourcePages->sourceBlocks($sharedSlot))) {
      return $this->apiAuthoringPolicy->rejectionResponse('page_slot.shared_slot');
    }

    $pageSlot = $this->operations->assignSharedSlot($page, $slot, $sharedSlot, 'page_slot', $errors);

    if ($errors !== [] || ! $pageSlot) {
      return $this->validationError($errors);
    }

    return response()->json([
      'ok' => true,
      'page_slot' => $this->presenter->pageSlot($pageSlot),
      'writes' => [['type' => 'page_slot_shared_slot', 'id' => $pageSlot->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  /**
   * Writes a page slot's content source.
   *
   * assignToPageSlot() could bind a slot to a Shared Slot but nothing could
   * unbind it: setting the source back to page-owned or disabled lived only on
   * the session-authenticated admin route, so an API client could create a
   * reference it had no way to undo. This closes that, and handles all three
   * source types so the field has one endpoint rather than a write path per
   * value.
   */
  public function updatePageSlotSource(Request $request, Page $page, string $slot): JsonResponse
  {
    $sourceType = PageSlot::normalizeSourceType($request->input('source_type'));

    if (! in_array($sourceType, PageSlot::sourceTypes(), true)) {
      return $this->validationError([[
        'path' => 'page_slot.source_type',
        'message' => 'Source type must be one of: '.implode(', ', PageSlot::sourceTypes()).'.',
      ]]);
    }

    if ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT) {
      // Binding stays gated on shared-slots.write, exactly as the dedicated
      // assign endpoint is. Unbinding is a content-structure change and needs
      // only content.apply, which the route already requires.
      if (! $this->hasCapability($request, CmsApiTokenCapabilities::SHARED_SLOTS_WRITE)) {
        return $this->capabilityError(
          CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
          'Pointing a page slot at a Shared Slot requires shared-slots.write.',
        );
      }

      return $this->assignToPageSlot($request, $page, $slot);
    }

    $pageSlot = $page->slots()->with('slotType')->get()
      ->first(fn (PageSlot $candidate) => $candidate->slotSlug() === $slot);

    if (! $pageSlot) {
      return $this->validationError([[
        'path' => 'page_slot.slot',
        'message' => 'Page slot must exist before changing its source.',
      ]]);
    }

    DB::transaction(function () use ($page, $pageSlot, $sourceType): void {
      $pageSlot->update([
        'source_type' => $sourceType,
        'shared_slot_id' => null,
      ]);

      // Blocks the page already owns are untouched either way: page-owned
      // renders them again, disabled keeps the wrapper and renders nothing.
      $page->forceFill(['updated_by_user_id' => null])->save();
    });

    return response()->json([
      'ok' => true,
      'page_slot' => $this->presenter->pageSlot($pageSlot->fresh(['slotType'])),
      'writes' => [['type' => 'page_slot_source', 'id' => $pageSlot->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function publishBlocks(SharedSlot $sharedSlot): JsonResponse
  {
    if ($this->apiAuthoringPolicy->scopeHasHumanOnlyBlock($this->sourcePages->sourceBlocks($sharedSlot))) {
      return $this->apiAuthoringPolicy->rejectionResponse('shared_slot.blocks');
    }

    $publishedCount = DB::transaction(function () use ($sharedSlot): int {
      $blockIds = $sharedSlot->slotBlocks()
        ->pluck('block_id')
        ->map(fn ($value) => (int) $value)
        ->filter()
        ->values();

      if ($blockIds->isEmpty()) {
        return 0;
      }

      return Block::query()
        ->whereIn('id', $blockIds)
        ->where('status', '!=', 'published')
        ->update(['status' => 'published']);
    });

    return response()->json([
      'ok' => true,
      'shared_slot' => $this->presenter->sharedSlot($sharedSlot->fresh(['site', 'slotBlocks.block.blockType', 'slotBlocks.block.slotType']), true),
      'published_blocks_count' => $publishedCount,
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function reorderBlocks(Request $request, SharedSlot $sharedSlot): JsonResponse
  {
    $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
    $slotType = $this->sourcePages->editorSlotTypeFor($sharedSlot);

    $blockIds = collect($request->input('blocks', []));

    if ($blockIds->isEmpty() || $blockIds->count() !== $blockIds->unique()->count()
      || $blockIds->contains(fn ($id) => ! is_numeric($id))) {
      return $this->validationError([
        ['path' => 'blocks', 'message' => 'Provide a non-empty list of distinct block ids in the desired order.'],
      ]);
    }

    $blockIds = $blockIds->map(fn ($id) => (int) $id)->values();

    $blocks = Block::query()
      ->whereIn('id', $blockIds)
      ->where('page_id', $sourcePage->id)
      ->where('slot_type_id', $slotType->id)
      ->get(['id', 'parent_id']);

    if ($blocks->count() !== $blockIds->count()) {
      return $this->validationError([
        ['path' => 'blocks', 'message' => 'Submitted blocks must belong to this Shared Slot.'],
      ]);
    }

    $parentIds = $blocks->map(fn (Block $block) => $block->parent_id)->uniqueStrict();

    if ($parentIds->count() !== 1) {
      return $this->validationError([
        ['path' => 'blocks', 'message' => 'Submitted blocks must belong to the same parent group.'],
      ]);
    }

    $parentId = $parentIds->first();

    $siblingIds = Block::query()
      ->where('page_id', $sourcePage->id)
      ->where('slot_type_id', $slotType->id)
      ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'))
      ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
      ->pluck('id')
      ->map(fn ($id) => (int) $id);

    if ($siblingIds->sort()->values()->all() !== $blockIds->sort()->values()->all()) {
      return $this->validationError([
        ['path' => 'blocks', 'message' => 'Submitted blocks must contain the full sibling group for one parent.'],
      ]);
    }

    if ($this->apiAuthoringPolicy->blockIdsScopeHasHumanOnlyBlock($blockIds)) {
      return $this->apiAuthoringPolicy->rejectionResponse('blocks');
    }

    DB::transaction(function () use ($sharedSlot, $sourcePage, $slotType, $blockIds, $parentId, $request): void {
      $siblings = Block::query()
        ->where('page_id', $sourcePage->id)
        ->where('slot_type_id', $slotType->id)
        ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'))
        ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
        ->lockForUpdate()
        ->get(['id', 'sort_order']);

      $positionMap = $blockIds->flip();

      $siblings
        ->sortBy(fn (Block $block) => $positionMap->get($block->id))
        ->values()
        ->each(function (Block $block, int $index): void {
          if ($block->sort_order !== $index) {
            $block->update(['sort_order' => $index]);
          }
        });

      $this->sourcePages->rebuildAssignments($sharedSlot);
      $this->captureRevision($sharedSlot, $request, 'block_reordered', 'Shared Slot block order updated', 'Shared Slot block order was changed through the Internal Content API.');
    });

    return $this->ok(['message' => 'Saved']);
  }

  public function deleteBlock(Request $request, SharedSlot $sharedSlot, Block $block): JsonResponse
  {
    $sourcePage = $this->sourcePages->ensureFor($sharedSlot);

    if ($block->page_id !== $sourcePage->id) {
      return $this->validationError([
        ['path' => 'block', 'message' => 'The block does not belong to this Shared Slot.'],
      ]);
    }

    if ($this->apiAuthoringPolicy->blockIdsScopeHasHumanOnlyBlock([$block->id])) {
      return $this->apiAuthoringPolicy->rejectionResponse('block');
    }

    $deletedCount = DB::transaction(function () use ($sharedSlot, $block, $request): int {
      $order = $this->blockDeletionManager->recursiveDeleteOrder($block);
      $order->each(fn (Block $candidate) => $candidate->delete());

      $this->sourcePages->rebuildAssignments($sharedSlot);
      $this->captureRevision($sharedSlot, $request, 'block_deleted', 'Shared Slot block deleted', 'A Shared Slot block was deleted through the Internal Content API.');

      return $order->count();
    });

    return $this->ok(['message' => 'Deleted', 'deleted_blocks_count' => $deletedCount]);
  }

  public function clearBlocks(Request $request, SharedSlot $sharedSlot): JsonResponse
  {
    $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
    $slotType = $this->sourcePages->editorSlotTypeFor($sharedSlot);

    if ($this->apiAuthoringPolicy->scopeHasHumanOnlyBlock($this->sourcePages->sourceBlocks($sharedSlot))) {
      return $this->apiAuthoringPolicy->rejectionResponse('shared_slot.blocks');
    }

    $deletedCount = DB::transaction(function () use ($sharedSlot, $sourcePage, $slotType, $request): int {
      $order = $this->blockDeletionManager
        ->scopedBlocksForSlot($sourcePage->id, $slotType->id)
        ->whereNull('parent_id')
        ->values()
        ->flatMap(fn (Block $block) => $this->blockDeletionManager->recursiveDeleteOrder($block))
        ->unique('id');

      $order->each(fn (Block $block) => $block->delete());

      $this->sourcePages->rebuildAssignments($sharedSlot);
      $this->captureRevision($sharedSlot, $request, 'block_deleted', 'Shared Slot blocks cleared', 'Every Shared Slot block was removed through the Internal Content API.');

      return $order->count();
    });

    return $this->ok(['message' => 'Cleared', 'deleted_blocks_count' => $deletedCount]);
  }

  private function captureRevision(SharedSlot $sharedSlot, Request $request, string $event, string $label, string $summary): void
  {
    if (! $this->revisionManager->revisionsTableExists()) {
      return;
    }

    $sharedSlot->forceFill(['updated_by_user_id' => $request->user()?->id])->save();
    $this->revisionManager->capture($sharedSlot->fresh(), $request->user(), $event, $label, $summary);
  }

  private function siteFromRequest(Request $request): ?Site
  {
    $value = $request->query('site', $request->input('site', $request->input('site_id')));

    if ($value === null || $value === '') {
      return null;
    }

    return Site::query()
      ->when(is_numeric($value), fn ($query) => $query->whereKey((int) $value), fn ($query) => $query->where('handle', trim((string) $value)))
      ->first();
  }

  private function hasCapability(Request $request, string $capability): bool
  {
    $token = $request->attributes->get('cms_api_token');

    return $token instanceof CmsApiToken && $this->capabilities->has($token, $capability);
  }

  private function capabilityError(string $capability, string $message): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => 'missing_internal_api_capability',
      'message' => $message,
      'required_capability' => $capability,
      'warnings' => [],
      'errors' => [['path' => 'Authorization', 'message' => $message]],
    ], 403);
  }

  private function ok(array $data): JsonResponse
  {
    return response()->json([
      'ok' => true,
      ...$data,
      'warnings' => [],
      'errors' => [],
    ]);
  }

  private function validationError(array $errors): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'warnings' => [],
      'errors' => $errors,
    ], 422);
  }
}
