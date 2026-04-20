@extends('layouts.admin', ['title' => 'Media', 'heading' => 'Media'])

@php
    use App\Models\Asset;

    $assetCount = $assets->total();
    $showUploadModal = $openModal === 'upload-asset';
    $showFolderModal = $openModal === 'new-folder';
    $baseQuery = array_filter([
        'folder_id' => $selectedFolderId,
        'search' => $search ?: null,
        'kind' => $kind ?: null,
        'usage' => $usage ?: null,
        'view' => $viewMode !== 'list' ? $viewMode : null,
    ]);
    $previewBaseQuery = array_merge($baseQuery, ['page' => $assets->currentPage() > 1 ? $assets->currentPage() : null]);
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Media',
        'description' => 'Review, filter, preview, and manage the shared media library from one compact screen.',
        'count' => $assetCount,
    ])

    @include('admin.partials.flash')

    @push('styles')
        <style>
            .wb-media-toolbar {
                align-items: end;
            }

            .wb-media-folder-pills {
                gap: var(--wb-s2);
            }

            .wb-media-folder-pill {
                border-radius: 9999px;
            }

            .wb-media-view-toggle .wb-btn[aria-current="page"] {
                background: var(--wb-accent);
                border-color: var(--wb-accent);
                color: var(--wb-accent-contrast, #fff);
            }

            .wb-media-preview-box {
                width: 88px;
                height: 64px;
                border-radius: var(--wb-radius-lg);
                background: var(--wb-surface-2);
                border: 1px solid color-mix(in srgb, var(--wb-border) 78%, transparent);
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--wb-text-muted);
            }

            .wb-media-preview-box img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .wb-media-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: var(--wb-s4);
            }

            .wb-media-grid-card .wb-card-body {
                padding: var(--wb-s3);
            }

            .wb-media-grid-preview {
                width: 100%;
                aspect-ratio: 16 / 10;
                border-radius: var(--wb-radius-lg);
                background: var(--wb-surface-2);
                border: 1px solid color-mix(in srgb, var(--wb-border) 78%, transparent);
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: var(--wb-s3);
                color: var(--wb-text-muted);
            }

            .wb-media-grid-preview img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .wb-media-grid-actions {
                justify-content: space-between;
            }

            .wb-media-copy-feedback {
                min-height: 1.25rem;
            }

            .wb-media-usage-list {
                max-height: 18rem;
                overflow: auto;
            }

            .wb-media-table .wb-action-group,
            .wb-media-grid .wb-action-group {
                flex-wrap: nowrap;
            }
        </style>
    @endpush

    <div class="wb-card">
        <div class="wb-card-body wb-stack wb-gap-4">
            <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-media-toolbar">
                <div class="wb-cluster wb-cluster-2">
                    <a href="{{ route('admin.media.index', array_merge($baseQuery, ['modal' => 'upload-asset'])) }}" class="wb-btn wb-btn-primary">Upload Asset</a>
                    <a href="{{ route('admin.media.index', array_merge($baseQuery, ['modal' => 'new-folder'])) }}" class="wb-btn wb-btn-secondary">New Folder</a>
                </div>

                <div class="wb-cluster wb-cluster-2 wb-media-view-toggle">
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

            <form method="GET" action="{{ route('admin.media.index') }}" class="wb-cluster wb-cluster-between wb-cluster-2">
                <div class="wb-cluster wb-cluster-2">
                    <div class="wb-stack wb-gap-1">
                        <label for="media_search">Search</label>
                        <input id="media_search" name="search" type="text" class="wb-input" value="{{ $search }}" placeholder="Search title, filename, alt text, or caption">
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="media_kind">Kind</label>
                        <select id="media_kind" name="kind" class="wb-select">
                            <option value="">All kinds</option>
                            <option value="image" @selected($kind === Asset::KIND_IMAGE)>Images</option>
                            <option value="video" @selected($kind === Asset::KIND_VIDEO)>Videos</option>
                            <option value="document" @selected($kind === Asset::KIND_DOCUMENT)>Documents</option>
                            <option value="other" @selected($kind === Asset::KIND_OTHER)>Other</option>
                        </select>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="media_usage">Usage</label>
                        <select id="media_usage" name="usage" class="wb-select">
                            <option value="">All assets</option>
                            <option value="used" @selected($usage === 'used')>Used</option>
                            <option value="unused" @selected($usage === 'unused')>Unused</option>
                        </select>
                    </div>
                </div>

                <div class="wb-cluster wb-cluster-2" style="align-self: end;">
                    @if ($selectedFolderId)
                        <input type="hidden" name="folder_id" value="{{ $selectedFolderId }}">
                    @endif
                    <input type="hidden" name="view" value="{{ $viewMode }}">
                    <button type="submit" class="wb-btn wb-btn-primary">Apply</button>
                    @if ($selectedFolderId || $search !== '' || $kind !== '' || $usage !== '' || $viewMode !== 'list')
                        <a href="{{ route('admin.media.index') }}" class="wb-btn wb-btn-secondary">Reset</a>
                    @endif
                </div>
            </form>

            <div class="wb-cluster wb-cluster-2 wb-media-folder-pills">
                <a href="{{ route('admin.media.index', array_filter(['search' => $search ?: null, 'kind' => $kind ?: null, 'usage' => $usage ?: null, 'view' => $viewMode !== 'list' ? $viewMode : null])) }}" class="wb-btn wb-media-folder-pill {{ $selectedFolderId ? 'wb-btn-secondary' : 'wb-btn-primary' }}">All folders <span class="wb-text-sm">{{ $assetCount }}</span></a>
                @foreach ($folders as $folder)
                    <a href="{{ route('admin.media.index', array_filter(['folder_id' => $folder->id, 'search' => $search ?: null, 'kind' => $kind ?: null, 'usage' => $usage ?: null, 'view' => $viewMode !== 'list' ? $viewMode : null])) }}" class="wb-btn wb-media-folder-pill {{ (string) $selectedFolderId === (string) $folder->id ? 'wb-btn-primary' : 'wb-btn-secondary' }}">
                        {{ $folder->name }} <span class="wb-text-sm">{{ $folder->assets_count }}</span>
                    </a>
                @endforeach
            </div>

            @if ($assets->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">No assets found</div>
                    <div class="wb-empty-text">Adjust the filters or upload the next file into the shared media library.</div>
                    <div class="wb-cluster wb-cluster-2">
                        <a href="{{ route('admin.media.index', ['modal' => 'upload-asset']) }}" class="wb-btn wb-btn-primary">Upload Asset</a>
                        <a href="{{ route('admin.media.index') }}" class="wb-btn wb-btn-secondary">Reset filters</a>
                    </div>
                </div>
            @elseif ($viewMode === 'grid')
                <div class="wb-media-grid">
                    @foreach ($assets as $asset)
                        @php($assetUsages = $asset->resolvedUsages)
                        <div class="wb-card wb-card-muted wb-media-grid-card">
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-media-grid-preview wb-no-decoration" title="Preview asset">
                                    @if ($asset->canPreview() && $asset->url())
                                        <img src="{{ $asset->url() }}" alt="{{ $asset->thumbnailLabel() }}">
                                    @else
                                        <i class="wb-icon {{ $asset->previewIconClass() }} wb-icon-2xl" aria-hidden="true"></i>
                                    @endif
                                </a>

                                <div class="wb-stack wb-gap-1">
                                    <strong><a href="{{ route('admin.media.show', $asset) }}">{{ $asset->displayTitle() }}</a></strong>
                                    <div class="wb-text-sm wb-text-muted" title="{{ $asset->original_name }}">{{ $asset->original_name }}</div>
                                    <div class="wb-text-sm wb-text-muted">{{ $asset->compactMetaLabel() }}</div>
                                    <div class="wb-text-sm wb-text-muted">{{ $asset->folder?->name ?? 'No folder' }}</div>
                                </div>

                                <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                    @if ($assetUsages->isNotEmpty())
                                        <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['usage_asset' => $asset->id])) }}" class="wb-status-pill wb-status-pending">Used in {{ $assetUsages->count() }}</a>
                                    @else
                                        <span class="wb-status-pill wb-status-info">Unused</span>
                                    @endif

                                    <span class="wb-status-pill wb-status-info">{{ ucfirst($asset->kind) }}</span>
                                </div>

                                <div class="wb-action-group wb-media-grid-actions">
                                    <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-action-btn wb-action-btn-view" title="Preview asset" aria-label="Preview asset"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a>
                                    <a href="{{ route('admin.media.edit', $asset) }}" class="wb-action-btn wb-action-btn-edit" title="Edit asset" aria-label="Edit asset"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                    @if ($asset->url())
                                        <button type="button" class="wb-action-btn" data-wb-copy-url="{{ $asset->url() }}" title="Copy asset URL" aria-label="Copy asset URL"><i class="wb-icon wb-icon-copy" aria-hidden="true"></i></button>
                                    @endif
                                    <form method="POST" action="{{ route('admin.media.destroy', $asset) }}" onsubmit="return confirm('Delete this asset?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete asset" aria-label="Delete asset" @disabled($assetUsages->isNotEmpty())><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                    </form>
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
                                <th>Preview</th>
                                <th>Asset</th>
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
                                        <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-media-preview-box wb-no-decoration" title="Preview asset">
                                            @if ($asset->canPreview() && $asset->url())
                                                <img src="{{ $asset->url() }}" alt="{{ $asset->thumbnailLabel() }}">
                                            @else
                                                <i class="wb-icon {{ $asset->previewIconClass() }} wb-icon-xl" aria-hidden="true"></i>
                                            @endif
                                        </a>
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong><a href="{{ route('admin.media.show', $asset) }}">{{ $asset->displayTitle() }}</a></strong>
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
                                            <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['usage_asset' => $asset->id])) }}" class="wb-status-pill wb-status-pending">Used in {{ $assetUsages->count() }}</a>
                                        @else
                                            <span class="wb-status-pill wb-status-info">Unused</span>
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
                                            <a href="{{ route('admin.media.index', array_merge($previewBaseQuery, ['preview' => $asset->id])) }}" class="wb-action-btn wb-action-btn-view" title="Preview asset" aria-label="Preview asset"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a>
                                            <a href="{{ route('admin.media.edit', $asset) }}" class="wb-action-btn wb-action-btn-edit" title="Edit asset" aria-label="Edit asset"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                            @if ($asset->url())
                                                <button type="button" class="wb-action-btn" data-wb-copy-url="{{ $asset->url() }}" title="Copy asset URL" aria-label="Copy asset URL"><i class="wb-icon wb-icon-copy" aria-hidden="true"></i></button>
                                            @endif
                                            <form method="POST" action="{{ route('admin.media.destroy', $asset) }}" onsubmit="return confirm('Delete this asset?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete asset" aria-label="Delete asset" @disabled($assetUsages->isNotEmpty())><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <div class="wb-text-sm wb-text-muted wb-media-copy-feedback" data-wb-copy-feedback aria-live="polite"></div>
                @include('admin.partials.pagination', ['paginator' => $assets])
            </div>
        </div>
    </div>
@endsection

@push('overlays')
    @if ($previewAsset)
        <div class="wb-overlay-layer wb-overlay-layer--dialog">
            <div class="wb-overlay-backdrop"></div>
            <div class="wb-modal wb-modal-xl is-open" id="media-preview-modal" role="dialog" aria-modal="true" aria-labelledby="media-preview-title">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-header">
                        <div class="wb-stack wb-gap-1">
                            <h2 class="wb-modal-title" id="media-preview-title">{{ $previewAsset->displayTitle() }}</h2>
                            <span class="wb-text-sm wb-text-muted">{{ $previewAsset->compactMetaLabel() }}</span>
                        </div>
                        <a href="{{ route('admin.media.index', $previewBaseQuery) }}" class="wb-modal-close" aria-label="Close"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></a>
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
                                        <div class="wb-empty-text">This asset type does not have an inline viewer yet. You can still copy its public URL or edit the metadata.</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <div class="wb-text-sm wb-text-muted">{{ $previewAsset->folder?->name ?? 'No folder' }}</div>
                            <div class="wb-action-group">
                                @if ($previewAsset->url())
                                    <button type="button" class="wb-action-btn" data-wb-copy-url="{{ $previewAsset->url() }}" title="Copy asset URL" aria-label="Copy asset URL"><i class="wb-icon wb-icon-copy" aria-hidden="true"></i></button>
                                @endif
                                <a href="{{ route('admin.media.show', $previewAsset) }}" class="wb-action-btn wb-action-btn-view" title="Open details" aria-label="Open details"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a>
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
                    <h2 class="wb-drawer-title" id="media-usage-title">Asset usage</h2>
                    <a href="{{ route('admin.media.index', $previewBaseQuery) }}" class="wb-drawer-close" aria-label="Close usage details"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></a>
                </div>
                <div class="wb-drawer-body wb-stack wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                        <strong>{{ $usageAsset->displayTitle() }}</strong>
                        <div class="wb-text-sm wb-text-muted">Used in {{ $usageAsset->resolvedUsages->count() }} location{{ $usageAsset->resolvedUsages->count() === 1 ? '' : 's' }}</div>
                    </div>

                    @if ($usageAsset->resolvedUsages->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">Unused asset</div>
                            <div class="wb-empty-text">This asset is not referenced by protected CMS content right now.</div>
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
                            <h2 class="wb-modal-title" id="media-upload-title">Upload Asset</h2>
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

                        <div class="wb-modal-footer">
                            <a href="{{ route('admin.media.index', $baseQuery) }}" class="wb-btn wb-btn-secondary">Cancel</a>
                            <button type="submit" class="wb-btn wb-btn-primary">Upload Asset</button>
                        </div>
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

                        <div class="wb-modal-footer">
                            <a href="{{ route('admin.media.index', $baseQuery) }}" class="wb-btn wb-btn-secondary">Cancel</a>
                            <button type="submit" class="wb-btn wb-btn-primary">Create Folder</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endpush

@push('scripts')
    <script>
        (function () {
            var feedback = document.querySelector('[data-wb-copy-feedback]');

            document.querySelectorAll('[data-wb-copy-url]').forEach(function (button) {
                button.addEventListener('click', async function () {
                    var url = button.getAttribute('data-wb-copy-url');

                    if (!url) {
                        return;
                    }

                    try {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            await navigator.clipboard.writeText(url);
                        } else {
                            var helper = document.createElement('input');
                            helper.value = url;
                            document.body.appendChild(helper);
                            helper.select();
                            document.execCommand('copy');
                            document.body.removeChild(helper);
                        }

                        if (feedback) {
                            feedback.textContent = 'Asset URL copied.';
                            window.clearTimeout(window.__wbMediaCopyTimer || 0);
                            window.__wbMediaCopyTimer = window.setTimeout(function () {
                                feedback.textContent = '';
                            }, 1600);
                        }
                    } catch (error) {
                        if (feedback) {
                            feedback.textContent = 'Copy failed.';
                        }
                    }
                });
            });
        })();
    </script>
@endpush
