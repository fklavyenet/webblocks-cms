<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Http\Requests\Admin\PageSlotSettingsRequest;
use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\LayoutType;
use App\Models\LayoutTypeSlot;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockPayloadWriter;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\Pages\PageRevisionManager;
use App\Support\Pages\PageWorkflowManager;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    private const SITE_CONTEXT_SESSION_KEY = 'admin.pages.site';

    public function __construct(
        private readonly BlockPayloadWriter $blockPayloadWriter,
        private readonly BlockTranslationResolver $blockTranslationResolver,
        private readonly PageRevisionManager $revisionManager,
        private readonly PageWorkflowManager $workflowManager,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function index(Request $request): View
    {
        $sites = $this->authorization->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), $request->user())->get();
        $search = trim((string) $request->string('search'));
        $status = $request->string('status')->toString();
        $sort = $request->string('sort')->toString();
        $direction = Str::lower($request->string('direction')->toString()) === 'asc' ? 'asc' : 'desc';
        $allowedStatuses = $this->workflowManager->allowedStatuses();
        $allowedSorts = ['created_at', 'title', 'slug', 'status', 'updated_at'];
        $defaultLocaleId = Page::defaultLocaleId();
        [$activeSite, $siteFilterValue] = $this->resolveSiteContext($request, $sites);

        if (! in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $siteLocaleCounts = $this->authorization->scopeSitesForUser(Site::query(), $request->user())
            ->withCount(['enabledLocales as locales_count'])
            ->pluck('locales_count', 'id');

        return view('admin.pages.index', [
            'pages' => $this->authorization->scopePagesForUser(Page::query(), $request->user())
                ->with(['site', 'translations.locale'])
                ->with('slots.slotType')
                ->withCount(['slots', 'blocks'])
                ->when($search !== '', function ($query) use ($search, $defaultLocaleId) {
                    $query->where(function ($inner) use ($search, $defaultLocaleId) {
                        $inner->where('page_type', 'like', "%{$search}%")
                            ->orWhereHas('blocks.textTranslations', fn ($translations) => $translations
                                ->where('title', 'like', "%{$search}%")
                                ->orWhere('subtitle', 'like', "%{$search}%")
                                ->orWhere('content', 'like', "%{$search}%"))
                            ->orWhereHas('blocks.buttonTranslations', fn ($translations) => $translations
                                ->where('title', 'like', "%{$search}%"))
                            ->orWhereHas('blocks.imageTranslations', fn ($translations) => $translations
                                ->where('caption', 'like', "%{$search}%")
                                ->orWhere('alt_text', 'like', "%{$search}%"))
                            ->orWhereHas('blocks.contactFormTranslations', fn ($translations) => $translations
                                ->where('title', 'like', "%{$search}%")
                                ->orWhere('content', 'like', "%{$search}%")
                                ->orWhere('submit_label', 'like', "%{$search}%")
                                ->orWhere('success_message', 'like', "%{$search}%"))
                            ->orWhereHas('translations', fn ($translations) => $translations
                                ->when($defaultLocaleId, fn ($defaultTranslations) => $defaultTranslations->where('locale_id', $defaultLocaleId))
                                ->where(function ($translationQuery) use ($search) {
                                    $translationQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('slug', 'like', "%{$search}%");
                                }));
                    });
                })
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($activeSite, fn ($query) => $query->where('site_id', $activeSite->id))
                ->when($sort === 'title', fn ($query) => $query->orderByDefaultTranslation('name', $direction))
                ->when($sort === 'slug', fn ($query) => $query->orderByDefaultTranslation('slug', $direction))
                ->when(! in_array($sort, ['title', 'slug'], true), fn ($query) => $query->orderBy($sort, $direction))
                ->when($sort !== 'created_at', fn ($query) => $query->orderByDesc('created_at'))
                ->paginate(15)
                ->withQueryString(),
            'sites' => $sites,
            'activeSite' => $activeSite,
            'showAllSites' => $siteFilterValue === 'all',
            'filters' => [
                'search' => $search,
                'status' => $status,
                'site' => $siteFilterValue,
                'site_id' => $activeSite?->id ?? 0,
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

    public function create(Request $request): View
    {
        $sites = $this->authorization->scopeSitesForUser(Site::query(), $request->user())
            ->with(['locales' => fn ($query) => $query->wherePivot('is_enabled', true)->orderByDesc('is_default')->orderBy('name')])
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
        [$activeSite] = $this->resolveSiteContext($request, $sites, persist: false);
        $selectedSiteId = $activeSite?->id ?? ($sites->firstWhere('is_primary', true)?->id ?? $sites->first()?->id);

        return view('admin.pages.create', [
            'page' => new Page,
            'sites' => $sites,
            'selectedSiteId' => $selectedSiteId,
            'slotTypes' => SlotType::query()->where('status', 'published')->orderBy('sort_order')->get(),
            'layoutTypes' => LayoutType::query()->with('slots.slotType')->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get(),
            'canEditContent' => true,
        ]);
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), (int) $request->validated('site_id'));

        $page = DB::transaction(function () use ($request) {
            $data = $request->validatedData();
            $slots = $data['slots'] ?? [];
            $blocks = $data['blocks'] ?? [];
            $translation = $data['translation'];
            unset($data['title'], $data['slug'], $data['slots'], $data['blocks'], $data['translation']);

            $page = Page::create($data);
            $this->syncDefaultTranslation($page, $translation);
            $this->syncSlots($page, $slots, true);

            if ($blocks === []) {
                $this->syncBlocks($page, []);
            } else {
                $this->syncBlocks($page, $blocks);
            }

            $this->revisionManager->capture(
                $page->fresh(),
                $request->user(),
                'Page created',
                'Initial page state was captured when the page was created.',
            );

            return $page;
        });

        $redirect = redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Page saved as draft.');

        return $redirect;
    }

    public function edit(Page $page): View
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $page);

        $page->loadMissing([
            'site',
            'layoutType.slots.slotType',
            'translations.locale',
            'slots.slotType',
            'blocks' => fn ($query) => $query
                ->with('children')
                ->whereNull('parent_id')
                ->orderBy('sort_order'),
        ]);
        $page->loadCount('blocks');

        $canEditContent = $this->workflowManager->canEditContent(request()->user(), $page);
        $canViewRevisions = $this->revisionManager->canView(request()->user(), $page);

        $slotBlockPreviews = $page->slots
            ->mapWithKeys(fn (PageSlot $slot) => [
                $slot->id => $this->slotBlockPreviewFor($page, $slot),
            ]);

        return view('admin.pages.edit', [
            'page' => $page,
            'sites' => Site::query()
                ->tap(fn ($query) => $this->authorization->scopeSitesForUser($query, request()->user()))
                ->with(['locales' => fn ($query) => $query->wherePivot('is_enabled', true)->orderByDesc('is_default')->orderBy('name')])
                ->primaryFirst()
                ->orderBy('name')
                ->get(),
            'slotTypes' => SlotType::query()->where('status', 'published')->orderBy('sort_order')->get(),
            'layoutTypes' => LayoutType::query()->with('slots.slotType')->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get(),
            'slotBlockPreviews' => $slotBlockPreviews,
            'translationStatuses' => $page->translationStatusForSite(),
            'canEditContent' => $canEditContent,
            'canViewRevisions' => $canViewRevisions,
            'workflowActions' => $this->workflowManager->workflowActionsFor(request()->user(), $page),
        ]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        $this->authorization->abortUnlessSiteAccess($request->user(), (int) $request->validated('site_id'));

        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);

        DB::transaction(function () use ($request, $page): void {
            $data = $request->validatedData();
            $slots = $data['slots'] ?? [];
            $translation = $data['translation'];
            unset($data['title'], $data['slug'], $data['slots'], $data['blocks'], $data['translation']);

            $page->update($data);
            $this->syncDefaultTranslation($page, $translation);
            $this->syncSlots($page, $slots, false);
            $this->revisionManager->capture(
                $page->fresh(),
                $request->user(),
                'Page updated',
                'Page fields, default translation, and slot assignments were updated.',
            );
        });

        $redirect = redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Page updated successfully.');

        if ($page->isPublished() && $page->publicUrl()) {
            $redirect->with('status_action', [
                'label' => 'View page',
                'url' => $page->publicUrl(),
            ]);
        }

        return $redirect;
    }

    public function editSlotBlocks(Page $page, PageSlot $slot): View
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
        abort_unless($this->workflowManager->canEditContent(request()->user(), $page), 403);
        abort_unless($slot->page_id === $page->id, 404);

        $page->loadMissing(['slots.slotType', 'site.locales', 'translations.locale']);
        $slot->loadMissing('slotType');

        $activeLocale = $this->slotEditorLocale($page);

        $blocks = Block::query()
            ->with([
                ...$this->slotBlockRelations(),
            ])
            ->where('page_id', $page->id)
            ->where('slot_type_id', $slot->slot_type_id)
            ->orderBy('sort_order')
            ->get();

        $blockTypes = BlockType::query()->where('status', 'published')->orderBy('sort_order')->orderBy('name')->get();
        $pickerParentId = request()->integer('parent_id') ?: null;
        $resolvedBlocks = $this->blockTranslationResolver->resolveCollection($blocks, $activeLocale)->values();
        $pickerBlockTypes = $this->pickerBlockTypes($resolvedBlocks, $blockTypes, $pickerParentId);
        $modalState = $this->slotBlockModalState($page, $slot, $blocks, $blockTypes, $pickerBlockTypes, $pickerParentId);
        $rootBlocks = $this->blockTranslationResolver
            ->resolveCollection($blocks->whereNull('parent_id')->values(), $activeLocale)
            ->values();
        $expandedBlockIds = $this->slotExpandedBlockIds($resolvedBlocks, $modalState['block']);

        return view('admin.pages.slot-blocks', [
            'page' => $page,
            'slot' => $slot,
            'blocks' => $rootBlocks,
            'blockTypes' => $blockTypes,
            'pickerBlockTypes' => $pickerBlockTypes,
            'pickerParentBlock' => $pickerParentId ? $resolvedBlocks->firstWhere('id', $pickerParentId) : null,
            'activeLocale' => $activeLocale,
            'availableLocales' => $page->translationStatusForSite(),
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

    public function updateWorkflow(Request $request, Page $page): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);

        $action = $request->string('action')->toString();
        $fromStatus = $page->status;
        $message = DB::transaction(function () use ($page, $request, $action, $fromStatus): string {
            $message = $this->workflowManager->apply($page, $request->user(), $action);
            $updatedPage = $page->fresh();

            $this->revisionManager->capture(
                $updatedPage,
                $request->user(),
                'Workflow updated',
                'Page workflow changed from '.$fromStatus.' to '.$updatedPage->status.'.',
            );

            return $message;
        });
        $page = $page->fresh();

        $redirect = redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', $message);

        if ($page->isPublished() && $page->publicUrl()) {
            $redirect->with('status_action', [
                'label' => 'View page',
                'url' => $page->publicUrl(),
            ]);
        }

        return $redirect;
    }

    public function reorderSlotBlocks(Request $request, Page $page, PageSlot $slot): JsonResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);
        abort_unless($slot->page_id === $page->id, 404);

        $validated = $request->validate([
            'blocks' => ['required', 'array', 'min:1'],
            'blocks.*' => ['required', 'integer', 'distinct'],
        ]);

        $blockIds = collect($validated['blocks'])
            ->map(fn (mixed $id) => (int) $id)
            ->values();

        $blocks = $this->authorization->scopeBlocksForUser(Block::query(), $request->user())
            ->whereIn('id', $blockIds)
            ->where('page_id', $page->id)
            ->where('slot_type_id', $slot->slot_type_id)
            ->get(['id', 'page_id', 'slot_type_id', 'parent_id', 'sort_order']);

        if ($blocks->count() !== $blockIds->count()) {
            return response()->json([
                'message' => 'Submitted blocks must belong to the current page and slot.',
                'errors' => ['blocks' => ['Submitted blocks must belong to the current page and slot.']],
            ], 422);
        }

        $parentIds = $blocks
            ->map(fn (Block $block) => $block->parent_id)
            ->uniqueStrict();

        if ($parentIds->count() !== 1) {
            return response()->json([
                'message' => 'Submitted blocks must belong to the same parent group.',
                'errors' => ['blocks' => ['Submitted blocks must belong to the same parent group.']],
            ], 422);
        }

        $parentId = $parentIds->first();

        $siblings = $this->authorization->scopeBlocksForUser(Block::query(), $request->user())
            ->where('page_id', $page->id)
            ->where('slot_type_id', $slot->slot_type_id)
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

        DB::transaction(function () use ($page, $slot, $blockIds, $parentId, $request): void {
            $siblings = $this->authorization->scopeBlocksForUser(Block::query(), $request->user())
                ->where('page_id', $page->id)
                ->where('slot_type_id', $slot->slot_type_id)
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

            $this->revisionManager->capture(
                $page->fresh(),
                $request->user(),
                'Block order updated',
                'Page block order was changed.',
            );
        });

        return response()->json([
            'ok' => true,
            'message' => 'Saved',
        ]);
    }

    public function updateSlotSettings(PageSlotSettingsRequest $request, Page $page, PageSlot $slot): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $page);
        abort_unless($this->workflowManager->canEditContent($request->user(), $page), 403);
        abort_unless($slot->page_id === $page->id, 404);

        DB::transaction(function () use ($request, $page, $slot): void {
            $slot->update(['settings' => $request->validatedSettings()]);

            $this->revisionManager->capture(
                $page->fresh(),
                $request->user(),
                'Slot settings updated',
                'Shared slot wrapper settings were updated.',
            );
        });

        return redirect()
            ->route('admin.pages.slots.blocks', $this->slotEditorRouteParameters($page, $slot))
            ->with('status', 'Slot settings updated successfully.');
    }

    private function resolveSiteContext(Request $request, Collection $sites, bool $persist = true): array
    {
        $requestedSite = null;
        $hasRequestedSite = false;

        if ($request->query->has('site')) {
            $requestedSite = $request->query('site');
            $hasRequestedSite = true;
        } elseif ($request->query->has('site_id')) {
            $requestedSite = $request->query('site_id');
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

    public function destroy(Page $page): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $page);
        $siteId = $page->site_id;

        $page->delete();

        return redirect()
            ->route('admin.pages.index', $siteId ? ['site' => $siteId] : [])
            ->with('status', 'Page deleted successfully.');
    }

    private function syncBlocks(Page $page, array $submittedBlocks): void
    {
        $existingBlocks = $page->blocks()->with('blockAssets')->get()->keyBy('id');
        $keptBlockIds = [];
        $localeCode = Locale::normalizeCode(request('locale'));

        foreach (array_values($submittedBlocks) as $index => $blockData) {
            $blockId = $blockData['id'] ?? null;
            $delete = (bool) ($blockData['_delete'] ?? false);

            unset($blockData['id'], $blockData['_delete']);

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
                ? $existingBlocks[$blockId]
                : new Block;

            $block = $this->blockPayloadWriter->save($block, $page, $blockData, $localeCode);
            $keptBlockIds[] = $block->id;
        }

        $page->blocks()
            ->whereNotIn('id', $keptBlockIds)
            ->delete();
    }

    private function syncDefaultTranslation(Page $page, array $translation): void
    {
        $defaultLocaleId = Page::defaultLocaleId();

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

        $page->unsetRelation('translations');
        $page->load('translations');
        $page->setRelation('currentTranslation', $page->defaultTranslation());
    }

    private function slotBlockModalState(Page $page, PageSlot $slot, $blocks, $blockTypes, $pickerBlockTypes, ?int $pickerParentId = null): array
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
        $block->setAttribute('translation_state', $selectedBlockType->slug === 'navigation-auto' || $selectedBlockType->slug === 'menu' ? 'shared' : 'missing');
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

        return $this->authorization->scopeAssetsForUser(Asset::query(), request()->user())
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
                'slot_page_id' => $this->pageSlotRouteId($block->page_id, $block->slot_type_id),
            ])
            ->values();
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

    private function slotEditorLocale(Page $page): Locale
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

    private function syncSlots(Page $page, array $submittedSlots, bool $isCreating = false): void
    {
        if ($page->layout_type_id) {
            $this->syncLayoutDrivenSlots($page);

            return;
        }

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

    private function syncLayoutDrivenSlots(Page $page): void
    {
        $layoutType = LayoutType::query()->with('slots.slotType')->find($page->layout_type_id);

        if (! $layoutType) {
            return;
        }

        $existingSlots = $page->slots()->get()->keyBy('slot_type_id');
        $pageOwnedLayoutSlots = $layoutType->slots
            ->filter(fn (LayoutTypeSlot $slot) => $slot->isPageOwned())
            ->values();
        $keptSlotTypeIds = [];

        foreach ($pageOwnedLayoutSlots as $index => $layoutSlot) {
            $slot = $existingSlots->get($layoutSlot->slot_type_id);
            $payload = [
                'page_id' => $page->id,
                'slot_type_id' => $layoutSlot->slot_type_id,
                'sort_order' => $index,
                'settings' => [
                    'wrapper_preset' => $layoutSlot->wrapperPreset(),
                    'wrapper_element' => $layoutSlot->wrapperElement(),
                ],
            ];

            if ($slot) {
                $slot->update($payload);
            } else {
                PageSlot::query()->create($payload);
            }

            $keptSlotTypeIds[] = $layoutSlot->slot_type_id;
        }

        $page->slots()->whereNotIn('slot_type_id', $keptSlotTypeIds)->delete();
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

    private function slotEditorRouteParameters(Page $page, PageSlot $slot): array
    {
        $parameters = ['page' => $page, 'slot' => $slot];
        $localeCode = Locale::normalizeCode(request('locale'));
        $defaultLocaleCode = Locale::query()->where('is_default', true)->value('code');

        if ($localeCode !== null && $localeCode !== $defaultLocaleCode) {
            $parameters['locale'] = $localeCode;
        }

        return $parameters;
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
