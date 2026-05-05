@php
    use App\Models\PageSlot;

    $pageSlots = $page->slots->sortBy('sort_order')->values();
    $availableSlotTypes = $slotTypes->reject(fn ($slotType) => $pageSlots->pluck('slot_type_id')->contains($slotType->id));
    $addSlotMenuId = 'page-slot-add-menu-'.$page->id;
    $slotSharedSlotOptions = $slotSharedSlotOptions ?? collect();
    $canCreateSharedSlots = $canCreateSharedSlots ?? false;
    $sharedSlotSourcesAvailable = $sharedSlotSourcesAvailable ?? false;
@endphp

<div class="wb-card">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <div class="wb-stack wb-gap-1">
            <strong>Slots</strong>
            <span class="wb-text-sm wb-text-muted">Manage page structure separately from page settings.</span>
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
                    Add Slot
                </button>

                <div class="wb-dropdown-menu" id="{{ $addSlotMenuId }}">
                    @forelse ($availableSlotTypes as $slotType)
                        <form method="POST" action="{{ route('admin.pages.slots.store', $page) }}">
                            @csrf
                            <input type="hidden" name="slot_type_id" value="{{ $slotType->id }}">
                            <button type="submit" class="wb-dropdown-item">{{ $slotType->name }}</button>
                        </form>
                    @empty
                        <span class="wb-dropdown-item" aria-disabled="true">No slots available</span>
                    @endforelse
                </div>
            </div>
        @else
            <span class="wb-text-sm wb-text-muted">Locked by workflow</span>
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
                <div class="wb-empty-title">No slots yet</div>
                <div class="wb-empty-text">Add Header, Main, Sidebar, or Footer to start defining the page structure.</div>
            </div>
        @else
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Source</th>
                            <th>Page Blocks</th>
                            <th>Actions</th>
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
                                $sourceModalName = 'slot-source-'.$pageSlot->id;
                                $showSourceModal = $isOldSlot && ($errors->has('source_type') || $errors->has('shared_slot_id'));
                                $pageBlockCount = $preview['is_empty'] ? 0 : $preview['items']->count() + $preview['remaining'];
                                $pageBlockCountLabel = $pageBlockCount.' '.($pageBlockCount === 1 ? 'page-owned block' : 'page-owned blocks');
                            @endphp
                            <tr>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $pageSlot->slotType?->name ?? 'Slot' }}</strong>
                                        <div>
                                            <span class="wb-status-pill wb-status-info">{{ $pageSlot->slotSlug() }}</span>
                                        </div>
                                        @if ($warning)
                                            <div class="wb-alert wb-alert-warning wb-text-sm">{{ $warning }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        @if ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT && $sharedSlot)
                                            <strong>Shared Slot: {{ $sharedSlot->name }}</strong>
                                            <span class="wb-text-sm wb-text-muted">Handle: <code>{{ $sharedSlot->handle }}</code></span>
                                            <span class="wb-text-sm wb-text-muted">{{ $sharedSlot->slot_name ?: 'Any slot' }} | {{ $sharedSlot->public_shell ?: 'Any shell' }}</span>
                                        @elseif ($sourceType === PageSlot::SOURCE_TYPE_DISABLED)
                                            <strong>Disabled</strong>
                                            <span class="wb-text-sm wb-text-muted">No public slot content will render.</span>
                                        @else
                                            <strong>Page Content</strong>
                                            <span class="wb-text-sm wb-text-muted">This page's own slot blocks render publicly.</span>
                                        @endif

                                        @if ($canEditContent && $sharedSlotSourcesAvailable && $showSourceModal)
                                            <div class="wb-alert wb-alert-danger wb-text-sm">This slot source update needs attention.</div>
                                        @elseif (! $sharedSlotSourcesAvailable)
                                            <span class="wb-text-sm wb-text-muted">Shared Slot source controls will appear after the Shared Slots migration is available.</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $pageBlockCountLabel }}</strong>
                                        @if ($preview['is_empty'])
                                            <span class="wb-text-sm wb-text-muted">No page blocks saved yet</span>
                                        @else
                                            <div class="wb-cluster wb-cluster-2 wb-flex-wrap wb-text-sm wb-text-muted">
                                                @foreach ($preview['items'] as $item)
                                                    <span class="wb-status-pill wb-status-info">{{ $item }}</span>
                                                @endforeach
                                                @if ($preview['remaining'] > 0)
                                                    <span class="wb-text-sm wb-text-muted">+{{ $preview['remaining'] }} more</span>
                                                @endif
                                            </div>
                                        @endif

                                        @if ($sourceType === PageSlot::SOURCE_TYPE_PAGE)
                                            <span class="wb-text-sm wb-text-muted">Rendered publicly.</span>
                                        @elseif ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT)
                                            <span class="wb-text-sm wb-text-muted">Preserved but not currently rendered.</span>
                                        @else
                                            <span class="wb-text-sm wb-text-muted">Preserved but not currently rendered.</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-2">
                                        @if ($canEditContent)
                                            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                                @if ($sharedSlotSourcesAvailable)
                                                    <button
                                                        type="button"
                                                        class="wb-btn wb-btn-secondary wb-btn-sm"
                                                        x-data=""
                                                        x-on:click.prevent="$dispatch('open-modal', '{{ $sourceModalName }}')"
                                                    >
                                                        Manage Source
                                                    </button>
                                                @endif

                                                @if ($sourceType === PageSlot::SOURCE_TYPE_PAGE)
                                                    <a href="{{ route('admin.pages.slots.blocks', [$page, $pageSlot]) }}" class="wb-btn wb-btn-primary wb-btn-sm">Edit Blocks</a>
                                                @elseif ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT && $sharedSlot)
                                                    <a href="{{ route('admin.shared-slots.blocks.edit', $sharedSlot) }}" class="wb-btn wb-btn-primary wb-btn-sm">Edit Shared Slot</a>
                                                    <a href="{{ route('admin.pages.slots.blocks', [$page, $pageSlot]) }}" class="wb-btn wb-btn-secondary wb-btn-sm">Edit Page Blocks (Preserved)</a>
                                                @else
                                                    <a href="{{ route('admin.pages.slots.blocks', [$page, $pageSlot]) }}" class="wb-btn wb-btn-secondary wb-btn-sm">Edit Page Blocks (Preserved)</a>
                                                @endif
                                            </div>
                                        @else
                                            <span class="wb-text-sm wb-text-muted">Workflow locks slot editing for this page.</span>
                                        @endif

                                        @if ($canEditContent)
                                            <div class="wb-action-group">
                                                <form method="POST" action="{{ route('admin.pages.slots.move-up', [$page, $pageSlot]) }}">
                                                    @csrf
                                                    <button type="submit" class="wb-action-btn" title="Move slot up" aria-label="Move slot up" @disabled($loop->first)><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.pages.slots.move-down', [$page, $pageSlot]) }}">
                                                    @csrf
                                                    <button type="submit" class="wb-action-btn" title="Move slot down" aria-label="Move slot down" @disabled($loop->last)><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.pages.slots.destroy', [$page, $pageSlot]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete slot" aria-label="Delete slot"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($canEditContent && $sharedSlotSourcesAvailable)
                                        <x-modal name="{{ $sourceModalName }}" :show="$showSourceModal" focusable>
                                            <div class="wb-modal-header">
                                                <div class="wb-stack wb-gap-1">
                                                    <h2 class="wb-modal-title">Slot Source: {{ $pageSlot->slotType?->name ?? 'Slot' }}</h2>
                                                    <span class="wb-text-sm wb-text-muted">Switch between page-owned blocks, reusable Shared Slot content, or a disabled public slot.</span>
                                                </div>

                                                <button type="button" class="wb-modal-close" x-on:click="$dispatch('close')" aria-label="Close slot source settings">
                                                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                                                </button>
                                            </div>

                                            <form method="POST" action="{{ route('admin.pages.slots.source.update', [$page, $pageSlot]) }}" class="wb-stack wb-gap-0">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="slot_id" value="{{ $pageSlot->id }}">

                                                <div class="wb-modal-body wb-stack wb-gap-4" x-data="{ sourceType: @js($selectedSourceType) }">
                                                    <div class="wb-stack wb-gap-1">
                                                        <strong>Current source</strong>
                                                        @if ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT && $sharedSlot)
                                                            <span>Shared Slot: {{ $sharedSlot->name }}</span>
                                                            <span class="wb-text-sm wb-text-muted">Handle: <code>{{ $sharedSlot->handle }}</code></span>
                                                            <span class="wb-text-sm wb-text-muted">{{ $sharedSlot->slot_name ?: 'Any slot' }} | {{ $sharedSlot->public_shell ?: 'Any shell' }}</span>
                                                        @elseif ($sourceType === PageSlot::SOURCE_TYPE_DISABLED)
                                                            <span>Disabled</span>
                                                            <span class="wb-text-sm wb-text-muted">No public slot content will render.</span>
                                                        @else
                                                            <span>Page Content</span>
                                                            <span class="wb-text-sm wb-text-muted">This page's own slot blocks render publicly.</span>
                                                        @endif

                                                        @if ($warning)
                                                            <div class="wb-alert wb-alert-warning wb-text-sm">{{ $warning }}</div>
                                                        @endif
                                                    </div>

                                                    <div class="wb-stack wb-gap-1">
                                                        <label for="slot-source-type-{{ $pageSlot->id }}">Source</label>
                                                        <select
                                                            id="slot-source-type-{{ $pageSlot->id }}"
                                                            name="source_type"
                                                            class="wb-select"
                                                            x-model="sourceType"
                                                        >
                                                            <option value="page" @selected($selectedSourceType === PageSlot::SOURCE_TYPE_PAGE)>Page Content</option>
                                                            <option value="shared_slot" @selected($selectedSourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT)>Shared Slot</option>
                                                            <option value="disabled" @selected($selectedSourceType === PageSlot::SOURCE_TYPE_DISABLED)>Disabled</option>
                                                        </select>
                                                        <div class="wb-text-sm wb-text-muted" x-show="sourceType === 'page'">Page Content renders this page's own slot blocks.</div>
                                                        <div class="wb-text-sm wb-text-muted" x-show="sourceType === 'shared_slot'">Shared Slot renders reusable slot content.</div>
                                                        <div class="wb-text-sm wb-text-muted" x-show="sourceType === 'disabled'">Disabled renders no public slot content.</div>
                                                        <div class="wb-text-sm wb-text-muted">Existing page-owned blocks are preserved when switching source.</div>
                                                        @if ($isOldSlot)
                                                            @error('source_type')
                                                                <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                                                            @enderror
                                                        @endif
                                                    </div>

                                                    <div class="wb-stack wb-gap-1" x-bind:aria-hidden="sourceType !== 'shared_slot'">
                                                        <label for="slot-shared-slot-{{ $pageSlot->id }}">Shared Slot</label>
                                                        <select
                                                            id="slot-shared-slot-{{ $pageSlot->id }}"
                                                            name="shared_slot_id"
                                                            class="wb-select"
                                                            x-bind:disabled="sourceType !== 'shared_slot'"
                                                        >
                                                            <option value="">Select Shared Slot</option>
                                                            @foreach ($compatibleSharedSlots as $compatibleSharedSlot)
                                                                <option value="{{ $compatibleSharedSlot->id }}" @selected($selectedSharedSlotId === (int) $compatibleSharedSlot->id)>
                                                                    {{ $compatibleSharedSlot->name }} ({{ $compatibleSharedSlot->handle }}) | {{ $compatibleSharedSlot->slot_name ?: 'Any slot' }} | {{ $compatibleSharedSlot->public_shell ?: 'Any shell' }}
                                                                </option>
                                                            @endforeach
                                                        </select>

                                                        @if ($compatibleSharedSlots->isEmpty())
                                                            <div class="wb-text-sm wb-text-muted">
                                                                No compatible active Shared Slots are available for this slot.
                                                                @if ($canCreateSharedSlots)
                                                                    <a href="{{ route('admin.shared-slots.create', ['site' => $page->site_id]) }}">Create Shared Slot</a>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div class="wb-text-sm wb-text-muted">Only active Shared Slots from this site with compatible shell and slot rules are listed.</div>
                                                        @endif

                                                        @if ($isOldSlot)
                                                            @error('shared_slot_id')
                                                                <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                                                            @enderror
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                                                    <span class="wb-text-sm wb-text-muted">Slot key: <code>{{ $pageSlot->slotSlug() }}</code></span>
                                                    <div class="wb-cluster wb-cluster-2">
                                                        <button type="button" class="wb-btn wb-btn-secondary" x-on:click="$dispatch('close')">Cancel</button>
                                                        <button type="submit" class="wb-btn wb-btn-primary">Save Source</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </x-modal>
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
