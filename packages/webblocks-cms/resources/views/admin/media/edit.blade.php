@extends('webblocks-cms::layouts.admin', ['title' => 'Edit Media: '.$asset->displayTitle(), 'heading' => 'Media'])

@php
    $editTitle = $asset->displayTitle();
    $publicUrl = $asset->url();
    $deleteModalId = 'media-delete-modal-'.$asset->id;
    $deleteModalTitleId = $deleteModalId.'Title';
    $deleteModalDescriptionId = $deleteModalId.'Description';
    $fileDetailsModalId = 'media-file-details-modal-'.$asset->id;
    $fileDetailsModalTitleId = $fileDetailsModalId.'Title';
    $fileDetailsModalDescriptionId = $fileDetailsModalId.'Description';
    $fileDetailsCloseUrl = route('admin.media.edit', ['media' => ($media ?? $asset), 'return_url' => $mediaReturnUrl]);
    $fileDetailsOpenUrl = route('admin.media.edit', ['media' => ($media ?? $asset), 'return_url' => $mediaReturnUrl, 'modal' => 'file-details']);
    $dimensions = $asset->width && $asset->height ? $asset->width.' x '.$asset->height : '-';
    $previewMeta = collect([
        trim(($asset->extension ? strtoupper($asset->extension).' ' : '').$asset->kind),
        $asset->humanSize(),
        $asset->disk.' disk',
    ])->filter()->implode(' · ');
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Edit Media: '.$editTitle,
        'description' => 'Review file details, update metadata, and manage this media item safely.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-gap-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                    <strong>Preview</strong>
                    <a href="{{ $fileDetailsOpenUrl }}" class="wb-btn wb-btn-secondary wb-btn-sm" aria-haspopup="dialog">File Details</a>
                </div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    @if ($asset->canPreview() && $publicUrl)
                        <img src="{{ $publicUrl }}" alt="{{ $asset->thumbnailLabel() }}">
                    @else
                        <div class="wb-empty wb-empty-sm">
                            <i class="wb-icon {{ $asset->previewIconClass() }} wb-icon-2xl" aria-hidden="true"></i>
                            <div class="wb-empty-title">Preview unavailable</div>
                            <div class="wb-empty-text">This media type does not have an inline preview in the current UI.</div>
                        </div>
                    @endif
                    @if ($previewMeta !== '')
                        <div class="wb-text-sm wb-text-muted">{{ $previewMeta }}</div>
                    @endif
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>Usage</strong></div>
                <div class="wb-card-body">
                    @if ($usages->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">Unused media</div>
                            <div class="wb-empty-text">This media item is not referenced by protected CMS consumers yet.</div>
                        </div>
                    @else
                        <div class="wb-stack wb-gap-2">
                            @foreach ($usages as $usage)
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body">
                                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                                            <div class="wb-stack wb-gap-1">
                                                <strong>{{ $usage['label'] }}</strong>
                                                <div class="wb-text-sm wb-text-muted">{{ $usage['type'] }} | {{ $usage['context'] }}@if($usage['page_title']) | {{ $usage['page_title'] }}@endif</div>
                                            </div>
                                            @if (! empty($usage['admin_url']))
                                                <a href="{{ $usage['admin_url'] }}" class="wb-btn wb-btn-secondary">Open</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.media.update', $media ?? $asset) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_url" value="{{ $mediaReturnUrl }}">

            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Media Information</strong>
                </div>
                <div class="wb-card-body wb-grid wb-grid-2 wb-gap-4">
                    <div class="wb-stack wb-gap-4">
                        <div class="wb-stack wb-gap-1">
                            <label for="title">Title</label>
                            <input id="title" name="title" type="text" class="wb-input" value="{{ old('title', $asset->title) }}">
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="alt_text">Alt Text</label>
                            <input id="alt_text" name="alt_text" type="text" class="wb-input" value="{{ old('alt_text', $asset->alt_text) }}">
                            <span class="wb-text-sm wb-text-muted">Accessibility text used when this image is rendered publicly.</span>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="folder_id">Folder</label>
                            <select id="folder_id" name="folder_id" class="wb-select">
                                <option value="">No folder</option>
                                @foreach ($folders as $folder)
                                    <option value="{{ $folder->id }}" @selected((string) old('folder_id', $asset->folder_id) === (string) $folder->id)>{{ $folder->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="wb-stack wb-gap-4">
                        <div class="wb-stack wb-gap-1">
                            <label for="caption">Caption</label>
                            <textarea id="caption" name="caption" class="wb-textarea" rows="4">{{ old('caption', $asset->caption) }}</textarea>
                            <span class="wb-text-sm wb-text-muted">Optional visible caption for contexts that support captions.</span>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="wb-textarea" rows="5">{{ old('description', $asset->description) }}</textarea>
                            <span class="wb-text-sm wb-text-muted">Internal notes or longer metadata.</span>
                        </div>
                    </div>
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions :cancel-url="$mediaReturnUrl" submit-label="Save changes" />
                </div>
            </div>
        </form>
    </div>

    <div class="wb-text-sm wb-text-muted wb-media-copy-feedback" data-wb-copy-feedback aria-live="polite"></div>
@endsection

@push('overlays')
    @if ($showFileDetailsModal)
        <div class="wb-overlay-layer wb-overlay-layer--dialog">
            <div class="wb-overlay-backdrop"></div>

            <div class="wb-modal wb-modal-lg is-open" id="{{ $fileDetailsModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $fileDetailsModalTitleId }}" aria-describedby="{{ $fileDetailsModalDescriptionId }}">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-header">
                        <div class="wb-stack wb-gap-1">
                            <h2 class="wb-modal-title" id="{{ $fileDetailsModalTitleId }}">File Details</h2>
                            <span class="wb-text-sm wb-text-muted" id="{{ $fileDetailsModalDescriptionId }}">Review read-only file, image, and storage details for this media item.</span>
                        </div>

                        <a href="{{ $fileDetailsCloseUrl }}" class="wb-modal-close" aria-label="Close file details modal">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                        </a>
                    </div>

                    <div class="wb-modal-body wb-stack wb-gap-4 wb-text-sm">
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-header"><strong>File</strong></div>
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <div><strong>Filename:</strong> {{ $asset->filename }}</div>
                                <div><strong>Original Name:</strong> {{ $asset->original_name }}</div>
                                <div><strong>MIME Type:</strong> {{ $asset->mime_type ?? '-' }}</div>
                                <div><strong>Extension:</strong> {{ $asset->extension ?? '-' }}</div>
                                <div><strong>Size:</strong> {{ $asset->humanSize() }}</div>
                                <div><strong>Kind:</strong> <span class="wb-status-pill wb-status-info">{{ ucfirst($asset->kind) }}</span></div>
                                <div><strong>Disk:</strong> {{ $asset->disk }}</div>
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-header"><strong>Image</strong></div>
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <div><strong>Dimensions:</strong> {{ $dimensions }}</div>
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-header"><strong>Storage</strong></div>
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <div class="wb-stack wb-gap-1">
                                    <strong>Path</strong>
                                    <code style="white-space: normal; word-break: break-word; display: block;">{{ $asset->path }}</code>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <div class="wb-cluster wb-gap-2 wb-flex-wrap">
                                        <strong>Public URL</strong>
                                        @if ($publicUrl)
                                            <button
                                                type="button"
                                                class="wb-btn wb-btn-ghost wb-btn-sm wb-btn-icon"
                                                data-wb-copy-url="{{ $publicUrl }}"
                                                aria-label="Copy public URL"
                                                title="Copy public URL"
                                            >
                                                <i class="wb-icon wb-icon-copy" aria-hidden="true"></i>
                                            </button>
                                        @endif
                                    </div>
                                    <code style="white-space: normal; word-break: break-word; display: block;">{{ $publicUrl ?: '-' }}</code>
                                </div>

                                <div><strong>Created:</strong> {{ $asset->created_at?->format('Y-m-d H:i') ?? '-' }}</div>
                                <div><strong>Updated:</strong> {{ $asset->updated_at?->format('Y-m-d H:i') ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                        <a href="{{ $fileDetailsCloseUrl }}" class="wb-btn wb-btn-secondary">Close</a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showDeleteModal && $usages->isEmpty())
        <div class="wb-overlay-layer wb-overlay-layer--dialog">
            <div class="wb-overlay-backdrop"></div>

            <div class="wb-modal wb-modal-lg is-open" id="{{ $deleteModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $deleteModalTitleId }}" aria-describedby="{{ $deleteModalDescriptionId }}">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-header">
                        <div class="wb-stack wb-gap-1">
                            <h2 class="wb-modal-title" id="{{ $deleteModalTitleId }}">Delete media</h2>
                            <span class="wb-text-sm wb-text-muted" id="{{ $deleteModalDescriptionId }}">Confirm whether this media item should be deleted permanently.</span>
                        </div>

                        <a href="{{ route('admin.media.edit', ['media' => ($media ?? $asset), 'return_url' => $mediaReturnUrl]) }}" class="wb-modal-close" aria-label="Close delete media modal">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                        </a>
                    </div>

                    <form method="POST" action="{{ route('admin.media.destroy', ['media' => ($media ?? $asset), 'return_url' => $mediaReturnUrl]) }}">
                        @csrf
                        @method('DELETE')

                        <div class="wb-modal-body wb-stack wb-gap-4">
                            <div class="wb-card wb-card-muted">
                                <div class="wb-card-body wb-stack wb-gap-2">
                                    <strong>{{ $editTitle }}</strong>
                                    <div class="wb-text-sm wb-text-muted">{{ $asset->original_name }}</div>
                                    <div class="wb-text-sm wb-text-muted"><code>{{ $asset->path }}</code></div>
                                </div>
                            </div>
                        </div>

                        <x-webblocks-cms::admin.form-actions
                            :cancel-url="route('admin.media.edit', ['media' => ($media ?? $asset), 'return_url' => $mediaReturnUrl])"
                            :show-submit="false"
                            :delete-submit="true"
                            delete-label="Delete media"
                            container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                        />
                    </form>
                </div>
            </div>
        </div>
    @endif
@endpush
