@php
    $pickerName = $name ?? 'asset-picker';
    $pickerMode = $mode ?? 'single';
    $pickerAccept = $accept ?? null;
    $pickerInputId = $inputId ?? $pickerName;
    $pickerFieldName = $fieldName ?? $pickerInputId;
    $pickerTitle = $title ?? 'Asset';
    $pickerSelectedAsset = $selectedAsset ?? null;
    $pickerSelectedAssets = collect($selectedAssets ?? [])->filter()->values();
    $pickerHasSelection = $pickerMode === 'multiple'
        ? $pickerSelectedAssets->isNotEmpty()
        : $pickerSelectedAsset !== null;
    $pickerButtonLabel = $buttonLabel ?? 'Choose from Media';
    $pickerReplaceLabel = $replaceLabel ?? 'Replace';
    $pickerClearLabel = $clearLabel ?? 'Remove';
@endphp

<div
    class="wb-stack wb-gap-2"
    data-wb-asset-picker-panel
    data-wb-picker-mode="{{ $pickerMode }}"
    data-wb-picker-name="{{ $pickerName }}"
    data-wb-picker-accept="{{ $pickerAccept ?? '' }}"
    data-wb-picker-field-name="{{ $pickerFieldName }}"
>
    @if ($pickerMode === 'multiple')
        <div class="wb-stack wb-gap-2" data-wb-picker-selected-list>
            @foreach ($pickerSelectedAssets as $asset)
                <input type="hidden" name="{{ $pickerFieldName }}[]" value="{{ $asset->id }}" data-wb-picker-selected-input>
            @endforeach
        </div>
    @else
        <input type="hidden" id="{{ $pickerInputId }}" name="{{ $pickerFieldName }}" value="{{ old($pickerFieldName, $pickerSelectedAsset?->id) }}" data-wb-picker-selected-input>
    @endif

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <div class="wb-stack wb-gap-1" data-wb-picker-summary>
                    @if ($pickerMode === 'multiple')
                        @if ($pickerSelectedAssets->isEmpty())
                            <strong>No assets selected</strong>
                            <div class="wb-text-sm wb-text-muted">Choose internal assets from the shared media library.</div>
                        @else
                            <strong>{{ $pickerSelectedAssets->count() }} assets selected</strong>
                            <div class="wb-text-sm wb-text-muted">{{ $pickerSelectedAssets->pluck('title')->filter()->implode(', ') ?: $pickerSelectedAssets->pluck('filename')->implode(', ') }}</div>
                        @endif
                    @elseif ($pickerSelectedAsset)
                        @if ($pickerSelectedAsset->canPreview())
                            <img src="{{ $pickerSelectedAsset->url() }}" alt="{{ $pickerSelectedAsset->alt_text ?: $pickerSelectedAsset->title ?: $pickerSelectedAsset->filename }}" width="96" height="64">
                        @endif
                        <strong>{{ $pickerSelectedAsset->title ?: $pickerSelectedAsset->filename }}</strong>
                        <div class="wb-text-sm wb-text-muted">{{ $pickerSelectedAsset->kind }} | {{ $pickerSelectedAsset->original_name }}</div>
                    @else
                        <strong>No asset selected</strong>
                        <div class="wb-text-sm wb-text-muted">Choose an internal asset from the shared media library.</div>
                    @endif
                </div>

                <div class="wb-cluster wb-cluster-2">
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-open>{{ $pickerHasSelection ? $pickerReplaceLabel : $pickerButtonLabel }}</button>
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-clear @disabled(! $pickerHasSelection)>{{ $pickerClearLabel }}</button>
                </div>
            </div>

            <div class="wb-grid wb-grid-3" data-wb-picker-preview-grid>
                @if ($pickerMode === 'multiple')
                    @foreach ($pickerSelectedAssets as $asset)
                        <div class="wb-card" data-wb-picker-preview data-wb-picker-preview-id="{{ $asset->id }}">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                @if ($asset->canPreview())
                                    <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text ?: $asset->title ?: $asset->filename }}" width="120" height="84">
                                @endif
                                <strong>{{ $asset->title ?: $asset->filename }}</strong>
                                <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-remove-preview data-asset-id="{{ $asset->id }}">Remove</button>
                            </div>
                        </div>
                    @endforeach
                @elseif ($pickerSelectedAsset)
                    <div class="wb-card" data-wb-picker-preview data-wb-picker-preview-id="{{ $pickerSelectedAsset->id }}">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            @if ($pickerSelectedAsset->canPreview())
                                <img src="{{ $pickerSelectedAsset->url() }}" alt="{{ $pickerSelectedAsset->alt_text ?: $pickerSelectedAsset->title ?: $pickerSelectedAsset->filename }}" width="120" height="84">
                            @endif
                            <strong>{{ $pickerSelectedAsset->title ?: $pickerSelectedAsset->filename }}</strong>
                            <div class="wb-text-sm wb-text-muted" data-wb-picker-preview-meta>{{ $pickerSelectedAsset->kind }} | {{ $pickerSelectedAsset->original_name }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="wb-card" data-wb-picker-panel hidden>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-grid wb-grid-3">
                <div class="wb-stack wb-gap-1">
                    <label for="{{ $pickerInputId }}_asset_search">Search</label>
                    <input id="{{ $pickerInputId }}_asset_search" type="text" class="wb-input" data-wb-picker-search placeholder="Search assets">
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="{{ $pickerInputId }}_asset_folder">Folder</label>
                    <select id="{{ $pickerInputId }}_asset_folder" class="wb-select" data-wb-picker-folder>
                        <option value="">All folders</option>
                        @foreach (($assetPickerFolders ?? collect()) as $folder)
                            <option value="{{ $folder->id }}">{{ $folder->name }} ({{ $folder->assets_count }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="{{ $pickerInputId }}_asset_kind">Kind</label>
                    <select id="{{ $pickerInputId }}_asset_kind" class="wb-select" data-wb-picker-kind>
                        <option value="">All kinds</option>
                        <option value="image" @selected($pickerAccept === 'image')>Image</option>
                        <option value="document" @selected($pickerAccept === 'document')>Document</option>
                        <option value="video">Video</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <div class="wb-grid wb-grid-3" data-wb-picker-grid>
                @foreach (($assetPickerAssets ?? collect()) as $asset)
                    @if (! $pickerAccept || $asset->kind === $pickerAccept)
                        @include('admin.media._asset-card', ['asset' => $asset, 'multi' => $pickerMode === 'multiple'])
                    @endif
                @endforeach
            </div>

            <div class="wb-empty" data-wb-picker-empty hidden>
                <div class="wb-empty-title">No matching assets</div>
                <div class="wb-empty-text">Adjust the search or folder filter to find an internal asset.</div>
            </div>

            @if (($inlineUpload ?? true) === true)
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body wb-stack wb-gap-2">
                        <strong>Upload to Library</strong>
                        <div class="wb-grid wb-grid-2">
                            <div class="wb-stack wb-gap-1">
                                <label for="{{ $pickerInputId }}_inline_upload">File</label>
                                <input id="{{ $pickerInputId }}_inline_upload" type="file" class="wb-input" data-wb-picker-upload-input>
                            </div>
                            <div class="wb-stack wb-gap-1">
                                <label for="{{ $pickerInputId }}_inline_upload_title">Title</label>
                                <input id="{{ $pickerInputId }}_inline_upload_title" type="text" class="wb-input" data-wb-picker-upload-title>
                            </div>
                        </div>
                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <span class="wb-text-sm wb-text-muted" data-wb-picker-upload-status>Select a file to upload it to the shared media library.</span>
                            <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-upload-submit>Upload</button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-close>Close Panel</button>
                @if ($pickerMode === 'multiple')
                    <button type="button" class="wb-btn wb-btn-primary" data-wb-picker-apply>Add Selected</button>
                @endif
            </div>
        </div>
    </div>
</div>
