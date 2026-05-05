@php
    $pageTitle = 'Shared Slot Revisions: '.$sharedSlot->name;
    $sharedSlotEditUrl = route('admin.shared-slots.edit', $sharedSlot);
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Review Shared Slot revision snapshots for restore. Restoring a Shared Slot can affect every page that references it and does not modify page-owned slot assignments.',
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.$sharedSlotEditUrl.'" class="wb-btn wb-btn-secondary">Back to Shared Slot</a><a href="'.route('admin.shared-slots.blocks.edit', $sharedSlot).'" class="wb-btn wb-btn-secondary">Edit Blocks</a></div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-1 wb-text-sm wb-text-muted">
            <span>Site: <strong>{{ $sharedSlot->site?->name ?? 'Site' }}</strong></span>
            <span>Handle: <strong><code>{{ $sharedSlot->handle }}</code></strong></span>
            <span>Total revisions: <strong>{{ $revisions->count() }}</strong></span>
            <span class="wb-text-danger">Warning: restoring this Shared Slot can change every page using it.</span>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Revision History</strong>
            <span class="wb-text-sm wb-text-muted">Newest first</span>
        </div>
        <div class="wb-card-body">
            @if ($revisions->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">No revisions yet</div>
                    <div class="wb-empty-text">Revisions are created automatically when Shared Slot metadata, status, or block structure changes.</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Created</th>
                                <th>Event</th>
                                <th>Triggered By</th>
                                <th>Details</th>
                                <th>Restore</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($revisions as $revision)
                                <tr>
                                    <td>{{ $revision->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $revision->labelText() }}</strong>
                                            <span class="wb-text-sm wb-text-muted">{{ $revision->eventText() }}</span>
                                            @if ($revision->summary)
                                                <span class="wb-text-sm wb-text-muted">{{ $revision->summary }}</span>
                                            @endif
                                            @if ($revision->restoredFrom)
                                                <span class="wb-text-sm wb-text-muted">Restored from revision #{{ $revision->restoredFrom->id }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $revision->actor?->name ?? 'System' }}</td>
                                    <td>
                                        <a href="{{ route('admin.shared-slots.revisions.show', [$sharedSlot, $revision]) }}" class="wb-btn wb-btn-secondary">Inspect</a>
                                    </td>
                                    <td>
                                        @if ($canRestoreRevisions)
                                            <form method="POST" action="{{ route('admin.shared-slots.revisions.restore', [$sharedSlot, $revision]) }}" onsubmit="return confirm('Restore this Shared Slot revision? A safety revision will be created first, and every referencing page may change.');">
                                                @csrf
                                                <button type="submit" class="wb-btn wb-btn-secondary">Restore</button>
                                            </form>
                                        @else
                                            <span class="wb-text-sm wb-text-muted">View only</span>
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
@endsection
