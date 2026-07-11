@php
    $label = $label ?? 'selected';
    $deleteTarget = $deleteTarget ?? null;
    $deleteLabel = $deleteLabel ?? 'Delete selected';
@endphp

<div class="wb-card wb-card-muted" data-wb-admin-bulk-actions hidden>
    <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <div class="wb-cluster wb-cluster-2">
            <span class="wb-status-pill wb-status-info"><span data-wb-admin-bulk-count>0</span> {{ $label }}</span>
        </div>

        @if ($deleteTarget)
            <button
                type="button"
                class="wb-btn wb-btn-danger"
                data-wb-toggle="modal"
                data-wb-target="{{ $deleteTarget }}"
                data-wb-admin-bulk-delete-trigger
                disabled
            >{{ $deleteLabel }}</button>
        @endif
    </div>
</div>
