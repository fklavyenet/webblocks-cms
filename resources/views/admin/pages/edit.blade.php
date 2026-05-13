@php
    $pageTitle = 'Edit Page: '.$page->title;
    $settingsTab = old('_page_settings_tab', request('tab') === 'page-assets' ? 'page-assets' : 'general');
    $pagePublicUrl = $page->isPublished() ? $page->publicUrl() : null;
    $pagesIndexUrl = $pagesIndexUrl ?? session('page_return_url') ?? route('admin.pages.index', ['site' => $page->site_id]);
    $pageReturnUrl = $pageReturnUrl ?? $pagesIndexUrl;
    $pageRevisionsUrl = $canViewRevisions ? route('admin.pages.revisions.index', $page) : null;
    $pageDuplicateUrl = $canDuplicatePage ? route('admin.pages.duplicate.create', ['page' => $page, 'return_url' => $pageReturnUrl]) : null;
    $pageMoveUrl = $canMoveToAnotherSite ? route('admin.pages.move-site.create', ['page' => $page, 'return_url' => $pageReturnUrl]) : null;
    $siteName = $page->site?->name ?? 'Site';
    $domainName = $page->site?->canonicalDomain() ?: 'Not set';
    $headerActions = collect([
        $pageDuplicateUrl ? '<a href="'.$pageDuplicateUrl.'" class="wb-btn wb-btn-secondary">Duplicate page</a>' : null,
        $pageMoveUrl ? '<a href="'.$pageMoveUrl.'" class="wb-btn wb-btn-secondary">Move to another site</a>' : null,
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
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Page Overview</strong>
            <span class="wb-text-sm wb-text-muted">Only published pages are visible on the public site.</span>
        </div>
        <div class="wb-card-body">
            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">Site</span>
                        <strong>{{ $siteName }}</strong>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">Domain</span>
                        <span>{{ $domainName }}</span>
                    </div>
                </div>

                <div class="wb-stack wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">Status</span>
                        <div>
                            <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span>
                        </div>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <span class="wb-text-sm wb-text-muted">Published</span>
                        <span>{{ $page->published_at ? $page->published_at->format('Y-m-d H:i') : 'Not published' }}</span>
                    </div>

                    @if ($page->review_requested_at)
                        <div class="wb-stack wb-gap-1">
                            <span class="wb-text-sm wb-text-muted">Review requested</span>
                            <span>{{ $page->review_requested_at->format('Y-m-d H:i') }}</span>
                        </div>
                    @endif

                    @if ($workflowActions !== [])
                        <div class="wb-stack wb-gap-2">
                            <span class="wb-text-sm wb-text-muted">Actions</span>
                            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                @foreach ($workflowActions as $workflowAction)
                                    <form method="POST" action="{{ route('admin.pages.workflow', $page) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="{{ $workflowAction['value'] }}">
                                        <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">
                                        <button type="submit" class="{{ $workflowAction['class'] }}">{{ $workflowAction['label'] }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Page Settings</strong>
            <span class="wb-text-sm wb-text-muted">Manage general settings and optional page-specific assets</span>
        </div>
        <form method="POST" action="{{ route('admin.pages.update', $page) }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <input type="hidden" name="_page_settings_tab" value="{{ $settingsTab }}" data-wb-page-settings-tab-input>
            <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">

            <div class="wb-card-body">
                <div class="wb-tabs" data-wb-tabs data-wb-page-settings-tabs>
                    <div class="wb-tabs-nav" role="tablist" aria-label="Page settings sections">
                        <button type="button" class="wb-tabs-btn {{ $settingsTab === 'general' ? 'is-active' : '' }}" data-wb-tab="page-settings-general-panel" aria-selected="{{ $settingsTab === 'general' ? 'true' : 'false' }}" @if ($settingsTab !== 'general') tabindex="-1" @endif>General</button>
                        @if ($canManagePageAssets)
                            <button type="button" class="wb-tabs-btn {{ $settingsTab === 'page-assets' ? 'is-active' : '' }}" data-wb-tab="page-settings-assets-panel" aria-selected="{{ $settingsTab === 'page-assets' ? 'true' : 'false' }}" @if ($settingsTab !== 'page-assets') tabindex="-1" @endif>Page Assets</button>
                        @elseif ($page->pageAssets->isNotEmpty())
                            <button type="button" class="wb-tabs-btn {{ $settingsTab === 'page-assets' ? 'is-active' : '' }}" data-wb-tab="page-settings-assets-panel" aria-selected="{{ $settingsTab === 'page-assets' ? 'true' : 'false' }}" @if ($settingsTab !== 'page-assets') tabindex="-1" @endif>Page Assets</button>
                        @endif
                    </div>

                    <div class="wb-tabs-panels">
                        <div class="wb-tabs-panel {{ $settingsTab === 'general' ? 'is-active' : '' }}" id="page-settings-general-panel">
                            @include('admin.pages._form', ['canEditContent' => $canEditContent])
                        </div>

                        @if ($canManagePageAssets || $page->pageAssets->isNotEmpty())
                            <div class="wb-tabs-panel {{ $settingsTab === 'page-assets' ? 'is-active' : '' }}" id="page-settings-assets-panel">
                                @include('admin.pages.partials.page-assets-tab', [
                                    'page' => $page,
                                    'canManagePageAssets' => $canManagePageAssets,
                                    'pageAssetsTab' => $pageAssetsTab,
                                ])
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="$pageReturnUrl" :show-submit="$canEditContent" submit-label="Save Changes" />
            </div>
        </form>
    </div>

    @include('admin.pages.partials.slots-card', [
        'page' => $page,
        'slotTypes' => $slotTypes,
        'slotBlockPreviews' => $slotBlockPreviews,
        'slotSharedSlotOptions' => $slotSharedSlotOptions,
        'sharedSlotSourcesAvailable' => $sharedSlotSourcesAvailable,
        'layoutSlotComparison' => $layoutSlotComparison,
        'canEditContent' => $canEditContent,
        'canCreateSharedSlots' => $canCreateSharedSlots,
        'pageReturnUrl' => $pageReturnUrl,
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
                                        <a href="{{ route('admin.pages.translations.edit', ['page' => $page, 'translation' => $translation, 'return_url' => $pageReturnUrl]) }}" class="wb-btn wb-btn-secondary">Edit translation</a>
                                    @else
                                        <a href="{{ route('admin.pages.translations.create', ['page' => $page, 'locale' => $locale, 'return_url' => $pageReturnUrl]) }}" class="wb-btn wb-btn-secondary">Add translation</a>
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

@push('overlays')
    @if ($canManagePageAssets || $page->pageAssets->isNotEmpty())
        @include('admin.pages.partials.page-assets-modals', [
            'page' => $page,
            'canManagePageAssets' => $canManagePageAssets,
            'pageAssetsTab' => $pageAssetsTab,
        ])
    @endif
@endpush
