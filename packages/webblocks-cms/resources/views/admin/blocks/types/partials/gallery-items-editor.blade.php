@php
  $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
  $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
  $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.blocks.partials.gallery_items_editor.'.$key, $adminLocale, $replace);
  $activeLocale = $activeLocale ?? null;
  $isDefaultLocale = $isDefaultLocale ?? (! $activeLocale || $activeLocale->is_default);
  $galleryMediaById = ($selectedGalleryAssets ?? collect())
    ->keyBy('id');
  $galleryItemRows = collect(old($galleryItemsOldKey ?? 'gallery_items', []));

  if ($galleryItemRows->isEmpty()) {
    $galleryItemRows = ($block->galleryItems() ?? collect())
      ->map(function ($item, $index) use ($activeLocale) {
        $translation = $item->galleryItemTranslations
          ->when($activeLocale !== null, fn ($translations) => $translations->where('locale_id', $activeLocale->id))
          ->sortBy('locale_id')
          ->first();

        return [
          'media_id' => $item->media_id,
          'sort_order' => $index,
          'alt_text' => $translation?->alt_text,
          'caption' => $translation?->caption,
          'overlay_title' => $translation?->overlay_title,
          'overlay_text' => $translation?->overlay_text,
        ];
      });
  }

  $galleryItemRows = $galleryItemRows
    ->map(function ($item, $index) use ($galleryMediaById) {
      $mediaId = (int) ($item['media_id'] ?? 0);
      $asset = $galleryMediaById->get($mediaId);

      if (! $asset) {
        $asset = \WebBlocks\Cms\Models\Media::query()->find($mediaId);
      }

      if (! $asset) {
        return null;
      }

      return [
        'media_id' => $mediaId,
        'sort_order' => (int) ($item['sort_order'] ?? $index),
        'alt_text' => trim((string) ($item['alt_text'] ?? '')),
        'caption' => trim((string) ($item['caption'] ?? '')),
        'overlay_title' => trim((string) ($item['overlay_title'] ?? '')),
        'overlay_text' => trim((string) ($item['overlay_text'] ?? '')),
        'asset' => $asset,
      ];
    })
    ->filter()
    ->sortBy('sort_order')
    ->values();

  $rootPrefix = $inputPrefix ?? null;
  $rowFieldName = static function (int $index, string $field) use ($rootPrefix): string {
    return $rootPrefix
      ? "{$rootPrefix}[gallery_items][{$index}][{$field}]"
      : "gallery_items[{$index}][{$field}]";
  };
  $modalIdPrefix = $modalIdPrefix ?? 'gallery-item-editor';
  $modalIdValue = static function (int $mediaId) use ($rootPrefix): string {
    $prefix = $rootPrefix ?: 'gallery';
    $sanitizedPrefix = preg_replace('/[^A-Za-z0-9_-]+/', '-', $prefix) ?: 'gallery';

    return 'gallery-item-modal-'.$sanitizedPrefix.'-'.$mediaId;
  };
  $renderOwnCard = (bool) ($renderOwnCard ?? false);
@endphp

<div class="wb-stack wb-gap-4" data-wb-gallery-items-editor data-wb-gallery-field-prefix="{{ $rootPrefix ?? '' }}">
  @if ($renderOwnCard)
    <div class="wb-card wb-card-accent">
      <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
          <strong>{{ $adminText('gallery_items') }}</strong>
          <span class="wb-status-pill wb-status-info" data-wb-gallery-items-count>{{ $adminText('items_count', ['count' => $galleryItemRows->count()]) }}</span>
        </div>

        @include('webblocks-cms::admin.media.asset-picker-panel', [
          'name' => $rootPrefix ? str_replace(['[', ']'], ['-', ''], $rootPrefix).'-gallery-assets' : 'gallery-assets',
          'mode' => 'multiple',
          'inputId' => $rootPrefix ? str_replace(['[', ']'], ['-', ''], $rootPrefix).'-gallery-media-ids' : 'gallery_media_ids',
          'fieldName' => $rootPrefix ? $rootPrefix.'[gallery_media_ids]' : 'gallery_media_ids',
          'selectedAssets' => $galleryItemRows->pluck('asset'),
          'buttonLabel' => $adminText('add_gallery_items'),
          'replaceLabel' => $adminText('add_gallery_items'),
          'clearLabel' => $adminText('remove_all'),
          'accept' => 'image',
                     'compactControls' => true,
                     'panelMode' => 'overlay',
                     'panelTitle' => $adminText('add_gallery_items'),
                     'resultsVariant' => 'compact-list',
                     'showSummary' => false,
                     'showPreviewGrid' => false,
                     'renderPreviewGrid' => false,
                     'showUpload' => false,
                     'controlsClass' => 'wb-card-actions wb-cluster wb-cluster-2 wb-flex-wrap wb-justify-end',
                   ])
      </div>

      <div class="wb-card-body">
        <div class="wb-stack wb-gap-2">
          <span class="wb-text-sm wb-text-muted">{{ $adminText('description') }}</span>

          <div class="wb-card wb-card-muted" data-wb-gallery-items-empty @if (! $galleryItemRows->isEmpty()) hidden @endif>
            <div class="wb-card-body wb-text-sm wb-text-muted">{{ $adminText('empty') }}</div>
          </div>

          <div class="wb-table-wrap" data-admin-sortable-list data-wb-gallery-items-table @if ($galleryItemRows->isEmpty()) hidden @endif>
            <table class="wb-table wb-table-striped wb-table-hover">
              <thead>
                <tr>
                  <th>{{ $adminText('order') }}</th>
                  <th>{{ $adminText('preview') }}</th>
                  <th>{{ $adminText('item') }}</th>
                  <th>{{ $adminText('summaries') }}</th>
                  <th>{{ $adminText('actions') }}</th>
                </tr>
              </thead>
              <tbody data-wb-gallery-items-list>
                @foreach ($galleryItemRows as $galleryIndex => $item)
                  @php
                    $asset = $item['asset'];
                    $itemLabel = $asset->title ?: $asset->filename;
                    $altSummary = $item['alt_text'] !== '' ? $item['alt_text'] : ($asset->alt_text ?: $adminText('no_alt_text'));
                    $captionSummary = $item['caption'] !== '' ? $item['caption'] : $adminText('no_caption');
                    $overlaySummary = $item['overlay_title'] !== ''
                      ? $item['overlay_title']
                      : ($item['overlay_text'] !== '' ? $item['overlay_text'] : $adminText('no_overlay_title'));
                    $modalId = $modalIdValue((int) $asset->id);
                  @endphp
                  <tr data-admin-sortable-item draggable="true" data-wb-gallery-item-row data-media-id="{{ $asset->id }}">
                    <td>
                      <div class="wb-cluster wb-cluster-2">
                        <button type="button" class="wb-action-btn" data-admin-sortable-handle title="{{ $adminText('drag_to_reorder') }}" aria-label="{{ $adminText('drag_to_reorder') }}">
                          <span aria-hidden="true">::</span>
                        </button>
                        <span>{{ $galleryIndex + 1 }}</span>
                      </div>
                      <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'sort_order') }}" value="{{ $galleryIndex }}" data-admin-sortable-order data-wb-gallery-field="sort_order">
                      <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'media_id') }}" value="{{ $asset->id }}" data-wb-gallery-field="media_id">
                      <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'alt_text') }}" value="{{ $item['alt_text'] }}" data-wb-gallery-field="alt_text">
                      <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'caption') }}" value="{{ $item['caption'] }}" data-wb-gallery-field="caption">
                      <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'overlay_title') }}" value="{{ $item['overlay_title'] }}" data-wb-gallery-field="overlay_title">
                      <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'overlay_text') }}" value="{{ $item['overlay_text'] }}" data-wb-gallery-field="overlay_text">
                    </td>
                    <td>
                      @if ($asset->canPreview())
                        <img src="{{ $asset->url() }}" alt="{{ $asset->thumbnailLabel() }}" width="72" height="48">
                      @else
                        <span class="wb-text-sm wb-text-muted">{{ $adminText('no_preview') }}</span>
                      @endif
                    </td>
                    <td>
                      <strong>{{ $itemLabel }}</strong>
                      <div class="wb-text-sm wb-text-muted">{{ $asset->compactMetaLabel() }}</div>
                    </td>
                    <td>
                      <div class="wb-text-sm"><strong>{{ $adminText('alt_summary') }}</strong> <span data-wb-gallery-alt-summary>{{ $altSummary }}</span></div>
                      <div class="wb-text-sm"><strong>{{ $adminText('caption_summary') }}</strong> <span data-wb-gallery-caption-summary>{{ $captionSummary }}</span></div>
                      <div class="wb-text-sm"><strong>{{ $adminText('overlay_summary') }}</strong> <span data-wb-gallery-overlay-summary>{{ $overlaySummary }}</span></div>
                    </td>
                    <td>
                      <div class="wb-action-group">
                        <button type="button" class="wb-action-btn wb-action-btn-edit" data-wb-toggle="modal" data-wb-target="#{{ $modalId }}" data-wb-gallery-edit-item title="{{ $adminText('edit_item_metadata') }}" aria-label="{{ $adminText('edit_item_metadata') }}">
                          <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-gallery-item-remove data-asset-id="{{ $asset->id }}" title="{{ $adminText('remove_item') }}" aria-label="{{ $adminText('remove_item') }}">
                          <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                        </button>
                      </div>
                    </td>
                  </tr>

                  @push('overlays')
                    <div class="wb-modal wb-modal-lg" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title" data-wb-gallery-item-modal data-media-id="{{ $asset->id }}">
                      <div class="wb-modal-dialog">
                        <div class="wb-modal-header">
                          <div class="wb-stack wb-gap-1">
                            <h2 class="wb-modal-title" id="{{ $modalId }}-title">{{ $adminText('edit_gallery_item', ['item' => $itemLabel]) }}</h2>
                            <span class="wb-text-sm wb-text-muted">{{ $adminText('per_item_locale_help') }}</span>
                          </div>

                          <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('close') }}">
                            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                          </button>
                        </div>

                        <div class="wb-modal-body wb-stack wb-gap-3">
                          <div class="wb-grid wb-grid-2">
                            <div class="wb-stack wb-gap-1">
                              <label for="{{ $modalId }}-alt-text">{{ $adminText('alt_text') }}</label>
                              <input id="{{ $modalId }}-alt-text" class="wb-input" type="text" value="{{ $item['alt_text'] }}" data-wb-gallery-modal-field="alt_text">
                            </div>

                            <div class="wb-stack wb-gap-1">
                              <label for="{{ $modalId }}-caption">{{ $adminText('caption') }}</label>
                              <input id="{{ $modalId }}-caption" class="wb-input" type="text" value="{{ $item['caption'] }}" data-wb-gallery-modal-field="caption">
                            </div>
                          </div>

                          <div class="wb-stack wb-gap-1">
                            <label for="{{ $modalId }}-overlay-title">{{ $adminText('overlay_title') }}</label>
                            <input id="{{ $modalId }}-overlay-title" class="wb-input" type="text" value="{{ $item['overlay_title'] }}" data-wb-gallery-modal-field="overlay_title">
                          </div>

                          <div class="wb-stack wb-gap-1">
                            <label for="{{ $modalId }}-overlay-text">{{ $adminText('overlay_text') }}</label>
                            <textarea id="{{ $modalId }}-overlay-text" class="wb-textarea" rows="4" data-wb-gallery-modal-field="overlay_text">{{ $item['overlay_text'] }}</textarea>
                          </div>

                          <div class="wb-text-sm wb-text-muted">
                            {{ $adminText('deferred_fields_help') }}
                          </div>
                        </div>

                        <div class="wb-modal-footer wb-flex wb-justify-end wb-gap-2">
                          <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('done') }}</button>
                        </div>
                      </div>
                    </div>
                  @endpush
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  @else
    <div class="wb-stack wb-gap-2">
      <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
          <strong>{{ $adminText('gallery_items') }}</strong>
          <span class="wb-status-pill wb-status-info" data-wb-gallery-items-count>{{ $adminText('items_count', ['count' => $galleryItemRows->count()]) }}</span>
        </div>

        @include('webblocks-cms::admin.media.asset-picker-panel', [
          'name' => $rootPrefix ? str_replace(['[', ']'], ['-', ''], $rootPrefix).'-gallery-assets' : 'gallery-assets',
          'mode' => 'multiple',
          'inputId' => $rootPrefix ? str_replace(['[', ']'], ['-', ''], $rootPrefix).'-gallery-media-ids' : 'gallery_media_ids',
          'fieldName' => $rootPrefix ? $rootPrefix.'[gallery_media_ids]' : 'gallery_media_ids',
          'selectedAssets' => $galleryItemRows->pluck('asset'),
          'buttonLabel' => $adminText('add_gallery_items'),
          'replaceLabel' => $adminText('add_gallery_items'),
          'clearLabel' => $adminText('remove_all'),
          'accept' => 'image',
                     'compactControls' => true,
                     'panelMode' => 'overlay',
                     'panelTitle' => $adminText('add_gallery_items'),
                     'resultsVariant' => 'compact-list',
                     'showSummary' => false,
                     'showPreviewGrid' => false,
                     'renderPreviewGrid' => false,
                     'showUpload' => false,
                   ])
      </div>

      <span class="wb-text-sm wb-text-muted">{{ $adminText('description') }}</span>

      <div class="wb-card wb-card-muted" data-wb-gallery-items-empty @if (! $galleryItemRows->isEmpty()) hidden @endif>
        <div class="wb-card-body wb-text-sm wb-text-muted">{{ $adminText('empty') }}</div>
      </div>

      <div class="wb-table-wrap" data-admin-sortable-list data-wb-gallery-items-table @if ($galleryItemRows->isEmpty()) hidden @endif>
        <table class="wb-table wb-table-striped wb-table-hover">
          <thead>
            <tr>
              <th>{{ $adminText('order') }}</th>
              <th>{{ $adminText('preview') }}</th>
              <th>{{ $adminText('item') }}</th>
              <th>{{ $adminText('summaries') }}</th>
              <th>{{ $adminText('actions') }}</th>
            </tr>
          </thead>
          <tbody data-wb-gallery-items-list>
            @foreach ($galleryItemRows as $galleryIndex => $item)
              @php
                $asset = $item['asset'];
                $itemLabel = $asset->title ?: $asset->filename;
                $altSummary = $item['alt_text'] !== '' ? $item['alt_text'] : ($asset->alt_text ?: $adminText('no_alt_text'));
                $captionSummary = $item['caption'] !== '' ? $item['caption'] : $adminText('no_caption');
                $overlaySummary = $item['overlay_title'] !== ''
                  ? $item['overlay_title']
                  : ($item['overlay_text'] !== '' ? $item['overlay_text'] : $adminText('no_overlay_title'));
                $modalId = $modalIdValue((int) $asset->id);
              @endphp
              <tr data-admin-sortable-item draggable="true" data-wb-gallery-item-row data-media-id="{{ $asset->id }}">
                <td>
                  <div class="wb-cluster wb-cluster-2">
                    <button type="button" class="wb-action-btn" data-admin-sortable-handle title="{{ $adminText('drag_to_reorder') }}" aria-label="{{ $adminText('drag_to_reorder') }}">
                      <span aria-hidden="true">::</span>
                    </button>
                    <span>{{ $galleryIndex + 1 }}</span>
                  </div>
                  <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'sort_order') }}" value="{{ $galleryIndex }}" data-admin-sortable-order data-wb-gallery-field="sort_order">
                  <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'media_id') }}" value="{{ $asset->id }}" data-wb-gallery-field="media_id">
                  <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'alt_text') }}" value="{{ $item['alt_text'] }}" data-wb-gallery-field="alt_text">
                  <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'caption') }}" value="{{ $item['caption'] }}" data-wb-gallery-field="caption">
                  <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'overlay_title') }}" value="{{ $item['overlay_title'] }}" data-wb-gallery-field="overlay_title">
                  <input type="hidden" name="{{ $rowFieldName($galleryIndex, 'overlay_text') }}" value="{{ $item['overlay_text'] }}" data-wb-gallery-field="overlay_text">
                </td>
                <td>
                  @if ($asset->canPreview())
                    <img src="{{ $asset->url() }}" alt="{{ $asset->thumbnailLabel() }}" width="72" height="48">
                  @else
                    <span class="wb-text-sm wb-text-muted">{{ $adminText('no_preview') }}</span>
                  @endif
                </td>
                <td>
                  <strong>{{ $itemLabel }}</strong>
                  <div class="wb-text-sm wb-text-muted">{{ $asset->compactMetaLabel() }}</div>
                </td>
                <td>
                  <div class="wb-text-sm"><strong>{{ $adminText('alt_summary') }}</strong> <span data-wb-gallery-alt-summary>{{ $altSummary }}</span></div>
                  <div class="wb-text-sm"><strong>{{ $adminText('caption_summary') }}</strong> <span data-wb-gallery-caption-summary>{{ $captionSummary }}</span></div>
                  <div class="wb-text-sm"><strong>{{ $adminText('overlay_summary') }}</strong> <span data-wb-gallery-overlay-summary>{{ $overlaySummary }}</span></div>
                </td>
                <td>
                  <div class="wb-action-group">
                    <button type="button" class="wb-action-btn wb-action-btn-edit" data-wb-toggle="modal" data-wb-target="#{{ $modalId }}" data-wb-gallery-edit-item title="{{ $adminText('edit_item_metadata') }}" aria-label="{{ $adminText('edit_item_metadata') }}">
                      <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-gallery-item-remove data-asset-id="{{ $asset->id }}" title="{{ $adminText('remove_item') }}" aria-label="{{ $adminText('remove_item') }}">
                      <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                    </button>
                  </div>
                </td>
              </tr>

              @push('overlays')
                <div class="wb-modal wb-modal-lg" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title" data-wb-gallery-item-modal data-media-id="{{ $asset->id }}">
                  <div class="wb-modal-dialog">
                    <div class="wb-modal-header">
                      <div class="wb-stack wb-gap-1">
                        <h2 class="wb-modal-title" id="{{ $modalId }}-title">{{ $adminText('edit_gallery_item', ['item' => $itemLabel]) }}</h2>
                        <span class="wb-text-sm wb-text-muted">{{ $adminText('per_item_locale_help') }}</span>
                      </div>

                      <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('close') }}">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                      </button>
                    </div>

                    <div class="wb-modal-body wb-stack wb-gap-3">
                      <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                          <label for="{{ $modalId }}-alt-text">{{ $adminText('alt_text') }}</label>
                          <input id="{{ $modalId }}-alt-text" class="wb-input" type="text" value="{{ $item['alt_text'] }}" data-wb-gallery-modal-field="alt_text">
                        </div>

                        <div class="wb-stack wb-gap-1">
                          <label for="{{ $modalId }}-caption">{{ $adminText('caption') }}</label>
                          <input id="{{ $modalId }}-caption" class="wb-input" type="text" value="{{ $item['caption'] }}" data-wb-gallery-modal-field="caption">
                        </div>
                      </div>

                      <div class="wb-stack wb-gap-1">
                        <label for="{{ $modalId }}-overlay-title">{{ $adminText('overlay_title') }}</label>
                        <input id="{{ $modalId }}-overlay-title" class="wb-input" type="text" value="{{ $item['overlay_title'] }}" data-wb-gallery-modal-field="overlay_title">
                      </div>

                      <div class="wb-stack wb-gap-1">
                        <label for="{{ $modalId }}-overlay-text">{{ $adminText('overlay_text') }}</label>
                        <textarea id="{{ $modalId }}-overlay-text" class="wb-textarea" rows="4" data-wb-gallery-modal-field="overlay_text">{{ $item['overlay_text'] }}</textarea>
                      </div>

                      <div class="wb-text-sm wb-text-muted">
                        {{ $adminText('deferred_fields_help') }}
                      </div>
                    </div>

                    <div class="wb-modal-footer wb-flex wb-justify-end wb-gap-2">
                      <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('done') }}</button>
                    </div>
                  </div>
                </div>
              @endpush
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <template data-wb-gallery-item-template>
    <tr data-admin-sortable-item draggable="true" data-wb-gallery-item-row data-media-id="__MEDIA_ID__">
      <td>
        <div class="wb-cluster wb-cluster-2">
          <button type="button" class="wb-action-btn" data-admin-sortable-handle title="{{ $adminText('drag_to_reorder') }}" aria-label="{{ $adminText('drag_to_reorder') }}">
            <span aria-hidden="true">::</span>
          </button>
          <span data-wb-gallery-item-index>__INDEX_LABEL__</span>
        </div>
        <input type="hidden" name="__MEDIA_NAME__" value="__MEDIA_ID__" data-wb-gallery-field="media_id">
        <input type="hidden" name="__SORT_NAME__" value="__SORT_VALUE__" data-admin-sortable-order data-wb-gallery-field="sort_order">
        <input type="hidden" name="__ALT_NAME__" value="" data-wb-gallery-field="alt_text">
        <input type="hidden" name="__CAPTION_NAME__" value="" data-wb-gallery-field="caption">
        <input type="hidden" name="__OVERLAY_TITLE_NAME__" value="" data-wb-gallery-field="overlay_title">
        <input type="hidden" name="__OVERLAY_TEXT_NAME__" value="" data-wb-gallery-field="overlay_text">
      </td>
      <td>
        __PREVIEW_HTML__
      </td>
      <td>
        <strong>__ITEM_LABEL__</strong>
        <div class="wb-text-sm wb-text-muted">__ITEM_META__</div>
      </td>
      <td>
        <div class="wb-text-sm"><strong>{{ $adminText('alt_summary') }}</strong> <span data-wb-gallery-alt-summary>{{ $adminText('no_alt_text') }}</span></div>
        <div class="wb-text-sm"><strong>{{ $adminText('caption_summary') }}</strong> <span data-wb-gallery-caption-summary>{{ $adminText('no_caption') }}</span></div>
        <div class="wb-text-sm"><strong>{{ $adminText('overlay_summary') }}</strong> <span data-wb-gallery-overlay-summary>{{ $adminText('no_overlay_title') }}</span></div>
      </td>
      <td>
        <div class="wb-action-group">
          <button type="button" class="wb-action-btn wb-action-btn-edit" data-wb-toggle="modal" data-wb-target="#__MODAL_ID__" data-wb-gallery-edit-item title="{{ $adminText('edit_item_metadata') }}" aria-label="{{ $adminText('edit_item_metadata') }}">
            <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
          </button>
          <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-gallery-item-remove data-asset-id="__MEDIA_ID__" title="{{ $adminText('remove_item') }}" aria-label="{{ $adminText('remove_item') }}">
            <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
          </button>
        </div>
      </td>
    </tr>
  </template>

  <template data-wb-gallery-item-modal-template>
    <div class="wb-modal wb-modal-lg" id="__MODAL_ID__" role="dialog" aria-modal="true" aria-labelledby="__MODAL_ID__-title" data-wb-gallery-item-modal data-media-id="__MEDIA_ID__">
      <div class="wb-modal-dialog">
        <div class="wb-modal-header">
          <div class="wb-stack wb-gap-1">
            <h2 class="wb-modal-title" id="__MODAL_ID__-title">{{ $adminText('edit_gallery_item_template') }}</h2>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('per_item_locale_help') }}</span>
          </div>

          <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('close') }}">
            <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
          </button>
        </div>

        <div class="wb-modal-body wb-stack wb-gap-3">
          <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
              <label for="__MODAL_ID__-alt-text">{{ $adminText('alt_text') }}</label>
              <input id="__MODAL_ID__-alt-text" class="wb-input" type="text" value="" data-wb-gallery-modal-field="alt_text">
            </div>

            <div class="wb-stack wb-gap-1">
              <label for="__MODAL_ID__-caption">{{ $adminText('caption') }}</label>
              <input id="__MODAL_ID__-caption" class="wb-input" type="text" value="" data-wb-gallery-modal-field="caption">
            </div>
          </div>

          <div class="wb-stack wb-gap-1">
            <label for="__MODAL_ID__-overlay-title">{{ $adminText('overlay_title') }}</label>
            <input id="__MODAL_ID__-overlay-title" class="wb-input" type="text" value="" data-wb-gallery-modal-field="overlay_title">
          </div>

          <div class="wb-stack wb-gap-1">
            <label for="__MODAL_ID__-overlay-text">{{ $adminText('overlay_text') }}</label>
            <textarea id="__MODAL_ID__-overlay-text" class="wb-textarea" rows="4" data-wb-gallery-modal-field="overlay_text"></textarea>
          </div>

          <div class="wb-text-sm wb-text-muted">
            {{ $adminText('deferred_fields_help') }}
          </div>
        </div>

        <div class="wb-modal-footer wb-flex wb-justify-end wb-gap-2">
          <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('done') }}</button>
        </div>
      </div>
    </div>
  </template>
</div>

@once
  @push('admin-scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin-sortable-list.js'])
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/gallery-items.js'])
  @endpush
@endonce
