@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $assetPickerAdminLocale = app(AdminLocaleResolver::class)->locale();
  $assetPickerTranslator = app(CmsTranslator::class);
  $assetPickerText = static fn (string $key, array $replace = []) => $assetPickerTranslator->admin('media_asset_picker.'.$key, $assetPickerAdminLocale, $replace);
  $pickerName = $name ?? 'asset-picker';
  $pickerMode = $mode ?? 'single';
  $pickerAccept = $accept ?? null;
  $pickerInputId = $inputId ?? $pickerName;
  $pickerFieldName = $fieldName ?? $pickerInputId;
  $pickerTitle = $title ?? $assetPickerText('asset');
  $pickerSelectedAsset = $selectedAsset ?? null;
  $pickerSelectedAssets = collect($selectedAssets ?? [])->filter()->values();
  $pickerHasSelection = $pickerMode === 'multiple'
    ? $pickerSelectedAssets->isNotEmpty()
    : $pickerSelectedAsset !== null;
  $pickerButtonLabel = $buttonLabel ?? $assetPickerText('choose_from_media');
  $pickerReplaceLabel = $replaceLabel ?? $assetPickerText('replace');
  $pickerClearLabel = $clearLabel ?? $assetPickerText('remove');
  $pickerCompactControls = (bool) ($compactControls ?? false);
  $pickerPanelMode = $panelMode ?? 'inline';
  $pickerBlock = $block ?? null;
  $pickerInstanceKey = $instanceKey
    ?? ($pickerInputId === $pickerFieldName
      ? $pickerInputId
      : $pickerName.'-'.$pickerInputId.'-'.str_replace('.', '-', uniqid('', true)));
  $pickerInstanceId = preg_replace('/[^A-Za-z0-9_-]+/', '-', $pickerInstanceKey);
  $pickerPanelId = $pickerInstanceId === $pickerInputId
    ? $pickerInputId.'_picker_panel'
    : $pickerInputId.'_'.$pickerInstanceId.'_picker_panel';
  $pickerPanelTitleId = $pickerPanelId.'_title';
  $pickerPanelTitle = $panelTitle ?? ($pickerMode === 'multiple' ? $assetPickerText('choose_assets') : $assetPickerText('choose_asset'));
  $pickerControlsClass = $controlsClass ?? 'wb-cluster wb-cluster-2';
  $pickerOverlayOwnerId = 'wb-picker-owner-'.$pickerInstanceId;
  $pickerResultsVariant = $resultsVariant ?? 'card';
  $pickerShowPreviewGrid = (bool) ($showPreviewGrid ?? ($pickerMode === 'multiple'));
  $pickerRenderPreviewGrid = (bool) ($renderPreviewGrid ?? ($pickerMode === 'multiple' || ! $pickerCompactControls));
  $pickerShowSummary = (bool) ($showSummary ?? true);
  $pickerShowUpload = (bool) ($showUpload ?? ($inlineUpload ?? true));
  $pickerSelectorCard = (bool) ($selectorCard ?? false);
  $pickerSelectorCardTitle = $selectorCardTitle ?? null;
  $pickerSelectorHelperText = $selectorHelperText ?? null;
  $pickerUsesDetachedFilterRegion = $pickerPanelMode === 'overlay'
    && $pickerResultsVariant === 'compact-list'
    && $pickerMode === 'multiple';
  $pickerAcceptedKinds = ['image', 'document', 'video', 'other', 'audio', 'file'];
  $pickerInitialKind = in_array($pickerAccept, $pickerAcceptedKinds, true) ? $pickerAccept : '';
  $pickerMatchesAccept = static function ($asset, string $accept): bool {
    $mimeType = strtolower((string) ($asset->mime_type ?? ''));

    return match ($accept) {
      'image' => $asset->kind === \WebBlocks\Cms\Models\Media::KIND_IMAGE,
      'document' => $asset->kind === \WebBlocks\Cms\Models\Media::KIND_DOCUMENT,
      'video' => $asset->kind === \WebBlocks\Cms\Models\Media::KIND_VIDEO,
      'other' => $asset->kind === \WebBlocks\Cms\Models\Media::KIND_OTHER,
      'audio' => $asset->kind === \WebBlocks\Cms\Models\Media::KIND_OTHER && str_starts_with($mimeType, 'audio/'),
      'file' => in_array($asset->kind, [\WebBlocks\Cms\Models\Media::KIND_DOCUMENT, \WebBlocks\Cms\Models\Media::KIND_OTHER], true),
      default => true,
    };
  };
  $pickerKindOptions = collect([
    '' => $assetPickerText('all_kinds'),
    'image' => $assetPickerText('image'),
    'document' => $assetPickerText('document'),
    'video' => $assetPickerText('video'),
    'other' => $assetPickerText('other'),
  ]);

  if ($pickerAccept === 'audio') {
    $pickerKindOptions->put('audio', $assetPickerText('audio'));
  }

  if ($pickerAccept === 'file') {
    $pickerKindOptions->put('file', $assetPickerText('file'));
  }

  $pickerVisibleAssets = collect($assetPickerAssets ?? [])
    ->filter(fn ($asset) => $pickerInitialKind === '' || $pickerMatchesAccept($asset, $pickerInitialKind))
    ->values();
  $pickerHasVisibleAssets = $pickerVisibleAssets->isNotEmpty();
  $pickerEmptyTitle = match ($pickerInitialKind) {
    'image' => $assetPickerText('empty_images_title'),
    'audio' => $assetPickerText('empty_audio_title'),
    'file' => $assetPickerText('empty_files_title'),
    default => $assetPickerText('empty_assets_title'),
  };
  $pickerEmptyText = match ($pickerInitialKind) {
    'image' => $assetPickerText($pickerShowUpload ? 'empty_images_upload_text' : 'empty_images_media_text'),
    'audio' => $assetPickerText($pickerShowUpload ? 'empty_audio_upload_text' : 'empty_audio_media_text'),
    'file' => $assetPickerText($pickerShowUpload ? 'empty_files_upload_text' : 'empty_files_media_text'),
    default => $assetPickerText('empty_assets_text'),
  };
@endphp

@once
  @push('admin-scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/asset-picker.js'])
  @endpush
@endonce

<div
  class="wb-stack wb-gap-2"
  id="{{ $pickerOverlayOwnerId }}"
  data-wb-asset-picker-panel
  data-wb-picker-mode="{{ $pickerMode }}"
  data-wb-picker-name="{{ $pickerName }}"
  data-wb-picker-accept="{{ $pickerAccept ?? '' }}"
  data-wb-picker-field-name="{{ $pickerFieldName }}"
  data-wb-picker-button-label="{{ $pickerButtonLabel }}"
  data-wb-picker-replace-label="{{ $pickerReplaceLabel }}"
  data-wb-picker-panel-mode="{{ $pickerPanelMode }}"
  data-wb-picker-owner-id="{{ $pickerOverlayOwnerId }}"
  data-wb-picker-results-variant="{{ $pickerResultsVariant }}"
>
  @if ($pickerMode === 'multiple')
    <div class="wb-stack wb-gap-2" data-wb-picker-selected-list>
      @foreach ($pickerSelectedAssets as $asset)
        <input type="hidden" name="{{ $pickerFieldName }}[]" value="{{ $asset->id }}" data-wb-picker-selected-input>
      @endforeach
    </div>
  @else
    <input
      type="hidden"
      id="{{ $pickerInputId }}"
      name="{{ $pickerFieldName }}"
      value="{{ old($pickerFieldName, $pickerSelectedAsset?->id) }}"
      data-wb-picker-selected-input
      data-wb-picker-selected-title="{{ $pickerSelectedAsset?->title ?? '' }}"
      data-wb-picker-selected-filename="{{ $pickerSelectedAsset?->filename ?? '' }}"
      data-wb-picker-selected-original-name="{{ $pickerSelectedAsset?->original_name ?? '' }}"
      data-wb-picker-selected-kind="{{ $pickerSelectedAsset?->kind ?? '' }}"
      data-wb-picker-selected-url="{{ $pickerSelectedAsset?->url() ?? '' }}"
      data-wb-picker-selected-alt="{{ $pickerSelectedAsset?->alt_text ?: $pickerSelectedAsset?->title ?: $pickerSelectedAsset?->filename ?? '' }}"
      data-wb-picker-selected-previewable="{{ $pickerSelectedAsset?->canPreview() ? 'true' : 'false' }}"
    >
  @endif

  @if ($pickerCompactControls)
    @if ($pickerSelectorCard)
      <div class="wb-card wb-card-muted" data-wb-picker-selector-card>
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
          @if ($pickerSelectorCardTitle)
            <strong data-wb-picker-selector-card-title>{{ $pickerSelectorCardTitle }}</strong>
          @else
            <span></span>
          @endif

          <div class="wb-action-group" data-wb-picker-selector-card-actions>
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-open aria-expanded="false" aria-controls="{{ $pickerPanelId }}">{{ $pickerHasSelection ? $pickerReplaceLabel : $pickerButtonLabel }}</button>
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-clear @disabled(! $pickerHasSelection)>{{ $pickerClearLabel }}</button>
          </div>
        </div>

        <div class="wb-card-body wb-stack wb-gap-3">
    @else
      <div class="{{ $pickerControlsClass }}">
        <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-open aria-expanded="false" aria-controls="{{ $pickerPanelId }}">{{ $pickerHasSelection ? $pickerReplaceLabel : $pickerButtonLabel }}</button>
        <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-clear @disabled(! $pickerHasSelection)>{{ $pickerClearLabel }}</button>
      </div>
    @endif

    @if ($pickerShowSummary)
      <div class="wb-stack wb-gap-1" data-wb-picker-summary>
        @if ($pickerMode === 'multiple')
          @if ($pickerSelectedAssets->isEmpty())
            <strong>{{ $assetPickerText('no_assets_selected') }}</strong>
            <div class="wb-text-sm wb-text-muted">{{ $assetPickerText('choose_internal_assets') }}</div>
          @else
            <strong>{{ $assetPickerText($pickerSelectedAssets->count() === 1 ? 'asset_selected_singular' : 'asset_selected_plural', ['count' => $pickerSelectedAssets->count()]) }}</strong>
            <div class="wb-text-sm wb-text-muted">{{ $pickerSelectedAssets->pluck('title')->filter()->implode(', ') ?: $pickerSelectedAssets->pluck('filename')->implode(', ') }}</div>
          @endif
        @elseif ($pickerSelectedAsset)
          @if ($pickerSelectedAsset->canPreview())
            <img src="{{ $pickerSelectedAsset->url() }}" alt="{{ $pickerSelectedAsset->alt_text ?: $pickerSelectedAsset->title ?: $pickerSelectedAsset->filename }}" width="96" height="64">
          @endif
          <strong>{{ $pickerSelectedAsset->title ?: $pickerSelectedAsset->filename }}</strong>
          <div class="wb-text-sm wb-text-muted">{{ $pickerSelectedAsset->kind }} | {{ $pickerSelectedAsset->original_name }}</div>
        @else
          <strong>{{ $assetPickerText('no_asset_selected') }}</strong>
          <div class="wb-text-sm wb-text-muted">{{ $assetPickerText('choose_internal_asset') }}</div>
        @endif
      </div>
    @endif

    @if ($pickerRenderPreviewGrid)
      <div class="wb-grid wb-grid-3" data-wb-picker-preview-grid @if (! $pickerShowPreviewGrid) hidden @endif>
        @if ($pickerMode === 'multiple')
          @foreach ($pickerSelectedAssets as $asset)
            <div class="wb-card" data-wb-picker-preview data-wb-picker-preview-id="{{ $asset->id }}">
              <div class="wb-card-body wb-stack wb-gap-2">
                @if ($asset->canPreview())
                  <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text ?: $asset->title ?: $asset->filename }}" width="120" height="84">
                @endif
                <strong>{{ $asset->title ?: $asset->filename }}</strong>
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-remove-preview data-asset-id="{{ $asset->id }}">{{ $assetPickerText('remove') }}</button>
              </div>
            </div>
          @endforeach
        @elseif ($pickerSelectedAsset && $pickerShowPreviewGrid)
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
    @endif

    @if ($pickerSelectorHelperText)
      <div class="wb-text-sm wb-text-muted" data-wb-picker-selector-help>{{ $pickerSelectorHelperText }}</div>
    @endif

    @if ($pickerSelectorCard)
        </div>
      </div>
    @endif
  @else
    <div class="wb-card wb-card-muted">
      <div class="wb-card-body wb-stack wb-gap-3">
        <div class="wb-cluster wb-cluster-between wb-cluster-2">
          <div class="wb-stack wb-gap-1" data-wb-picker-summary>
            @if ($pickerMode === 'multiple')
              @if ($pickerSelectedAssets->isEmpty())
                <strong>{{ $assetPickerText('no_assets_selected') }}</strong>
                <div class="wb-text-sm wb-text-muted">{{ $assetPickerText('choose_internal_assets') }}</div>
              @else
                <strong>{{ $assetPickerText($pickerSelectedAssets->count() === 1 ? 'asset_selected_singular' : 'asset_selected_plural', ['count' => $pickerSelectedAssets->count()]) }}</strong>
                <div class="wb-text-sm wb-text-muted">{{ $pickerSelectedAssets->pluck('title')->filter()->implode(', ') ?: $pickerSelectedAssets->pluck('filename')->implode(', ') }}</div>
              @endif
            @elseif ($pickerSelectedAsset)
              @if ($pickerSelectedAsset->canPreview())
                <img src="{{ $pickerSelectedAsset->url() }}" alt="{{ $pickerSelectedAsset->alt_text ?: $pickerSelectedAsset->title ?: $pickerSelectedAsset->filename }}" width="96" height="64">
              @endif
              <strong>{{ $pickerSelectedAsset->title ?: $pickerSelectedAsset->filename }}</strong>
              <div class="wb-text-sm wb-text-muted">{{ $pickerSelectedAsset->kind }} | {{ $pickerSelectedAsset->original_name }}</div>
            @else
              <strong>{{ $assetPickerText('no_asset_selected') }}</strong>
              <div class="wb-text-sm wb-text-muted">{{ $assetPickerText('choose_internal_asset') }}</div>
            @endif
          </div>

          <div class="wb-cluster wb-cluster-2">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-open aria-expanded="false" aria-controls="{{ $pickerPanelId }}">{{ $pickerHasSelection ? $pickerReplaceLabel : $pickerButtonLabel }}</button>
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-clear @disabled(! $pickerHasSelection)>{{ $pickerClearLabel }}</button>
          </div>
        </div>

        <div class="wb-grid wb-grid-3" data-wb-picker-preview-grid @if (! $pickerShowPreviewGrid) hidden @endif>
          @if ($pickerMode === 'multiple')
            @foreach ($pickerSelectedAssets as $asset)
              <div class="wb-card" data-wb-picker-preview data-wb-picker-preview-id="{{ $asset->id }}">
                <div class="wb-card-body wb-stack wb-gap-2">
                  @if ($asset->canPreview())
                    <img src="{{ $asset->url() }}" alt="{{ $asset->alt_text ?: $asset->title ?: $asset->filename }}" width="120" height="84">
                  @endif
                  <strong>{{ $asset->title ?: $asset->filename }}</strong>
                  <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-remove-preview data-asset-id="{{ $asset->id }}">{{ $assetPickerText('remove') }}</button>
                </div>
              </div>
            @endforeach
          @elseif ($pickerSelectedAsset && $pickerShowPreviewGrid)
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
  @endif

  @if ($pickerPanelMode === 'overlay')
    @push('overlays')
      <div class="wb-modal wb-modal-lg wb-gallery-picker-modal" id="{{ $pickerPanelId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $pickerPanelTitleId }}" data-wb-picker-panel data-wb-picker-panel-mode="{{ $pickerPanelMode }}" data-wb-picker-owner-id="{{ $pickerOverlayOwnerId }}" hidden>
        <div class="wb-modal-dialog wb-gallery-picker-dialog">
          <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="{{ $pickerPanelTitleId }}">{{ $pickerPanelTitle }}</h2>

            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" data-wb-picker-close aria-label="{{ $assetPickerText('close') }}">
              <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
            </button>
          </div>

          @if ($pickerUsesDetachedFilterRegion)
            <div class="wb-gallery-picker-filter-region">
              <div class="wb-card wb-card-muted" data-wb-picker-filters-card>
                <div class="wb-card-body wb-stack wb-gap-2">
                  <div class="wb-grid wb-grid-3" data-wb-picker-filters>
                    <div class="wb-stack wb-gap-1">
                      <label for="{{ $pickerInputId }}_asset_search">{{ $assetPickerText('search') }}</label>
                      <input id="{{ $pickerInputId }}_asset_search" type="text" class="wb-input" data-wb-picker-search placeholder="{{ $assetPickerText('search_assets') }}">
                    </div>

                    <div class="wb-stack wb-gap-1">
                      <label for="{{ $pickerInputId }}_asset_folder">{{ $assetPickerText('folder') }}</label>
                      <select id="{{ $pickerInputId }}_asset_folder" class="wb-select" data-wb-picker-folder>
                        <option value="">{{ $assetPickerText('all_folders') }}</option>
                        @foreach (($assetPickerFolders ?? collect()) as $folder)
                          <option value="{{ $folder->id }}">{{ $folder->name }} ({{ $folder->assets_count }})</option>
                        @endforeach
                      </select>
                    </div>

                    <div class="wb-stack wb-gap-1">
                      <label for="{{ $pickerInputId }}_asset_kind">{{ $assetPickerText('kind') }}</label>
                      <select id="{{ $pickerInputId }}_asset_kind" class="wb-select" data-wb-picker-kind>
                        @foreach ($pickerKindOptions as $value => $label)
                          <option value="{{ $value }}" @selected($pickerInitialKind === $value)>{{ $label }}</option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          @endif

          <div class="wb-modal-body wb-stack wb-gap-3">
            @unless ($pickerUsesDetachedFilterRegion)
              <div class="wb-card wb-card-muted" data-wb-picker-filters-card>
                <div class="wb-card-body wb-stack wb-gap-2">
                  <div class="wb-grid wb-grid-3" data-wb-picker-filters>
                    <div class="wb-stack wb-gap-1">
                      <label for="{{ $pickerInputId }}_asset_search">{{ $assetPickerText('search') }}</label>
                      <input id="{{ $pickerInputId }}_asset_search" type="text" class="wb-input" data-wb-picker-search placeholder="{{ $assetPickerText('search_assets') }}">
                    </div>

                    <div class="wb-stack wb-gap-1">
                      <label for="{{ $pickerInputId }}_asset_folder">{{ $assetPickerText('folder') }}</label>
                      <select id="{{ $pickerInputId }}_asset_folder" class="wb-select" data-wb-picker-folder>
                        <option value="">{{ $assetPickerText('all_folders') }}</option>
                        @foreach (($assetPickerFolders ?? collect()) as $folder)
                          <option value="{{ $folder->id }}">{{ $folder->name }} ({{ $folder->assets_count }})</option>
                        @endforeach
                      </select>
                    </div>

                    <div class="wb-stack wb-gap-1">
                      <label for="{{ $pickerInputId }}_asset_kind">{{ $assetPickerText('kind') }}</label>
                      <select id="{{ $pickerInputId }}_asset_kind" class="wb-select" data-wb-picker-kind>
                        @foreach ($pickerKindOptions as $value => $label)
                          <option value="{{ $value }}" @selected($pickerInitialKind === $value)>{{ $label }}</option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            @endunless

            <div class="{{ $pickerResultsVariant === 'compact-list' ? 'wb-stack wb-gap-2 wb-picker-results wb-picker-results--compact' : 'wb-grid wb-grid-3 wb-picker-results' }}" data-wb-picker-grid>
              @foreach ($pickerVisibleAssets as $asset)
                @include('webblocks-cms::admin.media._asset-card', ['asset' => $asset, 'multi' => $pickerMode === 'multiple', 'pickerVariant' => $pickerResultsVariant])
              @endforeach
            </div>

            <div class="wb-empty" data-wb-picker-empty @if ($pickerHasVisibleAssets) hidden @endif>
              <div class="wb-empty-title">{{ $pickerEmptyTitle }}</div>
              <div class="wb-empty-text">{{ $pickerEmptyText }}</div>
            </div>

            <div class="wb-empty" data-wb-picker-error hidden>
              <div class="wb-empty-title">{{ $assetPickerText('unable_to_load_media') }}</div>
              <div class="wb-empty-text" data-wb-picker-error-text>{{ $assetPickerText('close_picker_try_again') }}</div>
            </div>

            @if ($pickerShowUpload)
              <div class="wb-card wb-card-muted">
                <div class="wb-card-body wb-stack wb-gap-2">
                  <strong>{{ $assetPickerText('upload_to_library') }}</strong>
                  <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-1">
                      <label for="{{ $pickerInputId }}_inline_upload">{{ $assetPickerText('file') }}</label>
                      <input id="{{ $pickerInputId }}_inline_upload" type="file" class="wb-input" data-wb-picker-upload-input>
                    </div>
                    <div class="wb-stack wb-gap-1">
                      <label for="{{ $pickerInputId }}_inline_upload_title">{{ $assetPickerText('title') }}</label>
                      <input id="{{ $pickerInputId }}_inline_upload_title" type="text" class="wb-input" data-wb-picker-upload-title>
                    </div>
                  </div>
                  <div class="wb-cluster wb-cluster-between wb-cluster-2">
                    <span class="wb-text-sm wb-text-muted" data-wb-picker-upload-status>{{ $assetPickerText('select_file_to_upload') }}</span>
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-upload-submit>{{ $assetPickerText('upload') }}</button>
                  </div>
                </div>
              </div>
            @endif
          </div>

          <div class="wb-modal-footer wb-flex wb-justify-between wb-gap-2">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal" data-wb-picker-close>{{ $assetPickerText('close') }}</button>
            @if ($pickerMode === 'multiple')
              <button type="button" class="wb-btn wb-btn-primary" data-wb-picker-apply>{{ $assetPickerText('add_selected') }}</button>
            @endif
          </div>
        </div>
      </div>
    @endpush
  @else
    <div class="wb-card" id="{{ $pickerPanelId }}" data-wb-picker-panel data-wb-picker-panel-mode="{{ $pickerPanelMode }}" hidden>
      <div class="wb-card-body wb-stack wb-gap-3">
        <div class="wb-card wb-card-muted" data-wb-picker-filters-card>
          <div class="wb-card-body wb-stack wb-gap-2">
            <div class="wb-grid wb-grid-3" data-wb-picker-filters>
              <div class="wb-stack wb-gap-1">
                <label for="{{ $pickerInputId }}_asset_search">{{ $assetPickerText('search') }}</label>
                <input id="{{ $pickerInputId }}_asset_search" type="text" class="wb-input" data-wb-picker-search placeholder="{{ $assetPickerText('search_assets') }}">
              </div>

              <div class="wb-stack wb-gap-1">
                <label for="{{ $pickerInputId }}_asset_folder">{{ $assetPickerText('folder') }}</label>
                <select id="{{ $pickerInputId }}_asset_folder" class="wb-select" data-wb-picker-folder>
                  <option value="">{{ $assetPickerText('all_folders') }}</option>
                  @foreach (($assetPickerFolders ?? collect()) as $folder)
                    <option value="{{ $folder->id }}">{{ $folder->name }} ({{ $folder->assets_count }})</option>
                  @endforeach
                </select>
              </div>

              <div class="wb-stack wb-gap-1">
                <label for="{{ $pickerInputId }}_asset_kind">{{ $assetPickerText('kind') }}</label>
                <select id="{{ $pickerInputId }}_asset_kind" class="wb-select" data-wb-picker-kind>
                  @foreach ($pickerKindOptions as $value => $label)
                    <option value="{{ $value }}" @selected($pickerInitialKind === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="{{ $pickerResultsVariant === 'compact-list' ? 'wb-stack wb-gap-2 wb-picker-results wb-picker-results--compact' : 'wb-grid wb-grid-3 wb-picker-results' }}" data-wb-picker-grid>
          @foreach ($pickerVisibleAssets as $asset)
            @include('webblocks-cms::admin.media._asset-card', ['asset' => $asset, 'multi' => $pickerMode === 'multiple', 'pickerVariant' => $pickerResultsVariant])
            @endforeach
        </div>

        <div class="wb-empty" data-wb-picker-empty @if ($pickerHasVisibleAssets) hidden @endif>
          <div class="wb-empty-title">{{ $pickerEmptyTitle }}</div>
          <div class="wb-empty-text">{{ $pickerEmptyText }}</div>
        </div>

        <div class="wb-empty" data-wb-picker-error hidden>
          <div class="wb-empty-title">{{ $assetPickerText('unable_to_load_media') }}</div>
          <div class="wb-empty-text" data-wb-picker-error-text>{{ $assetPickerText('close_picker_try_again') }}</div>
        </div>

        @if ($pickerShowUpload)
          <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-2">
              <strong>{{ $assetPickerText('upload_to_library') }}</strong>
              <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                  <label for="{{ $pickerInputId }}_inline_upload">{{ $assetPickerText('file') }}</label>
                  <input id="{{ $pickerInputId }}_inline_upload" type="file" class="wb-input" data-wb-picker-upload-input>
                </div>
                <div class="wb-stack wb-gap-1">
                  <label for="{{ $pickerInputId }}_inline_upload_title">{{ $assetPickerText('title') }}</label>
                  <input id="{{ $pickerInputId }}_inline_upload_title" type="text" class="wb-input" data-wb-picker-upload-title>
                </div>
              </div>
              <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <span class="wb-text-sm wb-text-muted" data-wb-picker-upload-status>{{ $assetPickerText('select_file_to_upload') }}</span>
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-upload-submit>{{ $assetPickerText('upload') }}</button>
              </div>
            </div>
          </div>
        @endif

        <div class="wb-cluster wb-cluster-between wb-cluster-2">
          <button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-close>{{ $assetPickerText('close_panel') }}</button>
          @if ($pickerMode === 'multiple')
            <button type="button" class="wb-btn wb-btn-primary" data-wb-picker-apply>{{ $assetPickerText('add_selected') }}</button>
          @endif
        </div>
      </div>
    </div>
  @endif
</div>
