@extends('layouts.admin', ['title' => 'Media', 'heading' => 'Media'])

@php
    use App\Models\Asset;

    $assetCount = $assets->total();
    $showUploadModal = $openModal === 'upload-asset';
    $showFolderModal = $openModal === 'new-folder';
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Media',
        'description' => 'Review and organize the shared media library from one compact screen.',
        'count' => $assetCount,
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-cluster wb-cluster-2">
                <a href="{{ route('admin.media.index', array_filter(['folder_id' => $selectedFolderId, 'search' => $search ?: null, 'modal' => 'upload-asset'])) }}" class="wb-btn wb-btn-primary">Upload Asset</a>
                <a href="{{ route('admin.media.index', array_filter(['folder_id' => $selectedFolderId, 'search' => $search ?: null, 'modal' => 'new-folder'])) }}" class="wb-btn wb-btn-secondary">New Folder</a>
            </div>

            <form method="GET" action="{{ route('admin.media.index') }}" class="wb-cluster wb-cluster-2">
                @if ($selectedFolderId)
                    <input type="hidden" name="folder_id" value="{{ $selectedFolderId }}">
                @endif

                <input name="search" type="text" class="wb-input" value="{{ $search }}" placeholder="Search assets">
                <button type="submit" class="wb-btn wb-btn-secondary">Search</button>
                @if ($selectedFolderId || $search !== '')
                    <a href="{{ route('admin.media.index') }}" class="wb-btn wb-btn-secondary">Reset</a>
                @endif
            </form>
        </div>

        <div class="wb-card-body wb-stack wb-gap-4">
            <div class="wb-cluster wb-cluster-2">
                <a href="{{ route('admin.media.index', array_filter(['search' => $search ?: null])) }}" class="wb-btn {{ $selectedFolderId ? 'wb-btn-secondary' : 'wb-btn-primary' }}">All Assets ({{ $assetCount }})</a>
                @foreach ($folders as $folder)
                    <a href="{{ route('admin.media.index', array_filter(['folder_id' => $folder->id, 'search' => $search ?: null])) }}" class="wb-btn {{ (string) $selectedFolderId === (string) $folder->id ? 'wb-btn-primary' : 'wb-btn-secondary' }}">
                        {{ $folder->name }} ({{ $folder->assets_count }})
                    </a>
                @endforeach
            </div>

            @if ($assets->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">No assets yet</div>
                    <div class="wb-empty-text">Upload the first image, video, or document to start the media library.</div>
                    <div class="wb-cluster wb-cluster-2">
                        <a href="{{ route('admin.media.index', ['modal' => 'upload-asset']) }}" class="wb-btn wb-btn-primary">Upload Asset</a>
                        <a href="{{ route('admin.media.index', ['modal' => 'new-folder']) }}" class="wb-btn wb-btn-secondary">New Folder</a>
                    </div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Preview</th>
                                <th>Name</th>
                                <th>Kind</th>
                                <th>Folder</th>
                                <th>Usage</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assets as $asset)
                                <tr>
                                    <td>
                                        @if ($asset->isImage())
                                            <a href="{{ route('admin.media.show', $asset) }}" class="wb-no-decoration">
                                                <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text ?: $asset->title ?: $asset->filename }}" width="72" height="48">
                                            </a>
                                        @else
                                            <a href="{{ route('admin.media.show', $asset) }}" class="wb-action-btn wb-no-decoration" aria-label="View asset">
                                                <i class="wb-icon {{ $asset->kind === Asset::KIND_DOCUMENT ? 'wb-icon-file-text' : 'wb-icon-file' }}"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong><a href="{{ route('admin.media.show', $asset) }}">{{ $asset->title ?: $asset->filename }}</a></strong>
                                            <span class="wb-text-sm wb-text-muted">{{ $asset->original_name }}</span>
                                            <span class="wb-text-sm wb-text-muted">{{ $asset->mime_type ?? '-' }} | {{ $asset->humanSize() }}</span>
                                        </div>
                                    </td>
                                    <td><span class="wb-status-pill wb-status-info">{{ $asset->kind }}</span></td>
                                    <td>{{ $asset->folder?->name ?? '-' }}</td>
                                    <td>
                                        <span class="wb-status-pill {{ $asset->isUsed() ? 'wb-status-pending' : 'wb-status-info' }}">
                                            {{ $asset->isUsed() ? 'Used ('.$asset->usageCount().')' : 'Unused' }}
                                        </span>
                                    </td>
                                    <td>{{ $asset->created_at?->format('Y-m-d') }}</td>
                                    <td>
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.media.show', $asset) }}" class="wb-action-btn wb-action-btn-view" title="View asset" aria-label="View asset">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>
                                            <a href="{{ route('admin.media.edit', $asset) }}" class="wb-action-btn wb-action-btn-edit" title="Edit asset" aria-label="Edit asset">
                                                <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.media.destroy', $asset) }}" onsubmit="return confirm('Delete this asset?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete asset" aria-label="Delete asset" @disabled($asset->isUsed())>
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('admin.partials.pagination', ['paginator' => $assets])
            @endif
        </div>
    </div>
@endsection

@push('overlays')
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

                        <a href="{{ route('admin.media.index', array_filter(['folder_id' => $selectedFolderId, 'search' => $search ?: null])) }}" class="wb-modal-close" aria-label="Close">
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
                            <a href="{{ route('admin.media.index', array_filter(['folder_id' => $selectedFolderId, 'search' => $search ?: null])) }}" class="wb-btn wb-btn-secondary">Cancel</a>
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

                        <a href="{{ route('admin.media.index', array_filter(['folder_id' => $selectedFolderId, 'search' => $search ?: null])) }}" class="wb-modal-close" aria-label="Close">
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
                            <a href="{{ route('admin.media.index', array_filter(['folder_id' => $selectedFolderId, 'search' => $search ?: null])) }}" class="wb-btn wb-btn-secondary">Cancel</a>
                            <button type="submit" class="wb-btn wb-btn-primary">Create Folder</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endpush
