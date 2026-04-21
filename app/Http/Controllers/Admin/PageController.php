<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $siteId = $request->integer('site_id');
        $sort = $request->string('sort')->toString();
        $direction = Str::lower($request->string('direction')->toString()) === 'asc' ? 'asc' : 'desc';
        $allowedStatuses = ['draft', 'published'];
        $allowedSorts = ['created_at', 'title', 'slug', 'status', 'updated_at'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $siteLocaleCounts = Site::query()
            ->withCount(['locales' => fn ($query) => $query->wherePivot('is_enabled', true)])
            ->pluck('locales_count', 'id');

        return view('admin.pages.index', [
            'pages' => Page::query()
                ->with(['site', 'translations.locale'])
                ->with('slots.slotType')
                ->withCount(['slots', 'blocks'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('title', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%")
                            ->orWhere('page_type', 'like', "%{$search}%")
                            ->orWhereHas('translations', fn ($translations) => $translations
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%"));
                    });
                })
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($siteId > 0, fn ($query) => $query->where('site_id', $siteId))
                ->orderBy($sort, $direction)
                ->when($sort !== 'created_at', fn ($query) => $query->orderByDesc('created_at'))
                ->paginate(15)
                ->withQueryString(),
            'sites' => Site::query()->orderByDesc('is_primary')->orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'site_id' => $siteId,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'siteLocaleCounts' => $siteLocaleCounts,
        ]);
    }

    public function show(Page $page): RedirectResponse
    {
        return redirect()->route('admin.pages.edit', $page);
    }

    public function create(): View
    {
        $sites = Site::query()
            ->with(['locales' => fn ($query) => $query->wherePivot('is_enabled', true)->orderByDesc('is_default')->orderBy('name')])
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        return view('admin.pages.create', [
            'page' => new Page,
            'sites' => $sites,
            'slotTypes' => SlotType::query()->where('status', 'published')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $page = DB::transaction(function () use ($request) {
            $data = $request->validatedData();
            $slots = $data['slots'] ?? [];
            $blocks = $data['blocks'] ?? [];
            $translation = $data['translation'];
            unset($data['slots'], $data['blocks'], $data['translation']);

            $page = Page::create($data);
            $this->syncDefaultTranslation($page, $translation);
            $this->syncSlots($page, $slots);

            if ($blocks === []) {
                $this->syncBlocks($page, []);
            } else {
                $this->syncBlocks($page, $blocks);
            }

            return $page;
        });

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Page created successfully.')
            ->with('status_action', [
                'label' => 'View page',
                'url' => $page->publicUrl(),
            ]);
    }

    public function edit(Page $page): View
    {
        $page->loadMissing([
            'site',
            'translations.locale',
            'slots.slotType',
            'blocks' => fn ($query) => $query
                ->with('children')
                ->whereNull('parent_id')
                ->orderBy('sort_order'),
        ]);
        $page->loadCount('blocks');

        $slotBlockPreviews = $page->slots
            ->mapWithKeys(fn (PageSlot $slot) => [
                $slot->id => $this->slotBlockPreviewFor($page, $slot),
            ]);

        return view('admin.pages.edit', [
            'page' => $page,
            'sites' => Site::query()
                ->with(['locales' => fn ($query) => $query->wherePivot('is_enabled', true)->orderByDesc('is_default')->orderBy('name')])
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->get(),
            'slotTypes' => SlotType::query()->where('status', 'published')->orderBy('sort_order')->get(),
            'slotBlockPreviews' => $slotBlockPreviews,
            'translationStatuses' => $page->translationStatusForSite(),
        ]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        DB::transaction(function () use ($request, $page): void {
            $data = $request->validatedData();
            $slots = $data['slots'] ?? [];
            $translation = $data['translation'];
            unset($data['slots'], $data['blocks'], $data['translation']);

            $page->update($data);
            $this->syncDefaultTranslation($page, $translation);
            $this->syncSlots($page, $slots);
        });

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Page updated successfully.')
            ->with('status_action', [
                'label' => 'View page',
                'url' => $page->publicUrl(),
            ]);
    }

    public function editSlotBlocks(Page $page, PageSlot $slot): View
    {
        abort_unless($slot->page_id === $page->id, 404);

        $page->loadMissing('slots.slotType');
        $slot->loadMissing('slotType');

        $blocks = Block::query()
            ->with(['blockType', 'slotType', 'blockAssets.asset', 'children.blockType'])
            ->where('page_id', $page->id)
            ->where('slot_type_id', $slot->slot_type_id)
            ->orderBy('sort_order')
            ->get();

        $blockTypes = BlockType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get();
        $modalState = $this->slotBlockModalState($page, $slot, $blocks, $blockTypes);
        $rootBlocks = $blocks->whereNull('parent_id')->values();
        $expandedBlockIds = $this->slotExpandedBlockIds($rootBlocks, $modalState['block']);

        return view('admin.pages.slot-blocks', [
            'page' => $page,
            'slot' => $slot,
            'blocks' => $rootBlocks,
            'blockTypes' => $blockTypes,
            'assetPickerAssets' => $this->assetPickerAssets(),
            'assetPickerFolders' => $this->assetPickerFolders(),
            'pickerSearch' => trim((string) request('block_type_search')),
            'isPickerOpen' => request()->boolean('picker') || $modalState['mode'] === 'create',
            'slotModalMode' => $modalState['mode'],
            'slotModalBlock' => $modalState['block'],
            'slotModalSelectedBlockType' => $modalState['selectedBlockType'],
            'columnItemBlockType' => $blockTypes->firstWhere('slug', 'column_item'),
            'slotModalSelectedAsset' => $modalState['selectedAsset'],
            'slotModalSelectedGalleryAssets' => $modalState['selectedGalleryAssets'],
            'slotModalSelectedAttachmentAsset' => $modalState['selectedAttachmentAsset'],
            'expandedBlockIds' => $expandedBlockIds,
            'slotParentBlocks' => $this->slotParentBlocks($blocks, $modalState['block']?->id),
        ]);
    }

    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()
            ->route('admin.pages.index')
            ->with('status', 'Page deleted successfully.');
    }

    public function updateStatus(Request $request, Page $page): RedirectResponse
    {
        $status = $request->string('status')->toString();

        if (! in_array($status, ['draft', 'published'], true)) {
            return back()->with('status', 'Invalid page status.');
        }

        $page->update(['status' => $status]);

        return back()->with('status', 'Page status updated successfully.');
    }

    private function syncBlocks(Page $page, array $submittedBlocks): void
    {
        $existingBlocks = $page->blocks()->with('blockAssets')->get()->keyBy('id');
        $keptBlockIds = [];

        foreach (array_values($submittedBlocks) as $index => $blockData) {
            $blockId = $blockData['id'] ?? null;
            $delete = (bool) ($blockData['_delete'] ?? false);
            $blockAssets = $blockData['_block_assets'] ?? [];

            unset($blockData['id'], $blockData['_delete'], $blockData['_block_assets']);

            $blockType = ! empty($blockData['block_type_id'])
                ? BlockType::query()->find($blockData['block_type_id'])
                : null;
            $slotType = ! empty($blockData['slot_type_id'])
                ? SlotType::query()->find($blockData['slot_type_id'])
                : null;

            $blockData['page_id'] = $page->id;
            $blockData['parent_id'] = null;
            $blockData['sort_order'] = $index;
            $blockData['type'] = $blockType?->slug ?? $blockData['type'] ?? null;
            $blockData['source_type'] = $blockType?->source_type ?? ($blockData['source_type'] ?? 'static');
            $blockData['slot'] = $slotType?->slug ?? ($blockData['slot'] ?? 'main');
            $blockData['slot_type_id'] = $slotType?->id;

            if ($delete) {
                if ($blockId && $existingBlocks->has($blockId)) {
                    $existingBlocks[$blockId]->delete();
                }

                continue;
            }

            $block = $blockId && $existingBlocks->has($blockId)
                ? tap($existingBlocks[$blockId])->update($blockData)
                : Block::create($blockData);

            $this->syncBlockAssets($block, $blockAssets);
            $keptBlockIds[] = $block->id;
        }

        $page->blocks()
            ->whereNotIn('id', $keptBlockIds)
            ->delete();
    }

    private function syncDefaultTranslation(Page $page, array $translation): void
    {
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

        if (! $defaultLocaleId) {
            return;
        }

        $page->translations()->updateOrCreate(
            ['locale_id' => $defaultLocaleId],
            [
                'site_id' => $page->site_id,
                'name' => $translation['name'],
                'slug' => $translation['slug'],
                'path' => PageTranslation::pathFromSlug($translation['slug']),
            ],
        );

        $page->forceFill([
            'title' => $translation['name'],
            'slug' => $translation['slug'],
        ])->saveQuietly();

        $page->translations()->where('site_id', '!=', $page->site_id)->update(['site_id' => $page->site_id]);

        $page->unsetRelation('translations');
        $page->load('translations');
        $page->setRelation('currentTranslation', $page->defaultTranslation());
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

    private function slotBlockModalState(Page $page, PageSlot $slot, $blocks, $blockTypes): array
    {
        $editingBlockId = old('_slot_block_mode') === 'edit'
            ? (int) old('_slot_block_id')
            : request()->integer('edit');

        $editingBlock = $editingBlockId > 0
            ? $blocks->firstWhere('id', $editingBlockId)
            : null;

        if ($editingBlock) {
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
        $selectedBlockType = $selectedBlockTypeId > 0 ? $blockTypes->firstWhere('id', $selectedBlockTypeId) : null;

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
        $block->slot_type_id = $slot->slot_type_id;
        $block->sort_order = $blocks->count();
        $block->status = 'draft';
        $block->is_system = $selectedBlockType->is_system;
        $block->block_type_id = $selectedBlockType->id;
        $block->type = $selectedBlockType->slug;
        $block->slot = $slot->slotType?->slug;
        $block->source_type = $selectedBlockType->source_type ?: 'static';
        $block->setRelation('blockType', $selectedBlockType);
        $block->setRelation('slotType', $slot->slotType);

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

        return $resolvedId > 0 ? Asset::query()->find($resolvedId) : null;
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

        return Asset::query()
            ->whereIn('id', $resolvedIds)
            ->get()
            ->sortBy(fn (Asset $asset) => $resolvedIds->search($asset->id))
            ->values();
    }

    private function slotParentBlocks($blocks, ?int $ignoreId = null)
    {
        return $blocks
            ->when($ignoreId, fn ($collection) => $collection->where('id', '!=', $ignoreId))
            ->reject(fn (Block $block) => $block->isColumnItem())
            ->map(fn (Block $block) => [
                'id' => $block->id,
                'label' => $block->editorLabel(),
                'slot_page_id' => $this->pageSlotRouteId($block->page_id, $block->slot_type_id),
            ])
            ->values();
    }

    private function slotExpandedBlockIds($blocks, ?Block $modalBlock = null)
    {
        $expandedIds = collect(explode(',', trim((string) request('expanded'))))
            ->map(fn (string $value) => (int) trim($value))
            ->filter(fn (int $value) => $value > 0);

        if ($modalBlock?->parent_id) {
            $expandedIds->push($modalBlock->parent_id);
        }

        if ($modalBlock && $modalBlock->children->isNotEmpty()) {
            $expandedIds->push($modalBlock->id);
        }

        $expandableIds = $blocks
            ->filter(fn (Block $block) => $block->children->isNotEmpty())
            ->pluck('id');

        return $expandedIds
            ->unique()
            ->values()
            ->intersect($expandableIds)
            ->values();
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

    private function syncSlots(Page $page, array $submittedSlots): void
    {
        $existingSlots = $page->slots()->get()->keyBy('id');
        $keptSlotIds = [];

        foreach (array_values($submittedSlots) as $index => $slotData) {
            $slotId = $slotData['id'] ?? null;
            $delete = (bool) ($slotData['_delete'] ?? false);

            unset($slotData['id'], $slotData['_delete']);

            if ($delete) {
                if ($slotId && $existingSlots->has($slotId)) {
                    $existingSlots[$slotId]->delete();
                }

                continue;
            }

            $slotData['page_id'] = $page->id;
            $slotData['sort_order'] = $index;

            $slot = $slotId && $existingSlots->has($slotId)
                ? tap($existingSlots[$slotId])->update($slotData)
                : PageSlot::create($slotData);

            $keptSlotIds[] = $slot->id;
        }

        $page->slots()->whereNotIn('id', $keptSlotIds)->delete();
    }

    private function slotBlockPreviewFor(Page $page, PageSlot $slot): array
    {
        $blocks = $page->blocks
            ->where('slot_type_id', $slot->slot_type_id)
            ->sortBy('sort_order')
            ->values();

        if ($blocks->isEmpty()) {
            return [
                'items' => collect(),
                'remaining' => 0,
                'is_empty' => true,
            ];
        }

        $visibleItems = $blocks
            ->take(5)
            ->map(fn (Block $block) => $block->slotPreviewLabel())
            ->values();

        return [
            'items' => $visibleItems,
            'remaining' => max($blocks->count() - $visibleItems->count(), 0),
            'is_empty' => false,
        ];
    }

    private function assetPickerAssets()
    {
        return Asset::query()
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
