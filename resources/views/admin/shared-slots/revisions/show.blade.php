@php
    $pageTitle = 'Shared Slot Revision #'.$revision->id;
    $historyUrl = route('admin.shared-slots.revisions.index', $sharedSlot);
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Inspect the saved Shared Slot snapshot before restoring it. This restore will keep the same Shared Slot id and existing page slot references intact.',
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.$historyUrl.'" class="wb-btn wb-btn-secondary">Back to Revision History</a>'.($canRestoreRevisions ? '<form method="POST" action="'.route('admin.shared-slots.revisions.restore', [$sharedSlot, $revision]).'" onsubmit="return confirm(\'Restore this Shared Slot revision? A safety revision will be created first, and every referencing page may change.\');">'.csrf_field().'<button type="submit" class="wb-btn wb-btn-secondary">Restore Revision</button></form>' : '').'</div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>Revision Metadata</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                <div><strong>Shared Slot:</strong> {{ $sharedSlot->name }}</div>
                <div><strong>Created:</strong> {{ $revision->created_at?->format('Y-m-d H:i') ?? '-' }}</div>
                <div><strong>Event:</strong> {{ $revision->eventText() }}</div>
                <div><strong>User:</strong> {{ $revision->actor?->name ?? 'System' }}</div>
                <div><strong>Summary:</strong> {{ $revision->summary ?? 'None' }}</div>
                @if ($revision->restoredFrom)
                    <div><strong>Restored From:</strong> Revision #{{ $revision->restoredFrom->id }}</div>
                @endif
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>Snapshot Metadata</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                <div><strong>Name:</strong> {{ $snapshotMetadata['name'] ?? '-' }}</div>
                <div><strong>Handle:</strong> <code>{{ $snapshotMetadata['handle'] ?? '-' }}</code></div>
                <div><strong>Slot:</strong> {{ $snapshotMetadata['slot_name'] ?? 'Any' }}</div>
                <div><strong>Public Shell:</strong> {{ $snapshotMetadata['public_shell'] ?? 'Any' }}</div>
                <div><strong>Status:</strong> {{ array_key_exists('is_active', $snapshotMetadata) ? ((bool) $snapshotMetadata['is_active'] ? 'Active' : 'Inactive') : '-' }}</div>
                <div class="wb-text-danger">Restoring this snapshot affects all pages referencing this Shared Slot.</div>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Snapshot Block Tree</strong>
            <span class="wb-text-sm wb-text-muted">{{ $snapshotBlocks->count() }} block(s)</span>
        </div>
        <div class="wb-card-body">
            @if ($snapshotBlocks->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">No blocks in this revision</div>
                    <div class="wb-empty-text">This snapshot preserves Shared Slot metadata without a block tree.</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Block</th>
                                <th>Preview</th>
                                <th>Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshotBlocks as $snapshotBlock)
                                <tr>
                                    <td>
                                        <span style="padding-left: {{ $snapshotBlock['depth'] * 1.25 }}rem; display: inline-block;">
                                            {{ str($snapshotBlock['type'])->replace('-', ' ')->headline() }}
                                        </span>
                                    </td>
                                    <td>{{ $snapshotBlock['title'] ?: 'No text preview' }}</td>
                                    <td>{{ $snapshotBlock['sort_order'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
