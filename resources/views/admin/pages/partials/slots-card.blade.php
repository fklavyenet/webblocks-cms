@php
    $pageSlots = $page->slots->sortBy('sort_order')->values();
    $availableSlotTypes = $slotTypes->reject(fn ($slotType) => $pageSlots->pluck('slot_type_id')->contains($slotType->id));
    $addSlotMenuId = 'page-slot-add-menu-'.$page->id;
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
                            <th>Blocks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pageSlots as $pageSlot)
                            @php
                                $preview = $slotBlockPreviews->get($pageSlot->id, [
                                    'items' => collect(),
                                    'remaining' => 0,
                                    'is_empty' => true,
                                ]);
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $pageSlot->slotType?->name ?? 'Slot' }}</strong>
                                </td>
                                <td>
                                    @if ($preview['is_empty'])
                                        <span class="wb-text-sm wb-text-muted">No blocks yet</span>
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
                                </td>
                                <td>
                                    <div class="wb-action-group">
                                        @if ($canEditContent)
                                            <a href="{{ route('admin.pages.slots.blocks', [$page, $pageSlot]) }}" class="wb-action-btn wb-action-btn-view" title="Edit slot blocks" aria-label="Edit slot blocks"><i class="wb-icon wb-icon-layers" aria-hidden="true"></i></a>
                                        @else
                                            <span class="wb-action-btn" aria-disabled="true" title="Workflow locks slot editing for this page"><i class="wb-icon wb-icon-layers" aria-hidden="true"></i></span>
                                        @endif

                                        @if ($canEditContent)
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
