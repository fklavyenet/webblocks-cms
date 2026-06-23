<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;

class InternalSharedSlotController extends Controller
{
  public function __construct(
    private readonly InternalContentApiOperations $operations,
    private readonly InternalContentApiPresenter $presenter,
    private readonly SharedSlotSourcePageManager $sourcePages,
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
    $normalized = $this->operations->normalizeBlock($request->json()->all(), 'block', null, $errors, $warnings);

    if ($errors !== [] || ! $normalized || ! $locale) {
      return $this->validationError($errors);
    }

    $block = DB::transaction(function () use ($sharedSlot, $normalized, $locale) {
      $block = $this->operations->createSharedSlotBlock($sharedSlot, $normalized, $locale->code, null, (int) $sharedSlot->slotBlocks()->whereNull('parent_id')->max('sort_order') + 1);
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
