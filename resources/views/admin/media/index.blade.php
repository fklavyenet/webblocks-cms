@php
    use WebBlocks\Cms\Models\Media;
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('media_index.'.$key, $adminLocale, $replace);
    $showUploadModal = $openModal === 'upload-asset';
    $showFetchModal = $openModal === 'fetch-media';
    $showFolderModal = $openModal === 'new-folder';
    $mediaKindLabel = static fn (string $kind) => $adminTranslator->admin('media_index.kind_names.'.$kind, $adminLocale);
    $mediaUsageCount = static fn (int $count) => $adminTranslator->admin($count === 1 ? 'media_index.used_in_one' : 'media_index.used_in_many', $adminLocale, ['count' => $count]);
    $baseQuery = array_filter([
        'folder_id' => $selectedFolderId,
        'search' => $search ?: null,
        'kind' => $kind ?: null,
        'usage' => $usage ?: null,
        'sort' => $sort !== 'updated_at' ? $sort : null,
        'direction' => $direction !== 'desc' ? $direction : null,
        'view' => $viewMode !== 'list' ? $viewMode : null,
    ]);
    $previewBaseQuery = array_merge($baseQuery, ['page' => $assets->currentPage() > 1 ? $assets->currentPage() : null]);
    $currentReturnUrl = route('admin.media.index', $previewBaseQuery);
    // View mode and sorting change presentation, not which rows match.
    $hasActiveFilters = $selectedFolderId || $search !== '' || $kind !== '' || $usage !== '';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('title'), 'heading' => $adminText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('title'),
        'description' => $adminText('description'),
        'count' => $totalMediaCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.media.index'),
                'search' => [
                    'id' => 'media_search',
                    'name' => 'search',
                    'label' => $adminText('search'),
                    'value' => $search,
                    'placeholder' => $adminText('search_placeholder'),
                ],
                'selects' => [
                    [
                        'id' => 'media_kind',
                        'name' => 'kind',
                        'label' => $adminText('kind'),
                        'selected' => $kind,
                        'placeholder' => $adminText('all_kinds'),
                        'options' => [
                            Media::KIND_IMAGE => $adminText('images'),
                            Media::KIND_VIDEO => $adminText('videos'),
                            Media::KIND_DOCUMENT => $adminText('documents'),
                            Media::KIND_OTHER => $adminText('other'),
                        ],
                    ],
                    [
                        'id' => 'media_usage',
                        'name' => 'usage',
                        'label' => $adminText('usage'),
                        'selected' => $usage,
                        'placeholder' => $adminText('all_media'),
                        'options' => [
                            'used' => $adminText('used'),
                            'unused' => $adminText('unused'),
                        ],
                    ],
                    [
                        'id' => 'media_sort',
                        'name' => 'sort',
                        'label' => $adminText('sort_by'),
                        'selected' => $sort,
                        'options' => [
                            'created_at' => $adminText('created_at'),
                            'updated_at' => $adminText('updated_at'),
                            'title' => $adminText('title_field'),
                            'filename' => $adminText('filename'),
                            'kind' => $adminText('kind'),
                            'folder' => $adminText('folder'),
                            'usage' => $adminText('usage'),
                        ],
                    ],
                    [
                        'id' => 'media_direction',
                        'name' => 'direction',
                        'label' => $adminText('direction'),
                        'selected' => $direction,
                        'options' => [
                            'desc' => $adminText('descending'),
                            'asc' => $adminText('ascending'),
                        ],
                    ],
                ],
                'hidden' => [
                    'folder_id' => $selectedFolderId,
                    'view' => $viewMode,
                ],
                'showReset' => $selectedFolderId || $search !== '' || $kind !== '' || $usage !== '' || $sort !== 'updated_at' || $direction !== 'desc' || $viewMode !== 'list',
                'resetUrl' => route('admin.media.index'),
                'applyLabel' => $adminText('apply'),
            ])
        </div>
    </div>

    <div class="wb-card" data-wb-admin-bulk-listing>
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>{{ $adminText('library_title') }}</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredMediaCount }}</span>
            </div>

            <div class="wb-cluster wb-cluster-2">
                <a href="{{ route('admin.media.index', array_merge($baseQuery, ['modal' => 'upload-asset'])) }}" class="wb-btn wb-btn-primary">{{ $adminText('upload_media') }}</a>
                <a href="{{ route('admin.media.index', array_merge($baseQuery, ['modal' => 'fetch-media'])) }}" class="wb-btn wb-btn-secondary">{{ $adminText('fetch_url') }}</a>
                <a href="{{ route('admin.media.index', array_merge($baseQuery, ['modal' => 'new-folder'])) }}" class="wb-btn wb-btn-secondary">{{ $adminText('new_folder') }}</a>
            </div>
        </div>

        <div class="wb-card-body wb-stack wb-gap-4">
            <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-media-toolbar">
                <div class="wb-cluster wb-cluster-2 wb-media-folder-pills">
                    <a href="{{ route('admin.media.index', array_filter(['search' => $search ?: null, 'kind' => $kind ?: null, 'usage' => $usage ?: null, 'sort' => $sort !== 'updated_at' ? $sort : null, 'direction' => $direction !== 'desc' ? $direction : null, 'view' => $viewMode !== 'list' ? $viewMode : null])) }}" class="wb-btn wb-media-folder-pill {{ $selectedFolderId ? 'wb-btn-secondary' : 'wb-btn-primary' }}">{{ $adminText('all_folders') }} <span class="wb-text-sm">{{ $filteredMediaCount }}</span></a>
                    @foreach ($folders as $folder)
                        <a href="{{ route('admin.media.index', array_filter(['folder_id' => $folder->id, 'search' => $search ?: null, 'kind' => $kind ?: null, 'usage' => $usage ?: null, 'sort' => $sort !== 'updated_at' ? $sort : null, 'direction' => $direction !== 'desc' ? $direction : null, 'view' => $viewMode !== 'list' ? $viewMode : null])) }}" class="wb-btn wb-media-folder-pill {{ (string) $selectedFolderId === (string) $folder->id ? 'wb-btn-primary' : 'wb-btn-secondary' }}">
                            {{ $folder->name }} <span class="wb-text-sm">{{ $folder->assets_count }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="wb-cluster wb-cluster-2 wb-media-view-toggle">
                    <label class="wb-check" for="select_all_visible_media_toolbar">
                        <input id="select_all_visible_media_toolbar" type="checkbox" data-wb-admin-select-all-visible aria-label="{{ $adminText('select_all_visible_media') }}">
                        <span>{{ $adminText('select_visible') }}</span>
                    </label>
                    <a href="{{ route('admin.media.index', array_merge($baseQuery, ['view' => 'list'])) }}" class="wb-btn wb-btn-secondary" @if($viewMode === 'list') aria-current="page" @endif>
                        <i class="wb-icon wb-icon-list" aria-hidden="true"></i>
                        <span>{{ $adminText('list') }}</span>
                    </a>
                    <a href="{{ route('admin.media.index', array_merge($baseQuery, ['view' => 'grid'])) }}" class="wb-btn wb-btn-secondary" @if($viewMode === 'grid') aria-current="page" @endif>
                        <i class="wb-icon wb-icon-panel-left" aria-hidden="true"></i>
                        <span>{{ $adminText('grid') }}</span>
                    </a>
                </div>
            </div>

            @if ($assets->isNotEmpty())
                @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                    'label' => $adminText('selected'),
                    'deleteTarget' => '#bulk-delete-media-modal',
                    'deleteLabel' => $adminText('delete_selected'),
                ])
            @endif

            @if ($assets->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('no_media_found') }}</div>
                    <div class="wb-empty-text">
                        {{ $hasActiveFilters ? $adminText('no_media_filtered_help') : $adminText('no_media_found_help') }}
                    </div>
                    <div class="wb-cluster wb-cluster-2">
                        <a href="{{ route('admin.media.index', ['modal' => 'upload-asset']) }}" class="wb-btn wb-btn-primary">{{ $adminText('upload_media') }}</a>
                        <a href="{{ route('admin.media.index', ['modal' => 'fetch-media']) }}" class="wb-btn wb-btn-secondary">{{ $adminText('fetch_url') }}</a>
                        @if ($hasActiveFilters)
                            <a href="{{ route('admin.media.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('reset_filters') }}</a>
                        @endif
                    </div>
                </div>
            @elseif ($viewMode === 'grid')
                <div class="wb-media-grid">
                    @foreach ($assets as $asset)
                        @php($assetUsages = $asset->resolvedUsages)
                        <div class="wb-card wb-card-muted wb-media-grid-card">
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <label class="wb-check" for="media_grid_select_{{ $asset->id }}">
                                    <input id="media_grid_select_{{ $asset->id }}" type="checkbox" value="{{ $asset->id }}" data-wb-admin-row-select aria-label="{{ $adminText('select_media', ['title' => $asset->displayTitle()]) }}">
                                    <span class="wb-sr-only">{{ $adminText('select_media', ['title' => $asset->displayTitle()]) }}</span>
                                </label>

                                <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-media-grid-preview wb-no-decoration" title="{{ $adminText('preview_media') }}">
                                    @if ($asset->canPreview() && $asset->url())
                                        <img src="{{ $asset->url() }}" alt="{{ $asset->thumbnailLabel() }}">
                                    @else
                                        <i class="wb-icon {{ $asset->previewIconClass() }} wb-icon-2xl" aria-hidden="true"></i>
                                    @endif
                                </a>

                                <div class="wb-stack wb-gap-1">
                                    <strong><a href="{{ route('admin.media.edit', ['media' => $asset, 'return_url' => $currentReturnUrl]) }}">{{ $asset->displayTitle() }}</a></strong>
                                    <div class="wb-text-sm wb-text-muted" title="{{ $asset->original_name }}">{{ $asset->original_name }}</div>
                                    <div class="wb-text-sm wb-text-muted">{{ $asset->compactMetaLabel() }}</div>
                                    <div class="wb-text-sm wb-text-muted">{{ $asset->folder?->name ?? $adminText('no_folder') }}</div>
                                </div>

                                <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                    @if ($assetUsages->isNotEmpty())
                                        <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['usage_media' => $asset->id])) }}" class="wb-status-pill wb-status-pending">{{ $mediaUsageCount($assetUsages->count()) }}</a>
                                    @else
                                        <span class="wb-status-pill wb-status-info">{{ $adminText('unused_media') }}</span>
                                    @endif

                                    <span class="wb-status-pill wb-status-info">{{ $mediaKindLabel($asset->kind) }}</span>
                                </div>

                                <div class="wb-action-group wb-media-grid-actions">
                                    <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-action-btn wb-action-btn-view" title="{{ $adminText('preview_media') }}" aria-label="{{ $adminText('preview_media') }}"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a>
                                    <a href="{{ route('admin.media.edit', ['media' => $asset, 'return_url' => $currentReturnUrl]) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $adminText('edit_media') }}" aria-label="{{ $adminText('edit_media') }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                    @if ($assetUsages->isNotEmpty())
                                        <button type="button" class="wb-action-btn wb-action-btn-delete" title="{{ $adminText('delete_media') }}" aria-label="{{ $adminText('delete_media') }}" disabled><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                    @else
                                        <a href="{{ route('admin.media.edit', ['media' => $asset, 'return_url' => $currentReturnUrl, 'modal' => 'delete-media']) }}" class="wb-action-btn wb-action-btn-delete" title="{{ $adminText('delete_media') }}" aria-label="{{ $adminText('delete_media') }}" aria-haspopup="dialog"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover wb-media-table">
                        <thead>
                            <tr>
                                <th>
                                    <label class="wb-check" for="select_all_visible_media_table">
                                        <input id="select_all_visible_media_table" type="checkbox" data-wb-admin-select-all-visible aria-label="{{ $adminText('select_all_visible_media') }}">
                                        <span class="wb-sr-only">{{ $adminText('select_all_visible_media') }}</span>
                                    </label>
                                </th>
                                <th>{{ $adminText('preview') }}</th>
                                <th>{{ $adminText('media') }}</th>
                                <th>{{ $adminText('folder') }}</th>
                                <th>{{ $adminText('usage') }}</th>
                                <th>{{ $adminText('updated') }}</th>
                                <th>{{ $adminText('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assets as $asset)
                                @php($assetUsages = $asset->resolvedUsages)
                                <tr>
                                    <td>
                                        <label class="wb-check" for="media_select_{{ $asset->id }}">
                                            <input id="media_select_{{ $asset->id }}" type="checkbox" value="{{ $asset->id }}" data-wb-admin-row-select aria-label="{{ $adminText('select_media', ['title' => $asset->displayTitle()]) }}">
                                            <span class="wb-sr-only">{{ $adminText('select_media', ['title' => $asset->displayTitle()]) }}</span>
                                        </label>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-media-preview-box wb-no-decoration" title="{{ $adminText('preview_media') }}">
                                            @if ($asset->canPreview() && $asset->url())
                                                <img src="{{ $asset->url() }}" alt="{{ $asset->thumbnailLabel() }}">
                                            @else
                                                <i class="wb-icon {{ $asset->previewIconClass() }} wb-icon-xl" aria-hidden="true"></i>
                                            @endif
                                        </a>
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong><a href="{{ route('admin.media.edit', ['media' => $asset, 'return_url' => $currentReturnUrl]) }}">{{ $asset->displayTitle() }}</a></strong>
                                            <span class="wb-text-sm wb-text-muted" title="{{ $asset->original_name }}">{{ $asset->original_name }}</span>
                                            <span class="wb-text-sm wb-text-muted">{{ $asset->compactMetaLabel() }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <span>{{ $asset->folder?->name ?? $adminText('no_folder') }}</span>
                                            <span class="wb-text-sm wb-text-muted">{{ $mediaKindLabel($asset->kind) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($assetUsages->isNotEmpty())
                                            <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['usage_media' => $asset->id])) }}" class="wb-status-pill wb-status-pending">{{ $mediaUsageCount($assetUsages->count()) }}</a>
                                        @else
                                            <span class="wb-status-pill wb-status-info">{{ $adminText('unused_media') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <span>{{ $asset->updated_at?->format('Y-m-d') }}</span>
                                            <span class="wb-text-sm wb-text-muted">{{ $asset->updated_at?->format('H:i') }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-action-btn wb-action-btn-view" title="{{ $adminText('preview_media') }}" aria-label="{{ $adminText('preview_media') }}"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a>
                                            <a href="{{ route('admin.media.edit', ['media' => $asset, 'return_url' => $currentReturnUrl]) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $adminText('edit_media') }}" aria-label="{{ $adminText('edit_media') }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                            @if ($assetUsages->isNotEmpty())
                                                <button type="button" class="wb-action-btn wb-action-btn-delete" title="{{ $adminText('delete_media') }}" aria-label="{{ $adminText('delete_media') }}" disabled><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                            @else
                                                <a href="{{ route('admin.media.edit', ['media' => $asset, 'return_url' => $currentReturnUrl, 'modal' => 'delete-media']) }}" class="wb-action-btn wb-action-btn-delete" title="{{ $adminText('delete_media') }}" aria-label="{{ $adminText('delete_media') }}" aria-haspopup="dialog"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="wb-text-sm wb-text-muted wb-media-copy-feedback" data-wb-copy-feedback aria-live="polite"></div>
        </div>

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $assets, 'ariaLabel' => $adminText('media_pagination'), 'compact' => true])
    </div>
@endsection

@push('overlays')
    @if ($assets->isNotEmpty())
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'bulk-delete-media-modal',
            'title' => $adminText('delete_selected_media'),
            'description' => $adminText('delete_selected_media_description'),
            'action' => route('admin.media.bulk-destroy'),
            'method' => 'DELETE',
            'submitLabel' => $adminText('delete_selected'),
            'formAttributes' => [
                'data-wb-admin-bulk-delete-form' => true,
                'data-wb-admin-bulk-input-name' => 'media_ids[]',
            ],
            'submitAttributes' => [
                'data-wb-admin-bulk-delete-submit' => true,
                'disabled' => true,
            ],
        ])
            <input type="hidden" name="return_url" value="{{ $currentReturnUrl }}">

            <div class="wb-card wb-card-muted">
                <div class="wb-card-body wb-stack wb-gap-2">
                    <strong>{!! $adminText('bulk_delete_count_html') !!}</strong>
                    <p class="wb-text-sm wb-text-muted">{{ $adminText('bulk_delete_scope') }}</p>
                </div>
            </div>

            <div data-wb-admin-bulk-inputs></div>
            <input type="hidden" name="media_ids[]" value="" disabled data-wb-admin-bulk-empty-input>
        @endcomponent
    @endif

    @if ($previewAsset)
        <div class="wb-overlay-layer wb-overlay-layer--dialog" data-wb-media-preview-overlay data-wb-close-url="{{ route('admin.media.index', $previewBaseQuery) }}">
            <div class="wb-overlay-backdrop"></div>
            <div class="wb-modal wb-modal-xl is-open" id="media-preview-modal" role="dialog" aria-modal="true" aria-labelledby="media-preview-title" data-wb-admin-close-url="{{ route('admin.media.index', $previewBaseQuery) }}">
                <div class="wb-modal-dialog" data-wb-media-preview-panel>
                    <div class="wb-modal-header">
                        <div class="wb-stack wb-gap-1">
                            <h2 class="wb-modal-title" id="media-preview-title">{{ $previewAsset->displayTitle() }}</h2>
                            <span class="wb-text-sm wb-text-muted">{{ $previewAsset->compactMetaLabel() }}</span>
                        </div>
                        <a href="{{ route('admin.media.index', $previewBaseQuery) }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></a>
                    </div>
                    <div class="wb-modal-body wb-stack wb-gap-4">
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-3">
                                @if ($previewAsset->canPreview() && $previewAsset->url())
                                    <img src="{{ $previewAsset->url() }}" alt="{{ $previewAsset->thumbnailLabel() }}">
                                @else
                                    <div class="wb-empty">
                                        <i class="wb-icon {{ $previewAsset->previewIconClass() }} wb-icon-2xl" aria-hidden="true"></i>
                                        <div class="wb-empty-title">{{ $adminText('preview_unavailable') }}</div>
                                        <div class="wb-empty-text">{{ $adminText('preview_unavailable_help') }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <div class="wb-text-sm wb-text-muted">{{ $previewAsset->folder?->name ?? $adminText('no_folder') }}</div>
                            <div class="wb-action-group">
                                <a href="{{ route('admin.media.edit', ['media' => $previewAsset, 'return_url' => $currentReturnUrl]) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $adminText('edit_media') }}" aria-label="{{ $adminText('edit_media') }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($usageAsset)
        <div class="wb-overlay-layer wb-overlay-layer--dialog">
            <div class="wb-overlay-backdrop"></div>
            <div class="wb-drawer wb-drawer-right wb-drawer-sm is-open" id="media-usage-drawer" role="dialog" aria-modal="true" aria-labelledby="media-usage-title">
                <div class="wb-drawer-header">
                    <h2 class="wb-drawer-title" id="media-usage-title">{{ $adminText('media_usage') }}</h2>
                    <a href="{{ route('admin.media.index', $previewBaseQuery) }}" class="wb-drawer-close" aria-label="{{ $adminText('close_usage_details') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></a>
                </div>
                <div class="wb-drawer-body wb-stack wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                        <strong>{{ $usageAsset->displayTitle() }}</strong>
                        <div class="wb-text-sm wb-text-muted">{{ $usageAsset->resolvedUsages->count() === 1 ? $adminText('used_in_one_location') : $adminText('used_in_many_locations', ['count' => $usageAsset->resolvedUsages->count()]) }}</div>
                    </div>

                    @if ($usageAsset->resolvedUsages->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('unused_media') }}</div>
                            <div class="wb-empty-text">{{ $adminText('unused_media_help') }}</div>
                        </div>
                    @else
                        <div class="wb-stack wb-gap-2 wb-media-usage-list">
                            @foreach ($usageAsset->resolvedUsages as $usageItem)
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body wb-stack wb-gap-1">
                                        <strong>{{ $usageItem['page_title'] ?: $adminText('shared_content') }}</strong>
                                        <div class="wb-text-sm wb-text-muted">{{ $usageItem['context'] }} • {{ $usageItem['label'] }}</div>
                                        @if (! empty($usageItem['admin_url']))
                                            <a href="{{ $usageItem['admin_url'] }}" class="wb-btn wb-btn-secondary">{{ $adminText('open_usage') }}</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($showUploadModal)
        <div class="wb-overlay-layer wb-overlay-layer--dialog">
            <div class="wb-overlay-backdrop"></div>

            <div class="wb-modal wb-modal-xl is-open" id="media-upload-modal" role="dialog" aria-modal="true" aria-labelledby="media-upload-title">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-header">
                        <div class="wb-stack wb-gap-1">
                            <h2 class="wb-modal-title" id="media-upload-title">{{ $adminText('upload_media') }}</h2>
                            <span class="wb-text-sm wb-text-muted">{{ $adminText('upload_media_help') }}</span>
                        </div>

                        <a href="{{ route('admin.media.index', $baseQuery) }}" class="wb-modal-close" aria-label="{{ $adminText('close') }}">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                        </a>
                    </div>

                    <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="wb-stack wb-gap-4">
                        @csrf
                        <input type="hidden" name="_media_modal" value="upload-asset">

                        <div class="wb-modal-body wb-stack wb-gap-4">
                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <label for="file">{{ $adminText('file') }}</label>
                                    <input id="file" name="file" type="file" class="wb-input" required>
                                    <span>{{ $adminText('accepted_file_types') }}</span>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="folder_id">{{ $adminText('folder') }}</label>
                                    <select id="folder_id" name="folder_id" class="wb-select">
                                        <option value="">{{ $adminText('no_folder') }}</option>
                                        @foreach ($folders as $folder)
                                            <option value="{{ $folder->id }}" @selected((string) old('folder_id', $selectedFolderId) === (string) $folder->id)>
                                                {{ $folder->name }}@if($folder->parent) ({{ $folder->parent->name }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <label for="title">{{ $adminText('title_field') }}</label>
                                    <input id="title" name="title" type="text" class="wb-input" value="{{ old('title') }}">
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="alt_text">{{ $adminText('alt_text') }}</label>
                                    <input id="alt_text" name="alt_text" type="text" class="wb-input" value="{{ old('alt_text') }}">
                                </div>
                            </div>

                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <label for="caption">{{ $adminText('caption') }}</label>
                                    <textarea id="caption" name="caption" class="wb-textarea" rows="3">{{ old('caption') }}</textarea>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="description">{{ $adminText('description_field') }}</label>
                                    <textarea id="description" name="description" class="wb-textarea" rows="3">{{ old('description') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <x-webblocks-cms::admin.form-actions
                            :cancel-url="route('admin.media.index', $baseQuery)"
                            :submit-label="$adminText('save')"
                            container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                        />
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showFetchModal)
        <div class="wb-overlay-layer wb-overlay-layer--dialog">
            <div class="wb-overlay-backdrop"></div>

            <div class="wb-modal wb-modal-xl is-open" id="media-fetch-modal" role="dialog" aria-modal="true" aria-labelledby="media-fetch-title">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-header">
                        <div class="wb-stack wb-gap-1">
                            <h2 class="wb-modal-title" id="media-fetch-title">{{ $adminText('fetch_remote_media') }}</h2>
                            <span class="wb-text-sm wb-text-muted">{{ $adminText('fetch_remote_media_help') }}</span>
                        </div>

                        <a href="{{ route('admin.media.index', $baseQuery) }}" class="wb-modal-close" aria-label="{{ $adminText('close') }}">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                        </a>
                    </div>

                    <form method="POST" action="{{ route('admin.media.fetch') }}" class="wb-stack wb-gap-4">
                        @csrf
                        <input type="hidden" name="_media_modal" value="fetch-media">

                        <div class="wb-modal-body wb-stack wb-gap-4">
                            @if ($errors->has('source_url'))
                                <div class="wb-alert wb-alert-danger">
                                    <div>{{ $errors->first('source_url') }}</div>
                                </div>
                            @endif

                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <label for="source_url">{{ $adminText('remote_url') }}</label>
                                    <input id="source_url" name="source_url" type="url" class="wb-input" value="{{ old('source_url') }}" placeholder="https://example.com/image.jpg" required>
                                    <span>{{ $adminText('remote_url_help') }}</span>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="fetch_folder_id">{{ $adminText('folder') }}</label>
                                    <select id="fetch_folder_id" name="folder_id" class="wb-select">
                                        <option value="">{{ $adminText('no_folder') }}</option>
                                        @foreach ($folders as $folder)
                                            <option value="{{ $folder->id }}" @selected((string) old('folder_id', $selectedFolderId) === (string) $folder->id)>
                                                {{ $folder->name }}@if($folder->parent) ({{ $folder->parent->name }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <label for="fetch_title">{{ $adminText('title_field') }}</label>
                                    <input id="fetch_title" name="title" type="text" class="wb-input" value="{{ old('title') }}">
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="fetch_alt_text">{{ $adminText('alt_text') }}</label>
                                    <input id="fetch_alt_text" name="alt_text" type="text" class="wb-input" value="{{ old('alt_text') }}">
                                </div>
                            </div>

                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <label for="fetch_caption">{{ $adminText('caption') }}</label>
                                    <textarea id="fetch_caption" name="caption" class="wb-textarea" rows="3">{{ old('caption') }}</textarea>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="fetch_description">{{ $adminText('description_field') }}</label>
                                    <textarea id="fetch_description" name="description" class="wb-textarea" rows="3">{{ old('description') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <x-webblocks-cms::admin.form-actions
                            :cancel-url="route('admin.media.index', $baseQuery)"
                            :submit-label="$adminText('fetch_media')"
                            container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                        />
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showFolderModal)
        <div class="wb-overlay-layer wb-overlay-layer--dialog">
            <div class="wb-overlay-backdrop"></div>

            <div class="wb-modal wb-modal-lg is-open" id="media-folder-modal" role="dialog" aria-modal="true" aria-labelledby="media-folder-title">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-header">
                        <div class="wb-stack wb-gap-1">
                            <h2 class="wb-modal-title" id="media-folder-title">{{ $adminText('create_folder') }}</h2>
                            <span class="wb-text-sm wb-text-muted">{{ $adminText('create_folder_help') }}</span>
                        </div>

                        <a href="{{ route('admin.media.index', $baseQuery) }}" class="wb-modal-close" aria-label="{{ $adminText('close') }}">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                        </a>
                    </div>

                    <form method="POST" action="{{ route('admin.media.folders.store') }}" class="wb-stack wb-gap-4">
                        @csrf
                        <input type="hidden" name="_media_modal" value="new-folder">

                        <div class="wb-modal-body wb-stack wb-gap-4">
                            <div class="wb-grid wb-grid-3">
                                <div class="wb-stack wb-gap-1">
                                    <label for="folder_name">{{ $adminText('name') }}</label>
                                    <input id="folder_name" name="name" type="text" class="wb-input" value="{{ old('name') }}" required>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="folder_slug">{{ $adminText('slug') }}</label>
                                    <input id="folder_slug" name="slug" type="text" class="wb-input" value="{{ old('slug') }}">
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="parent_id">{{ $adminText('parent_folder') }}</label>
                                    <select id="parent_id" name="parent_id" class="wb-select">
                                        <option value="">{{ $adminText('no_parent') }}</option>
                                        @foreach ($folders as $folder)
                                            <option value="{{ $folder->id }}" @selected((string) old('parent_id', $selectedFolderId) === (string) $folder->id)>
                                                {{ $folder->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <x-webblocks-cms::admin.form-actions
                            :cancel-url="route('admin.media.index', $baseQuery)"
                            :submit-label="$adminText('save')"
                            container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                        />
                    </form>
                </div>
            </div>
        </div>
    @endif
@endpush

@push('scripts')
    @php($bulkActionsJsPath = public_path('cms/js/admin/listing-bulk-actions.js'))
    @if (is_file($bulkActionsJsPath))
        <script src="{{ asset('cms/js/admin/listing-bulk-actions.js') }}?v={{ filemtime($bulkActionsJsPath) }}" defer></script>
    @endif
@endpush
