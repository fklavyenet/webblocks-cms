<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Blocks\BlockDeletionManager;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Icons\IconCatalog;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotRevisionManager;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class BlockController extends Controller
{
  public function __construct(
    private readonly BlockPayloadWriter $blockPayloadWriter,
    private readonly BlockDeletionManager $blockDeletionManager,
    private readonly BlockTranslationResolver $blockTranslationResolver,
    private readonly PageRevisionManager $revisionManager,
    private readonly PageWorkflowManager $workflowManager,
    private readonly SharedSlotRevisionManager $sharedSlotRevisionManager,
    private readonly AdminAuthorization $authorization,
    private readonly SharedSlotSourcePageManager $sharedSlotSourcePages,
  ) {}

  public function moveUp(Block $block): RedirectResponse
  {
    [$sharedSlot, $page] = $this->editingContext($block);
    $this->authorization->abortUnlessSiteAccess(request()->user(), $sharedSlot ?? $block);
    abort_unless($this->workflowManager->canEditContent(request()->user(), $page), 403);

    return $this->move($block, 'up');
  }

  public function moveDown(Block $block): RedirectResponse
  {
    [$sharedSlot, $page] = $this->editingContext($block);
    $this->authorization->abortUnlessSiteAccess(request()->user(), $sharedSlot ?? $block);
    abort_unless($this->workflowManager->canEditContent(request()->user(), $page), 403);

    return $this->move($block, 'down');
  }

  public function index(Request $request): View
  {
    abort_unless($request->user()?->can('access-system'), 403);

    $filters = $this->blockIndexFilters($request);
    $pageId = $filters['page_id'] !== '' ? (int) $filters['page_id'] : null;
    $localeId = $this->localeIdForFilter($filters['locale']);
    $totalCount = $this->applyIndexFilters(
      $this->authorization->scopeBlocksForUser(Block::query(), $request->user()),
      array_merge($filters, [
        'search' => '',
        'block_type_id' => '',
        'status' => '',
        'locale' => '',
      ]),
      null,
    )->count();
    $blocks = $this->applyIndexFilters(
      $this->authorization->scopeBlocksForUser(Block::query(), $request->user())
        ->with(['page', 'parent', 'blockType', 'slotType', 'children']),
      $filters,
      $localeId,
    )
      ->with('page.translations')
      ->orderByDesc('id')
      ->paginate(AdminPagination::perPage())
      ->withQueryString();

    AdminPagination::redirectOutOfRange($blocks, $request);

    $currentPage = $pageId
      ? Page::query()->with(['site', 'translations.locale'])->find($pageId)
      : null;

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.index', [
      'blocks' => $blocks,
      'currentPage' => $currentPage,
      'filters' => $filters,
      'filterSites' => $this->blockIndexSiteOptions(),
      'filterPages' => $this->blockIndexPageOptions($filters['site']),
      'filterBlockTypes' => $this->blockIndexBlockTypeOptions(),
      'filterLocales' => $this->blockIndexLocaleOptions(),
      'hasActiveFilters' => $this->hasActiveIndexFilters($filters),
      'totalCount' => $totalCount,
      'filteredCount' => $blocks->total(),
    ]);
  }

  public function create(Request $request): View
  {
    if ($request->filled('page_id') && $request->filled('slot_type_id')) {
      if ($sharedSlot = $this->sharedSlotForPageId($request->integer('page_id'))) {
        return redirect()->route('admin.shared-slots.blocks.edit', array_filter([
          'shared_slot' => $sharedSlot,
          'block_type_id' => $request->integer('block_type_id') ?: null,
          'picker' => $request->integer('block_type_id') ? 1 : null,
          'locale' => $this->requestedLocaleCode($request),
          'return_url' => $request->input('return_url'),
        ], fn ($value) => $value !== null))->throwResponse();
      }

      $pageSlotId = $this->pageSlotRouteId($request->integer('page_id'), $request->integer('slot_type_id'));

      if ($pageSlotId) {
        return redirect()->route('admin.pages.slots.blocks', [
          'page' => $request->integer('page_id'),
          'slot' => $pageSlotId,
          'block_type_id' => $request->integer('block_type_id') ?: null,
          'return_url' => $request->input('return_url'),
        ])->throwResponse();
      }
    }

    $block = new Block;
    $block->page_id = $request->integer('page_id') ?: null;
    $block->parent_id = $request->integer('parent_id') ?: null;
    $block->block_type_id = $request->integer('block_type_id') ?: null;
    $block->slot_type_id = $request->integer('slot_type_id') ?: null;
    $pages = $this->authorization->scopePagesForUser(Page::query(), request()->user())
      ->with(['blocks', 'translations'])
      ->orderByDefaultTranslation('name')
      ->get();
    $blockTypes = app(PluginBlockCatalog::class)->filterDiscoverableBlockTypes(
      BlockType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get()
    );
    $slotTypes = SlotType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get();
    $assetPickerAssets = $this->assetPickerAssets();
    $selectedAsset = $block->media_id
      ? $this->authorization->scopeMediaForUser(Media::query(), $request->user())->find($block->media_id)
      : null;
    $selectedGalleryAssets = $block->galleryAssets();
    $selectedAttachmentAsset = $block->attachmentAsset();
    $selectedBlockType = $this->selectedBlockType($request, $block, $blockTypes);

    if ($selectedBlockType) {
      $block->block_type_id = $selectedBlockType->id;
      $block->type = $selectedBlockType->slug;
      $block->source_type = $selectedBlockType->source_type ?: 'static';
    }

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.create', [
      'block' => $block,
      'pages' => $pages,
      'parentBlocks' => $this->parentBlocksFor($block->page_id),
      'columnItemBlockType' => $blockTypes->firstWhere('slug', 'column_item'),
      'featureItemBlockType' => $blockTypes->firstWhere('slug', 'feature-item'),
      'linkListItemBlockType' => $blockTypes->firstWhere('slug', 'link-list-item'),
      'blockTypes' => $blockTypes,
      'slotTypes' => $slotTypes,
      'assetPickerAssets' => $assetPickerAssets,
      'assetPickerFolders' => $this->assetPickerFolders(),
      'selectedAsset' => $selectedAsset,
      'selectedGalleryAssets' => $selectedGalleryAssets,
      'selectedAttachmentAsset' => $selectedAttachmentAsset,
      'selectedBlockType' => $selectedBlockType,
    ]);
  }

  public function store(BlockRequest $request): RedirectResponse
  {
    $data = $request->validatedData();
    $localeCode = $data['locale'] ?? null;
    $columnItems = $this->builderChildItemsFrom($request, 'column_items', true);
    $featureItems = $this->builderChildItemsFrom($request, 'feature_items');
    $linkListItems = $this->builderChildItemsFrom($request, 'link_list_items', true);
    $sharedSlot = $this->sharedSlotContext($request, null, (int) $data['page_id']);
    $page = $this->editablePageFromRequest($request, $sharedSlot, (int) $data['page_id']);
    $this->authorization->abortUnlessSiteAccess($request->user(), $sharedSlot ?? $page);
    abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

    $block = DB::transaction(function () use ($page, $columnItems, $featureItems, $linkListItems, $data, $localeCode, $sharedSlot) {
      $block = $this->blockPayloadWriter->save(new Block, $page, $data, $localeCode);
      $this->syncColumnItems($block, $columnItems, $localeCode);
      $this->syncFeatureItems($block, $featureItems, $localeCode);
      $this->syncLinkListItems($block, $linkListItems, $localeCode);

      if ($sharedSlot) {
        $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);
        $sharedSlot->forceFill(['updated_by_user_id' => request()->user()?->id])->save();
        $this->sharedSlotRevisionManager->capture(
          $sharedSlot->fresh(),
          request()->user(),
          'block_created',
          'Shared Slot block created',
          'Shared Slot block structure or content was updated by adding a block.',
        );
      } else {
        $page->forceFill(['updated_by_user_id' => request()->user()?->id])->save();
        $this->revisionManager->capture(
          $page->fresh(),
          request()->user(),
          'Block created',
          'Page block structure or content was updated by adding a block.',
          event: 'block_created',
        );
      }

      return $block;
    });

    if ($sharedSlot) {
      return redirect()
        ->route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => $localeCode, 'return_url' => $request->input('return_url')])
        ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
        ->with('status', 'Block created successfully.');
    }

    $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);
    $previewUrl = $block->page->publicUrl($localeCode);

    $redirect = redirect()
      ->route('admin.pages.slots.blocks', ['page' => $block->page_id, 'slot' => $pageSlotId ?: $block->slot_type_id, 'locale' => $localeCode, 'return_url' => $request->input('return_url')])
      ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
      ->with('status', 'Block created successfully.');

    if ($previewUrl) {
      $redirect->with('status_action', [
        'label' => 'View page',
        'url' => $previewUrl,
      ]);
    }

    return $redirect;
  }

  public function edit(Request $request, Block $block): View
  {
    [$sharedSlot, $page] = $this->editingContext($block);
    $this->authorization->abortUnlessSiteAccess($request->user(), $sharedSlot ?? $block);
    abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

    if ($block->supportsTranslations()) {
      $defaultLocale = $block->page?->availableSiteLocales()->firstWhere('is_default', true);
      $block = $this->blockTranslationResolver->resolve($block, $defaultLocale);
    }

    if ($sharedSlot) {
      return redirect()->route('admin.shared-slots.blocks.edit', [
        'shared_slot' => $sharedSlot,
        'edit' => $block->id,
        'return_url' => $request->input('return_url'),
      ])->throwResponse();
    }

    if ($block->page_id && $block->slot_type_id) {
      $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);

      if ($pageSlotId) {
        return redirect()->route('admin.pages.slots.blocks', [
          'page' => $block->page_id,
          'slot' => $pageSlotId,
          'edit' => $block->id,
          'return_url' => $request->input('return_url'),
        ])->throwResponse();
      }
    }

    $pages = $this->authorization->scopePagesForUser(Page::query(), $request->user())
      ->with(['blocks', 'translations'])
      ->orderByDefaultTranslation('name')
      ->get();
    $blockTypes = app(PluginBlockCatalog::class)->filterDiscoverableBlockTypes(
      BlockType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get()
    );
    $slotTypes = SlotType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get();
    $assetPickerAssets = $this->assetPickerAssets();
    $selectedAsset = $block->media_id
      ? $this->authorization->scopeMediaForUser(Media::query(), $request->user())->find($block->media_id)
      : null;
    $selectedGalleryAssets = $block->galleryAssets();
    $selectedAttachmentAsset = $block->attachmentAsset();
    $selectedBlockType = $this->selectedBlockType($request, $block, $blockTypes);

    if ($selectedBlockType) {
      $block->block_type_id = $selectedBlockType->id;
      $block->type = $selectedBlockType->slug;
      $block->source_type = $selectedBlockType->source_type ?: $block->source_type;
      $block->setRelation('blockType', $selectedBlockType);
    }

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.blocks.edit', [
      'block' => $block,
      'pages' => $pages,
      'parentBlocks' => $this->parentBlocksFor($block->page_id, $block->id),
      'columnItemBlockType' => $blockTypes->firstWhere('slug', 'column_item'),
      'featureItemBlockType' => $blockTypes->firstWhere('slug', 'feature-item'),
      'linkListItemBlockType' => $blockTypes->firstWhere('slug', 'link-list-item'),
      'blockTypes' => $blockTypes,
      'slotTypes' => $slotTypes,
      'assetPickerAssets' => $assetPickerAssets,
      'assetPickerFolders' => $this->assetPickerFolders(),
      'selectedAsset' => $selectedAsset,
      'selectedGalleryAssets' => $selectedGalleryAssets,
      'selectedAttachmentAsset' => $selectedAttachmentAsset,
      'selectedBlockType' => $selectedBlockType,
    ]);
  }

  public function update(BlockRequest $request, Block $block): RedirectResponse
  {
    [$contextSharedSlot, $existingPage] = $this->editingContext($block);
    $this->authorization->abortUnlessSiteAccess($request->user(), $contextSharedSlot ?? $block);
    $data = $request->validatedData();
    $localeCode = $data['locale'] ?? null;
    $columnItems = $this->builderChildItemsFrom($request, 'column_items', true);
    $featureItems = $this->builderChildItemsFrom($request, 'feature_items');
    $linkListItems = $this->builderChildItemsFrom($request, 'link_list_items', true);
    $sharedSlot = $this->sharedSlotContext($request, $contextSharedSlot, (int) $data['page_id']);
    $page = $this->editablePageFromRequest($request, $sharedSlot, (int) $data['page_id']);
    $this->authorization->abortUnlessSiteAccess($request->user(), $sharedSlot ?? $page);
    abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

    DB::transaction(function () use ($block, $page, $columnItems, $featureItems, $linkListItems, $data, $localeCode, $sharedSlot): void {
      $this->blockPayloadWriter->save($block, $page, $data, $localeCode);
      $this->syncColumnItems($block, $columnItems, $localeCode);
      $this->syncFeatureItems($block, $featureItems, $localeCode);
      $this->syncLinkListItems($block, $linkListItems, $localeCode);

      if ($sharedSlot) {
        $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);
        $sharedSlot->forceFill(['updated_by_user_id' => request()->user()?->id])->save();
        $this->sharedSlotRevisionManager->capture(
          $sharedSlot->fresh(),
          request()->user(),
          'block_updated',
          'Shared Slot block updated',
          'Shared Slot block structure or content was updated.',
        );
      } else {
        $page->forceFill(['updated_by_user_id' => request()->user()?->id])->save();
        $this->revisionManager->capture(
          $page->fresh(),
          request()->user(),
          'Block updated',
          'Page block structure or content was updated.',
          event: 'block_updated',
        );
      }
    });

    if ($sharedSlot) {
      return redirect()
        ->route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => $localeCode, 'return_url' => $request->input('return_url')])
        ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
        ->with('status', 'Block updated successfully.');
    }

    $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);
    $previewUrl = $block->page->publicUrl($localeCode);

    $redirect = redirect()
      ->route('admin.pages.slots.blocks', ['page' => $block->page_id, 'slot' => $pageSlotId ?: $block->slot_type_id, 'locale' => $localeCode, 'return_url' => $request->input('return_url')])
      ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
      ->with('status', 'Block updated successfully.');

    if ($previewUrl) {
      $redirect->with('status_action', [
        'label' => 'View page',
        'url' => $previewUrl,
      ]);
    }

    return $redirect;
  }

  public function destroy(Request $request, Block $block): RedirectResponse
  {
    [$sharedSlot, $page] = $this->editingContext($block);
    $this->authorization->abortUnlessSiteAccess($request->user(), $sharedSlot ?? $block);
    abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);
    $validated = $request->validate([
      'delete_descendants' => ['nullable', 'boolean'],
    ]);
    $deleteDescendants = (bool) ($validated['delete_descendants'] ?? false);
    $pageId = $block->page_id;
    $slotTypeId = $block->slot_type_id;
    $pageSlotId = $this->pageSlotRouteId($pageId, $slotTypeId);

    DB::transaction(function () use ($block, $request, $sharedSlot, $deleteDescendants): void {
      $page = $block->page()->firstOrFail();

      if ($deleteDescendants) {
        $this->blockDeletionManager
          ->recursiveDeleteOrder($block)
          ->each(fn (Block $candidate) => $candidate->delete());
      } else {
        $block->delete();
      }

      if ($sharedSlot) {
        $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);
        $sharedSlot->forceFill(['updated_by_user_id' => $request->user()?->id])->save();
        $this->sharedSlotRevisionManager->capture(
          $sharedSlot->fresh(),
          $request->user(),
          'block_deleted',
          'Shared Slot block deleted',
          'Shared Slot block structure or content was updated by removing a block.',
        );
      } else {
        $page->forceFill(['updated_by_user_id' => $request->user()?->id])->save();
        $this->revisionManager->capture(
          $page->fresh(),
          $request->user(),
          'Block deleted',
          'Page block structure or content was updated by removing a block.',
          event: 'block_deleted',
        );
      }
    });

    if ($sharedSlot) {
      return redirect()
        ->route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => $this->requestedLocaleCode(request()), 'return_url' => request('return_url')])
        ->with('slot_block_expanded', $this->slotExpandedBlockIds($block, false))
        ->with('status', $deleteDescendants ? 'Block and nested child blocks deleted.' : 'Block deleted.');
    }

    return redirect()
      ->route('admin.pages.slots.blocks', ['page' => $pageId, 'slot' => $pageSlotId ?: $slotTypeId, 'locale' => $this->requestedLocaleCode(request()), 'return_url' => request('return_url')])
      ->with('slot_block_expanded', $this->slotExpandedBlockIds($block, false))
      ->with('status', $deleteDescendants ? 'Block and nested child blocks deleted.' : 'Block deleted.');
  }

  private function move(Block $block, string $direction): RedirectResponse
  {
    [$sharedSlot] = $this->editingContext($block);
    $moved = DB::transaction(function () use ($block, $direction, $sharedSlot): bool {
      $siblings = Block::query()
        ->where('page_id', $block->page_id)
        ->where('slot_type_id', $block->slot_type_id)
        ->where('parent_id', $block->parent_id)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->lockForUpdate()
        ->get()
        ->values();

      $currentIndex = $siblings->search(fn (Block $candidate) => $candidate->id === $block->id);

      if (! is_int($currentIndex)) {
        return false;
      }

      $swapIndex = $direction === 'up'
        ? $currentIndex - 1
        : $currentIndex + 1;

      if ($swapIndex < 0 || $swapIndex >= $siblings->count()) {
        return false;
      }

      $orderedSiblings = $siblings->all();
      $currentSibling = $orderedSiblings[$currentIndex];
      $orderedSiblings[$currentIndex] = $orderedSiblings[$swapIndex];
      $orderedSiblings[$swapIndex] = $currentSibling;

      foreach ($orderedSiblings as $index => $sibling) {
        if ($sibling->sort_order === $index) {
          continue;
        }

        $sibling->update(['sort_order' => $index]);
      }

      if ($sharedSlot) {
        $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);
        $sharedSlot->forceFill(['updated_by_user_id' => request()->user()?->id])->save();
        $this->sharedSlotRevisionManager->capture(
          $sharedSlot->fresh(),
          request()->user(),
          'blocks_reordered',
          'Shared Slot blocks reordered',
          'Shared Slot block order was changed.',
        );
      } else {
        $page = $block->page()->firstOrFail();
        $page->forceFill(['updated_by_user_id' => request()->user()?->id])->save();
        $this->revisionManager->capture(
          $page->fresh(),
          request()->user(),
          'Block order updated',
          'Page block order was changed.',
          event: 'block_reordered',
        );
      }

      return true;
    });

    if ($sharedSlot) {
      if (! $moved) {
        return redirect()
          ->route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => $this->requestedLocaleCode(request()), 'return_url' => request('return_url')])
          ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
          ->with('status', 'Block is already at the edge of its group.');
      }

      return redirect()
        ->route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => $this->requestedLocaleCode(request()), 'return_url' => request('return_url')])
        ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
        ->with('status', 'Block order updated successfully.');
    }

    if (! $moved) {
      return redirect()
        ->route('admin.pages.slots.blocks', $this->slotRedirectParameters($block))
        ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
        ->with('status', 'Block is already at the edge of its group.');
    }

    return redirect()
      ->route('admin.pages.slots.blocks', $this->slotRedirectParameters($block))
      ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
      ->with('status', 'Block order updated successfully.');
  }

  private function sharedSlotFromRequest(Request $request): ?SharedSlot
  {
    $sharedSlotId = $request->integer('shared_slot_id');

    if ($sharedSlotId <= 0) {
      return null;
    }

    $sharedSlot = SharedSlot::query()->findOrFail($sharedSlotId);

    return $sharedSlot;
  }

  private function sharedSlotContext(Request $request, ?SharedSlot $contextSharedSlot = null, ?int $pageId = null): ?SharedSlot
  {
    $requestedSharedSlot = $this->sharedSlotFromRequest($request);
    $pageSharedSlot = $this->sharedSlotForPageId($pageId);

    if ($requestedSharedSlot && $contextSharedSlot && $requestedSharedSlot->isNot($contextSharedSlot)) {
      abort(403);
    }

    if ($requestedSharedSlot && $pageSharedSlot && $requestedSharedSlot->isNot($pageSharedSlot)) {
      abort(403);
    }

    if ($contextSharedSlot && $pageSharedSlot && $contextSharedSlot->isNot($pageSharedSlot)) {
      abort(403);
    }

    return $requestedSharedSlot ?? $contextSharedSlot ?? $pageSharedSlot;
  }

  private function sharedSlotForPageId(?int $pageId): ?SharedSlot
  {
    if (! $pageId || $pageId < 1) {
      return null;
    }

    $page = Page::query()->find($pageId);

    if (! $page?->isSharedSlotSourcePage()) {
      return null;
    }

    $sharedSlotId = (int) data_get($page->settings, 'shared_slot_id');

    return $sharedSlotId > 0
      ? SharedSlot::query()->find($sharedSlotId)
      : null;
  }

  private function editablePageFromRequest(Request $request, ?SharedSlot $sharedSlot, int $pageId): Page
  {
    if ($sharedSlot) {
      $page = $this->sharedSlotSourcePages->ensureFor($sharedSlot);

      abort_unless((int) $page->id === $pageId, 403);

      return $page;
    }

    return $this->authorization->scopePagesForUser(Page::query(), $request->user())->findOrFail($pageId);
  }

  private function editingContext(Block $block): array
  {
    $page = $block->page()->with('site.locales', 'translations')->firstOrFail();

    if (! $page->isSharedSlotSourcePage()) {
      return [null, $page];
    }

    $sharedSlotId = (int) data_get($page->settings, 'shared_slot_id');
    $sharedSlot = $sharedSlotId > 0 ? SharedSlot::query()->find($sharedSlotId) : null;

    return [$sharedSlot, $page];
  }

  private function parentBlocksFor(?int $pageId, ?int $ignoreId = null)
  {
    if (! $pageId) {
      return collect();
    }

    $page = $this->authorization->scopePagesForUser(Page::query(), request()->user())
      ->with('site.locales')
      ->find($pageId);
    $defaultLocale = $page?->availableSiteLocales()->firstWhere('is_default', true);

    $blocks = $this->authorization->scopeBlocksForUser(Block::query(), request()->user())
      ->where('page_id', $pageId)
      ->with([
        'children',
        'textTranslations',
        'buttonTranslations',
        'imageTranslations',
        'contactFormTranslations',
        'children.textTranslations',
        'children.buttonTranslations',
        'children.imageTranslations',
        'children.contactFormTranslations',
      ])
      ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
      ->orderBy('sort_order')
      ->get();

    if ($defaultLocale) {
      $blocks = $this->blockTranslationResolver->resolveCollection($blocks, $defaultLocale);
    }

    return $this->flattenBlockOptions($blocks->whereNull('parent_id'));
  }

  private function flattenBlockOptions($blocks, string $prefix = '')
  {
    return $blocks->flatMap(function ($block) use ($prefix) {
      if ($block->isBuilderManagedChild()) {
        return collect();
      }

      $current = $block->stringValueOrNull($block->title) ?? $block->typeName();
      $label = $prefix === '' ? $current : $prefix.' > '.$current;
      $item = collect([['id' => $block->id, 'label' => $label]]);

      $children = $this->flattenBlockOptions($block->children, $label);

      return $item->concat($children);
    });
  }

  private function selectedBlockType(Request $request, Block $block, $blockTypes): ?BlockType
  {
    $selectedId = $request->integer('block_type_id') ?: $block->block_type_id;

    if ($selectedId) {
      $selectedBlockType = $blockTypes->firstWhere('id', $selectedId)
        ?? $block->blockType
        ?? BlockType::query()->find($selectedId);

      if ($selectedBlockType) {
        return $selectedBlockType;
      }
    }

    $typeSlug = trim((string) ($block->typeSlug() ?? $block->type));

    if ($typeSlug === '') {
      return null;
    }

    return $blockTypes->firstWhere('slug', $typeSlug)
      ?? $block->blockType
      ?? BlockType::query()->where('slug', $typeSlug)->first();
  }

  private function builderChildItemsFrom(Request $request, string $inputKey, bool $includeSubtitle = false): array
  {
    return collect($request->input($inputKey, []))
      ->map(function ($item, int $index) {
        $title = trim((string) ($item['title'] ?? ''));
        $subtitle = trim((string) ($item['subtitle'] ?? ''));
        $content = trim((string) ($item['content'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        $iconSlug = app(IconCatalog::class)->normalizeSlug($item['icon_slug'] ?? null);
        $iconTone = app(PublicIconPresenter::class)->visualTone($item['icon_tone'] ?? null);
        $badgeLabel = trim((string) ($item['badge_label'] ?? ''));
        $badgeTone = trim((string) ($item['badge_tone'] ?? ''));
        $settings = array_filter([
          'icon_slug' => $iconSlug,
          'icon_tone' => $iconTone !== 'default' ? $iconTone : null,
          'badge_tone' => in_array($badgeTone, ['info', 'success', 'warning', 'danger'], true) ? $badgeTone : null,
        ], fn ($value) => $value !== null && $value !== '');

        return [
          'id' => ! empty($item['id']) ? (int) $item['id'] : null,
          'block_type_id' => ! empty($item['block_type_id']) ? (int) $item['block_type_id'] : null,
          'title' => $title !== '' ? $title : null,
          'eyebrow' => $badgeLabel !== '' ? $badgeLabel : null,
          'subtitle' => $subtitle !== '' ? $subtitle : null,
          'content' => $content !== '' ? $content : null,
          'url' => $url !== '' ? $url : null,
          'settings' => $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES),
          'status' => in_array(($item['status'] ?? 'published'), ['draft', 'published'], true) ? $item['status'] : 'published',
          'is_system' => (bool) ($item['is_system'] ?? false),
          'sort_order' => is_numeric($item['sort_order'] ?? null) ? (int) $item['sort_order'] : $index,
          '_delete' => (bool) ($item['_delete'] ?? false),
        ];
      })
      ->sortBy('sort_order')
      ->values()
      ->map(function (array $item) use ($includeSubtitle): array {
        if (! $includeSubtitle) {
          unset($item['subtitle']);
        }

        return $item;
      })
      ->all();
  }

  private function syncFeatureItems(Block $block, array $featureItems, ?string $localeCode = null): void
  {
    if (! $block->isFeatureGrid()) {
      return;
    }

    $existingItems = $block->children()->where('type', 'feature-item')->get()->keyBy('id');
    $keptIds = [];

    foreach (array_values($featureItems) as $index => $itemData) {
      $itemId = $itemData['id'] ?? null;
      $delete = (bool) ($itemData['_delete'] ?? false);
      $blockTypeId = $itemData['block_type_id'] ?? null;
      unset($itemData['id'], $itemData['_delete'], $itemData['block_type_id']);

      if ($delete) {
        if ($itemId && $existingItems->has($itemId)) {
          $existingItems[$itemId]->delete();
        }

        continue;
      }

      if (! $blockTypeId || blank($itemData['title']) || blank($itemData['content'])) {
        continue;
      }

      $blockType = BlockType::query()->find($blockTypeId);

      if (! $blockType || $blockType->slug !== 'feature-item') {
        continue;
      }

      $featureItem = $itemId && $existingItems->has($itemId)
        ? $existingItems[$itemId]
        : new Block;

      if ($localeCode !== null && $featureItem->exists) {
        $itemData['url'] = $featureItem->getRawOriginal('url');
      }

      $payload = $itemData + [
        'page_id' => $block->page_id,
        'parent_id' => $block->id,
        'block_type_id' => $blockType->id,
        'type' => $blockType->slug,
        'source_type' => $blockType->source_type ?? 'static',
        'slot_type_id' => $block->slot_type_id,
        'slot' => $block->slot,
        'sort_order' => $index,
      ];

      $featureItem = $this->blockPayloadWriter->save($featureItem, $block->page, $payload, $localeCode);

      $keptIds[] = $featureItem->id;
    }

    $staleItems = $block->children()->where('type', 'feature-item');

    if ($keptIds !== []) {
      $staleItems->whereNotIn('id', $keptIds);
    }

    $staleItems->delete();
  }

  private function syncColumnItems(Block $block, array $columnItems, ?string $localeCode = null): void
  {
    if (! $block->isColumnContainer()) {
      return;
    }

    $existingItems = $block->children()->where('type', 'column_item')->get()->keyBy('id');
    $keptIds = [];

    foreach (array_values($columnItems) as $index => $itemData) {
      $itemId = $itemData['id'] ?? null;
      $delete = (bool) ($itemData['_delete'] ?? false);
      $blockTypeId = $itemData['block_type_id'] ?? null;
      unset($itemData['id'], $itemData['_delete'], $itemData['block_type_id']);

      if ($delete) {
        if ($itemId && $existingItems->has($itemId)) {
          $existingItems[$itemId]->delete();
        }

        continue;
      }

      if (! $blockTypeId || blank($itemData['title']) || blank($itemData['content'])) {
        continue;
      }

      $blockType = BlockType::query()->find($blockTypeId);

      if (! $blockType || $blockType->slug !== 'column_item') {
        continue;
      }

      $columnItem = $itemId && $existingItems->has($itemId)
        ? $existingItems[$itemId]
        : new Block;

      if ($localeCode !== null && $columnItem->exists) {
        $itemData['url'] = $columnItem->getRawOriginal('url');
        $itemData['settings'] = $columnItem->getRawOriginal('settings');
      }

      $payload = $itemData + [
        'page_id' => $block->page_id,
        'parent_id' => $block->id,
        'block_type_id' => $blockType->id,
        'type' => $blockType->slug,
        'source_type' => $blockType->source_type ?? 'static',
        'slot_type_id' => $block->slot_type_id,
        'slot' => $block->slot,
        'sort_order' => $index,
      ];

      $columnItem = $this->blockPayloadWriter->save($columnItem, $block->page, $payload, $localeCode);

      $keptIds[] = $columnItem->id;
    }

    $staleItems = $block->children()->where('type', 'column_item');

    if ($keptIds !== []) {
      $staleItems->whereNotIn('id', $keptIds);
    }

    $staleItems->delete();
  }

  private function syncLinkListItems(Block $block, array $linkListItems, ?string $localeCode = null): void
  {
    if (! $block->isLinkList()) {
      return;
    }

    $existingItems = $block->children()->where('type', 'link-list-item')->get()->keyBy('id');
    $keptIds = [];

    foreach (array_values($linkListItems) as $index => $itemData) {
      $itemId = $itemData['id'] ?? null;
      $delete = (bool) ($itemData['_delete'] ?? false);
      $blockTypeId = $itemData['block_type_id'] ?? null;
      unset($itemData['id'], $itemData['_delete'], $itemData['block_type_id']);

      if ($delete) {
        if ($itemId && $existingItems->has($itemId)) {
          $existingItems[$itemId]->delete();
        }

        continue;
      }

      if (! $blockTypeId || $itemData['title'] === null || $itemData['url'] === null) {
        continue;
      }

      $blockType = BlockType::query()->find($blockTypeId);

      if (! $blockType || $blockType->slug !== 'link-list-item') {
        continue;
      }

      if ($localeCode !== null && $itemId && $existingItems->has($itemId)) {
        $itemData['url'] = $existingItems[$itemId]->getRawOriginal('url');
        $itemData['settings'] = $existingItems[$itemId]->getRawOriginal('settings');
      }

      $payload = $itemData + [
        'page_id' => $block->page_id,
        'parent_id' => $block->id,
        'block_type_id' => $blockType->id,
        'type' => $blockType->slug,
        'source_type' => $blockType->source_type ?? 'static',
        'slot_type_id' => $block->slot_type_id,
        'slot' => $block->slot,
        'sort_order' => $index,
      ];

      $linkListItem = $itemId && $existingItems->has($itemId)
        ? $existingItems[$itemId]
        : new Block;

      $linkListItem = $this->blockPayloadWriter->save($linkListItem, $block->page, $payload, $localeCode);

      $keptIds[] = $linkListItem->id;
    }

    $staleItems = $block->children()->where('type', 'link-list-item');

    if ($keptIds !== []) {
      $staleItems->whereNotIn('id', $keptIds);
    }

    $staleItems->delete();
  }

  private function pageSlotRouteId(?int $pageId, ?int $slotTypeId): ?int
  {
    if (! $pageId || ! $slotTypeId) {
      return null;
    }

    return PageSlot::query()
      ->where('page_id', $pageId)
      ->where('slot_type_id', $slotTypeId)
      ->value('id');
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

  private function slotExpandedBlockIds(Block $block, bool $includeCurrent = true): array
  {
    $expanded = collect();
    $ancestorId = $block->parent_id;

    while ($ancestorId) {
      $expanded->push($ancestorId);
      $ancestorId = Block::query()->whereKey($ancestorId)->value('parent_id');
    }

    if ($includeCurrent && $block->id) {
      $expanded->push($block->id);
    }

    return $expanded->unique()->values()->all();
  }

  private function slotRedirectParameters(Block $block): array
  {
    $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);

    return [
      'page' => $block->page_id,
      'slot' => $pageSlotId ?: $block->slot_type_id,
      'locale' => $this->requestedLocaleCode(request()),
      'return_url' => request('return_url'),
    ];
  }

  private function requestedLocaleCode(Request $request): ?string
  {
    $localeCode = trim((string) $request->input('locale', $request->query('locale', '')));

    return $localeCode !== '' ? $localeCode : null;
  }

  private function blockIndexFilters(Request $request): array
  {
    $status = trim((string) $request->query('status', ''));
    $localeCode = Locale::normalizeCode(trim((string) $request->query('locale', '')));

    return [
      'search' => trim((string) $request->query('search', '')),
      'site' => $this->normalizePositiveIntegerFilter($request->query('site')),
      'page_id' => $this->normalizePositiveIntegerFilter($request->query('page_id')),
      'block_type_id' => $this->normalizePositiveIntegerFilter($request->query('block_type_id')),
      'status' => in_array($status, ['draft', 'published'], true) ? $status : '',
      'locale' => $localeCode && Locale::query()->where('code', $localeCode)->exists() ? $localeCode : '',
    ];
  }

  private function normalizePositiveIntegerFilter(mixed $value): string
  {
    $resolved = is_scalar($value) ? trim((string) $value) : '';

    if ($resolved === '' || ! ctype_digit($resolved)) {
      return '';
    }

    return (int) $resolved > 0 ? $resolved : '';
  }

  private function localeIdForFilter(string $localeCode): ?int
  {
    if ($localeCode === '') {
      return null;
    }

    return Locale::query()->where('code', $localeCode)->value('id');
  }

  private function applyIndexFilters(Builder $query, array $filters, ?int $localeId): Builder
  {
    if ($filters['search'] !== '') {
      $term = '%'.$this->escapeLike($filters['search']).'%';

      $query->where(function (Builder $searchQuery) use ($term, $localeId): void {
        $searchQuery
          ->where((new Block)->qualifyColumn('id'), 'like', $term)
          ->orWhere((new Block)->qualifyColumn('type'), 'like', $term)
          ->orWhere((new Block)->qualifyColumn('title'), 'like', $term)
          ->orWhere((new Block)->qualifyColumn('subtitle'), 'like', $term)
          ->orWhere((new Block)->qualifyColumn('content'), 'like', $term)
          ->orWhere((new Block)->qualifyColumn('meta'), 'like', $term)
          ->orWhere((new Block)->qualifyColumn('url'), 'like', $term)
          ->orWhere((new Block)->qualifyColumn('slot'), 'like', $term)
          ->orWhere((new Block)->qualifyColumn('status'), 'like', $term)
          ->orWhereHas('blockType', function (Builder $blockTypeQuery) use ($term): void {
            $blockTypeQuery->where(function (Builder $candidateQuery) use ($term): void {
              $candidateQuery
                ->where('name', 'like', $term)
                ->orWhere('slug', 'like', $term);
            });
          })
          ->orWhereHas('page.translations', function (Builder $pageTranslationQuery) use ($term): void {
            $pageTranslationQuery->where(function (Builder $candidateQuery) use ($term): void {
              $candidateQuery
                ->where('name', 'like', $term)
                ->orWhere('slug', 'like', $term)
                ->orWhere('path', 'like', $term);
            });
          })
          ->orWhereHas('textTranslations', function (Builder $translationQuery) use ($term, $localeId): void {
            if ($localeId) {
              $translationQuery->where('locale_id', $localeId);
            }

            $translationQuery->where(function (Builder $candidateQuery) use ($term): void {
              $candidateQuery
                ->where('title', 'like', $term)
                ->orWhere('eyebrow', 'like', $term)
                ->orWhere('subtitle', 'like', $term)
                ->orWhere('content', 'like', $term)
                ->orWhere('meta', 'like', $term);
            });
          })
          ->orWhereHas('buttonTranslations', function (Builder $translationQuery) use ($term, $localeId): void {
            if ($localeId) {
              $translationQuery->where('locale_id', $localeId);
            }

            $translationQuery->where('title', 'like', $term);
          })
          ->orWhereHas('imageTranslations', function (Builder $translationQuery) use ($term, $localeId): void {
            if ($localeId) {
              $translationQuery->where('locale_id', $localeId);
            }

            $translationQuery->where(function (Builder $candidateQuery) use ($term): void {
              $candidateQuery
                ->where('caption', 'like', $term)
                ->orWhere('alt_text', 'like', $term);
            });
          })
          ->orWhereHas('contactFormTranslations', function (Builder $translationQuery) use ($term, $localeId): void {
            if ($localeId) {
              $translationQuery->where('locale_id', $localeId);
            }

            $translationQuery->where(function (Builder $candidateQuery) use ($term): void {
              $candidateQuery
                ->where('title', 'like', $term)
                ->orWhere('content', 'like', $term)
                ->orWhere('submit_label', 'like', $term)
                ->orWhere('success_message', 'like', $term);
            });
          });
      });
    }

    if ($filters['site'] !== '') {
      $query->whereHas('page', fn (Builder $pageQuery) => $pageQuery->where('site_id', (int) $filters['site']));
    }

    if ($filters['page_id'] !== '') {
      $query->where('page_id', (int) $filters['page_id']);
    }

    if ($filters['block_type_id'] !== '') {
      $query->where('block_type_id', (int) $filters['block_type_id']);
    }

    if ($filters['status'] !== '') {
      $query->where('status', $filters['status']);
    }

    if ($localeId) {
      $query->where(function (Builder $localeQuery) use ($localeId): void {
        $localeQuery
          ->whereHas('textTranslations', fn (Builder $translationQuery) => $translationQuery->where('locale_id', $localeId))
          ->orWhereHas('buttonTranslations', fn (Builder $translationQuery) => $translationQuery->where('locale_id', $localeId))
          ->orWhereHas('imageTranslations', fn (Builder $translationQuery) => $translationQuery->where('locale_id', $localeId))
          ->orWhereHas('contactFormTranslations', fn (Builder $translationQuery) => $translationQuery->where('locale_id', $localeId));
      });
    }

    return $query;
  }

  private function escapeLike(string $value): string
  {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
  }

  private function blockIndexSiteOptions(): array
  {
    return Site::query()
      ->whereHas('pages.blocks')
      ->primaryFirst()
      ->orderBy('name')
      ->get()
      ->mapWithKeys(fn (Site $site) => [(string) $site->id => $site->name])
      ->all();
  }

  private function blockIndexPageOptions(string $siteId): array
  {
    return Page::query()
      ->with(['translations.locale'])
      ->whereHas('blocks')
      ->when($siteId !== '', fn (Builder $query) => $query->where('site_id', (int) $siteId))
      ->orderByDesc('id')
      ->get()
      ->mapWithKeys(fn (Page $page) => [(string) $page->id => $this->blockIndexPageLabel($page)])
      ->all();
  }

  private function blockIndexPageLabel(Page $page): string
  {
    $title = trim((string) ($page->title ?? ''));
    $path = trim((string) ($page->defaultTranslation()?->path ?? ''));
    $parts = [$title !== '' ? $title : 'Page'];

    if ($path !== '') {
      $parts[] = $path;
    }

    $parts[] = '#'.$page->id;

    return implode(' | ', $parts);
  }

  private function blockIndexBlockTypeOptions(): array
  {
    return BlockType::query()
      ->whereHas('blocks')
      ->orderBy('sort_order')
      ->orderBy('name')
      ->get()
      ->mapWithKeys(fn (BlockType $blockType) => [(string) $blockType->id => $blockType->name])
      ->all();
  }

  private function blockIndexLocaleOptions(): array
  {
    return Locale::query()
      ->orderByDesc('is_default')
      ->orderBy('name')
      ->get()
      ->mapWithKeys(fn (Locale $locale) => [$locale->code => $locale->name.' ('.$locale->code.')'])
      ->all();
  }

  private function hasActiveIndexFilters(array $filters): bool
  {
    foreach ($filters as $value) {
      if ($value !== '') {
        return true;
      }
    }

    return false;
  }
}
