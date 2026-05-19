@php
    $layoutSlots = $pageLayout->layoutSlots->sortBy('sort_order')->values();
@endphp

<div class="wb-card">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <div class="wb-stack wb-gap-1">
            <strong>Page Layout Slots</strong>
            <span class="wb-text-sm wb-text-muted">Slot Types are the catalog for managed layout regions and wrapper rendering.</span>
        </div>

        <a href="{{ route('admin.page-layouts.slots.create', $pageLayout) }}" class="wb-btn wb-btn-primary wb-btn-sm">Add Slot</a>
    </div>

    <div class="wb-card-body">
        @error('page_layout_slot')
            <div class="wb-alert wb-alert-danger">{{ $message }}</div>
        @enderror

        <div class="wb-card wb-card-muted wb-mb-3">
            <div class="wb-card-body wb-stack wb-gap-1 wb-text-sm wb-text-muted">
                <div>Page Layout Slots define the wrapper for each page region.</div>
                <div>Blocks render inside these wrappers.</div>
                <div>Use Body Class plus slot ID and classes for layout-specific CSS.</div>
            </div>
        </div>

        @if ($layoutSlots->isEmpty())
            <div class="wb-empty">
                <div class="wb-empty-title">No layout slots yet</div>
                <div class="wb-empty-text">Add managed slots to define how this Page Layout renders publicly.</div>
            </div>
        @else
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Slot</th>
                            <th>Wrapper</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($layoutSlots as $layoutSlot)
                            <tr>
                                <td class="wb-nowrap">{{ $layoutSlot->sort_order }}</td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                            <strong>{{ $layoutSlot->label ?: ($layoutSlot->slotType?->name ?? $layoutSlot->slot_name) }}</strong>
                                            <span class="wb-status-pill wb-status-info">{{ $layoutSlot->slotType?->name ?? 'No Slot Type' }}</span>
                                        </div>
                                        <div class="wb-text-sm wb-text-muted"><code>{{ $layoutSlot->slot_name }}</code></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1 wb-text-sm">
                                        <div><strong>Element:</strong> <code>{{ $layoutSlot->html_element }}</code></div>
                                        <div><strong>ID:</strong> <span title="{{ $layoutSlot->html_id ?: '-' }}">{{ $layoutSlot->html_id ?: '-' }}</span></div>
                                        <div title="{{ $layoutSlot->html_classes ?: '-' }}"><strong>Classes:</strong> {{ \Illuminate\Support\Str::limit($layoutSlot->html_classes ?: '-', 48) }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                            <span class="wb-status-pill {{ $layoutSlot->is_required ? 'wb-status-info' : 'wb-status-pending' }}">{{ $layoutSlot->is_required ? 'Required' : 'Optional' }}</span>
                                            <span class="wb-status-pill {{ $layoutSlot->statusBadgeClass() }}">{{ $layoutSlot->statusLabel() }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="wb-nowrap">
                                    <div class="wb-action-group">
                                        <a href="{{ route('admin.page-layouts.slots.edit', [$pageLayout, $layoutSlot]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit Page Layout Slot" aria-label="Edit Page Layout Slot">
                                            <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                        </a>
                                        @if (! $layoutSlot->is_system && ! $layoutSlot->is_required)
                                            <form method="POST" action="{{ route('admin.page-layouts.slots.destroy', [$pageLayout, $layoutSlot]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete Page Layout Slot" aria-label="Delete Page Layout Slot">
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
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
