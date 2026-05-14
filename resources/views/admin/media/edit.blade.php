@extends('layouts.admin', ['title' => 'Edit Media: '.$asset->displayTitle(), 'heading' => 'Media'])

@php
    $editTitle = $asset->displayTitle();
    $publicUrl = $asset->url();
    $deleteModalId = 'media-delete-modal-'.$asset->id;
    $deleteModalTitleId = $deleteModalId.'Title';
    $deleteModalDescriptionId = $deleteModalId.'Description';
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Edit Media: '.$editTitle,
        'description' => 'Review file details, update metadata, and manage this media item safely.',
    ])

    @include('admin.partials.flash')

    <form method="POST" action="{{ route('admin.media.update', $media ?? $asset) }}" class="wb-stack wb-gap-4">
        @csrf
        @method('PUT')
        <input type="hidden" name="return_url" value="{{ $mediaReturnUrl }}">

        <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-4">
                <div class="wb-card">
                    <div class="wb-card-header"><strong>Preview</strong></div>
                    <div class="wb-card-body">
                        @if ($asset->canPreview() && $publicUrl)
                            <img src="{{ $publicUrl }}" alt="{{ $asset->thumbnailLabel() }}">
                        @else
                            <div class="wb-empty wb-empty-sm">
                                <i class="wb-icon {{ $asset->previewIconClass() }} wb-icon-2xl" aria-hidden="true"></i>
                                <div class="wb-empty-title">Preview unavailable</div>
                                <div class="wb-empty-text">This media type does not have an inline preview in the current UI.</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="wb-card">
                    <div class="wb-card-header"><strong>File Details</strong></div>
                    <div class="wb-card-body wb-stack wb-gap-3 wb-text-sm">
                        <div class="wb-grid wb-grid-2">
                            <div class="wb-stack wb-gap-2">
                                <div><strong>Filename:</strong> {{ $asset->filename }}</div>
                                <div><strong>Original Name:</strong> {{ $asset->original_name }}</div>
                                <div><strong>MIME Type:</strong> {{ $asset->mime_type ?? '-' }}</div>
                                <div><strong>Extension:</strong> {{ $asset->extension ?? '-' }}</div>
                                <div><strong>Size:</strong> {{ $asset->humanSize() }}</div>
                                <div><strong>Kind:</strong> <span class="wb-status-pill wb-status-info">{{ ucfirst($asset->kind) }}</span></div>
                                <div><strong>Disk:</strong> {{ $asset->disk }}</div>
                            </div>

                            <div class="wb-stack wb-gap-2">
                                <div><strong>Dimensions:</strong> {{ $asset->width && $asset->height ? $asset->width.' x '.$asset->height : '-' }}</div>
                                <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                                    <span><strong>Path:</strong> <code>{{ $asset->path }}</code></span>
                                    @if ($publicUrl)
                                        <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-copy-url="{{ $publicUrl }}">Copy public URL</button>
                                    @endif
                                </div>
                                @if ($publicUrl)
                                    <div><strong>Public URL:</strong> <code>{{ $publicUrl }}</code></div>
                                @endif
                                <div><strong>Created:</strong> {{ $asset->created_at?->format('Y-m-d H:i') ?? '-' }}</div>
                                <div><strong>Updated:</strong> {{ $asset->updated_at?->format('Y-m-d H:i') ?? '-' }}</div>
                            </div>
                        </div>
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

            <div class="wb-stack wb-gap-4">
                <div class="wb-card">
                    <div class="wb-card-header"><strong>Metadata</strong></div>
                    <div class="wb-card-body wb-stack wb-gap-4">
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

                <div class="wb-card">
                    <div class="wb-card-header"><strong>Organization</strong></div>
                    <div class="wb-card-body wb-stack wb-gap-4">
                        <div class="wb-stack wb-gap-1">
                            <label for="folder_id">Folder</label>
                            <select id="folder_id" name="folder_id" class="wb-select">
                                <option value="">No folder</option>
                                @foreach ($folders as $folder)
                                    <option value="{{ $folder->id }}" @selected((string) old('folder_id', $asset->folder_id) === (string) $folder->id)>{{ $folder->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <span class="wb-text-sm wb-text-muted">Kind</span>
                            <div><span class="wb-status-pill wb-status-info">{{ ucfirst($asset->kind) }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="wb-card">
                    <div class="wb-card-header"><strong>Danger Zone</strong></div>
                    <div class="wb-card-body wb-stack wb-gap-3">
                        @if ($usages->isNotEmpty())
                            <div class="wb-text-sm wb-text-muted">Delete is blocked because this media item is still used by protected CMS consumers.</div>
                            <button type="button" class="wb-btn wb-btn-danger" disabled>Delete media</button>
                        @else
                            <div class="wb-text-sm wb-text-muted">Delete this media item only when you are sure it is no longer needed.</div>
                            <a href="{{ route('admin.media.edit', ['media' => ($media ?? $asset), 'return_url' => $mediaReturnUrl, 'modal' => 'delete-media']) }}" class="wb-btn wb-btn-danger" aria-haspopup="dialog">Delete media</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="$mediaReturnUrl" submit-label="Save changes" />
            </div>
        </div>
    </form>

    <div class="wb-text-sm wb-text-muted wb-media-copy-feedback" data-wb-copy-feedback aria-live="polite"></div>
@endsection

@push('overlays')
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

                        <x-admin.form-actions
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
                            feedback.textContent = 'Public URL copied.';
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
