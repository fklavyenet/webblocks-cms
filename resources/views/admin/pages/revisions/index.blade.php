@php
    $pageTitle = 'Page Revisions: '.$page->title;
    $pagePublicUrl = $page->isPublished() ? $page->publicUrl() : null;
    $pageEditUrl = route('admin.pages.edit', $page);
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Review page-level revision snapshots for content restore. This history is page-specific and does not replace system backups or site export packages.',
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.$pageEditUrl.'" class="wb-btn wb-btn-secondary">Back to Page</a>'.($pagePublicUrl ? '<a href="'.$pagePublicUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>View Page</span></a>' : '').'</div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-1 wb-text-sm wb-text-muted">
            <span>Site: <strong>{{ $page->site?->name ?? 'Site' }}</strong></span>
            <span>Current workflow: <strong>{{ $page->workflowLabel() }}</strong></span>
            <span>Total revisions: <strong>{{ $revisions->count() }}</strong></span>
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
                    <div class="wb-empty-text">Revisions are created automatically when page content, translations, workflow, slots, or blocks change.</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Created</th>
                                <th>Revision</th>
                                <th>Triggered By</th>
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
                                            @if ($revision->reason)
                                                <span class="wb-text-sm wb-text-muted">{{ $revision->reason }}</span>
                                            @endif
                                            @if ($revision->restoredFrom)
                                                <span class="wb-text-sm wb-text-muted">Restored from revision #{{ $revision->restoredFrom->id }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $revision->actor?->name ?? 'System' }}</td>
                                    <td>
                                        @if ($canRestoreRevisions)
                                            <form method="POST" action="{{ route('admin.pages.revisions.restore', [$page, $revision]) }}" onsubmit="return confirm('Restore this page revision? A safety revision will be created first.');">
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
