<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\SharedSlotRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Audit\CurrentActorResolver;
use WebBlocks\Cms\Support\Blocks\BlockDeletionManager;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotRevisionManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSchema;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class SharedSlotController extends Controller
{
  private const SITE_CONTEXT_SESSION_KEY = 'admin.shared-slots.site';

  public function __construct(
    private readonly BlockTranslationResolver $blockTranslationResolver,
    private readonly BlockDeletionManager $blockDeletionManager,
    private readonly CurrentActorResolver $currentActorResolver,
    private readonly PageWorkflowManager $workflowManager,
    private readonly AdminAuthorization $authorization,
    private readonly SharedSlotRevisionManager $revisionManager,
    private readonly SharedSlotSchema $schema,
    private readonly SharedSlotSourcePageManager $sourcePages,
  ) {}

  public function index(Request $request): View
  {
    $sites = $this->authorization->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), $request->user())->get();
    [$activeSite, $siteFilterValue] = $this->resolveSiteContext($request, $sites);
    $sharedSlotsReady = $this->schema->sharedSlotsTableExists();
    $search = trim((string) $request->string('search'));
    $status = $request->string('status')->toString();
    $sort = $request->string('sort')->toString();
    $direction = Str::lower($request->string('direction')->toString()) === 'asc' ? 'asc' : 'desc';
    $allowedSorts = ['updated_at', 'name', 'handle', 'slot_name', 'public_shell'];

    if (! in_array($status, ['active', 'inactive'], true)) {
      $status = '';
    }

    if (! in_array($sort, $allowedSorts, true)) {
      $sort = 'updated_at';
    }

    $sharedSlots = null;
    $totalCount = null;

    if ($sharedSlotsReady) {
      $totalCount = $this->authorization->scopeSharedSlotsForUser(SharedSlot::query(), $request->user())
        ->when($activeSite, fn ($query) => $query->where('site_id', $activeSite->id))
        ->count();
    }

    if ($sharedSlotsReady) {
      $sharedSlots = $this->authorization->scopeSharedSlotsForUser(SharedSlot::query(), $request->user())
        ->with(['site'])
        ->withCount('pageSlots')
        ->when($search !== '', function ($query) use ($search) {
          $query->where(function ($inner) use ($search): void {
            $inner->where('name', 'like', "%{$search}%")
              ->orWhere('handle', 'like', "%{$search}%")
              ->orWhere('slot_name', 'like', "%{$search}%")
              ->orWhere('public_shell', 'like', "%{$search}%");
          });
        })
        ->when($status === 'active', fn ($query) => $query->where('is_active', true))
        ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
        ->when($activeSite, fn ($query) => $query->where('site_id', $activeSite->id))
        ->orderBy($sort, $direction)
        ->when($sort !== 'updated_at', fn ($query) => $query->orderByDesc('updated_at'))
        ->paginate(AdminPagination::perPage())
        ->withQueryString();

      // The usage modal lists the consuming pages, so the page slots travel with
      // the paginated rows rather than being fetched once per open row.
      $sharedSlots->getCollection()->load([
        'pageSlots.slotType',
        'pageSlots.page.translations.locale',
      ]);
    }

    return view('webblocks-cms::admin.shared-slots.index', [
      'sharedSlots' => $sharedSlots,
      'sharedSlotsReady' => $sharedSlotsReady,
      'sites' => $sites,
      'activeSite' => $activeSite,
      'showAllSites' => $siteFilterValue === 'all',
      'filters' => [
        'search' => $search,
        'status' => $status,
        'site' => $siteFilterValue,
        'sort' => $sort,
        'direction' => $direction,
      ],
      'totalCount' => $totalCount,
      'filteredCount' => $sharedSlots?->total() ?? 0,
      'canCreateSharedSlots' => $this->canManageSharedSlots($request->user()),
    ]);
  }

  public function create(Request $request): View|RedirectResponse
  {
    abort_unless($this->canManageSharedSlots($request->user()), 403);

    if (! $this->schema->sharedSlotsTableExists()) {
      return $this->sharedSlotsNotReadyRedirect();
    }

    $sites = $this->authorization->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), $request->user())->get();
    [$activeSite] = $this->resolveSiteContext($request, $sites, persist: false);

    return view('webblocks-cms::admin.shared-slots.create', [
      'sharedSlot' => new SharedSlot(['is_active' => true, 'site_id' => $activeSite?->id ?? $sites->first()?->id]),
      'sites' => $sites,
      'canManageSharedSlots' => true,
    ]);
  }

  public function store(SharedSlotRequest $request): RedirectResponse
  {
    abort_unless($this->canManageSharedSlots($request->user()), 403);
    $this->authorization->abortUnlessSiteAccess($request->user(), (int) $request->validated('site_id'));

    $sharedSlot = DB::transaction(function () use ($request): SharedSlot {
      $actor = $this->currentActorResolver->resolve($request->user());
      $sharedSlot = SharedSlot::query()->create($request->validatedData() + [
        'created_by_user_id' => $actor['user_id'],
        'updated_by_user_id' => $actor['user_id'],
      ]);
      $this->sourcePages->ensureFor($sharedSlot);

      if ($this->revisionManager->revisionsTableExists()) {
        $this->revisionManager->capture(
          $sharedSlot->fresh(),
          $request->user(),
          'created',
          'Shared Slot created',
          'Shared Slot metadata was created.',
          force: true,
          source: 'admin',
        );
      }

      return $sharedSlot;
    });

    return redirect()
      ->route('admin.shared-slots.edit', $sharedSlot)
      ->with('status', 'Shared Slot created successfully.');
  }

  public function edit(SharedSlot $sharedSlot): View|RedirectResponse
  {
    if (! $this->schema->sharedSlotsTableExists()) {
      return $this->sharedSlotsNotReadyRedirect();
    }

    $this->authorization->abortUnlessSiteAccess(request()->user(), $sharedSlot);

    return view('webblocks-cms::admin.shared-slots.edit', [
      // page_slots_count drives the delete modal, which reports how many page slots
      // still reference this Shared Slot rather than letting the guarded delete fail.
      'sharedSlot' => $sharedSlot->load('site')->loadCount('pageSlots'),
      'sites' => $this->authorization->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), request()->user())->get(),
      'canManageSharedSlots' => $this->canManageSharedSlots(request()->user()),
      'canDeleteSharedSlot' => $this->canManageSharedSlots(request()->user()),
      'canEditSharedSlot' => $this->canEditSharedSlot(request()->user(), $sharedSlot),
      'canViewRevisions' => $this->revisionManager->revisionsTableExists() && $this->revisionManager->canView(request()->user(), $sharedSlot),
    ]);
  }

  public function update(SharedSlotRequest $request, SharedSlot $sharedSlot): RedirectResponse
  {
    $this->authorization->abortUnlessSiteAccess($request->user(), $sharedSlot);
    $this->authorization->abortUnlessSiteAccess($request->user(), (int) $request->validated('site_id'));
    abort_unless($this->canEditSharedSlot($request->user(), $sharedSlot), 403);

    DB::transaction(function () use ($request, $sharedSlot): void {
      $before = $sharedSlot->fresh();
      $sharedSlot->update($request->validatedData() + [
        'updated_by_user_id' => $request->user()?->id,
      ]);
      $freshSharedSlot = $sharedSlot->fresh();
      $this->sourcePages->ensureFor($freshSharedSlot);
      $this->sourcePages->rebuildAssignments($freshSharedSlot);

      $metadataChanged = collect(['name', 'handle', 'slot_name', 'public_shell'])
        ->contains(fn (string $key) => data_get($before, $key) !== data_get($freshSharedSlot, $key));
      $statusChanged = (bool) $before->is_active !== (bool) $freshSharedSlot->is_active;

      if ($metadataChanged && $this->revisionManager->revisionsTableExists()) {
        $this->revisionManager->capture(
          $freshSharedSlot,
          $request->user(),
          'metadata_updated',
          'Shared Slot updated',
          'Shared Slot metadata was updated.',
        );
      }

      if ($statusChanged && $this->revisionManager->revisionsTableExists()) {
        $this->revisionManager->capture(
          $freshSharedSlot,
          $request->user(),
          'status_updated',
          'Shared Slot status updated',
          'Shared Slot active status was changed.',
        );
      }
    });

    return redirect()
      ->route('admin.shared-slots.edit', $sharedSlot)
      ->with('status', 'Shared Slot updated successfully.');
  }

  public function destroy(SharedSlot $sharedSlot): RedirectResponse
  {
    if (! $this->schema->sharedSlotsTableExists()) {
      return $this->sharedSlotsNotReadyRedirect();
    }

    $this->authorization->abortUnlessSiteAccess(request()->user(), $sharedSlot);
    abort_unless($this->canManageSharedSlots(request()->user()), 403);

    if (! $this->schema->pageSlotSourceColumnsExist()) {
      return $this->sharedSlotsNotReadyRedirect(
        route('admin.shared-slots.edit', $sharedSlot),
        'Shared Slot references are not ready yet. Run the latest migrations before deleting Shared Slots.'
      );
    }

    if ($sharedSlot->pageSlots()->exists()) {
      return redirect()
        ->route('admin.shared-slots.edit', $sharedSlot)
        ->withErrors(['shared_slot' => 'Shared Slot cannot be deleted while it is referenced by one or more page slots.']);
    }

    $siteId = $sharedSlot->site_id;

    DB::transaction(function () use ($sharedSlot): void {
      $this->sourcePages->deleteFor($sharedSlot);
      $sharedSlot->delete();
    });

    return redirect()
      ->route('admin.shared-slots.index', ['site' => $siteId])
      ->with('status', 'Shared Slot deleted successfully.');
  }

  public function editBlocks(SharedSlot $sharedSlot): View|RedirectResponse
  {
    if (! $this->schema->sharedSlotsTableExists() || ! $this->schema->sharedSlotBlocksTableExists()) {
      return $this->sharedSlotsNotReadyRedirect(
        route('admin.shared-slots.index'),
        'Shared Slot block editing is not ready yet. Run the latest migrations before editing Shared Slot blocks.'
      );
    }

    $this->authorization->abortUnlessSiteAccess(request()->user(), $sharedSlot);
    abort_unless($this->canEditSharedSlot(request()->user(), $sharedSlot), 403);

    $sharedSlot->loadMissing('site');
    $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
    $slot = $sourcePage->slots()->with('slotType')->firstOrFail();
    $activeLocale = $this->slotEditorLocale($sourcePage);
    $allBlocks = Block::query()
      ->with($this->slotBlockRelations())
      ->where('page_id', $sourcePage->id)
      ->where('slot_type_id', $slot->slot_type_id)
      ->orderBy('sort_order')
      ->get();
    $resolvedBlocks = $this->blockTranslationResolver->resolveCollection($allBlocks, $activeLocale)->values();
    $rootBlocks = $this->blockTranslationResolver
      ->resolveCollection($allBlocks->whereNull('parent_id')->values(), $activeLocale)
      ->values();
    $blockTypes = app(PluginBlockCatalog::class)->filterDiscoverableBlockTypes(
      BlockType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get()
    );
    $pickerParentId = request()->integer('parent_id') ?: null;
    $pickerBlockTypes = $this->pickerBlockTypes($resolvedBlocks, $blockTypes, $pickerParentId);
    $pickerCategory = $this->pickerCategory($pickerBlockTypes);
    $modalState = $this->slotBlockModalState($sourcePage, $slot, $allBlocks, $blockTypes, $pickerBlockTypes, $pickerParentId);
    $deleteModalState = $this->slotBlockDeleteModalState($resolvedBlocks);
    $expandedBlockIds = $this->slotExpandedBlockIds($resolvedBlocks, $modalState['block']);

    return view('webblocks-cms::admin.shared-slots.slot-blocks', [
      'sharedSlot' => $sharedSlot,
      'sourcePage' => $sourcePage,
      'slot' => $slot,
      'blocks' => $rootBlocks,
      'blockTypes' => $blockTypes,
      'pickerBlockTypes' => $pickerBlockTypes,
      'pickerParentBlock' => $pickerParentId ? $resolvedBlocks->firstWhere('id', $pickerParentId) : null,
      'pickerSearch' => trim((string) request('block_type_search')),
      'pickerCategory' => $pickerCategory,
      'isPickerOpen' => request()->boolean('picker') || $modalState['mode'] === 'create',
      'slotModalMode' => $modalState['mode'],
      'slotModalBlock' => $modalState['block'],
      'slotModalSelectedBlockType' => $modalState['selectedBlockType'],
      'columnItemBlockType' => $blockTypes->firstWhere('slug', 'column_item'),
      'featureItemBlockType' => $blockTypes->firstWhere('slug', 'feature-item'),
      'linkListItemBlockType' => $blockTypes->firstWhere('slug', 'link-list-item'),
      'assetPickerAssets' => $this->assetPickerAssets(),
      'assetPickerFolders' => $this->assetPickerFolders(),
      'slotModalSelectedAsset' => $modalState['selectedAsset'],
      'slotModalSelectedGalleryAssets' => $modalState['selectedGalleryAssets'],
      'slotModalSelectedAttachmentAsset' => $modalState['selectedAttachmentAsset'],
      'slotParentBlocks' => $this->slotParentBlocks($resolvedBlocks, $modalState['block']),
      'expandedBlockIds' => $expandedBlockIds,
      'activeLocale' => $activeLocale,
      'availableLocales' => $sourcePage->translationStatusForSite(),
      'slotDeleteModalBlock' => $deleteModalState['block'],
      'slotDeleteModalMeta' => $deleteModalState['meta'],
      'slotDeleteAllModalMeta' => $this->blockDeletionManager->slotMetadata($sourcePage->id, $slot->slot_type_id),
    ]);
  }

  public function destroyBlocks(Request $request, SharedSlot $sharedSlot): RedirectResponse
  {
    if (! $this->schema->sharedSlotsTableExists() || ! $this->schema->sharedSlotBlocksTableExists()) {
      return $this->sharedSlotsNotReadyRedirect(
        route('admin.shared-slots.index'),
        'Shared Slot block editing is not ready yet. Run the latest migrations before editing Shared Slot blocks.'
      );
    }

    $this->authorization->abortUnlessSiteAccess($request->user(), $sharedSlot);
    abort_unless($this->canEditSharedSlot($request->user(), $sharedSlot), 403);

    $validated = $request->validate([
      'confirm_delete_all_blocks' => ['accepted'],
      'locale' => ['nullable', 'string'],
    ], [
      'confirm_delete_all_blocks.accepted' => 'Confirm that all blocks in this Shared Slot should be deleted.',
    ]);

    $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
    $slot = $sourcePage->slots()->firstOrFail();

    DB::transaction(function () use ($sharedSlot, $sourcePage, $slot, $request): void {
      $this->blockDeletionManager
        ->scopedBlocksForSlot($sourcePage->id, $slot->slot_type_id)
        ->whereNull('parent_id')
        ->values()
        ->flatMap(fn (Block $block) => $this->blockDeletionManager->recursiveDeleteOrder($block))
        ->unique('id')
        ->each(fn (Block $block) => $block->delete());

      $this->sourcePages->rebuildAssignments($sharedSlot);

      if ($this->revisionManager->revisionsTableExists()) {
        $sharedSlot->forceFill(['updated_by_user_id' => $request->user()?->id])->save();
        $this->revisionManager->capture(
          $sharedSlot->fresh(),
          $request->user(),
          'block_deleted',
          'Shared Slot blocks deleted',
          'Shared Slot block structure or content was updated by removing every block from the slot.',
        );
      }
    });

    return redirect()
      ->route('admin.shared-slots.blocks.edit', [
        'shared_slot' => $sharedSlot,
        'locale' => $validated['locale'] ?? null,
        'return_url' => $request->input('return_url'),
      ])
      ->with('status', 'Deleted all blocks from '.($slot->slotType?->name ?? 'slot').'.');
  }

  public function reorderBlocks(Request $request, SharedSlot $sharedSlot): JsonResponse
  {
    if (! $this->schema->sharedSlotsTableExists() || ! $this->schema->sharedSlotBlocksTableExists()) {
      return response()->json([
        'message' => 'Shared Slot block editing is not ready yet. Run the latest migrations before editing Shared Slot blocks.',
        'errors' => ['shared_slots' => ['Shared Slot block editing is not ready yet. Run the latest migrations before editing Shared Slot blocks.']],
      ], 409);
    }

    $this->authorization->abortUnlessSiteAccess($request->user(), $sharedSlot);
    abort_unless($this->canEditSharedSlot($request->user(), $sharedSlot), 403);

    $validated = $request->validate([
      'blocks' => ['required', 'array', 'min:1'],
      'blocks.*' => ['required', 'integer', 'distinct'],
    ]);

    $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
    $blockIds = collect($validated['blocks'])->map(fn (mixed $id) => (int) $id)->values();
    $blocks = Block::query()
      ->where('page_id', $sourcePage->id)
      ->whereIn('id', $blockIds)
      ->get(['id', 'parent_id', 'sort_order']);

    if ($blocks->count() !== $blockIds->count()) {
      return response()->json([
        'message' => 'Submitted blocks must belong to the current Shared Slot.',
        'errors' => ['blocks' => ['Submitted blocks must belong to the current Shared Slot.']],
      ], 422);
    }

    $parentIds = $blocks->pluck('parent_id')->uniqueStrict();

    if ($parentIds->count() !== 1) {
      return response()->json([
        'message' => 'Submitted blocks must belong to the same parent group.',
        'errors' => ['blocks' => ['Submitted blocks must belong to the same parent group.']],
      ], 422);
    }

    $parentId = $parentIds->first();
    $siblings = Block::query()
      ->where('page_id', $sourcePage->id)
      ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'))
      ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
      ->orderBy('sort_order')
      ->orderBy('id')
      ->get(['id', 'sort_order']);

    if ($siblings->count() !== $blockIds->count() || $siblings->pluck('id')->sort()->values()->all() !== $blockIds->sort()->values()->all()) {
      return response()->json([
        'message' => 'Submitted blocks must contain the full sibling group for one parent.',
        'errors' => ['blocks' => ['Submitted blocks must contain the full sibling group for one parent.']],
      ], 422);
    }

    DB::transaction(function () use ($sharedSlot, $sourcePage, $blockIds, $parentId): void {
      $siblings = Block::query()
        ->where('page_id', $sourcePage->id)
        ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'))
        ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
        ->orderBy('sort_order')
        ->orderBy('id')
        ->lockForUpdate()
        ->get(['id', 'sort_order']);

      $positionMap = $blockIds->flip();

      $siblings
        ->sortBy(fn (Block $block) => $positionMap->get($block->id))
        ->values()
        ->each(function (Block $block, int $index): void {
          if ($block->sort_order === $index) {
            return;
          }

          $block->update(['sort_order' => $index]);
        });

      $this->sourcePages->rebuildAssignments($sharedSlot);
      if ($this->revisionManager->revisionsTableExists()) {
        $sharedSlot->forceFill(['updated_by_user_id' => request()->user()?->id])->save();
        $this->revisionManager->capture(
          $sharedSlot->fresh(),
          request()->user(),
          'blocks_reordered',
          'Shared Slot blocks reordered',
          'Shared Slot block order was changed.',
        );
      }
    });

    return response()->json([
      'ok' => true,
      'message' => 'Saved',
    ]);
  }

  private function canManageSharedSlots($user): bool
  {
    return $user?->isSuperAdmin() || $user?->isSiteAdmin();
  }

  private function canEditSharedSlot($user, SharedSlot $sharedSlot): bool
  {
    $sourcePage = $this->sourcePages->ensureFor($sharedSlot);

    return $this->workflowManager->canEditContent($user, $sourcePage);
  }

  private function resolveSiteContext(Request $request, Collection $sites, bool $persist = true): array
  {
    $requestedSite = null;
    $hasRequestedSite = false;

    if ($request->query->has('site')) {
      $requestedSite = $request->query('site');
      $hasRequestedSite = true;
    } elseif ($request->hasSession()) {
      $requestedSite = $request->session()->get(self::SITE_CONTEXT_SESSION_KEY);
      $hasRequestedSite = $requestedSite !== null;
    }

    if ($hasRequestedSite) {
      $normalizedSite = is_string($requestedSite) ? trim($requestedSite) : (string) $requestedSite;

      if (Str::lower($normalizedSite) === 'all') {
        if ($persist && $request->hasSession()) {
          $request->session()->put(self::SITE_CONTEXT_SESSION_KEY, 'all');
        }

        return [null, 'all'];
      }

      if (ctype_digit($normalizedSite)) {
        $site = $sites->firstWhere('id', (int) $normalizedSite);

        if ($site) {
          if ($persist && $request->hasSession()) {
            $request->session()->put(self::SITE_CONTEXT_SESSION_KEY, (string) $site->id);
          }

          return [$site, (string) $site->id];
        }
      }
    }

    $defaultSite = $sites->firstWhere('is_primary', true) ?? $sites->first();

    if ($persist && $request->hasSession()) {
      if ($defaultSite) {
        $request->session()->put(self::SITE_CONTEXT_SESSION_KEY, (string) $defaultSite->id);
      } else {
        $request->session()->forget(self::SITE_CONTEXT_SESSION_KEY);
      }
    }

    return [$defaultSite, $defaultSite ? (string) $defaultSite->id : 'all'];
  }

  private function slotBlockModalState($page, $slot, $blocks, $blockTypes, $pickerBlockTypes, ?int $pickerParentId = null): array
  {
    $activeLocale = $this->slotEditorLocale($page);
    $editingBlockId = old('_slot_block_mode') === 'edit'
      ? (int) old('_slot_block_id')
      : request()->integer('edit');

    $editingBlock = $editingBlockId > 0
      ? $blocks->firstWhere('id', $editingBlockId)
      : null;

    if ($editingBlock) {
      $editingBlock = $this->blockTranslationResolver->resolve($editingBlock, $activeLocale);
      $selectedBlockType = $this->resolvedEditorBlockType($blockTypes, $editingBlock, old('block_type_id'));

      if ($selectedBlockType) {
        $editingBlock->block_type_id = $selectedBlockType->id;
        $editingBlock->type = $selectedBlockType->slug;
        $editingBlock->source_type = $selectedBlockType->source_type ?: $editingBlock->source_type;
        $editingBlock->setRelation('blockType', $selectedBlockType);
      }

      return [
        'mode' => 'edit',
        'block' => $editingBlock,
        'selectedBlockType' => $selectedBlockType,
        'selectedAsset' => $this->resolveSelectedAsset(old('media_id', old('asset_id', $editingBlock->media_id))),
        'selectedGalleryAssets' => $this->resolveGalleryAssets(old('gallery_media_ids', old('gallery_asset_ids', $editingBlock->galleryMediaIds()))),
        'selectedAttachmentAsset' => $this->resolveSelectedAsset(old('attachment_media_id', old('attachment_asset_id', $editingBlock->attachmentMedia()?->id))),
      ];
    }

    $selectedBlockTypeId = (int) old('block_type_id', request()->integer('block_type_id'));
    $selectedBlockType = $selectedBlockTypeId > 0 ? $pickerBlockTypes->firstWhere('id', $selectedBlockTypeId) : null;

    if (! $selectedBlockType) {
      return [
        'mode' => null,
        'block' => null,
        'selectedBlockType' => null,
        'selectedAsset' => null,
        'selectedGalleryAssets' => collect(),
        'selectedAttachmentAsset' => null,
      ];
    }

    $block = new Block;
    $block->page_id = $page->id;
    $block->parent_id = $pickerParentId;
    $block->slot_type_id = $slot->slot_type_id;
    $block->sort_order = $blocks->where('parent_id', $pickerParentId)->count();
    $block->status = 'published';
    $block->is_system = $selectedBlockType->is_system;
    $block->block_type_id = $selectedBlockType->id;
    $block->type = $selectedBlockType->slug;
    $block->slot = $slot->slotType?->slug;
    $block->source_type = $selectedBlockType->source_type ?: 'static';
    $block->setRelation('blockType', $selectedBlockType);
    $block->setRelation('slotType', $slot->slotType);
    $block->setAttribute('translation_state', in_array($selectedBlockType->slug, ['navigation-auto', 'menu'], true) ? 'shared' : 'missing');
    $block->setAttribute('resolved_locale_code', $activeLocale->code);

    return [
      'mode' => 'create',
      'block' => $block,
      'selectedBlockType' => $selectedBlockType,
      'selectedAsset' => $this->resolveSelectedAsset(old('media_id', old('asset_id'))),
      'selectedGalleryAssets' => $this->resolveGalleryAssets(old('gallery_media_ids', old('gallery_asset_ids', []))),
      'selectedAttachmentAsset' => $this->resolveSelectedAsset(old('attachment_media_id', old('attachment_asset_id'))),
    ];
  }

  private function resolveSelectedAsset(mixed $assetId): ?Media
  {
    $resolvedId = (int) $assetId;

    if ($resolvedId <= 0) {
      return null;
    }

    return $this->authorization->scopeMediaForUser(Media::query(), request()->user())
      ->find($resolvedId);
  }

  private function resolveGalleryAssets(mixed $assetIds)
  {
    $resolvedIds = collect(is_array($assetIds) ? $assetIds : [])
      ->map(fn ($id) => (int) $id)
      ->filter(fn ($id) => $id > 0)
      ->values();

    if ($resolvedIds->isEmpty()) {
      return collect();
    }

    return $this->authorization->scopeMediaForUser(Media::query(), request()->user())
      ->whereIn('id', $resolvedIds)
      ->get()
      ->sortBy(fn (Media $asset) => $resolvedIds->search($asset->id))
      ->values();
  }

  private function slotParentBlocks($blocks, ?Block $editedBlock = null)
  {
    $ignoredIds = collect();

    if ($editedBlock?->id) {
      $ignoredIds = $this->descendantIdsFor($blocks, $editedBlock->id)->prepend($editedBlock->id);
    }

    return $blocks
      ->reject(fn (Block $block) => $block->isColumnItem())
      ->reject(fn (Block $block) => $ignoredIds->contains($block->id))
      ->filter(fn (Block $block) => $block->canAcceptChildren())
      ->filter(fn (Block $block) => ! $editedBlock || $block->canAcceptChildType($editedBlock->typeSlug()))
      ->map(fn (Block $block) => [
        'id' => $block->id,
        'label' => str_repeat('— ', $this->blockDepth($block)).$block->parentCandidateLabel(),
      ])
      ->values();
  }

  private function slotBlockDeleteModalState($blocks): array
  {
    $deleteBlockId = request()->integer('delete');
    $deleteBlock = $deleteBlockId > 0 ? $blocks->firstWhere('id', $deleteBlockId) : null;

    if (! $deleteBlock) {
      return ['block' => null, 'meta' => null];
    }

    return [
      'block' => $deleteBlock,
      'meta' => $this->blockDeletionManager->metadata($deleteBlock),
    ];
  }

  private function pickerBlockTypes($blocks, $blockTypes, ?int $parentId = null)
  {
    $blockTypes = $this->visiblePickerBlockTypesFor(request()->user(), $blockTypes);

    if (! $parentId) {
      return $blockTypes
        ->reject(fn ($blockType) => in_array($blockType->slug, ['sidebar-nav-item', 'sidebar-nav-group', 'card_header', 'card_body', 'card_footer', 'slide'], true))
        ->values();
    }

    $parentBlock = $blocks->firstWhere('id', $parentId);

    if (! $parentBlock || ! $parentBlock->canAcceptChildren()) {
      return collect();
    }

    return $blockTypes
      ->filter(fn ($blockType) => $parentBlock->canAcceptChildType($blockType->slug))
      ->values();
  }

  private function visiblePickerBlockTypesFor(?User $user, $blockTypes)
  {
    if ($user?->isSuperAdmin()) {
      return $blockTypes;
    }

    return $blockTypes
      ->reject(fn (BlockType $blockType) => $blockType->slug === 'html')
      ->values();
  }

  private function pickerCategory($pickerBlockTypes): ?string
  {
    $requestedCategory = trim((string) request('block_type_category'));

    if ($requestedCategory === '') {
      return null;
    }

    $validCategories = $pickerBlockTypes
      ->map(fn ($blockType) => trim((string) ($blockType->category ?? '')))
      ->filter()
      ->unique()
      ->values();

    return $validCategories->contains($requestedCategory) ? $requestedCategory : null;
  }

  private function resolvedEditorBlockType($publishedBlockTypes, Block $block, mixed $requestedBlockTypeId = null): ?BlockType
  {
    $requestedId = (int) $requestedBlockTypeId;

    if ($requestedId > 0) {
      $requestedBlockType = $publishedBlockTypes->firstWhere('id', $requestedId)
        ?? BlockType::query()->find($requestedId);

      if ($requestedBlockType) {
        return $requestedBlockType;
      }
    }

    $blockTypeId = (int) ($block->block_type_id ?? 0);

    if ($blockTypeId > 0) {
      $resolvedById = $publishedBlockTypes->firstWhere('id', $blockTypeId)
        ?? $block->blockType
        ?? BlockType::query()->find($blockTypeId);

      if ($resolvedById) {
        return $resolvedById;
      }
    }

    $typeSlug = trim((string) $block->typeSlug());

    if ($typeSlug === '') {
      return null;
    }

    return $publishedBlockTypes->firstWhere('slug', $typeSlug)
      ?? BlockType::query()->where('slug', $typeSlug)->first();
  }

  private function descendantIdsFor($blocks, int $blockId)
  {
    $childrenByParent = $blocks->groupBy('parent_id');
    $ids = collect();
    $stack = collect([$blockId]);

    while ($stack->isNotEmpty()) {
      $currentId = $stack->pop();
      $children = $childrenByParent->get($currentId, collect());

      foreach ($children as $child) {
        if ($ids->contains($child->id)) {
          continue;
        }

        $ids->push($child->id);
        $stack->push($child->id);
      }
    }

    return $ids->values();
  }

  private function blockDepth(Block $block): int
  {
    $depth = 0;
    $cursor = $block->parent;

    while ($cursor) {
      $depth++;
      $cursor = $cursor->parent;
    }

    return $depth;
  }

  private function slotBlockRelations(): array
  {
    return [
      'blockType',
      'slotType',
      'blockAssets.asset',
      'textTranslations',
      'buttonTranslations',
      'imageTranslations',
      'contactFormTranslations',
      'children' => fn ($query) => $query
        ->with($this->slotBlockRelations())
        ->orderBy('sort_order'),
    ];
  }

  private function slotExpandedBlockIds($blocks, ?Block $modalBlock = null)
  {
    $toggleableIds = $blocks->pluck('id');

    $expandedIds = collect(session('slot_block_expanded', []))
      ->map(fn (mixed $value) => (int) $value)
      ->filter(fn (int $value) => $value > 0);

    if ($modalBlock) {
      $ancestorId = $modalBlock->parent_id;

      while ($ancestorId) {
        $expandedIds->push($ancestorId);
        $ancestorId = $blocks->firstWhere('id', $ancestorId)?->parent_id;
      }

      if ($modalBlock->id && $toggleableIds->contains($modalBlock->id)) {
        $expandedIds->push($modalBlock->id);
      }
    }

    return $expandedIds
      ->unique()
      ->values()
      ->intersect($toggleableIds)
      ->values();
  }

  private function slotEditorLocale($page): Locale
  {
    $requestedCode = Locale::normalizeCode(request('locale'));
    $siteLocales = $page->availableSiteLocales();

    if ($requestedCode !== null) {
      $requestedLocale = $siteLocales->firstWhere('code', $requestedCode);

      if ($requestedLocale) {
        return $requestedLocale;
      }

      abort(404);
    }

    return $siteLocales->firstWhere('is_default', true)
      ?? Locale::query()->where('is_default', true)->firstOrFail();
  }

  private function assetPickerAssets()
  {
    return $this->authorization->scopeMediaForUser(Media::query(), request()->user())
      ->with('folder')
      ->latest()
      ->get();
  }

  private function assetPickerFolders()
  {
    return MediaFolder::query()
      ->withCount('assets')
      ->with('parent')
      ->orderBy('name')
      ->get();
  }

  private function sharedSlotsNotReadyRedirect(?string $url = null, ?string $message = null): RedirectResponse
  {
    return redirect()
      ->to($url ?? route('admin.shared-slots.index'))
      ->withErrors(['shared_slots' => $message ?? 'Shared Slots are not ready yet. Run the latest migrations before using Shared Slots.']);
  }
}
