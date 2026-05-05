@php
    $pageTitle = 'Edit Page: '.$page->title;
    $pagePublicUrl = $page->isPublished() ? $page->publicUrl() : null;
    $pagesIndexUrl = route('admin.pages.index', ['site' => $page->site_id]);
    $pageRevisionsUrl = $canViewRevisions ? route('admin.pages.revisions.index', $page) : null;
    $siteName = $page->site?->name ?? 'Site';
    $headerActions = collect([
        $pageRevisionsUrl ? '<a href="'.$pageRevisionsUrl.'" class="wb-btn wb-btn-secondary">Revision History</a>' : null,
        $pagePublicUrl ? '<a href="'.$pagePublicUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>View Page</span></a>' : null,
    ])->filter()->implode('');
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">'.$siteName.'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.$pagesIndexUrl.'">Pages</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.$page->title.'</span></li></ol></nav>',
        'title' => $pageTitle,
        'description' => 'Manage the canonical page, English base fields, and translation routing from one compact screen.',
        'actions' => $headerActions,
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <span class="wb-text-sm wb-text-muted">Site</span>
                    <strong>{{ $siteName }}</strong>
                </div>
                <div class="wb-stack wb-gap-1">
                    <span class="wb-text-sm wb-text-muted">Workflow</span>
                    <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span>
                </div>
                @if ($page->site?->domain)
                    <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">Domain</span>
                        <span>{{ $page->site->domain }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Editorial Workflow</strong>
            <span class="wb-text-sm wb-text-muted">Only published pages are visible on the public site.</span>
        </div>
        <div class="wb-card-body">
            <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-stack wb-gap-1 wb-text-sm wb-text-muted">
                    <span>Current status: <strong>{{ $page->workflowLabel() }}</strong></span>
                    @if ($page->review_requested_at)
                        <span>Review requested: {{ $page->review_requested_at->format('Y-m-d H:i') }}</span>
                    @endif
                    @if ($page->published_at)
                        <span>Published: {{ $page->published_at->format('Y-m-d H:i') }}</span>
                    @endif
                </div>

                @if ($workflowActions !== [])
                    <div class="wb-cluster wb-cluster-2">
                        @foreach ($workflowActions as $workflowAction)
                            <form method="POST" action="{{ route('admin.pages.workflow', $page) }}">
                                @csrf
                                <input type="hidden" name="action" value="{{ $workflowAction['value'] }}">
                                <button type="submit" class="{{ $workflowAction['class'] }}">{{ $workflowAction['label'] }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Page Settings</strong>
            <span class="wb-text-sm wb-text-muted">Update core page fields only</span>
        </div>
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.pages.update', $page) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                @include('admin.pages._form', ['canEditContent' => $canEditContent])
            </form>
        </div>
    </div>

    @include('admin.pages.partials.slots-card', [
        'page' => $page,
        'slotTypes' => $slotTypes,
        'slotBlockPreviews' => $slotBlockPreviews,
        'slotSharedSlotOptions' => $slotSharedSlotOptions,
        'canEditContent' => $canEditContent,
        'canCreateSharedSlots' => $canCreateSharedSlots,
    ])

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Translations</strong>
            <span class="wb-text-sm wb-text-muted">Page title and routing only</span>
        </div>
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Locale</th>
                            <th>Status</th>
                            <th>Slug</th>
                            <th>Path</th>
                            <th>Open</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($translationStatuses as $translationStatus)
                            @php
                                $locale = $translationStatus['locale'];
                                $translation = $translationStatus['translation'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="wb-cluster wb-cluster-2">
                                        <strong>{{ strtoupper($locale->code) }}</strong>
                                        <span>{{ $locale->name }}</span>
                                        @if ($translationStatus['is_default'])
                                            <span class="wb-status-pill wb-status-info">Default</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="wb-status-pill {{ $translationStatus['is_missing'] ? 'wb-status-pending' : 'wb-status-active' }}">
                                        {{ $translationStatus['is_missing'] ? 'Missing' : 'Ready' }}
                                    </span>
                                </td>
                                <td>{{ $translation?->slug ?? 'Missing' }}</td>
                                <td>{{ $translationStatus['public_path'] ?? 'Missing' }}</td>
                                <td>
                                    @if ($page->isPublished() && $translationStatus['public_url'])
                                        <a href="{{ $translationStatus['public_url'] }}" target="_blank" rel="noopener noreferrer" class="wb-action-btn wb-action-btn-view" title="Open translation" aria-label="Open translation">
                                            <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
                                        </a>
                                    @else
                                        <span class="wb-action-btn" aria-disabled="true"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i></span>
                                    @endif
                                </td>
                                <td>
                                    @if (! $canEditContent)
                                        <span class="wb-text-sm wb-text-muted">Locked by workflow</span>
                                    @elseif ($translation)
                                        <a href="{{ route('admin.pages.translations.edit', [$page, $translation]) }}" class="wb-btn wb-btn-secondary">Edit translation</a>
                                    @else
                                        <a href="{{ route('admin.pages.translations.create', [$page, $locale]) }}" class="wb-btn wb-btn-secondary">Add translation</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
