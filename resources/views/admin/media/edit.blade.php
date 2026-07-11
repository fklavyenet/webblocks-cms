@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('media_index.'.$key, $adminLocale, $replace);
    $editTitle = $asset->displayTitle();
    $editPageTitle = $adminText('edit_title', ['title' => $editTitle]);
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
        $adminText('disk_meta', ['disk' => $asset->disk]),
    ])->filter()->implode(' · ');
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $editPageTitle, 'heading' => $adminText('media')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $editPageTitle,
        'description' => $adminText('edit_description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-gap-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('preview') }}</strong>
                    <a href="{{ $fileDetailsOpenUrl }}" class="wb-btn wb-btn-secondary wb-btn-sm" aria-haspopup="dialog">{{ $adminText('file_details') }}</a>
                </div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    @if ($asset->canPreview() && $publicUrl)
                        <img src="{{ $publicUrl }}" alt="{{ $asset->thumbnailLabel() }}">
                    @else
                        <div class="wb-empty wb-empty-sm">
                            <i class="wb-icon {{ $asset->previewIconClass() }} wb-icon-2xl" aria-hidden="true"></i>
                            <div class="wb-empty-title">{{ $adminText('preview_unavailable') }}</div>
                            <div class="wb-empty-text">{{ $adminText('edit_preview_unavailable_help') }}</div>
                        </div>
                    @endif
                    @if ($previewMeta !== '')
                        <div class="wb-text-sm wb-text-muted">{{ $previewMeta }}</div>
                    @endif
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header"><strong>{{ $adminText('usage') }}</strong></div>
                <div class="wb-card-body">
                    @if ($usages->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('unused_media') }}</div>
                            <div class="wb-empty-text">{{ $adminText('edit_unused_help') }}</div>
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
                                                <a href="{{ $usage['admin_url'] }}" class="wb-btn wb-btn-secondary">{{ $adminText('open') }}</a>
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
                    <strong>{{ $adminText('media_information') }}</strong>
                </div>
                <div class="wb-card-body wb-grid wb-grid-2 wb-gap-4">
                    <div class="wb-stack wb-gap-4">
                        <div class="wb-stack wb-gap-1">
                            <label for="title">{{ $adminText('title_field') }}</label>
                            <input id="title" name="title" type="text" class="wb-input" value="{{ old('title', $asset->title) }}">
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="alt_text">{{ $adminText('alt_text') }}</label>
                            <input id="alt_text" name="alt_text" type="text" class="wb-input" value="{{ old('alt_text', $asset->alt_text) }}">
                            <span class="wb-text-sm wb-text-muted">{{ $adminText('edit_alt_text_help') }}</span>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="folder_id">{{ $adminText('folder') }}</label>
                            <select id="folder_id" name="folder_id" class="wb-select">
                                <option value="">{{ $adminText('no_folder') }}</option>
                                @foreach ($folders as $folder)
                                    <option value="{{ $folder->id }}" @selected((string) old('folder_id', $asset->folder_id) === (string) $folder->id)>{{ $folder->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($asset->isImage() && $publicUrl && $focalPointReady)
                            <div class="wb-stack wb-gap-2" data-wb-focal-point>
                                <label>{{ $adminText('focal_point') }}</label>
                                <span class="wb-text-sm wb-text-muted">{{ $adminText('focal_point_help') }}</span>
                                <button type="button" class="wb-media-focal-picker" data-wb-focal-image aria-label="{{ $adminText('choose_focal_point') }}">
                                    <img src="{{ $asset->transformUrl('content') }}" alt="{{ $asset->thumbnailLabel() }}">
                                    <span class="wb-media-focal-marker" data-wb-focal-marker style="left: {{ old('focal_point_x', $asset->focal_point_x ?? 0.5) * 100 }}%; top: {{ old('focal_point_y', $asset->focal_point_y ?? 0.5) * 100 }}%;"></span>
                                </button>
                                <input type="hidden" name="focal_point_x" value="{{ old('focal_point_x', $asset->focal_point_x ?? 0.5) }}" data-wb-focal-x>
                                <input type="hidden" name="focal_point_y" value="{{ old('focal_point_y', $asset->focal_point_y ?? 0.5) }}" data-wb-focal-y>
                            </div>
                        @endif
                    </div>

                    <div class="wb-stack wb-gap-4">
                        <div class="wb-stack wb-gap-1">
                            <label for="caption">{{ $adminText('caption') }}</label>
                            <textarea id="caption" name="caption" class="wb-textarea" rows="4">{{ old('caption', $asset->caption) }}</textarea>
                            <span class="wb-text-sm wb-text-muted">{{ $adminText('edit_caption_help') }}</span>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="description">{{ $adminText('description_field') }}</label>
                            <textarea id="description" name="description" class="wb-textarea" rows="5">{{ old('description', $asset->description) }}</textarea>
                            <span class="wb-text-sm wb-text-muted">{{ $adminText('edit_description_help') }}</span>
                        </div>
                    </div>
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions :cancel-url="$mediaReturnUrl" :submit-label="$adminText('save_changes_lower')" />
                </div>
            </div>
        </form>

        @if ($asset->isImage())
            <section class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                    <div class="wb-stack wb-gap-1">
                        <strong>{{ $adminText('image_variants') }}</strong>
                        <span class="wb-text-sm wb-text-muted">{{ $adminText('image_variants_help') }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.media.transforms.regenerate', $asset) }}">
                        @csrf
                        <input type="hidden" name="return_url" value="{{ $mediaReturnUrl }}">
                        <button type="submit" class="wb-btn wb-btn-secondary">{{ $adminText('regenerate_variants') }}</button>
                    </form>
                </div>
                <div class="wb-card-body wb-grid wb-grid-auto wb-gap-3">
                    @foreach ($transformVariants as $variant)
                        <div class="wb-stack wb-gap-2">
                            <img src="{{ $variant['url'] }}" alt="{{ $asset->thumbnailLabel() }}" loading="lazy">
                            <strong>{{ ucfirst($variant['name']) }}</strong>
                            <span class="wb-text-sm wb-text-muted">{{ $variant['width'] }}@if($variant['height']) × {{ $variant['height'] }}@endif · {{ ucfirst($variant['fit']) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
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
                            <h2 class="wb-modal-title" id="{{ $fileDetailsModalTitleId }}">{{ $adminText('file_details') }}</h2>
                            <span class="wb-text-sm wb-text-muted" id="{{ $fileDetailsModalDescriptionId }}">{{ $adminText('file_details_description') }}</span>
                        </div>

                        <a href="{{ $fileDetailsCloseUrl }}" class="wb-modal-close" aria-label="{{ $adminText('close_file_details_modal') }}">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                        </a>
                    </div>

                    <div class="wb-modal-body wb-stack wb-gap-4 wb-text-sm">
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-header"><strong>{{ $adminText('file') }}</strong></div>
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <div><strong>{{ $adminText('filename_label') }}</strong> {{ $asset->filename }}</div>
                                <div><strong>{{ $adminText('original_name_label') }}</strong> {{ $asset->original_name }}</div>
                                <div><strong>{{ $adminText('mime_type_label') }}</strong> {{ $asset->mime_type ?? '-' }}</div>
                                <div><strong>{{ $adminText('extension_label') }}</strong> {{ $asset->extension ?? '-' }}</div>
                                <div><strong>{{ $adminText('size_label') }}</strong> {{ $asset->humanSize() }}</div>
                                <div><strong>{{ $adminText('kind_label') }}</strong> <span class="wb-status-pill wb-status-info">{{ ucfirst($asset->kind) }}</span></div>
                                <div><strong>{{ $adminText('disk_label') }}</strong> {{ $asset->disk }}</div>
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-header"><strong>{{ $adminText('image') }}</strong></div>
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <div><strong>{{ $adminText('dimensions_label') }}</strong> {{ $dimensions }}</div>
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-header"><strong>{{ $adminText('storage') }}</strong></div>
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <div class="wb-stack wb-gap-1">
                                    <strong>{{ $adminText('path') }}</strong>
                                    <code style="white-space: normal; word-break: break-word; display: block;">{{ $asset->path }}</code>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <div class="wb-cluster wb-gap-2 wb-flex-wrap">
                                        <strong>{{ $adminText('public_url') }}</strong>
                                        @if ($publicUrl)
                                            <button
                                                type="button"
                                                class="wb-btn wb-btn-ghost wb-btn-sm wb-btn-icon"
                                                data-wb-copy-url="{{ $publicUrl }}"
                                                aria-label="{{ $adminText('copy_public_url') }}"
                                                title="{{ $adminText('copy_public_url') }}"
                                            >
                                                <i class="wb-icon wb-icon-copy" aria-hidden="true"></i>
                                            </button>
                                        @endif
                                    </div>
                                    <code style="white-space: normal; word-break: break-word; display: block;">{{ $publicUrl ?: '-' }}</code>
                                </div>

                                <div><strong>{{ $adminText('created_label') }}</strong> {{ $asset->created_at?->format('Y-m-d H:i') ?? '-' }}</div>
                                <div><strong>{{ $adminText('updated_label') }}</strong> {{ $asset->updated_at?->format('Y-m-d H:i') ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                        <a href="{{ $fileDetailsCloseUrl }}" class="wb-btn wb-btn-secondary">{{ $adminText('close') }}</a>
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
                            <h2 class="wb-modal-title" id="{{ $deleteModalTitleId }}">{{ $adminText('delete_media') }}</h2>
                            <span class="wb-text-sm wb-text-muted" id="{{ $deleteModalDescriptionId }}">{{ $adminText('delete_media_description') }}</span>
                        </div>

                        <a href="{{ route('admin.media.edit', ['media' => ($media ?? $asset), 'return_url' => $mediaReturnUrl]) }}" class="wb-modal-close" aria-label="{{ $adminText('close_delete_media_modal') }}">
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
                            :delete-label="$adminText('delete_media')"
                            container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                        />
                    </form>
                </div>
            </div>
        </div>
    @endif
@endpush

@push('admin-scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/media-copy.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/media-focal-point.js'])
@endpush
