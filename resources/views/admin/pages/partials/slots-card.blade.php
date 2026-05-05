@php
    use App\Models\PageSlot;

    $pageSlots = $page->slots->sortBy('sort_order')->values();
    $availableSlotTypes = $slotTypes->reject(fn ($slotType) => $pageSlots->pluck('slot_type_id')->contains($slotType->id));
    $addSlotMenuId = 'page-slot-add-menu-'.$page->id;
    $slotSharedSlotOptions = $slotSharedSlotOptions ?? collect();
    $canCreateSharedSlots = $canCreateSharedSlots ?? false;
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
                            @endphp
                            <tr>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $pageSlot->slotType?->name ?? 'Slot' }}</strong>
                                        <span class="wb-text-sm wb-text-muted">Slot key: <code>{{ $pageSlot->slotSlug() }}</code></span>
                                        @if ($warning)
                                            <div class="wb-alert wb-alert-warning wb-text-sm">{{ $warning }}</div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-2">
                                        <div class="wb-stack wb-gap-1">
                                            <span class="wb-text-sm wb-text-muted">Current source</span>
                                            @if ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT && $sharedSlot)
                                                <strong>Shared Slot: {{ $sharedSlot->name }}</strong>
                                                <span class="wb-text-sm wb-text-muted"><code>{{ $sharedSlot->handle }}</code></span>
                                                <span class="wb-text-sm wb-text-muted">{{ $sharedSlot->slot_name ?: 'Any slot' }} | {{ $sharedSlot->public_shell ?: 'Any shell' }}</span>
                                            @elseif ($sourceType === PageSlot::SOURCE_TYPE_DISABLED)
                                                <strong>Disabled</strong>
                                                <span class="wb-text-sm wb-text-muted">This slot is disabled for public rendering.</span>
                                            @else
                                                <strong>Page Content</strong>
                                            @endif
                                        </div>

                                        @if ($canEditContent)
                                            <form method="POST" action="{{ route('admin.pages.slots.source.update', [$page, $pageSlot]) }}" class="wb-stack wb-gap-2">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="slot_id" value="{{ $pageSlot->id }}">

                                                <div class="wb-grid wb-grid-2">
                                                    <div>
                                                        <label class="wb-text-sm" for="slot-source-type-{{ $pageSlot->id }}">Source</label>
                                                        <select id="slot-source-type-{{ $pageSlot->id }}" name="source_type" class="wb-select">
                                                            <option value="page" @selected($selectedSourceType === PageSlot::SOURCE_TYPE_PAGE)>Page Content</option>
                                                            <option value="shared_slot" @selected($selectedSourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT)>Shared Slot</option>
                                                            <option value="disabled" @selected($selectedSourceType === PageSlot::SOURCE_TYPE_DISABLED)>Disabled</option>
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label class="wb-text-sm" for="slot-shared-slot-{{ $pageSlot->id }}">Shared Slot</label>
                                                        <select id="slot-shared-slot-{{ $pageSlot->id }}" name="shared_slot_id" class="wb-select">
                                                            <option value="">Select Shared Slot</option>
                                                            @if ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT && $sharedSlot && $compatibleSharedSlots->doesntContain(fn ($candidate) => $candidate->id === $sharedSlot->id))
                                                                <option value="{{ $sharedSlot->id }}" @selected($selectedSharedSlotId === (int) $sharedSlot->id)>
                                                                    {{ $sharedSlot->name }} ({{ $sharedSlot->handle }}) - currently incompatible
                                                                </option>
                                                            @endif
                                                            @foreach ($compatibleSharedSlots as $compatibleSharedSlot)
                                                                <option value="{{ $compatibleSharedSlot->id }}" @selected($selectedSharedSlotId === (int) $compatibleSharedSlot->id)>
                                                                    {{ $compatibleSharedSlot->name }} ({{ $compatibleSharedSlot->handle }}) | {{ $compatibleSharedSlot->slot_name ?: 'Any slot' }} | {{ $compatibleSharedSlot->public_shell ?: 'Any shell' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>

                                                @if ($isOldSlot)
                                                    @error('source_type')
                                                        <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                                                    @enderror
                                                    @error('shared_slot_id')
                                                        <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                                                    @enderror
                                                @endif

                                                @if ($compatibleSharedSlots->isEmpty())
                                                    <div class="wb-text-sm wb-text-muted">
                                                        No compatible active Shared Slots are available for this slot.
                                                        @if ($canCreateSharedSlots)
                                                            <a href="{{ route('admin.shared-slots.create', ['site' => $page->site_id]) }}">Create Shared Slot</a>
                                                        @endif
                                                    </div>
                                                @endif

                                                <div>
                                                    <button type="submit" class="wb-btn wb-btn-secondary wb-btn-sm">Update Source</button>
                                                </div>
                                            </form>
                                        @else
                                            <span class="wb-text-sm wb-text-muted">Locked by workflow</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-2">
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
                                            <span class="wb-text-sm wb-text-muted">These page blocks are rendered publicly.</span>
                                        @elseif ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT)
                                            <span class="wb-text-sm wb-text-muted">These page blocks are preserved but not currently rendered.</span>
                                        @else
                                            <span class="wb-text-sm wb-text-muted">These page blocks are preserved while the slot is disabled.</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-2">
                                        @if ($canEditContent)
                                            @if ($sourceType === PageSlot::SOURCE_TYPE_PAGE)
                                                <a href="{{ route('admin.pages.slots.blocks', [$page, $pageSlot]) }}" class="wb-btn wb-btn-secondary wb-btn-sm">Edit Blocks</a>
                                            @elseif ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT && $sharedSlot)
                                                <a href="{{ route('admin.shared-slots.blocks.edit', $sharedSlot) }}" class="wb-btn wb-btn-secondary wb-btn-sm">Edit Shared Slot</a>
                                                <a href="{{ route('admin.pages.slots.blocks', [$page, $pageSlot]) }}" class="wb-btn wb-btn-secondary wb-btn-sm">Edit Page Blocks</a>
                                            @else
                                                <a href="{{ route('admin.pages.slots.blocks', [$page, $pageSlot]) }}" class="wb-btn wb-btn-secondary wb-btn-sm">Edit Page Blocks</a>
                                            @endif
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
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
