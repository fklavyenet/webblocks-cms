@php
    $indexUrl = route('admin.shared-slots.index', ['site' => $sharedSlot->site_id]);
    $blocksUrl = route('admin.shared-slots.blocks.edit', $sharedSlot);
    $revisionsUrl = $canViewRevisions ? route('admin.shared-slots.revisions.index', $sharedSlot) : null;
@endphp

@extends('layouts.admin', ['title' => 'Edit Shared Slot', 'heading' => 'Shared Slots'])

@section('content')
    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$indexUrl.'">Shared Slots</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($sharedSlot->name).'</span></li></ol></nav>',
        'title' => 'Edit Shared Slot',
        'context' => '<span>'.e($sharedSlot->site?->name ?? 'Site').'</span>',
        'actions' => '<div class="wb-cluster wb-cluster-2">'.($revisionsUrl ? '<a href="'.$revisionsUrl.'" class="wb-btn wb-btn-secondary">Revision History</a>' : '').'<a href="'.$blocksUrl.'" class="wb-btn wb-btn-secondary">Edit Blocks</a><a href="'.$indexUrl.'" class="wb-btn wb-btn-secondary">Back to Shared Slots</a></div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.shared-slots.update', $sharedSlot) }}" class="wb-stack wb-gap-4">
                    @csrf
                    @method('PUT')
                    @include('admin.shared-slots._form', ['sharedSlot' => $sharedSlot, 'sites' => $sites, 'cancelUrl' => $indexUrl])
                </form>
            </div>
        </div>

        <div class="wb-stack wb-gap-4">
            <div class="wb-card wb-card-muted">
                <div class="wb-card-header"><strong>Usage</strong></div>
                <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                    <div><strong>Handle:</strong> <code>{{ $sharedSlot->handle }}</code></div>
                    <div><strong>Slot:</strong> {{ $sharedSlot->slotLabel() }}</div>
                    <div><strong>Public Shell:</strong> {{ $sharedSlot->publicShellLabel() }}</div>
                    <div><strong>Status:</strong> <span class="wb-status-pill {{ $sharedSlot->statusBadgeClass() }}">{{ $sharedSlot->statusLabel() }}</span></div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>Danger Zone</strong></div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-text-sm wb-text-muted">Deletion is blocked while any page slot still references this Shared Slot.</div>
                    <form method="POST" action="{{ route('admin.shared-slots.destroy', $sharedSlot) }}" onsubmit="return confirm('Delete this Shared Slot?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="wb-btn wb-btn-danger">Delete Shared Slot</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
