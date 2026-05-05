<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockRequest;
use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SharedSlot;
use App\Models\SlotType;
use App\Support\Blocks\BlockPayloadWriter;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\Pages\PageRevisionManager;
use App\Support\Pages\PageWorkflowManager;
use App\Support\SharedSlots\SharedSlotSourcePageManager;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BlockController extends Controller
{
    public function __construct(
        private readonly BlockPayloadWriter $blockPayloadWriter,
        private readonly BlockTranslationResolver $blockTranslationResolver,
        private readonly PageRevisionManager $revisionManager,
        private readonly PageWorkflowManager $workflowManager,
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

    public function index(): View
    {
        $pageId = request()->integer('page_id') ?: null;

        return view('admin.blocks.index', [
            'blocks' => $this->authorization->scopeBlocksForUser(Block::query(), request()->user())
                ->with(['page', 'parent', 'blockType', 'slotType', 'children'])
                ->when($pageId, fn ($query) => $query->where('page_id', $pageId))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'currentPage' => $pageId ? $this->authorization->scopePagesForUser(Page::query(), request()->user())->find($pageId) : null,
        ]);
    }

    public function create(Request $request): View
    {
        if ($request->filled('page_id') && $request->filled('slot_type_id')) {
            $pageSlotId = $this->pageSlotRouteId($request->integer('page_id'), $request->integer('slot_type_id'));

            if ($pageSlotId) {
                return redirect()->route('admin.pages.slots.blocks', [
                    'page' => $request->integer('page_id'),
                    'slot' => $pageSlotId,
                    'block_type_id' => $request->integer('block_type_id') ?: null,
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
        $blockTypes = BlockType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get();
        $slotTypes = SlotType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get();
        $assetPickerAssets = $this->assetPickerAssets();
        $selectedAsset = $block->asset_id
            ? $this->authorization->scopeAssetsForUser(Asset::query(), $request->user())->find($block->asset_id)
            : null;
        $selectedGalleryAssets = $block->galleryAssets();
        $selectedAttachmentAsset = $block->attachmentAsset();
        $selectedBlockType = $this->selectedBlockType($request, $block, $blockTypes);

        if ($selectedBlockType) {
            $block->block_type_id = $selectedBlockType->id;
            $block->type = $selectedBlockType->slug;
            $block->source_type = $selectedBlockType->source_type ?: 'static';
        }

        return view('admin.blocks.create', [
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
        $columnItems = $this->builderChildItemsFrom($request, 'column_items');
        $featureItems = $this->builderChildItemsFrom($request, 'feature_items');
        $linkListItems = $this->builderChildItemsFrom($request, 'link_list_items', true);
        $managedCtas = $this->managedCtasFrom($request);
        $sharedSlot = $this->sharedSlotFromRequest($request);
        $page = $this->editablePageFromRequest($request, $sharedSlot, (int) $data['page_id']);
        $this->authorization->abortUnlessSiteAccess($request->user(), $sharedSlot ?? $page);
        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

        $block = DB::transaction(function () use ($page, $columnItems, $featureItems, $linkListItems, $managedCtas, $data, $localeCode) {
            $block = $this->blockPayloadWriter->save(new Block, $page, $data, $localeCode);
            $this->syncColumnItems($block, $columnItems, $localeCode);
            $this->syncFeatureItems($block, $featureItems, $localeCode);
            $this->syncLinkListItems($block, $linkListItems, $localeCode);
            $this->syncManagedCtas($block, $managedCtas, $localeCode);

            $this->revisionManager->capture(
                $block->page()->firstOrFail(),
                request()->user(),
                'Block created',
                'Page block structure or content was updated by adding a block.',
            );

            return $block;
        });

        if ($sharedSlot) {
            $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);

            return redirect()
                ->route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => $localeCode])
                ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
                ->with('status', 'Block created successfully.');
        }

        $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);
        $previewUrl = $block->page->publicUrl($localeCode);

        $redirect = redirect()
            ->route('admin.pages.slots.blocks', ['page' => $block->page_id, 'slot' => $pageSlotId ?: $block->slot_type_id, 'locale' => $localeCode])
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
            ])->throwResponse();
        }

        if ($block->page_id && $block->slot_type_id) {
            $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);

            if ($pageSlotId) {
                return redirect()->route('admin.pages.slots.blocks', [
                    'page' => $block->page_id,
                    'slot' => $pageSlotId,
                    'edit' => $block->id,
                ])->throwResponse();
            }
        }

        $pages = $this->authorization->scopePagesForUser(Page::query(), $request->user())
            ->with(['blocks', 'translations'])
            ->orderByDefaultTranslation('name')
            ->get();
        $blockTypes = BlockType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get();
        $slotTypes = SlotType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get();
        $assetPickerAssets = $this->assetPickerAssets();
        $selectedAsset = $block->asset_id
            ? $this->authorization->scopeAssetsForUser(Asset::query(), $request->user())->find($block->asset_id)
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

        return view('admin.blocks.edit', [
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
        $columnItems = $this->builderChildItemsFrom($request, 'column_items');
        $featureItems = $this->builderChildItemsFrom($request, 'feature_items');
        $linkListItems = $this->builderChildItemsFrom($request, 'link_list_items', true);
        $managedCtas = $this->managedCtasFrom($request);
        $sharedSlot = $this->sharedSlotFromRequest($request) ?? $contextSharedSlot;
        $page = $this->editablePageFromRequest($request, $sharedSlot, (int) $data['page_id']);
        $this->authorization->abortUnlessSiteAccess($request->user(), $sharedSlot ?? $page);
        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

        DB::transaction(function () use ($block, $page, $columnItems, $featureItems, $linkListItems, $managedCtas, $data, $localeCode): void {
            $this->blockPayloadWriter->save($block, $page, $data, $localeCode);
            $this->syncColumnItems($block, $columnItems, $localeCode);
            $this->syncFeatureItems($block, $featureItems, $localeCode);
            $this->syncLinkListItems($block, $linkListItems, $localeCode);
            $this->syncManagedCtas($block, $managedCtas, $localeCode);

            $this->revisionManager->capture(
                $block->page()->firstOrFail(),
                request()->user(),
                'Block updated',
                'Page block structure or content was updated.',
            );
        });

        if ($sharedSlot) {
            $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);

            return redirect()
                ->route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => $localeCode])
                ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
                ->with('status', 'Block updated successfully.');
        }

        $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);
        $previewUrl = $block->page->publicUrl($localeCode);

        $redirect = redirect()
            ->route('admin.pages.slots.blocks', ['page' => $block->page_id, 'slot' => $pageSlotId ?: $block->slot_type_id, 'locale' => $localeCode])
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
        $pageId = $block->page_id;
        $slotTypeId = $block->slot_type_id;
        $pageSlotId = $this->pageSlotRouteId($pageId, $slotTypeId);

        DB::transaction(function () use ($block, $request): void {
            $page = $block->page()->firstOrFail();
            $block->delete();

            $this->revisionManager->capture(
                $page->fresh(),
                $request->user(),
                'Block deleted',
                'Page block structure or content was updated by removing a block.',
                );
        });

        if ($sharedSlot) {
            $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);

            return redirect()
                ->route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => $this->requestedLocaleCode(request())])
                ->with('slot_block_expanded', $this->slotExpandedBlockIds($block, false))
                ->with('status', 'Block deleted successfully.');
        }

        return redirect()
            ->route('admin.pages.slots.blocks', ['page' => $pageId, 'slot' => $pageSlotId ?: $slotTypeId, 'locale' => $this->requestedLocaleCode(request())])
            ->with('slot_block_expanded', $this->slotExpandedBlockIds($block, false))
            ->with('status', 'Block deleted successfully.');
    }

    private function move(Block $block, string $direction): RedirectResponse
    {
        [$sharedSlot] = $this->editingContext($block);
        $moved = DB::transaction(function () use ($block, $direction): bool {
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

            $this->revisionManager->capture(
                $block->page()->firstOrFail(),
                request()->user(),
                'Block order updated',
                'Page block order was changed.',
            );

            return true;
        });

        if ($sharedSlot) {
            $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);

            if (! $moved) {
                return redirect()
                    ->route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => $this->requestedLocaleCode(request())])
                    ->with('slot_block_expanded', $this->slotExpandedBlockIds($block))
                    ->with('status', 'Block is already at the edge of its group.');
            }

            return redirect()
                ->route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot, 'locale' => $this->requestedLocaleCode(request())])
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

        if (! $selectedId && $block->type) {
            return $blockTypes->firstWhere('slug', $block->type);
        }

        return $blockTypes->firstWhere('id', $selectedId);
    }

    private function builderChildItemsFrom(Request $request, string $inputKey, bool $includeSubtitle = false): array
    {
        return collect($request->input($inputKey, []))
            ->map(function ($item, int $index) {
                $title = trim((string) ($item['title'] ?? ''));
                $subtitle = trim((string) ($item['subtitle'] ?? ''));
                $content = trim((string) ($item['content'] ?? ''));
                $url = trim((string) ($item['url'] ?? ''));

                return [
                    'id' => ! empty($item['id']) ? (int) $item['id'] : null,
                    'block_type_id' => ! empty($item['block_type_id']) ? (int) $item['block_type_id'] : null,
                    'title' => $title !== '' ? $title : null,
                    'subtitle' => $subtitle !== '' ? $subtitle : null,
                    'content' => $content !== '' ? $content : null,
                    'url' => $url !== '' ? $url : null,
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

    private function managedCtasFrom(Request $request): array
    {
        return collect([
            [
                'key' => 'primary',
                'label' => $request->input('primary_cta_label'),
                'url' => $request->input('primary_cta_url'),
                'variant' => 'primary',
            ],
            [
                'key' => 'secondary',
                'label' => $request->input('secondary_cta_label'),
                'url' => $request->input('secondary_cta_url'),
                'variant' => 'secondary',
            ],
        ])->map(function (array $cta, int $index): array {
            return [
                'key' => $cta['key'],
                'label' => trim((string) ($cta['label'] ?? '')) ?: null,
                'url' => trim((string) ($cta['url'] ?? '')) ?: null,
                'variant' => $cta['variant'],
                'sort_order' => $index,
            ];
        })->all();
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

            $featureItem = $itemId && $existingItems->has($itemId)
                ? $existingItems[$itemId]
                : new Block;

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

            $columnItem = $itemId && $existingItems->has($itemId)
                ? $existingItems[$itemId]
                : new Block;

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

            if (! $blockTypeId || $itemData['title'] === null || $itemData['subtitle'] === null || $itemData['content'] === null || $itemData['url'] === null) {
                continue;
            }

            $blockType = BlockType::query()->find($blockTypeId);

            if (! $blockType || $blockType->slug !== 'link-list-item') {
                continue;
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

    private function syncManagedCtas(Block $block, array $managedCtas, ?string $localeCode = null): void
    {
        if (! in_array($block->typeSlug(), ['hero', 'cta'], true)) {
            return;
        }

        $buttonType = BlockType::query()->where('slug', 'button')->first();

        if (! $buttonType) {
            return;
        }

        $resolvedLocale = $localeCode
            ? Locale::query()->whereRaw('LOWER(code) = ?', [strtolower($localeCode)])->first()
            : null;
        $isDefaultLocaleEdit = ! $resolvedLocale || $resolvedLocale->is_default;

        $managedButtons = $block->children()
            ->where('type', 'button')
            ->orderBy('sort_order')
            ->limit(2)
            ->get()
            ->values();

        foreach ($managedCtas as $index => $cta) {
            $existing = $managedButtons->get($index);
            $hasSharedPayload = filled($cta['url']) || ($isDefaultLocaleEdit && filled($cta['label']));
            $hasTranslatedPayload = ! $isDefaultLocaleEdit && filled($cta['label']);

            if (! $existing && ! $hasSharedPayload && ! $hasTranslatedPayload) {
                continue;
            }

            if (! $existing && ! $isDefaultLocaleEdit) {
                continue;
            }

            if ($existing && blank($cta['label']) && blank($cta['url']) && $isDefaultLocaleEdit) {
                $existing->delete();

                continue;
            }

            if ($existing && blank($cta['label']) && ! $isDefaultLocaleEdit) {
                continue;
            }

            $payload = [
                'page_id' => $block->page_id,
                'parent_id' => $block->id,
                'block_type_id' => $buttonType->id,
                'type' => $buttonType->slug,
                'source_type' => $buttonType->source_type ?? 'static',
                'slot_type_id' => $block->slot_type_id,
                'slot' => $block->slot,
                'sort_order' => $cta['sort_order'],
                'title' => $cta['label'],
                'url' => $isDefaultLocaleEdit ? $cta['url'] : ($existing?->url),
                'subtitle' => $existing?->subtitle ?: '_self',
                'variant' => $cta['variant'],
                'status' => $existing?->status ?: 'published',
                'is_system' => false,
            ];

            $this->blockPayloadWriter->save($existing ?? new Block, $block->page, $payload, $localeCode);
        }
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
        return $this->authorization->scopeAssetsForUser(Asset::query(), request()->user())
            ->with('folder')
            ->latest()
            ->get();
    }

    private function assetPickerFolders()
    {
        return AssetFolder::query()
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
        ];
    }

    private function requestedLocaleCode(Request $request): ?string
    {
        $localeCode = trim((string) $request->input('locale', $request->query('locale', '')));

        return $localeCode !== '' ? $localeCode : null;
    }
}
