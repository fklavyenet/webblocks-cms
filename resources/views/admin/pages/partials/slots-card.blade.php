@php
    use WebBlocks\Cms\Models\PageSlot;
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('page_slots_card.'.$key, $adminLocale, $replace);
    $pageSlots = $page->slots->sortBy('sort_order')->values();
    $availableSlotTypes = $slotTypes->reject(fn ($slotType) => $pageSlots->pluck('slot_type_id')->contains($slotType->id));
    $addSlotMenuId = 'page-slot-add-menu-'.$page->id;
    $slotSharedSlotOptions = $slotSharedSlotOptions ?? collect();
    $canCreateSharedSlots = $canCreateSharedSlots ?? false;
    $sharedSlotSourcesAvailable = $sharedSlotSourcesAvailable ?? false;
    $pageReturnUrl = $pageReturnUrl ?? route('admin.pages.index', ['site' => $page->site_id]);
    $requestedModal = request('modal');
    $selectedDeleteSlotId = (int) request('slot');
    $selectedDeleteSlot = $requestedModal === 'delete-page-slot'
        ? $pageSlots->firstWhere('id', $selectedDeleteSlotId)
        : null;
@endphp

<div class="wb-card">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <div class="wb-stack wb-gap-1">
            <strong>{{ $adminText('slots') }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('description') }}</span>
        </div>

        @if ($canEditContent)
            <div class="wb-dropdown wb-dropdown-end">
                <button
                    type="button"
                    class="wb-btn wb-btn-primary wb-btn-sm"
                    data-wb-toggle="dropdown"
                    data-wb-target="#{{ $addSlotMenuId }}"
                    aria-expanded="false"
                    @disabled($availableSlotTypes->isEmpty())
                >
                    {{ $adminText('add_slot') }}
                </button>

                <div class="wb-dropdown-menu" id="{{ $addSlotMenuId }}">
                    @forelse ($availableSlotTypes as $slotType)
                        <form method="POST" action="{{ route('admin.pages.slots.store', $page) }}">
                            @csrf
                            <input type="hidden" name="slot_type_id" value="{{ $slotType->id }}">
                            <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
                            <button type="submit" class="wb-dropdown-item">{{ $slotType->name }}</button>
                        </form>
                    @empty
                        <span class="wb-dropdown-item" aria-disabled="true">{{ $adminText('no_slots_available') }}</span>
                    @endforelse
                </div>
            </div>
        @else
            <span class="wb-text-sm wb-text-muted">{{ $adminText('locked_by_workflow') }}</span>
        @endif
    </div>

    <div class="wb-card-body wb-stack wb-gap-3">
        @error('slot_type_id')
            <div class="wb-alert wb-alert-danger">{{ $message }}</div>
        @enderror

        @error('slot')
            <div class="wb-alert wb-alert-danger">{{ $message }}</div>
        @enderror

        @if ($pageSlots->isEmpty())
            <div class="wb-empty">
                <div class="wb-empty-title">{{ $adminText('no_slots_title') }}</div>
                <div class="wb-empty-text">{{ $adminText('no_slots_help') }}</div>
            </div>
        @else
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>{{ $adminText('slot') }}</th>
                            <th>{{ $adminText('source') }}</th>
                            <th title="{{ $adminText('top_level_blocks_title') }}">{{ $adminText('top_level_blocks') }}</th>
                            <th>{{ $adminText('actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pageSlots as $pageSlot)
                            @php
                                $sourceType = $pageSlot->runtimeSourceType();
                                $sharedSlot = $pageSlot->sharedSlot;
                                $warning = $pageSlot->sharedSlotWarning();
                                $compatibleSharedSlots = $slotSharedSlotOptions->get($pageSlot->id, collect());
                                $preview = $slotBlockPreviews->get($pageSlot->id, [
                                    'items' => collect(),
                                    'remaining' => 0,
                                    'is_empty' => true,
                                ]);
                                $oldSlotId = (int) old('slot_id');
                                $isOldSlot = $oldSlotId === $pageSlot->id;
                                $selectedSourceType = $isOldSlot && old('source_type') !== null
                                    ? old('source_type')
                                    : $sourceType;
                                $selectedSharedSlotId = $isOldSlot && old('shared_slot_id') !== null
                                    ? (int) old('shared_slot_id')
                                    : (int) ($pageSlot->shared_slot_id ?? 0);
                                $showSourceModal = $isOldSlot && ($errors->has('source_type') || $errors->has('shared_slot_id'));
                                $pageBlockCount = $preview['is_empty'] ? 0 : $preview['items']->count() + $preview['remaining'];
                                $sourceModalId = 'slot-source-modal-'.$pageSlot->id;
                                $slotName = $pageSlot->slotType?->name ?? $adminText('fallback_slot');
                                $topLevelLabel = $pageBlockCount === 1 ? $adminText('top_level_block') : $adminText('top_level_blocks_count');
                                $pageBlockCountLabel = $sourceType === PageSlot::SOURCE_TYPE_PAGE
                                    ? $adminText('count_label', ['count' => $pageBlockCount, 'label' => $topLevelLabel])
                                    : $adminText('page_owned_count', ['count' => $pageBlockCount, 'label' => $topLevelLabel]);
                            @endphp
                            <tr>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $slotName }}</strong>
                                        <div class="wb-cluster wb-cluster-2">
                                            <span class="wb-status-pill wb-status-info">{{ $pageSlot->slotSlug() }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        @if ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT && $sharedSlot)
                                            <strong>{{ $adminText('shared_slot_source', ['name' => $sharedSlot->name]) }}</strong>
                                            <span class="wb-text-sm wb-text-muted"><code>{{ $sharedSlot->handle }}</code></span>
                                        @elseif ($sourceType === PageSlot::SOURCE_TYPE_DISABLED)
                                            <strong>{{ $adminText('disabled') }}</strong>
                                        @else
                                            <strong>{{ $adminText('page_content') }}</strong>
                                        @endif

                                        @if ($warning)
                                            <div class="wb-alert wb-alert-warning wb-text-sm">{{ $warning }}</div>
                                        @elseif ($canEditContent && $sharedSlotSourcesAvailable && $showSourceModal)
                                            <div class="wb-alert wb-alert-danger wb-text-sm">{{ $adminText('source_update_attention') }}</div>
                                        @elseif (! $sharedSlotSourcesAvailable)
                                            <span class="wb-text-sm wb-text-muted">{{ $adminText('shared_slot_migration_pending') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $pageBlockCountLabel }}</strong>
                                        @if ($sourceType !== PageSlot::SOURCE_TYPE_PAGE && $pageBlockCount > 0)
                                            <span class="wb-text-sm wb-text-muted">{{ $adminText('preserved') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($canEditContent)
                                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                            @if ($sharedSlotSourcesAvailable)
                                                <button
                                                    type="button"
                                                    class="wb-btn wb-btn-secondary wb-btn-sm"
                                                    data-wb-page-slot-source-open
                                                    data-wb-page-slot-source-target="#{{ $sourceModalId }}"
                                                    aria-controls="{{ $sourceModalId }}"
                                                >
                                                    {{ $adminText('manage_source') }}
                                                </button>
                                            @endif

                                            @if ($sourceType === PageSlot::SOURCE_TYPE_PAGE)
                                                <a href="{{ route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $pageSlot, 'return_url' => $pageReturnUrl]) }}" class="wb-btn wb-btn-primary wb-btn-sm">{{ $adminText('edit_blocks') }}</a>
                                            @else
                                                <a
                                                    href="{{ route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $pageSlot, 'return_url' => $pageReturnUrl]) }}"
                                                    class="wb-btn wb-btn-secondary wb-btn-sm"
                                                    title="{{ $adminText('preserved_blocks_title') }}"
                                                    aria-label="{{ $adminText('edit_preserved_blocks') }}"
                                                >
                                                    {{ $adminText('page_blocks') }}
                                                </a>
                                            @endif

                                            <div class="wb-action-group">
                                                <form method="POST" action="{{ route('admin.pages.slots.move-up', [$page, $pageSlot]) }}">
                                                    @csrf
                                                    <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
                                                    <button type="submit" class="wb-action-btn" title="{{ $adminText('move_slot_up') }}" aria-label="{{ $adminText('move_slot_up') }}" @disabled($loop->first)><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.pages.slots.move-down', [$page, $pageSlot]) }}">
                                                    @csrf
                                                    <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
                                                    <button type="submit" class="wb-action-btn" title="{{ $adminText('move_slot_down') }}" aria-label="{{ $adminText('move_slot_down') }}" @disabled($loop->last)><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                                                </form>
                                                <a
                                                    href="{{ route('admin.pages.edit', ['page' => $page, 'modal' => 'delete-page-slot', 'slot' => $pageSlot->id, 'return_url' => $pageReturnUrl]) }}"
                                                    class="wb-action-btn wb-action-btn-delete"
                                                    title="{{ $adminText('delete_slot') }}"
                                                    aria-label="{{ $adminText('delete_slot') }}"
                                                    aria-haspopup="dialog"
                                                >
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </a>
                                            </div>
                                        </div>
                                    @else
                                        <span class="wb-text-sm wb-text-muted">{{ $adminText('workflow_locks_slot_editing') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@if ($canEditContent && $sharedSlotSourcesAvailable)
    @push('overlays')
        @foreach ($pageSlots as $pageSlot)
            @php
                $sourceType = $pageSlot->runtimeSourceType();
                $sharedSlot = $pageSlot->sharedSlot;
                $warning = $pageSlot->sharedSlotWarning();
                $compatibleSharedSlots = $slotSharedSlotOptions->get($pageSlot->id, collect());
                $oldSlotId = (int) old('slot_id');
                $isOldSlot = $oldSlotId === $pageSlot->id;
                $selectedSourceType = $isOldSlot && old('source_type') !== null
                    ? old('source_type')
                    : $sourceType;
                $selectedSharedSlotId = $isOldSlot && old('shared_slot_id') !== null
                    ? (int) old('shared_slot_id')
                    : (int) ($pageSlot->shared_slot_id ?? 0);
                $showSourceModal = $isOldSlot && ($errors->has('source_type') || $errors->has('shared_slot_id'));
                $sourceModalId = 'slot-source-modal-'.$pageSlot->id;
                $sourceModalTitleId = $sourceModalId.'-title';
                $slotName = $pageSlot->slotType?->name ?? $adminText('fallback_slot');
                $currentSourceSummary = match (true) {
                    $sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT && $sharedSlot => $adminText('shared_slot_source', ['name' => $sharedSlot->name]),
                    $sourceType === PageSlot::SOURCE_TYPE_DISABLED => $adminText('disabled'),
                    default => $adminText('page_content'),
                };
                $selectedSourceHelper = match ($selectedSourceType) {
                    PageSlot::SOURCE_TYPE_SHARED_SLOT => $adminText('source_shared_slot_helper'),
                    PageSlot::SOURCE_TYPE_DISABLED => $adminText('source_disabled_helper'),
                    default => $adminText('source_page_helper'),
                };
            @endphp
            <div class="wb-overlay-layer wb-overlay-layer--dialog" data-wb-page-slot-source-modal @if (! $showSourceModal) hidden @endif>
                <div class="wb-modal wb-modal-lg {{ $showSourceModal ? 'is-open' : '' }}" id="{{ $sourceModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $sourceModalTitleId }}">
                    <div class="wb-modal-dialog">
                        <div class="wb-modal-header">
                            <div class="wb-stack wb-gap-1">
                                <h2 class="wb-modal-title" id="{{ $sourceModalTitleId }}">{{ $adminText('manage_source_title', ['slot' => $slotName]) }}</h2>
                                <span class="wb-text-sm wb-text-muted">{{ $adminText('manage_source_help') }}</span>
                            </div>

                            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" data-wb-page-slot-source-modal-close aria-label="{{ $adminText('close_source_settings') }}">
                                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('admin.pages.slots.source.update', [$page, $pageSlot]) }}" class="wb-stack wb-gap-0" data-wb-page-slot-source-form data-wb-admin-dirty-form data-wb-admin-dirty-close-confirm="{{ $adminText('discard_source_changes') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="slot_id" value="{{ $pageSlot->id }}">
                            <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">

                            <div class="wb-modal-body wb-stack wb-gap-4">
                                <div class="wb-stack wb-gap-1">
                                    <span class="wb-text-sm wb-text-muted">{{ $adminText('current_source_summary', ['source' => $currentSourceSummary]) }}</span>

                                    @if ($warning)
                                        <div class="wb-alert wb-alert-warning wb-text-sm">{{ $warning }}</div>
                                    @endif
                                </div>

                                <div class="wb-stack wb-gap-2">
                                    <label class="wb-text-sm" for="slot-source-type-page-{{ $pageSlot->id }}">{{ $adminText('source') }}</label>

                                    <div class="wb-btn-check-group" role="radiogroup" aria-label="{{ $adminText('source') }}" data-wb-slot-source-picker>
                                        <label class="wb-btn-check" for="slot-source-type-page-{{ $pageSlot->id }}">
                                            <input
                                                id="slot-source-type-page-{{ $pageSlot->id }}"
                                                type="radio"
                                                name="source_type"
                                                value="page"
                                                data-wb-slot-source-type
                                                @checked($selectedSourceType === PageSlot::SOURCE_TYPE_PAGE)
                                            >
                                            <span>{{ $adminText('page_content') }}</span>
                                        </label>

                                        <label class="wb-btn-check" for="slot-source-type-shared-slot-{{ $pageSlot->id }}">
                                            <input
                                                id="slot-source-type-shared-slot-{{ $pageSlot->id }}"
                                                type="radio"
                                                name="source_type"
                                                value="shared_slot"
                                                data-wb-slot-source-type
                                                @checked($selectedSourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT)
                                            >
                                            <span>{{ $adminText('shared_slot') }}</span>
                                        </label>

                                        <label class="wb-btn-check" for="slot-source-type-disabled-{{ $pageSlot->id }}">
                                            <input
                                                id="slot-source-type-disabled-{{ $pageSlot->id }}"
                                                type="radio"
                                                name="source_type"
                                                value="disabled"
                                                data-wb-slot-source-type
                                                @checked($selectedSourceType === PageSlot::SOURCE_TYPE_DISABLED)
                                            >
                                            <span>{{ $adminText('disabled') }}</span>
                                        </label>
                                    </div>

                                    <div class="wb-text-sm wb-text-muted" data-wb-slot-source-helper>{{ $selectedSourceHelper }}</div>

                                    @if ($isOldSlot)
                                        @error('source_type')
                                            <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>

                                <div class="wb-stack wb-gap-1" data-wb-shared-slot-field @if ($selectedSourceType !== PageSlot::SOURCE_TYPE_SHARED_SLOT) hidden @endif>
                                    <label for="slot-shared-slot-{{ $pageSlot->id }}">{{ $adminText('shared_slot') }}</label>
                                    <select id="slot-shared-slot-{{ $pageSlot->id }}" name="shared_slot_id" class="wb-select" data-wb-shared-slot-select @disabled($selectedSourceType !== PageSlot::SOURCE_TYPE_SHARED_SLOT)>
                                        <option value="">{{ $adminText('select_shared_slot') }}</option>
                                        @foreach ($compatibleSharedSlots as $compatibleSharedSlot)
                                            <option value="{{ $compatibleSharedSlot->id }}" @selected($selectedSharedSlotId === (int) $compatibleSharedSlot->id)>
                                                {{ $compatibleSharedSlot->name }} ({{ $compatibleSharedSlot->handle }})
                                            </option>
                                        @endforeach
                                    </select>

                                    @if ($compatibleSharedSlots->isEmpty())
                                        <div class="wb-text-sm wb-text-muted">
                                            {{ $adminText('no_compatible_shared_slots') }}
                                            @if ($canCreateSharedSlots)
                                                <a href="{{ route('admin.shared-slots.create', ['site' => $page->site_id]) }}">{{ $adminText('create_shared_slot') }}</a>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($isOldSlot)
                                        @error('shared_slot_id')
                                            <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                                        @enderror
                                    @endif
                                </div>

                                <div class="wb-text-sm wb-text-muted">{{ $adminText('page_owned_preserved_help') }}</div>
                            </div>

                            <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                                <div class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                                    <button type="submit" class="wb-btn wb-btn-primary">{{ $adminText('save_source') }}</button>
                                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal" data-wb-page-slot-source-modal-close>{{ $adminText('cancel') }}</button>
                                </div>
                                <span class="wb-text-sm wb-text-muted">{{ $adminText('slot_key') }} <code>{{ $pageSlot->slotSlug() }}</code></span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endpush
@endif

@if ($canEditContent && $selectedDeleteSlot)
    @push('overlays')
        @php
            $deleteSlotName = $selectedDeleteSlot->slotType?->name ?? $adminText('fallback_slot');
            $deleteSlotKey = $selectedDeleteSlot->slotSlug();
            $deleteSlotPreview = $slotBlockPreviews->get($selectedDeleteSlot->id, [
                'items' => collect(),
                'remaining' => 0,
                'is_empty' => true,
            ]);
            $deleteSlotBlockCount = $deleteSlotPreview['is_empty']
                ? 0
                : $deleteSlotPreview['items']->count() + $deleteSlotPreview['remaining'];
            $deleteSlotCloseUrl = route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]);
            $deleteSlotModalId = 'page-slot-delete-modal-'.$selectedDeleteSlot->id;
            $deleteSlotTitleId = $deleteSlotModalId.'-title';
            $deleteSlotDescriptionId = $deleteSlotModalId.'-description';
        @endphp

        <div class="wb-overlay-layer wb-overlay-layer--dialog">
            <div class="wb-modal wb-modal-lg is-open" id="{{ $deleteSlotModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $deleteSlotTitleId }}" aria-describedby="{{ $deleteSlotDescriptionId }}">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-header">
                        <div>
                            <h2 class="wb-modal-title" id="{{ $deleteSlotTitleId }}">{{ $adminText('delete_page_slot') }}</h2>
                            <p class="wb-text-sm wb-text-muted" id="{{ $deleteSlotDescriptionId }}">{{ $adminText('delete_page_slot_description') }}</p>
                        </div>

                        <a href="{{ $deleteSlotCloseUrl }}" class="wb-modal-close" aria-label="{{ $adminText('close_delete_dialog') }}">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                        </a>
                    </div>

                    <form method="POST" action="{{ route('admin.pages.slots.destroy', [$page, $selectedDeleteSlot]) }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
                        <input type="hidden" name="confirm_delete_slot" value="1">

                        <div class="wb-modal-body wb-stack wb-gap-4">
                            <div class="wb-alert wb-alert-warning">
                                {{ $adminText('delete_warning') }}
                            </div>

                            <div class="wb-stack wb-gap-2">
                                <div><strong>{{ $deleteSlotName }}</strong></div>
                                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                    <span class="wb-status-pill wb-status-info">{{ $deleteSlotKey }}</span>
                                    <span class="wb-status-pill {{ $deleteSlotBlockCount > 0 ? 'wb-status-pending' : 'wb-status-active' }}">
                                        {{ $deleteSlotBlockCount }} {{ $deleteSlotBlockCount === 1 ? $adminText('block') : $adminText('blocks') }}
                                    </span>
                                </div>
                            </div>

                            @if ($deleteSlotBlockCount > 0)
                                <div class="wb-alert wb-alert-danger">
                                    {{ $adminText('delete_blocked') }}
                                </div>
                            @endif
                        </div>

                        <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                            <div class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                                <button type="submit" class="wb-btn wb-btn-danger" @disabled($deleteSlotBlockCount > 0)>{{ $adminText('delete_slot') }}</button>
                                <a href="{{ $deleteSlotCloseUrl }}" class="wb-btn wb-btn-secondary">{{ $adminText('cancel') }}</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endpush
@endif
