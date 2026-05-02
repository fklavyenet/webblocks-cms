<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LayoutTypeRequest;
use App\Http\Requests\Admin\LayoutTypeSlotSettingsRequest;
use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\LayoutTypeSlot;
use App\Models\Locale;
use App\Models\LayoutType;
use App\Models\PageSlot;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LayoutTypeController extends Controller
{
    public function __construct(
        private readonly BlockTranslationResolver $blockTranslationResolver,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function index(): View
    {
        return view('admin.layout-types.index', [
            'layoutTypes' => LayoutType::query()
                ->withCount(['slots', 'pages'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.layout-types.create', [
            'layoutType' => new LayoutType,
            'slotTypes' => SlotType::query()->where('status', 'published')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(LayoutTypeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validatedData();
            $slots = $data['slots'] ?? [];
            unset($data['slots']);

            $layoutType = LayoutType::create($data);
            $this->syncSlots($layoutType, $slots);
        });

        return redirect()->route('admin.layout-types.index')->with('status', 'Layout type created successfully.');
    }

    public function edit(LayoutType $layoutType): View
    {
        $layoutType->loadMissing('slots.slotType');

        return view('admin.layout-types.edit', [
            'layoutType' => $layoutType,
            'slotTypes' => SlotType::query()->where('status', 'published')->orderBy('sort_order')->get(),
        ]);
    }

    public function update(LayoutTypeRequest $request, LayoutType $layoutType): RedirectResponse
    {
        DB::transaction(function () use ($request, $layoutType): void {
            $data = $request->validatedData();
            $slots = $data['slots'] ?? [];
            unset($data['slots']);

            $layoutType->update($data);
            $this->syncSlots($layoutType, $slots);
        });

        return redirect()->route('admin.layout-types.index')->with('status', 'Layout type updated successfully.');
    }

    public function destroy(LayoutType $layoutType): RedirectResponse
    {
        if ($layoutType->is_system) {
            return redirect()->route('admin.layout-types.index')->with('status', 'System layout types cannot be deleted.');
        }

        $layoutType->delete();

        return redirect()->route('admin.layout-types.index')->with('status', 'Layout type deleted successfully.');
    }

    public function editSlotBlocks(LayoutType $layoutType, LayoutTypeSlot $slot): View
    {
        abort_unless($slot->layout_type_id === $layoutType->id, 404);

        $layoutType->loadMissing('slots.slotType');
        $slot->loadMissing('slotType');
        $activeLocale = Locale::query()->where('is_default', true)->firstOrFail();

        $blocks = Block::query()
            ->with($this->slotBlockRelations())
            ->where('layout_type_slot_id', $slot->id)
            ->orderBy('sort_order')
            ->get();

        $blockTypes = BlockType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get();
        $pickerParentId = request()->integer('parent_id') ?: null;
        $resolvedBlocks = $this->blockTranslationResolver->resolveCollection($blocks, $activeLocale)->values();
        $pickerBlockTypes = $this->pickerBlockTypes($resolvedBlocks, $blockTypes, $pickerParentId);
        $modalState = $this->slotBlockModalState($layoutType, $slot, $blocks, $blockTypes, $pickerBlockTypes, $pickerParentId);
        $rootBlocks = $this->blockTranslationResolver
            ->resolveCollection($blocks->whereNull('parent_id')->values(), $activeLocale)
            ->values();
        $expandedBlockIds = $this->slotExpandedBlockIds($resolvedBlocks, $modalState['block']);

        return view('admin.layout-types.slot-blocks', [
            'layoutType' => $layoutType,
            'slot' => $slot,
            'blocks' => $rootBlocks,
            'blockTypes' => $blockTypes,
            'pickerBlockTypes' => $pickerBlockTypes,
            'pickerParentBlock' => $pickerParentId ? $resolvedBlocks->firstWhere('id', $pickerParentId) : null,
            'activeLocale' => $activeLocale,
            'availableLocales' => collect([['locale' => $activeLocale]]),
            'assetPickerAssets' => $this->assetPickerAssets(),
            'assetPickerFolders' => $this->assetPickerFolders(),
            'pickerSearch' => trim((string) request('block_type_search')),
            'isPickerOpen' => request()->boolean('picker') || $modalState['mode'] === 'create',
            'slotModalMode' => $modalState['mode'],
            'slotModalBlock' => $modalState['block'],
            'slotModalSelectedBlockType' => $modalState['selectedBlockType'],
            'columnItemBlockType' => $blockTypes->firstWhere('slug', 'column_item'),
            'featureItemBlockType' => $blockTypes->firstWhere('slug', 'feature-item'),
            'linkListItemBlockType' => $blockTypes->firstWhere('slug', 'link-list-item'),
            'slotModalSelectedAsset' => $modalState['selectedAsset'],
            'slotModalSelectedGalleryAssets' => $modalState['selectedGalleryAssets'],
            'slotModalSelectedAttachmentAsset' => $modalState['selectedAttachmentAsset'],
            'expandedBlockIds' => $expandedBlockIds,
            'slotParentBlocks' => $this->slotParentBlocks($resolvedBlocks, $modalState['block']),
        ]);
    }

    public function updateSlotSettings(LayoutTypeSlotSettingsRequest $request, LayoutType $layoutType, LayoutTypeSlot $slot): RedirectResponse
    {
        abort_unless($slot->layout_type_id === $layoutType->id, 404);

        $slot->update($request->validatedSettings());

        return redirect()
            ->route('admin.layout-types.slots.blocks', [$layoutType, $slot])
            ->with('status', 'Layout slot settings updated successfully.');
    }

    public function reorderSlotBlocks(Request $request, LayoutType $layoutType, LayoutTypeSlot $slot): JsonResponse
    {
        abort_unless($slot->layout_type_id === $layoutType->id, 404);

        $validated = $request->validate([
            'blocks' => ['required', 'array', 'min:1'],
            'blocks.*' => ['required', 'integer', 'distinct'],
        ]);

        $blockIds = collect($validated['blocks'])
            ->map(fn (mixed $id) => (int) $id)
            ->values();

        $blocks = Block::query()
            ->whereIn('id', $blockIds)
            ->where('layout_type_slot_id', $slot->id)
            ->get(['id', 'layout_type_slot_id', 'parent_id', 'sort_order']);

        if ($blocks->count() !== $blockIds->count()) {
            return response()->json([
                'message' => 'Submitted blocks must belong to the current layout slot.',
                'errors' => ['blocks' => ['Submitted blocks must belong to the current layout slot.']],
            ], 422);
        }

        $parentIds = $blocks->map(fn (Block $block) => $block->parent_id)->uniqueStrict();

        if ($parentIds->count() !== 1) {
            return response()->json([
                'message' => 'Submitted blocks must belong to the same parent group.',
                'errors' => ['blocks' => ['Submitted blocks must belong to the same parent group.']],
            ], 422);
        }

        $parentId = $parentIds->first();

        $siblings = Block::query()
            ->where('layout_type_slot_id', $slot->id)
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

        DB::transaction(function () use ($slot, $blockIds, $parentId): void {
            $siblings = Block::query()
                ->where('layout_type_slot_id', $slot->id)
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
                    if ($block->sort_order !== $index) {
                        $block->update(['sort_order' => $index]);
                    }
                });
        });

        return response()->json(['ok' => true, 'message' => 'Saved']);
    }

    private function syncSlots(LayoutType $layoutType, array $submittedSlots): void
    {
        $existingSlots = $layoutType->slots()->get()->keyBy('id');
        $keptSlotIds = [];

        foreach (array_values($submittedSlots) as $index => $slotData) {
            $slotId = $slotData['id'] ?? null;

            $payload = [
                'layout_type_id' => $layoutType->id,
                'slot_type_id' => $slotData['slot_type_id'],
                'ownership' => $slotData['ownership'],
                'sort_order' => $index,
                'wrapper_element' => $slotData['wrapper_element'] ?? null,
                'wrapper_preset' => $slotData['wrapper_preset'] ?? 'default',
                'settings' => $slotData['settings'] ?? null,
            ];

            $slot = $slotId && $existingSlots->has($slotId)
                ? tap($existingSlots[$slotId])->update($payload)
                : LayoutTypeSlot::query()->create($payload);

            $keptSlotIds[] = $slot->id;
        }

        $layoutType->slots()->whereNotIn('id', $keptSlotIds)->delete();
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

    private function pickerBlockTypes($blocks, $blockTypes, ?int $parentId = null)
    {
        if (! $parentId) {
            return $blockTypes
                ->reject(fn (BlockType $blockType) => in_array($blockType->slug, ['sidebar-nav-item', 'sidebar-nav-group'], true))
                ->values();
        }

        $parentBlock = $blocks->firstWhere('id', $parentId);

        if (! $parentBlock || ! $parentBlock->canAcceptChildren()) {
            return collect();
        }

        return $blockTypes
            ->filter(fn (BlockType $blockType) => $parentBlock->canAcceptChildType($blockType->slug))
            ->values();
    }

    private function slotBlockModalState(LayoutType $layoutType, LayoutTypeSlot $slot, Collection $blocks, Collection $blockTypes, Collection $pickerBlockTypes, ?int $pickerParentId = null): array
    {
        $activeLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $editingBlockId = old('_slot_block_mode') === 'edit'
            ? (int) old('_slot_block_id')
            : request()->integer('edit');

        $editingBlock = $editingBlockId > 0 ? $blocks->firstWhere('id', $editingBlockId) : null;

        if ($editingBlock) {
            $editingBlock = $this->blockTranslationResolver->resolve($editingBlock, $activeLocale);
            $selectedBlockType = $blockTypes->firstWhere('id', (int) old('block_type_id', $editingBlock->block_type_id));

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
                'selectedAsset' => $this->resolveSelectedAsset(old('asset_id', $editingBlock->asset_id)),
                'selectedGalleryAssets' => $this->resolveGalleryAssets(old('gallery_asset_ids', $editingBlock->galleryAssetIds())),
                'selectedAttachmentAsset' => $this->resolveSelectedAsset(old('attachment_asset_id', $editingBlock->attachmentAsset()?->id)),
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
        $block->layout_type_slot_id = $slot->id;
        $block->page_id = null;
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
        $block->setAttribute('translation_state', 'shared');
        $block->setAttribute('resolved_locale_code', $activeLocale->code);

        return [
            'mode' => 'create',
            'block' => $block,
            'selectedBlockType' => $selectedBlockType,
            'selectedAsset' => $this->resolveSelectedAsset(old('asset_id')),
            'selectedGalleryAssets' => $this->resolveGalleryAssets(old('gallery_asset_ids', [])),
            'selectedAttachmentAsset' => $this->resolveSelectedAsset(old('attachment_asset_id')),
        ];
    }

    private function resolveSelectedAsset(mixed $assetId): ?Asset
    {
        $resolvedId = (int) $assetId;

        if ($resolvedId <= 0) {
            return null;
        }

        return $this->authorization->scopeAssetsForUser(Asset::query(), request()->user())->find($resolvedId);
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

        return $this->authorization->scopeAssetsForUser(Asset::query(), request()->user())
            ->whereIn('id', $resolvedIds)
            ->get()
            ->sortBy(fn (Asset $asset) => $resolvedIds->search($asset->id))
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

    private function slotExpandedBlockIds($blocks, ?Block $modalBlock = null)
    {
        $expandableIds = $blocks
            ->filter(fn (Block $block) => $block->children->isNotEmpty())
            ->pluck('id');

        $expandedIds = collect(session('slot_block_expanded', []))
            ->map(fn (mixed $value) => (int) $value)
            ->filter(fn (int $value) => $value > 0);

        if ($modalBlock) {
            $ancestorId = $modalBlock->parent_id;

            while ($ancestorId) {
                $expandedIds->push($ancestorId);
                $ancestorId = $blocks->firstWhere('id', $ancestorId)?->parent_id;
            }

            if ($modalBlock->id && $expandableIds->contains($modalBlock->id)) {
                $expandedIds->push($modalBlock->id);
            }
        }

        return $expandedIds
            ->unique()
            ->values()
            ->intersect($expandableIds)
            ->values();
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
}
