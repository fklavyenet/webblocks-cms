<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockRequest;
use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BlockController extends Controller
{
    public function __construct(
        private readonly BlockTranslationWriter $blockTranslationWriter,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function moveUp(Block $block): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $block);
        return $this->move($block, 'up');
    }

    public function moveDown(Block $block): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $block);
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
        $pages = $this->authorization->scopePagesForUser(Page::query(), request()->user())->with(['blocks', 'translations'])->orderBy('title')->get();
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
        $blockAssets = $data['_block_assets'] ?? [];
        $columnItems = $this->columnItemsFrom($request);
        $page = $this->authorization->scopePagesForUser(Page::query(), $request->user())->findOrFail($data['page_id']);
        $canonicalData = $this->blockTranslationWriter->canonicalPayload($data, null, $page, $localeCode, true);
        unset($canonicalData['_block_assets'], $canonicalData['locale']);

        $block = DB::transaction(function () use ($canonicalData, $blockAssets, $columnItems, $data, $localeCode) {
            $block = Block::create($canonicalData);
            $this->blockTranslationWriter->sync($block, $data, $localeCode, true);
            $this->syncBlockAssets($block, $blockAssets);
            $this->syncColumnItems($block, $columnItems);

            return $block;
        });

        $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);
        $expanded = $this->expandedStateFor($request, $block);
        $previewUrl = $block->page->publicUrl($localeCode);

        $redirect = redirect()
            ->route('admin.pages.slots.blocks', ['page' => $block->page_id, 'slot' => $pageSlotId ?: $block->slot_type_id, 'expanded' => $expanded ?: null, 'locale' => $localeCode])
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
        $this->authorization->abortUnlessSiteAccess($request->user(), $block);

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

        $pages = $this->authorization->scopePagesForUser(Page::query(), $request->user())->with(['blocks', 'translations'])->orderBy('title')->get();
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
        $this->authorization->abortUnlessSiteAccess($request->user(), $block);
        $data = $request->validatedData();
        $localeCode = $data['locale'] ?? null;
        $blockAssets = $data['_block_assets'] ?? [];
        $columnItems = $this->columnItemsFrom($request);
        $page = $this->authorization->scopePagesForUser(Page::query(), $request->user())->findOrFail($data['page_id']);
        $canonicalData = $this->blockTranslationWriter->canonicalPayload($data, $block, $page, $localeCode);
        unset($canonicalData['_block_assets'], $canonicalData['locale']);

        DB::transaction(function () use ($block, $canonicalData, $blockAssets, $columnItems, $data, $localeCode): void {
            $block->update($canonicalData);
            $this->blockTranslationWriter->sync($block, $data, $localeCode);
            $this->syncBlockAssets($block, $blockAssets);
            $this->syncColumnItems($block, $columnItems);
        });

        $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);
        $expanded = $this->expandedStateFor($request, $block);
        $previewUrl = $block->page->publicUrl($localeCode);

        $redirect = redirect()
            ->route('admin.pages.slots.blocks', ['page' => $block->page_id, 'slot' => $pageSlotId ?: $block->slot_type_id, 'expanded' => $expanded ?: null, 'locale' => $localeCode])
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
        $this->authorization->abortUnlessSiteAccess($request->user(), $block);
        $pageId = $block->page_id;
        $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);
        $expanded = $this->expandedStateFor($request, $block, false);
        $block->delete();

        return redirect()
            ->route('admin.pages.slots.blocks', ['page' => $pageId, 'slot' => $pageSlotId ?: $block->slot_type_id, 'expanded' => $expanded ?: null, 'locale' => $this->requestedLocaleCode(request())])
            ->with('status', 'Block deleted successfully.');
    }

    private function move(Block $block, string $direction): RedirectResponse
    {
        $sibling = Block::query()
            ->where('page_id', $block->page_id)
            ->where('parent_id', $block->parent_id)
            ->whereKeyNot($block->id)
            ->where('sort_order', $direction === 'up' ? '<' : '>', $block->sort_order)
            ->orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
            ->first();

        if (! $sibling) {
            return redirect()
                ->route('admin.pages.slots.blocks', $this->slotRedirectParameters($block, $this->expandedStateFor(request(), $block)))
                ->with('status', 'Block is already at the edge of its group.');
        }

        DB::transaction(function () use ($block, $sibling): void {
            $currentOrder = $block->sort_order;
            $block->update(['sort_order' => $sibling->sort_order]);
            $sibling->update(['sort_order' => $currentOrder]);
        });

        return redirect()
            ->route('admin.pages.slots.blocks', $this->slotRedirectParameters($block, $this->expandedStateFor(request(), $block)))
            ->with('status', 'Block order updated successfully.');
    }

    private function parentBlocksFor(?int $pageId, ?int $ignoreId = null)
    {
        if (! $pageId) {
            return collect();
        }

        $blocks = $this->authorization->scopeBlocksForUser(Block::query(), request()->user())
            ->where('page_id', $pageId)
            ->with('children')
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->orderBy('sort_order')
            ->get();

        return $this->flattenBlockOptions($blocks->whereNull('parent_id'));
    }

    private function flattenBlockOptions($blocks, string $prefix = '')
    {
        return $blocks->flatMap(function ($block) use ($prefix) {
            if ($block->isColumnItem()) {
                return collect();
            }

            $current = $block->title ?: $block->typeName();
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

    private function syncBlockAssets(Block $block, array $blockAssets): void
    {
        $block->blockAssets()->delete();

        foreach ($blockAssets as $role => $assetIds) {
            foreach (array_values($assetIds) as $position => $assetId) {
                if (! $assetId) {
                    continue;
                }

                BlockAsset::create([
                    'block_id' => $block->id,
                    'asset_id' => $assetId,
                    'role' => $role,
                    'position' => $position,
                ]);
            }
        }
    }

    private function columnItemsFrom(Request $request): array
    {
        return collect($request->input('column_items', []))
            ->map(function ($item, int $index) {
                return [
                    'id' => ! empty($item['id']) ? (int) $item['id'] : null,
                    'block_type_id' => ! empty($item['block_type_id']) ? (int) $item['block_type_id'] : null,
                    'title' => trim((string) ($item['title'] ?? '')) ?: null,
                    'content' => trim((string) ($item['content'] ?? '')) ?: null,
                    'url' => trim((string) ($item['url'] ?? '')) ?: null,
                    'status' => in_array(($item['status'] ?? 'published'), ['draft', 'published'], true) ? $item['status'] : 'published',
                    'is_system' => (bool) ($item['is_system'] ?? false),
                    'sort_order' => is_numeric($item['sort_order'] ?? null) ? (int) $item['sort_order'] : $index,
                    '_delete' => (bool) ($item['_delete'] ?? false),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    private function syncColumnItems(Block $block, array $columnItems): void
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
                ? tap($existingItems[$itemId])->update($payload)
                : Block::create($payload);

            $keptIds[] = $columnItem->id;
        }

        $staleItems = $block->children()->where('type', 'column_item');

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

    private function expandedStateFor(Request $request, Block $block, bool $includeCurrent = true): string
    {
        $expanded = collect(explode(',', trim((string) $request->input('expanded', ''))))
            ->map(fn (string $value) => (int) trim($value))
            ->filter(fn (int $value) => $value > 0);

        $targetId = $block->parent_id ?: ($includeCurrent && $block->children()->exists() ? $block->id : null);

        if ($targetId) {
            $expanded->push($targetId);
        }

        return $expanded->unique()->implode(',');
    }

    private function slotRedirectParameters(Block $block, string $expanded = ''): array
    {
        $pageSlotId = $this->pageSlotRouteId($block->page_id, $block->slot_type_id);

        return [
            'page' => $block->page_id,
            'slot' => $pageSlotId ?: $block->slot_type_id,
            'expanded' => $expanded !== '' ? $expanded : null,
            'locale' => $this->requestedLocaleCode(request()),
        ];
    }

    private function requestedLocaleCode(Request $request): ?string
    {
        $localeCode = trim((string) $request->input('locale', $request->query('locale', '')));

        return $localeCode !== '' ? $localeCode : null;
    }
}
