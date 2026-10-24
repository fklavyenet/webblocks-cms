@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);
    $siteContext = $activeSite?->name ?? $adminText('pages.all_sites');
    $newPageUrl = $activeSite ? route('admin.pages.create', ['site' => $activeSite->id]) : route('admin.pages.create');
    $clearUrl = route('admin.pages.index', ['reset' => 1]);
    // The site filter is a scope, not a narrowing filter: a site with no pages
    // really is empty, and "create your first page" is the right thing to say.
    $hasActiveFilters = ($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '';
    $detailsBaseQuery = array_filter([
        ...app(\WebBlocks\Cms\Support\ListingViews\ListingViewState::class)->presentationQuery(request()),
        'site' => $filters['site'],
        'search' => $filters['search'] !== '' ? $filters['search'] : null,
        'status' => $filters['status'] !== '' ? $filters['status'] : null,
        'sort' => $filters['sort'] !== 'created_at' ? $filters['sort'] : null,
        'direction' => $filters['direction'] !== 'desc' ? $filters['direction'] : null,
        'page' => $pages->currentPage() > 1 ? $pages->currentPage() : null,
    ], fn ($value) => $value !== null && $value !== '');
    $importPageUrl = route('admin.pages.index', array_filter(array_merge($detailsBaseQuery, ['modal' => 'page-import']), fn ($value) => $value !== null && $value !== ''));
    $pageConverterUrl = route('admin.pages.converter.index', array_filter(['site_id' => $activeSite?->id], fn ($value) => $value !== null && $value !== ''));
    $closeImportUrl = route('admin.pages.index', $detailsBaseQuery);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('pages.title'), 'heading' => $adminText('pages.title')])

@section('content')
@php
    $tableSort = \WebBlocks\Cms\Queries\AdminTableSort::for('pages');
@endphp
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('pages.title').' · '.$siteContext,
        'description' => null,
        'count' => $filteredCount,
    ])

    @include('webblocks-cms::admin.partials.flash')
    @include('webblocks-cms::admin.partials.listing-views', ['listingColumnLabels' => [
        'id' => $adminText('pages.columns.id'),
        'view' => $adminText('pages.columns.view'),
        'blocks' => $adminText('pages.columns.total_blocks'),
        'status' => $adminText('common.status'),
        'updated' => $adminText('pages.last_edited'),
    ]])

    <div class="wb-card wb-card-muted wb-admin-pages-index-filters">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.pages.index'),
                'hidden' => [...app(\WebBlocks\Cms\Support\ListingViews\ListingViewState::class)->presentationQuery(request()), ...($listingView['blocked'] ? ['correct_view' => 1] : [])],
                'search' => [
                    'id' => 'pages_search',
                    'name' => 'search',
                    'label' => $adminText('common.search'),
                    'value' => $filters['search'],
                    'placeholder' => $adminText('pages.search_placeholder'),
                ],
                'selects' => [
                    [
                        'id' => 'pages_site_context',
                        'name' => 'site',
                        'label' => $adminText('sites.singular'),
                        'selected' => $filters['site'],
                        'placeholder' => null,
                        'options' => collect($sites)->mapWithKeys(fn ($site) => [$site->id => $site->name])->all() + ['all' => $adminText('pages.all_sites')],
                    ],
                    [
                        'id' => 'pages_status',
                        'name' => 'status',
                        'label' => $adminText('common.status'),
                        'selected' => $filters['status'],
                        'placeholder' => $adminText('common.all_statuses'),
                        'options' => [
                            'draft' => $adminText('pages.statuses.draft'),
                            'in_review' => $adminText('pages.statuses.in_review'),
                            'published' => $adminText('pages.statuses.published'),
                            'archived' => $adminText('pages.statuses.archived'),
                        ],
                    ],
                    [
                        'id' => 'pages_sort',
                        'name' => 'sort',
                        'label' => $adminText('common.sort_by'),
                        'selected' => $filters['sort'],
                        'options' => [
                            'title' => $adminText('pages.columns.page'),
                            'id' => $adminText('pages.columns.id'),
                            'blocks_count' => $adminText('pages.columns.total_blocks'),
                            'status' => $adminText('common.status'),
                            'updated_at' => $adminText('pages.last_edited'),
                            'created_at' => $adminText('pages.created_at'),
                            'slug' => $adminText('page_form.slug'),
                        ],
                    ],
                    [
                        'id' => 'pages_direction',
                        'name' => 'direction',
                        'label' => $adminText('common.direction'),
                        'selected' => $filters['direction'],
                        'options' => [
                            'desc' => $adminText('common.descending'),
                            'asc' => $adminText('common.ascending'),
                        ],
                    ],
                ],
                'showReset' => $filters['search'] !== '' || $filters['status'] !== '' || $filters['sort'] !== 'created_at' || $filters['direction'] !== 'desc',
                'resetUrl' => $clearUrl,
                'applyLabel' => $adminText('common.apply'),
                'resetLabel' => $adminText('pages.clear_filters'),
            ])
        </div>
    </div>

    @if ($pages->isEmpty())
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-end wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    @if ($canCreatePage)
                    <a href="{{ $newPageUrl }}" class="wb-btn wb-btn-primary">{{ $adminText('pages.new_page') }}</a>
                    @endif
                    @if ($canCreatePage)
                    <a href="{{ $pageConverterUrl }}" class="wb-btn wb-btn-secondary">{{ $adminText('pages.page_converter') }}</a>
                    @endif
                    @if ($canCreatePage)
                    <a href="{{ $importPageUrl }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">{{ $adminText('pages.import_page') }}</a>
                    @endif
                </div>
            </div>

            <div class="wb-card-body">
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('pages.no_pages') }}</div>
                        <div class="wb-empty-text">
                            {{ $hasActiveFilters
                                ? $adminText('pages.no_pages_filtered_help')
                                : $adminText('pages.no_pages_help', ['site' => strtolower($siteContext)]) }}
                        </div>
                        <div class="wb-empty-action">
                            @if ($hasActiveFilters)
                                <a href="{{ $clearUrl }}" class="wb-btn wb-btn-secondary">{{ $adminText('pages.clear_filters') }}</a>
                            @endif
                            @if ($canCreatePage)
                            <a href="{{ $newPageUrl }}" class="wb-btn wb-btn-primary">{{ $adminText('pages.create_page') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
        </div>
    @else
        <div class="wb-card" data-wb-admin-bulk-listing>
            <div class="wb-card-header wb-cluster wb-cluster-end wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    @if ($canCreatePage)
                    <a href="{{ $newPageUrl }}" class="wb-btn wb-btn-primary">{{ $adminText('pages.new_page') }}</a>
                    @endif
                    @if ($canCreatePage)
                    <a href="{{ $pageConverterUrl }}" class="wb-btn wb-btn-secondary">{{ $adminText('pages.page_converter') }}</a>
                    @endif
                    @if ($canCreatePage)
                    <a href="{{ $importPageUrl }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">{{ $adminText('pages.import_page') }}</a>
                    @endif
                </div>
            </div>

            <div class="wb-card-body">
                @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                    'label' => $adminText('common.selected'),
                    'deleteTarget' => $deletablePageIds !== [] ? '#bulk-delete-pages-modal' : null,
                    'deleteLabel' => $adminText('common.delete_selected'),
                ])

                <div class="wb-table-wrap wb-admin-pages-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover wb-admin-pages-table" @include('webblocks-cms::admin.partials.table-sort-attributes')>
                        <thead>
                            <tr>
                                <th>
                                    <label class="wb-check" for="select_all_visible_pages">
                                        <input id="select_all_visible_pages" type="checkbox" @disabled($deletablePageIds === []) data-wb-admin-select-all-visible aria-label="{{ $adminText('pages.select_all_visible') }}">
                                        <span class="wb-sr-only">{{ $adminText('pages.select_all_visible') }}</span>
                                    </label>
                                </th>
                                @include('webblocks-cms::admin.partials.table-sort-heading', ['key' => 'title', 'label' => $adminText('pages.columns.page')])
                                @foreach ($listingColumns as $listingColumn)
                                    @include('webblocks-cms::admin.pages.columns.'.$listingColumn, ['columnHeading' => true])
                                @endforeach
                                <th>{{ $adminText('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pages as $page)
                                @php
                                    $translations = $page->translations->sortByDesc(fn ($translation) => $translation->locale?->is_default)->values();
                                    $enabledLocaleCount = (int) ($siteLocaleCounts[$page->site_id] ?? $translations->count());
                                    $missingTranslations = max($enabledLocaleCount - $translations->count(), 0);
                                    $defaultPublicUrl = $page->isPublished() ? $page->publicUrl() : null;
                                    $previewUrl = route('admin.pages.preview', $page);
                                @endphp
                                <tr>
                                    <td>
                                        <label class="wb-check" for="page_select_{{ $page->id }}">
                                            <input id="page_select_{{ $page->id }}" type="checkbox" @disabled(! in_array($page->id, $deletablePageIds, true)) value="{{ $page->id }}" data-wb-admin-row-select aria-label="{{ $adminText('pages.select_page', ['title' => $page->title]) }}">
                                            <span class="wb-sr-only">{{ $adminText('pages.select_page', ['title' => $page->title]) }}</span>
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
                                                    <span class="wb-status-pill wb-admin-locale-pill {{ $translation->locale?->is_default ? 'wb-status-info' : 'wb-status-active' }}">
                                                        {{ $translation->locale?->code }}
                                                        @if ($translation->locale?->is_default)
                                                            {{ $adminText('common.default') }}
                                                        @endif
                                                    </span>
                                                @endforeach

                                                @if ($missingTranslations > 0)
                                                    <span class="wb-text-sm wb-text-muted">{{ $adminText('pages.missing_translations', ['count' => $missingTranslations]) }}</span>
                                                @endif
                                            </div>

                                            <div class="wb-admin-pages-path-row wb-cluster wb-cluster-2 wb-flex-wrap wb-text-sm">
                                                @foreach ($translations->take(3) as $translation)
                                                    @php
                                                        $translationPublicUrl = $page->isPublished() ? $page->publicUrl($translation->locale?->code) : null;
                                                        $translationPublicPath = $page->publicPath($translation->locale?->code);
                                                    @endphp
                                                    @if ($translationPublicUrl && $translationPublicPath && $page->isPublished())
                                                         <a href="{{ $translationPublicUrl }}" target="_blank" rel="noopener noreferrer" class="wb-link wb-admin-pages-path-link">
                                                            {{ strtoupper($translation->locale?->code ?? 'en') }} {{ $translationPublicPath }}
                                                          </a>
                                                      @elseif ($translationPublicPath && ! $page->isPublished())
                                                          <span class="wb-text-muted wb-admin-pages-path-text">{{ strtoupper($translation->locale?->code ?? 'en') }} {{ $translationPublicPath }} ({{ $adminText('pages.not_public') }})</span>
                                                      @else
                                                          <span class="wb-text-muted wb-admin-pages-path-text">{{ strtoupper($translation->locale?->code ?? 'en') }} {{ $adminText('pages.missing_route') }}</span>
                                                       @endif
                                                   @endforeach
                                            </div>
                                        </div>
                                    </td>
                                    @foreach ($listingColumns as $listingColumn)
                                        @include('webblocks-cms::admin.pages.columns.'.$listingColumn, ['columnHeading' => false])
                                    @endforeach
                                    <td class="wb-admin-pages-table-cell wb-admin-pages-actions-cell">
                                        <div class="wb-action-group">
                                            <a
                                                href="{{ route('admin.pages.index', array_merge($detailsBaseQuery, ['details' => $page->id])) }}"
                                                class="wb-action-btn"
                                                aria-haspopup="dialog"
                                                aria-controls="pageDetailsModal-{{ $page->id }}"
                                                title="{{ $adminText('pages.details') }}"
                                                aria-label="{{ $adminText('pages.open_details') }}"
                                            >
                                                <i class="wb-icon wb-icon-panel-right" aria-hidden="true"></i>
                                            </a>

                                            <a href="{{ route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $adminText('pages.edit_page') }}" aria-label="{{ $adminText('pages.edit_page') }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                            @if (in_array($page->id, $deletablePageIds, true))
                                            <button
                                                type="button"
                                                class="wb-action-btn wb-action-btn-delete"
                                                title="{{ $adminText('pages.delete_page') }}"
                                                aria-label="{{ $adminText('pages.delete_page') }}"
                                                data-wb-toggle="modal"
                                                data-wb-target="#delete-page-{{ $page->id }}-modal"
                                            >
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </button>
                                            @endif
                                        </div>
                                    </td>
</tr>

                                @push('overlays')
                                    @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                                        'id' => 'delete-page-'.$page->id.'-modal',
                                        'title' => $adminText('pages.delete_page_title'),
                                        'description' => $adminText('pages.delete_page_description'),
                                        'action' => route('admin.pages.destroy', $page),
                                        'method' => 'DELETE',
                                        'submitLabel' => $adminText('pages.delete_page'),
                                    ])
                                        <div class="wb-card wb-card-muted">
                                            <div class="wb-card-body wb-stack wb-gap-2">
                                                <div><strong>{{ $page->title }}</strong></div>
                                                <div class="wb-text-sm wb-text-muted">{{ $adminText('common.status') }} {{ $page->workflowLabel() }} | {{ $adminText('sites.singular') }} {{ $page->site?->name ?? '-' }}</div>
                                            </div>
                                        </div>

                                        <p class="wb-text-sm wb-text-muted">{{ $adminText('pages.delete_page_warning') }}</p>
                                    @endcomponent
                                @endpush
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $pages, 'ariaLabel' => $adminText('pages.pagination'), 'compact' => true])
        </div>

        @push('overlays')
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                'id' => 'bulk-delete-pages-modal',
                'title' => $adminText('pages.bulk_delete_title'),
                'description' => $adminText('pages.bulk_delete_description'),
                'action' => route('admin.pages.bulk-destroy'),
                'method' => 'DELETE',
                'submitLabel' => $adminText('common.delete_selected'),
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
                        <strong>{{ $adminText('pages.bulk_delete_count_prefix') }} <span data-wb-admin-bulk-modal-count>0</span> {{ $adminText('pages.bulk_delete_count_suffix') }}</strong>
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('pages.bulk_delete_help') }}</p>
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
