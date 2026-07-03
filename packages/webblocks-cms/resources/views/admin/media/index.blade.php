@extends('webblocks-cms::layouts.admin', ['title' => 'Media', 'heading' => 'Media'])

@php
    use WebBlocks\Cms\Models\Media;

    $showUploadModal = $openModal === 'upload-asset';
    $showFetchModal = $openModal === 'fetch-media';
    $showFolderModal = $openModal === 'new-folder';
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
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Media',
        'description' => 'Review, filter, preview, and manage the shared media library from one compact screen.',
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
                    'label' => 'Search',
                    'value' => $search,
                    'placeholder' => 'Search title, filename, alt text, or caption',
                ],
                'selects' => [
                    [
                        'id' => 'media_kind',
                        'name' => 'kind',
                        'label' => 'Kind',
                        'selected' => $kind,
                        'placeholder' => 'All kinds',
                        'options' => [
                            Media::KIND_IMAGE => 'Images',
                            Media::KIND_VIDEO => 'Videos',
                            Media::KIND_DOCUMENT => 'Documents',
                            Media::KIND_OTHER => 'Other',
                        ],
                    ],
                    [
                        'id' => 'media_usage',
                        'name' => 'usage',
                        'label' => 'Usage',
                        'selected' => $usage,
                        'placeholder' => 'All media',
                        'options' => [
                            'used' => 'Used',
                            'unused' => 'Unused',
                        ],
                    ],
                    [
                        'id' => 'media_sort',
                        'name' => 'sort',
                        'label' => 'Sort by',
                        'selected' => $sort,
                        'options' => [
                            'created_at' => 'Created at',
                            'updated_at' => 'Updated at',
                            'title' => 'Title',
                            'filename' => 'Filename',
                            'kind' => 'Kind',
                            'folder' => 'Folder',
                            'usage' => 'Usage',
                        ],
                    ],
                    [
                        'id' => 'media_direction',
                        'name' => 'direction',
                        'label' => 'Direction',
                        'selected' => $direction,
                        'options' => [
                            'desc' => 'Descending',
                            'asc' => 'Ascending',
                        ],
                    ],
                ],
                'hidden' => [
                    'folder_id' => $selectedFolderId,
                    'view' => $viewMode,
                ],
                'showReset' => $selectedFolderId || $search !== '' || $kind !== '' || $usage !== '' || $sort !== 'updated_at' || $direction !== 'desc' || $viewMode !== 'list',
                'resetUrl' => route('admin.media.index'),
                'applyLabel' => 'Apply',
            ])
        </div>
    </div>

    <div class="wb-card" data-wb-admin-bulk-listing>
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>Media Library</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredMediaCount }}</span>
            </div>

            <div class="wb-cluster wb-cluster-2">
                <a href="{{ route('admin.media.index', array_merge($baseQuery, ['modal' => 'upload-asset'])) }}" class="wb-btn wb-btn-primary">Upload Media</a>
                <a href="{{ route('admin.media.index', array_merge($baseQuery, ['modal' => 'fetch-media'])) }}" class="wb-btn wb-btn-secondary">Fetch URL</a>
                <a href="{{ route('admin.media.index', array_merge($baseQuery, ['modal' => 'new-folder'])) }}" class="wb-btn wb-btn-secondary">New Folder</a>
            </div>
        </div>

        <div class="wb-card-body wb-stack wb-gap-4">
            <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-media-toolbar">
                <div class="wb-cluster wb-cluster-2 wb-media-folder-pills">
                    <a href="{{ route('admin.media.index', array_filter(['search' => $search ?: null, 'kind' => $kind ?: null, 'usage' => $usage ?: null, 'sort' => $sort !== 'updated_at' ? $sort : null, 'direction' => $direction !== 'desc' ? $direction : null, 'view' => $viewMode !== 'list' ? $viewMode : null])) }}" class="wb-btn wb-media-folder-pill {{ $selectedFolderId ? 'wb-btn-secondary' : 'wb-btn-primary' }}">All folders <span class="wb-text-sm">{{ $filteredMediaCount }}</span></a>
                    @foreach ($folders as $folder)
                        <a href="{{ route('admin.media.index', array_filter(['folder_id' => $folder->id, 'search' => $search ?: null, 'kind' => $kind ?: null, 'usage' => $usage ?: null, 'sort' => $sort !== 'updated_at' ? $sort : null, 'direction' => $direction !== 'desc' ? $direction : null, 'view' => $viewMode !== 'list' ? $viewMode : null])) }}" class="wb-btn wb-media-folder-pill {{ (string) $selectedFolderId === (string) $folder->id ? 'wb-btn-primary' : 'wb-btn-secondary' }}">
                            {{ $folder->name }} <span class="wb-text-sm">{{ $folder->assets_count }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="wb-cluster wb-cluster-2 wb-media-view-toggle">
                    <label class="wb-checkbox" for="select_all_visible_media_toolbar">
                        <input id="select_all_visible_media_toolbar" type="checkbox" data-wb-admin-select-all-visible aria-label="Select all visible media">
                        <span>Select visible</span>
                    </label>
                    <a href="{{ route('admin.media.index', array_merge($baseQuery, ['view' => 'list'])) }}" class="wb-btn wb-btn-secondary" @if($viewMode === 'list') aria-current="page" @endif>
                        <i class="wb-icon wb-icon-list" aria-hidden="true"></i>
                        <span>List</span>
                    </a>
                    <a href="{{ route('admin.media.index', array_merge($baseQuery, ['view' => 'grid'])) }}" class="wb-btn wb-btn-secondary" @if($viewMode === 'grid') aria-current="page" @endif>
                        <i class="wb-icon wb-icon-panel-left" aria-hidden="true"></i>
                        <span>Grid</span>
                    </a>
                </div>
            </div>

            @if ($assets->isNotEmpty())
                @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                    'label' => 'selected',
                    'deleteTarget' => '#bulk-delete-media-modal',
                    'deleteLabel' => 'Delete selected',
                ])
            @endif

            @if ($assets->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">No media found</div>
                    <div class="wb-empty-text">Adjust the filters or upload the next file into the shared media library.</div>
                    <div class="wb-cluster wb-cluster-2">
                        <a href="{{ route('admin.media.index', ['modal' => 'upload-asset']) }}" class="wb-btn wb-btn-primary">Upload Media</a>
                        <a href="{{ route('admin.media.index', ['modal' => 'fetch-media']) }}" class="wb-btn wb-btn-secondary">Fetch URL</a>
                        <a href="{{ route('admin.media.index') }}" class="wb-btn wb-btn-secondary">Reset filters</a>
                    </div>
                </div>
            @elseif ($viewMode === 'grid')
                <div class="wb-media-grid">
                    @foreach ($assets as $asset)
                        @php($assetUsages = $asset->resolvedUsages)
                        <div class="wb-card wb-card-muted wb-media-grid-card">
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <label class="wb-checkbox" for="media_grid_select_{{ $asset->id }}">
                                    <input id="media_grid_select_{{ $asset->id }}" type="checkbox" value="{{ $asset->id }}" data-wb-admin-row-select aria-label="Select media {{ $asset->displayTitle() }}">
                                    <span class="wb-sr-only">Select media {{ $asset->displayTitle() }}</span>
                                </label>

                                <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-media-grid-preview wb-no-decoration" title="Preview media">
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
                                    <div class="wb-text-sm wb-text-muted">{{ $asset->folder?->name ?? 'No folder' }}</div>
                                </div>

                                <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                    @if ($assetUsages->isNotEmpty())
                                        <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['usage_media' => $asset->id])) }}" class="wb-status-pill wb-status-pending">Used in {{ $assetUsages->count() }}</a>
                                    @else
                                        <span class="wb-status-pill wb-status-info">Unused media</span>
                                    @endif

                                    <span class="wb-status-pill wb-status-info">{{ ucfirst($asset->kind) }}</span>
                                </div>

                                <div class="wb-action-group wb-media-grid-actions">
                                    <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-action-btn wb-action-btn-view" title="Preview media" aria-label="Preview media"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a>
                                    <a href="{{ route('admin.media.edit', ['media' => $asset, 'return_url' => $currentReturnUrl]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit media" aria-label="Edit media"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                    @if ($assetUsages->isNotEmpty())
                                        <button type="button" class="wb-action-btn wb-action-btn-delete" title="Delete media" aria-label="Delete media" disabled><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                    @else
                                        <a href="{{ route('admin.media.edit', ['media' => $asset, 'return_url' => $currentReturnUrl, 'modal' => 'delete-media']) }}" class="wb-action-btn wb-action-btn-delete" title="Delete media" aria-label="Delete media" aria-haspopup="dialog"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></a>
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
                                    <label class="wb-checkbox" for="select_all_visible_media_table">
                                        <input id="select_all_visible_media_table" type="checkbox" data-wb-admin-select-all-visible aria-label="Select all visible media">
                                        <span class="wb-sr-only">Select all visible media</span>
                                    </label>
                                </th>
                                <th>Preview</th>
                                <th>Media</th>
                                <th>Folder</th>
                                <th>Usage</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assets as $asset)
                                @php($assetUsages = $asset->resolvedUsages)
                                <tr>
                                    <td>
                                        <label class="wb-checkbox" for="media_select_{{ $asset->id }}">
                                            <input id="media_select_{{ $asset->id }}" type="checkbox" value="{{ $asset->id }}" data-wb-admin-row-select aria-label="Select media {{ $asset->displayTitle() }}">
                                            <span class="wb-sr-only">Select media {{ $asset->displayTitle() }}</span>
                                        </label>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-media-preview-box wb-no-decoration" title="Preview media">
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
                                            <span>{{ $asset->folder?->name ?? 'No folder' }}</span>
                                            <span class="wb-text-sm wb-text-muted">{{ ucfirst($asset->kind) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($assetUsages->isNotEmpty())
                                            <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['usage_media' => $asset->id])) }}" class="wb-status-pill wb-status-pending">Used in {{ $assetUsages->count() }}</a>
                                        @else
                                            <span class="wb-status-pill wb-status-info">Unused media</span>
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
                                            <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-action-btn wb-action-btn-view" title="Preview media" aria-label="Preview media"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a>
                                            <a href="{{ route('admin.media.edit', ['media' => $asset, 'return_url' => $currentReturnUrl]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit media" aria-label="Edit media"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                            @if ($assetUsages->isNotEmpty())
                                                <button type="button" class="wb-action-btn wb-action-btn-delete" title="Delete media" aria-label="Delete media" disabled><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                            @else
                                                <a href="{{ route('admin.media.edit', ['media' => $asset, 'return_url' => $currentReturnUrl, 'modal' => 'delete-media']) }}" class="wb-action-btn wb-action-btn-delete" title="Delete media" aria-label="Delete media" aria-haspopup="dialog"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></a>
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

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $assets, 'ariaLabel' => 'Media pagination', 'compact' => true])
    </div>
@endsection

@push('overlays')
    @if ($assets->isNotEmpty())
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'bulk-delete-media-modal',
            'title' => 'Delete Selected Media',
            'description' => 'This deletes selected media records and their stored files when they are not in use.',
            'action' => route('admin.media.bulk-destroy'),
            'method' => 'DELETE',
            'submitLabel' => 'Delete selected',
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
                    <strong><span data-wb-admin-bulk-modal-count>0</span> selected media items will be deleted.</strong>
                    <p class="wb-text-sm wb-text-muted">This bulk action applies only to media visible on this page. The server re-checks access and usage for every selected item before deletion.</p>
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
                        <a href="{{ route('admin.media.index', $previewBaseQuery) }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></a>
                    </div>
                    <div class="wb-modal-body wb-stack wb-gap-4">
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-3">
                                @if ($previewAsset->canPreview() && $previewAsset->url())
                                    <img src="{{ $previewAsset->url() }}" alt="{{ $previewAsset->thumbnailLabel() }}">
                                @else
                                    <div class="wb-empty">
                                        <i class="wb-icon {{ $previewAsset->previewIconClass() }} wb-icon-2xl" aria-hidden="true"></i>
                                        <div class="wb-empty-title">Preview unavailable</div>
                                        <div class="wb-empty-text">This media type does not have an inline viewer yet. You can still edit the metadata.</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <div class="wb-text-sm wb-text-muted">{{ $previewAsset->folder?->name ?? 'No folder' }}</div>
                            <div class="wb-action-group">
                                <a href="{{ route('admin.media.edit', ['media' => $previewAsset, 'return_url' => $currentReturnUrl]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit media" aria-label="Edit media"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
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
                    <h2 class="wb-drawer-title" id="media-usage-title">Media usage</h2>
                    <a href="{{ route('admin.media.index', $previewBaseQuery) }}" class="wb-drawer-close" aria-label="Close usage details"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></a>
                </div>
                <div class="wb-drawer-body wb-stack wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                        <strong>{{ $usageAsset->displayTitle() }}</strong>
                        <div class="wb-text-sm wb-text-muted">Used in {{ $usageAsset->resolvedUsages->count() }} location{{ $usageAsset->resolvedUsages->count() === 1 ? '' : 's' }}</div>
                    </div>

                    @if ($usageAsset->resolvedUsages->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">Unused media</div>
                            <div class="wb-empty-text">This media item is not referenced by protected CMS content right now.</div>
                        </div>
                    @else
                        <div class="wb-stack wb-gap-2 wb-media-usage-list">
                            @foreach ($usageAsset->resolvedUsages as $usageItem)
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body wb-stack wb-gap-1">
                                        <strong>{{ $usageItem['page_title'] ?: 'Shared content' }}</strong>
                                        <div class="wb-text-sm wb-text-muted">{{ $usageItem['context'] }} • {{ $usageItem['label'] }}</div>
                                        @if (! empty($usageItem['admin_url']))
                                            <a href="{{ $usageItem['admin_url'] }}" class="wb-btn wb-btn-secondary">Open usage</a>
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
                            <h2 class="wb-modal-title" id="media-upload-title">Upload Media</h2>
                            <span class="wb-text-sm wb-text-muted">Add a new file to the shared media library.</span>
                        </div>

                        <a href="{{ route('admin.media.index', $baseQuery) }}" class="wb-modal-close" aria-label="Close">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                        </a>
                    </div>

                    <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="wb-stack wb-gap-4">
                        @csrf
                        <input type="hidden" name="_media_modal" value="upload-asset">

                        <div class="wb-modal-body wb-stack wb-gap-4">
                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <label for="file">File</label>
                                    <input id="file" name="file" type="file" class="wb-input" required>
                                    <span>Accepted: images, videos, PDF, Office files, text, CSV, ZIP.</span>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="folder_id">Folder</label>
                                    <select id="folder_id" name="folder_id" class="wb-select">
                                        <option value="">No folder</option>
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
                                    <label for="title">Title</label>
                                    <input id="title" name="title" type="text" class="wb-input" value="{{ old('title') }}">
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="alt_text">Alt Text</label>
                                    <input id="alt_text" name="alt_text" type="text" class="wb-input" value="{{ old('alt_text') }}">
                                </div>
                            </div>

                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <label for="caption">Caption</label>
                                    <textarea id="caption" name="caption" class="wb-textarea" rows="3">{{ old('caption') }}</textarea>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" class="wb-textarea" rows="3">{{ old('description') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <x-webblocks-cms::admin.form-actions
                            :cancel-url="route('admin.media.index', $baseQuery)"
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
                            <h2 class="wb-modal-title" id="media-fetch-title">Fetch Remote Media</h2>
                            <span class="wb-text-sm wb-text-muted">Import a public file URL into the shared media library.</span>
                        </div>

                        <a href="{{ route('admin.media.index', $baseQuery) }}" class="wb-modal-close" aria-label="Close">
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
                                    <label for="source_url">Remote URL</label>
                                    <input id="source_url" name="source_url" type="url" class="wb-input" value="{{ old('source_url') }}" placeholder="https://example.com/image.jpg" required>
                                    <span>Only public HTTP or HTTPS files are fetched. Private network targets are blocked.</span>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="fetch_folder_id">Folder</label>
                                    <select id="fetch_folder_id" name="folder_id" class="wb-select">
                                        <option value="">No folder</option>
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
                                    <label for="fetch_title">Title</label>
                                    <input id="fetch_title" name="title" type="text" class="wb-input" value="{{ old('title') }}">
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="fetch_alt_text">Alt Text</label>
                                    <input id="fetch_alt_text" name="alt_text" type="text" class="wb-input" value="{{ old('alt_text') }}">
                                </div>
                            </div>

                            <div class="wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <label for="fetch_caption">Caption</label>
                                    <textarea id="fetch_caption" name="caption" class="wb-textarea" rows="3">{{ old('caption') }}</textarea>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="fetch_description">Description</label>
                                    <textarea id="fetch_description" name="description" class="wb-textarea" rows="3">{{ old('description') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <x-webblocks-cms::admin.form-actions
                            :cancel-url="route('admin.media.index', $baseQuery)"
                            submit-label="Fetch media"
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
                            <h2 class="wb-modal-title" id="media-folder-title">Create Folder</h2>
                            <span class="wb-text-sm wb-text-muted">Organize shared assets into compact folders.</span>
                        </div>

                        <a href="{{ route('admin.media.index', $baseQuery) }}" class="wb-modal-close" aria-label="Close">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                        </a>
                    </div>

                    <form method="POST" action="{{ route('admin.media.folders.store') }}" class="wb-stack wb-gap-4">
                        @csrf
                        <input type="hidden" name="_media_modal" value="new-folder">

                        <div class="wb-modal-body wb-stack wb-gap-4">
                            <div class="wb-grid wb-grid-3">
                                <div class="wb-stack wb-gap-1">
                                    <label for="folder_name">Name</label>
                                    <input id="folder_name" name="name" type="text" class="wb-input" value="{{ old('name') }}" required>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="folder_slug">Slug</label>
                                    <input id="folder_slug" name="slug" type="text" class="wb-input" value="{{ old('slug') }}">
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <label for="parent_id">Parent Folder</label>
                                    <select id="parent_id" name="parent_id" class="wb-select">
                                        <option value="">No parent</option>
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
