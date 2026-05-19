@php
    $layoutSlotComparison = $layoutSlotComparison ?? [];
    $layoutSlotRows = $layoutSlotComparison['layout_slots'] ?? collect();
    $extraSlotRows = $layoutSlotComparison['extra_slots'] ?? collect();
    $missingCount = (int) ($layoutSlotComparison['missing_count'] ?? 0);
    $hasLayoutSlots = (bool) ($layoutSlotComparison['has_layout_slots'] ?? false);
    $layoutLabel = $layoutSlotComparison['layout_label'] ?? 'Page Layout';
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <div class="wb-stack wb-gap-1">
            <strong>Page Layout Slots</strong>
            <span class="wb-text-sm wb-text-muted">Compare the selected Page Layout with this page's current Page Slots before adding any missing Layout Slots.</span>
        </div>

        @if ($canEditContent && $missingCount > 0)
            <form method="POST" action="{{ route('admin.pages.layout-slots.sync', $page) }}">
                @csrf
                <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
                <button type="submit" class="wb-btn wb-btn-primary wb-btn-sm">Add Missing Layout Slots</button>
            </form>
        @endif
    </div>

    <div class="wb-card-body wb-stack wb-gap-3">
        <div class="wb-grid wb-grid-3">
            <div class="wb-stack wb-gap-1">
                <span class="wb-text-sm wb-text-muted">Page Layout</span>
                <strong>{{ $layoutLabel }}</strong>
            </div>
            <div class="wb-stack wb-gap-1">
                <span class="wb-text-sm wb-text-muted">Layout Slots</span>
                <strong>{{ $layoutSlotComparison['layout_slot_count'] ?? 0 }}</strong>
            </div>
            <div class="wb-stack wb-gap-1">
                <span class="wb-text-sm wb-text-muted">Page Slots</span>
                <strong>{{ $layoutSlotComparison['page_slot_count'] ?? 0 }}</strong>
            </div>
        </div>

        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
            <span class="wb-status-pill wb-status-active">Present: {{ $layoutSlotComparison['present_count'] ?? 0 }}</span>
            <span class="wb-status-pill {{ $missingCount > 0 ? 'wb-status-pending' : 'wb-status-active' }}">Missing: {{ $missingCount }}</span>
            <span class="wb-status-pill wb-status-info">Extra: {{ $layoutSlotComparison['extra_count'] ?? 0 }}</span>
            <span class="wb-status-pill wb-status-pending">Disabled: {{ $layoutSlotComparison['disabled_count'] ?? 0 }}</span>
            <span class="wb-status-pill wb-status-info">Shared Slot: {{ $layoutSlotComparison['shared_slot_count'] ?? 0 }}</span>
        </div>

        @if (! $hasLayoutSlots)
            <div class="wb-alert wb-alert-info wb-text-sm">
                This Page Layout does not currently define managed Layout Slots. Existing Page Slots are preserved and public rendering still falls back safely.
            </div>
        @elseif ($missingCount === 0)
            <div class="wb-alert wb-alert-success wb-text-sm">
                This page already has all slots defined by the selected Page Layout.
            </div>
        @else
            <div class="wb-alert wb-alert-info wb-text-sm">
                Adding missing Layout Slots is safe: existing Page Slots, blocks, Shared Slot assignments, and disabled slot states are preserved.
            </div>
        @endif

        <div class="wb-text-sm wb-text-muted">
            Extra Page Slots are kept for safety and may or may not render depending on the current Page Layout.
        </div>

        @if ($layoutSlotRows->isNotEmpty() || $extraSlotRows->isNotEmpty())
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Layout Slot</th>
                            <th>Page Slot</th>
                            <th>Status</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($layoutSlotRows as $row)
                            @php
                                $pageSlot = $row['page_slot'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $row['layout_label'] }}</strong>
                                        <span class="wb-text-sm wb-text-muted"><code>{{ $row['layout_slot_name'] }}</code></span>
                                    </div>
                                </td>
                                <td>
                                    @if ($pageSlot)
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $row['page_slot_label'] ?: 'Page Slot' }}</strong>
                                            <span class="wb-text-sm wb-text-muted"><code>{{ $row['page_slot_name'] }}</code></span>
                                        </div>
                                    @else
                                        <span class="wb-text-sm wb-text-muted">Missing on this page</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                        <span class="wb-status-pill {{ $row['status'] === 'missing' ? 'wb-status-pending' : 'wb-status-active' }}">{{ $row['status'] === 'missing' ? 'Missing' : 'Present' }}</span>
                                        @if ($row['is_disabled'])
                                            <span class="wb-status-pill wb-status-pending">Disabled</span>
                                        @endif
                                        @if ($row['is_shared_slot'])
                                            <span class="wb-status-pill wb-status-info">Shared Slot</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($pageSlot)
                                        <div class="wb-stack wb-gap-1">
                                            <span>{{ $row['source_label'] }}</span>
                                            @if ($row['shared_slot_name'])
                                                <span class="wb-text-sm wb-text-muted">{{ $row['shared_slot_name'] }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="wb-text-sm wb-text-muted">Will be added as Page Content</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @foreach ($extraSlotRows as $row)
                            <tr>
                                <td>
                                    <span class="wb-text-sm wb-text-muted">Not defined by this Page Layout</span>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <strong>{{ $row['page_slot_label'] ?: 'Page Slot' }}</strong>
                                        <span class="wb-text-sm wb-text-muted"><code>{{ $row['page_slot_name'] }}</code></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                        <span class="wb-status-pill wb-status-info">Extra</span>
                                        @if ($row['is_disabled'])
                                            <span class="wb-status-pill wb-status-pending">Disabled</span>
                                        @endif
                                        @if ($row['is_shared_slot'])
                                            <span class="wb-status-pill wb-status-info">Shared Slot</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <span>{{ $row['source_label'] }}</span>
                                        @if ($row['shared_slot_name'])
                                            <span class="wb-text-sm wb-text-muted">{{ $row['shared_slot_name'] }}</span>
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
