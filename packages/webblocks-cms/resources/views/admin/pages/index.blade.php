@php
    $siteContext = $activeSite?->name ?? 'All sites';
    $siteContextDescription = $showAllSites
        ? 'Showing pages across all sites. Choose a site to return to the normal editorial flow.'
        : 'Showing pages for '.$activeSite->name.($activeSite->canonicalDomain() ? ' ('.$activeSite->canonicalDomain().')' : '').'.';
    $newPageUrl = $activeSite ? route('admin.pages.create', ['site' => $activeSite->id]) : route('admin.pages.create');
    $clearUrl = route('admin.pages.index', ['reset' => 1]);
    $detailsBaseQuery = array_filter([
        'site' => $filters['site'],
        'search' => $filters['search'] !== '' ? $filters['search'] : null,
        'status' => $filters['status'] !== '' ? $filters['status'] : null,
        'sort' => $filters['sort'] !== 'created_at' ? $filters['sort'] : null,
        'direction' => $filters['direction'] !== 'desc' ? $filters['direction'] : null,
        'page' => $pages->currentPage() > 1 ? $pages->currentPage() : null,
    ], fn ($value) => $value !== null && $value !== '');
    $importPageUrl = route('admin.pages.index', array_filter(array_merge($detailsBaseQuery, ['modal' => 'page-import']), fn ($value) => $value !== null && $value !== ''));
    $closeImportUrl = route('admin.pages.index', $detailsBaseQuery);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => 'Pages', 'heading' => 'Pages'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Pages',
        'description' => null,
        'context' => '<span>'.e($siteContextDescription).'</span>',
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.pages.index'),
                'search' => [
                    'id' => 'pages_search',
                    'name' => 'search',
                    'label' => 'Search',
                    'value' => $filters['search'],
                    'placeholder' => 'Search by title, slug, or page type',
                ],
                'selects' => [
                    [
                        'id' => 'pages_site_context',
                        'name' => 'site',
                        'label' => 'Site',
                        'selected' => $filters['site'],
                        'placeholder' => null,
                        'options' => collect($sites)->mapWithKeys(fn ($site) => [$site->id => $site->name])->all() + ['all' => 'All sites'],
                    ],
                    [
                        'id' => 'pages_status',
                        'name' => 'status',
                        'label' => 'Status',
                        'selected' => $filters['status'],
                        'placeholder' => 'All statuses',
                        'options' => [
                            'draft' => 'Draft',
                            'in_review' => 'In Review',
                            'published' => 'Published',
                            'archived' => 'Archived',
                        ],
                    ],
                    [
                        'id' => 'pages_sort',
                        'name' => 'sort',
                        'label' => 'Sort by',
                        'selected' => $filters['sort'],
                        'options' => [
                            'created_at' => 'Created at',
                            'updated_at' => 'Last edited',
                            'title' => 'Title',
                            'slug' => 'Slug',
                            'status' => 'Status',
                        ],
                    ],
                    [
                        'id' => 'pages_direction',
                        'name' => 'direction',
                        'label' => 'Direction',
                        'selected' => $filters['direction'],
                        'options' => [
                            'desc' => 'Descending',
                            'asc' => 'Ascending',
                        ],
                    ],
                ],
                'showReset' => $filters['search'] !== '' || $filters['status'] !== '' || $filters['sort'] !== 'created_at' || $filters['direction'] !== 'desc',
                'resetUrl' => $clearUrl,
                'applyLabel' => 'Apply',
            ])
        </div>
    </div>

    @if ($pages->isEmpty())
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>Pages for {{ $siteContext }}</strong>
                    <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                </div>

                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <a href="{{ $newPageUrl }}" class="wb-btn wb-btn-primary">New Page</a>
                    <a href="{{ $importPageUrl }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">Import Page</a>
                </div>
            </div>

            <div class="wb-card-body">
                    <div class="wb-empty">
                        <div class="wb-empty-title">No pages found</div>
                        <div class="wb-empty-text">Adjust the filters or create your first page for {{ strtolower($siteContext) }}.</div>
                        <div class="wb-empty-action">
                            <a href="{{ $newPageUrl }}" class="wb-btn wb-btn-primary">Create Page</a>
                        </div>
                    </div>
                </div>
        </div>
    @else
        <div class="wb-card" data-wb-admin-bulk-listing>
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>Pages for {{ $siteContext }}</strong>
                    <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                </div>

                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <a href="{{ $newPageUrl }}" class="wb-btn wb-btn-primary">New Page</a>
                    <a href="{{ $importPageUrl }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">Import Page</a>
                </div>
            </div>

            <div class="wb-card-body">
                @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                    'label' => 'selected',
                    'deleteTarget' => '#bulk-delete-pages-modal',
                    'deleteLabel' => 'Delete selected',
                ])

                <div class="wb-table-wrap wb-admin-pages-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover wb-admin-pages-table">
                        <thead>
                            <tr>
                                <th>
                                    <label class="wb-checkbox" for="select_all_visible_pages">
                                        <input id="select_all_visible_pages" type="checkbox" data-wb-admin-select-all-visible aria-label="Select all visible pages">
                                        <span class="wb-sr-only">Select all visible pages</span>
                                    </label>
                                </th>
                                <th>Page</th>
                                <th>View</th>
                                <th>Blocks</th>
                                <th>Status</th>
                                <th>Last edited</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pages as $page)
                                @php
                                    $translations = $page->translations->sortByDesc(fn ($translation) => $translation->locale?->is_default)->values();
                                    $enabledLocaleCount = (int) ($siteLocaleCounts[$page->site_id] ?? $translations->count());
                                    $missingTranslations = max($enabledLocaleCount - $translations->count(), 0);
                                    $defaultPublicUrl = $page->publicUrl();
                                @endphp
                                <tr>
                                    <td>
                                        <label class="wb-checkbox" for="page_select_{{ $page->id }}">
                                            <input id="page_select_{{ $page->id }}" type="checkbox" value="{{ $page->id }}" data-wb-admin-row-select aria-label="Select page {{ $page->title }}">
                                            <span class="wb-sr-only">Select page {{ $page->title }}</span>
                                        </label>
                                    </td>
                                    <td class="wb-admin-pages-table-cell wb-admin-pages-page-cell">
                                        <div class="wb-admin-pages-page-meta">
                                            <div class="wb-admin-pages-title-row wb-cluster wb-cluster-2 wb-flex-wrap">
                                                <strong>{{ $page->title }}</strong>
                                                @if ($showAllSites)
                                                    <span class="wb-status-pill {{ $page->site?->is_primary ? 'wb-status-info' : 'wb-status-pending' }}">{{ $page->site?->name }}</span>
                                                    @if ($page->site?->canonicalDomain())
                                                        <span class="wb-text-sm wb-text-muted">{{ $page->site->canonicalDomain() }}</span>
                                                    @endif
                                                @endif
                                            </div>

                                            <div class="wb-admin-pages-locale-row wb-cluster wb-cluster-2 wb-flex-wrap wb-text-sm wb-text-muted">
                                                @foreach ($translations as $translation)
                                                    <span class="wb-status-pill {{ $translation->locale?->is_default ? 'wb-status-info' : 'wb-status-active' }}">
                                                        {{ $translation->locale?->code }}
                                                        @if ($translation->locale?->is_default)
                                                            Default
                                                        @endif
                                                    </span>
                                                @endforeach

                                                @if ($missingTranslations > 0)
                                                    <span class="wb-text-sm wb-text-muted">Missing {{ $missingTranslations }}</span>
                                                @endif
                                            </div>

                                            <div class="wb-admin-pages-path-row wb-cluster wb-cluster-2 wb-flex-wrap wb-text-sm">
                                                @foreach ($translations->take(3) as $translation)
                                                    @php
                                                        $translationPublicUrl = $page->publicUrl($translation->locale?->code);
                                                        $translationPublicPath = $page->publicPath($translation->locale?->code);
                                                    @endphp
                                                    @if ($translationPublicUrl && $translationPublicPath && $page->isPublished())
                                                         <a href="{{ $translationPublicUrl }}" target="_blank" rel="noopener noreferrer" class="wb-link wb-admin-pages-path-link">
                                                            {{ strtoupper($translation->locale?->code ?? 'en') }} {{ $translationPublicPath }}
                                                          </a>
                                                      @elseif ($translationPublicPath && ! $page->isPublished())
                                                          <span class="wb-text-muted wb-admin-pages-path-text">{{ strtoupper($translation->locale?->code ?? 'en') }} {{ $translationPublicPath }} (not public)</span>
                                                      @else
                                                          <span class="wb-text-muted wb-admin-pages-path-text">{{ strtoupper($translation->locale?->code ?? 'en') }} Missing route</span>
                                                       @endif
                                                   @endforeach
                                            </div>
                                        </div>
                                    </td>
                                    <td class="wb-admin-pages-table-cell wb-admin-pages-view-cell">
                                        @if ($page->isPublished() && $defaultPublicUrl)
                                            <a
                                                href="{{ $defaultPublicUrl }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="wb-action-btn wb-action-btn-view"
                                                title="Open page in new tab"
                                                aria-label="Open page in new tab"
                                            >
                                                <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
                                            </a>
                                        @else
                                            <span class="wb-action-btn" title="Only published pages can be opened publicly" aria-label="Only published pages can be opened publicly" aria-disabled="true">
                                                <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="wb-admin-pages-table-cell wb-admin-pages-count-cell">{{ $page->blocks_count ?? $page->blocks()->count() }}</td>
                                    <td class="wb-admin-pages-table-cell wb-admin-pages-status-cell">
                                        <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">
                                            {{ $page->workflowLabel() }}
                                        </span>
                                    </td>
                                    <td class="wb-admin-pages-table-cell wb-admin-pages-last-edited-cell">
                                        <div class="wb-admin-pages-last-edited wb-text-sm">
                                            <span>{{ $page->updated_at?->format('Y-m-d H:i') ?? '-' }}</span>
                                            <span class="wb-text-muted">
                                                {{ $page->updatedByUser?->name ?? 'Not recorded' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="wb-admin-pages-table-cell wb-admin-pages-actions-cell">
                                        <div class="wb-action-group">
                                            <a
                                                href="{{ route('admin.pages.index', array_merge($detailsBaseQuery, ['details' => $page->id])) }}"
                                                class="wb-action-btn"
                                                aria-haspopup="dialog"
                                                aria-controls="pageDetailsModal-{{ $page->id }}"
                                                title="Page details"
                                                aria-label="Open page details"
                                            >
                                                <i class="wb-icon wb-icon-panel-right" aria-hidden="true"></i>
                                            </a>

                                            <a href="{{ route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit page" aria-label="Edit page"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                            <button
                                                type="button"
                                                class="wb-action-btn wb-action-btn-delete"
                                                title="Delete page"
                                                aria-label="Delete page"
                                                data-wb-toggle="modal"
                                                data-wb-target="#delete-page-{{ $page->id }}-modal"
                                            >
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                @push('overlays')
                                    @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                                        'id' => 'delete-page-'.$page->id.'-modal',
                                        'title' => 'Delete Page',
                                        'description' => 'This deletes the page and its related CMS content according to the existing page cleanup rules.',
                                        'action' => route('admin.pages.destroy', $page),
                                        'method' => 'DELETE',
                                        'submitLabel' => 'Delete page',
                                    ])
                                        <div class="wb-card wb-card-muted">
                                            <div class="wb-card-body wb-stack wb-gap-2">
                                                <div><strong>{{ $page->title }}</strong></div>
                                                <div class="wb-text-sm wb-text-muted">Status {{ $page->workflowLabel() }} | Site {{ $page->site?->name ?? '-' }}</div>
                                            </div>
                                        </div>

                                        <p class="wb-text-sm wb-text-muted">This cannot be undone from the admin UI. Recovery requires revision history or a backup restore.</p>
                                    @endcomponent
                                @endpush
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $pages, 'ariaLabel' => 'Pages pagination', 'compact' => true])
        </div>

        @push('overlays')
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                'id' => 'bulk-delete-pages-modal',
                'title' => 'Delete Selected Pages',
                'description' => 'This deletes the selected pages and their related CMS content according to the existing page cleanup rules.',
                'action' => route('admin.pages.bulk-destroy'),
                'method' => 'DELETE',
                'submitLabel' => 'Delete selected',
                'formAttributes' => [
                    'data-wb-admin-bulk-delete-form' => true,
                    'data-wb-admin-bulk-input-name' => 'page_ids[]',
                ],
                'submitAttributes' => [
                    'data-wb-admin-bulk-delete-submit' => true,
                    'disabled' => true,
                ],
            ])
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body wb-stack wb-gap-2">
                        <strong><span data-wb-admin-bulk-modal-count>0</span> selected pages will be deleted.</strong>
                        <p class="wb-text-sm wb-text-muted">This applies only to pages visible on this page. Every selected page is re-checked server-side against your site access before deletion.</p>
                    </div>
                </div>

                <div data-wb-admin-bulk-inputs></div>
                <input type="hidden" name="page_ids[]" value="" disabled data-wb-admin-bulk-empty-input>
            @endcomponent
        @endpush

        @push('scripts')
            @php($bulkActionsJsPath = public_path('cms/js/admin/listing-bulk-actions.js'))
            @if (is_file($bulkActionsJsPath))
                <script src="{{ asset('cms/js/admin/listing-bulk-actions.js') }}?v={{ filemtime($bulkActionsJsPath) }}" defer></script>
            @endif
        @endpush

    @endif
@endsection

@push('overlays')
    @include('webblocks-cms::admin.pages.partials.import-modal', [
        'importSites' => $importSites,
        'pageImportOpen' => $pageImportOpen,
        'pageImportSelectedSiteId' => $pageImportSelectedSiteId,
        'closeUrl' => $closeImportUrl,
        'pageReturnUrl' => $pageReturnUrl,
    ])
@endpush

@if ($detailsPage)
    @push('overlays')
        @include('webblocks-cms::admin.pages.partials.details-modal', [
            'page' => $detailsPage,
            'drawerId' => 'pageDetailsModal-'.$detailsPage->id,
            'closeUrl' => route('admin.pages.index', $detailsBaseQuery),
            'pageReturnUrl' => $pageReturnUrl,
        ])
    @endpush
@endif
