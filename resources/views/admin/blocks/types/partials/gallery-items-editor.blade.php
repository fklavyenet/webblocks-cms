@php
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
                $asset = \App\Models\Media::query()->find($mediaId);
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
@endphp

<div class="wb-stack wb-gap-4" data-wb-gallery-items-editor data-wb-gallery-field-prefix="{{ $rootPrefix ?? '' }}">
    <div class="wb-alert wb-alert-info">
        <div>Gallery is a media collection block. Use Content Header and Plain Text or Rich Text before Gallery for headings or descriptive copy.</div>
    </div>

    @if ($activeLocale && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Alt text, caption, and overlay copy are translated per locale. Media selection, order, and presentation settings stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-2">
        <div class="wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-stack wb-gap-1">
                <strong>Gallery Items</strong>
                <span class="wb-text-sm wb-text-muted">{{ $galleryItemRows->count() }} {{ \Illuminate\Support\Str::plural('item', $galleryItemRows->count()) }}</span>
            </div>

            @include('admin.media.asset-picker-panel', [
                'name' => $rootPrefix ? str_replace(['[', ']'], ['-', ''], $rootPrefix).'-gallery-assets' : 'gallery-assets',
                'mode' => 'multiple',
                'inputId' => $rootPrefix ? str_replace(['[', ']'], ['-', ''], $rootPrefix).'-gallery-media-ids' : 'gallery_media_ids',
                'fieldName' => $rootPrefix ? $rootPrefix.'[gallery_media_ids]' : 'gallery_media_ids',
                'selectedAssets' => $galleryItemRows->pluck('asset'),
                'buttonLabel' => 'Choose from Media',
                'replaceLabel' => 'Add More Assets',
                'clearLabel' => 'Remove All',
                'accept' => 'image',
                'compactControls' => true,
            ])
        </div>

        <span class="wb-text-sm wb-text-muted">Add, remove, and reorder internal images. Per-item copy is edited from the compact list below.</span>

        @if ($galleryItemRows->isEmpty())
            <div class="wb-card wb-card-muted">
                <div class="wb-card-body wb-text-sm wb-text-muted">No gallery items selected yet.</div>
            </div>
        @else
            <div class="wb-table-wrap" data-admin-sortable-list>
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Preview</th>
                            <th>Item</th>
                            <th>Summaries</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody data-wb-gallery-items-list>
                        @foreach ($galleryItemRows as $galleryIndex => $item)
                            @php
                                $asset = $item['asset'];
                                $itemLabel = $asset->title ?: $asset->filename;
                                $altSummary = $item['alt_text'] !== '' ? $item['alt_text'] : ($asset->alt_text ?: 'No alt text');
                                $captionSummary = $item['caption'] !== '' ? $item['caption'] : 'No caption';
                                $overlaySummary = $item['overlay_title'] !== '' ? $item['overlay_title'] : 'No overlay title';
                                $modalId = $modalIdPrefix.'-'.$galleryIndex.'-'.(int) $asset->id;
                            @endphp
                            <tr data-admin-sortable-item draggable="true" data-wb-gallery-item-row data-media-id="{{ $asset->id }}">
                                <td>
                                    <div class="wb-cluster wb-cluster-2">
                                        <button type="button" class="wb-action-btn" data-admin-sortable-handle title="Drag to reorder item" aria-label="Drag to reorder item">
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
                                        <span class="wb-text-sm wb-text-muted">No preview</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $itemLabel }}</strong>
                                    <div class="wb-text-sm wb-text-muted">{{ $asset->compactMetaLabel() }}</div>
                                </td>
                                <td>
                                    <div class="wb-text-sm"><strong>Alt:</strong> {{ $altSummary }}</div>
                                    <div class="wb-text-sm"><strong>Caption:</strong> {{ $captionSummary }}</div>
                                    <div class="wb-text-sm"><strong>Overlay:</strong> {{ $overlaySummary }}</div>
                                </td>
                                <td>
                                    <div class="wb-action-group">
                                        <button type="button" class="wb-action-btn wb-action-btn-edit" data-wb-toggle="modal" data-wb-target="#{{ $modalId }}" title="Edit item metadata" aria-label="Edit item metadata">
                                            <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-gallery-item-remove data-asset-id="{{ $asset->id }}" title="Remove item" aria-label="Remove item">
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
                                                <h2 class="wb-modal-title" id="{{ $modalId }}-title">Edit Gallery Item: {{ $itemLabel }}</h2>
                                                <span class="wb-text-sm wb-text-muted">Per-item copy belongs to the active locale.</span>
                                            </div>

                                            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close">
                                                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                                            </button>
                                        </div>

                                        <div class="wb-modal-body wb-stack wb-gap-3">
                                            <div class="wb-grid wb-grid-2">
                                                <div class="wb-stack wb-gap-1">
                                                    <label for="{{ $modalId }}-alt-text">Alt Text</label>
                                                    <input id="{{ $modalId }}-alt-text" class="wb-input" type="text" value="{{ $item['alt_text'] }}" data-wb-gallery-modal-field="alt_text">
                                                </div>

                                                <div class="wb-stack wb-gap-1">
                                                    <label for="{{ $modalId }}-caption">Caption</label>
                                                    <input id="{{ $modalId }}-caption" class="wb-input" type="text" value="{{ $item['caption'] }}" data-wb-gallery-modal-field="caption">
                                                </div>
                                            </div>

                                            <div class="wb-stack wb-gap-1">
                                                <label for="{{ $modalId }}-overlay-title">Overlay Title</label>
                                                <input id="{{ $modalId }}-overlay-title" class="wb-input" type="text" value="{{ $item['overlay_title'] }}" data-wb-gallery-modal-field="overlay_title">
                                            </div>

                                            <div class="wb-stack wb-gap-1">
                                                <label for="{{ $modalId }}-overlay-text">Overlay Text</label>
                                                <textarea id="{{ $modalId }}-overlay-text" class="wb-textarea" rows="4" data-wb-gallery-modal-field="overlay_text">{{ $item['overlay_text'] }}</textarea>
                                            </div>

                                            <div class="wb-text-sm wb-text-muted">
                                                Credit, source text, link URL, and per-item open behavior are deferred until the gallery item model includes explicit shared behavior fields.
                                            </div>
                                        </div>

                                        <div class="wb-modal-footer wb-flex wb-justify-end wb-gap-2">
                                            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">Done</button>
                                        </div>
                                    </div>
                                </div>
                            @endpush
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <template data-wb-gallery-item-template>
        <tr data-admin-sortable-item draggable="true" data-wb-gallery-item-row data-media-id="__MEDIA_ID__">
            <td>
                <div class="wb-cluster wb-cluster-2">
                    <button type="button" class="wb-action-btn" data-admin-sortable-handle title="Drag to reorder item" aria-label="Drag to reorder item">
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
                <div class="wb-text-sm"><strong>Alt:</strong> <span data-wb-gallery-alt-summary>No alt text</span></div>
                <div class="wb-text-sm"><strong>Caption:</strong> <span data-wb-gallery-caption-summary>No caption</span></div>
                <div class="wb-text-sm"><strong>Overlay:</strong> <span data-wb-gallery-overlay-summary>No overlay title</span></div>
            </td>
            <td>
                <div class="wb-action-group">
                    <button type="button" class="wb-action-btn wb-action-btn-edit" data-wb-toggle="modal" data-wb-target="#__MODAL_ID__" title="Edit item metadata" aria-label="Edit item metadata">
                        <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-gallery-item-remove data-asset-id="__MEDIA_ID__" title="Remove item" aria-label="Remove item">
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
                        <h2 class="wb-modal-title" id="__MODAL_ID__-title">Edit Gallery Item: __ITEM_LABEL__</h2>
                        <span class="wb-text-sm wb-text-muted">Per-item copy belongs to the active locale.</span>
                    </div>

                    <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="wb-modal-body wb-stack wb-gap-3">
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <label for="__MODAL_ID__-alt-text">Alt Text</label>
                            <input id="__MODAL_ID__-alt-text" class="wb-input" type="text" value="" data-wb-gallery-modal-field="alt_text">
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="__MODAL_ID__-caption">Caption</label>
                            <input id="__MODAL_ID__-caption" class="wb-input" type="text" value="" data-wb-gallery-modal-field="caption">
                        </div>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="__MODAL_ID__-overlay-title">Overlay Title</label>
                        <input id="__MODAL_ID__-overlay-title" class="wb-input" type="text" value="" data-wb-gallery-modal-field="overlay_title">
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="__MODAL_ID__-overlay-text">Overlay Text</label>
                        <textarea id="__MODAL_ID__-overlay-text" class="wb-textarea" rows="4" data-wb-gallery-modal-field="overlay_text"></textarea>
                    </div>

                    <div class="wb-text-sm wb-text-muted">
                        Credit, source text, link URL, and per-item open behavior are deferred until the gallery item model includes explicit shared behavior fields.
                    </div>
                </div>

                <div class="wb-modal-footer wb-flex wb-justify-end wb-gap-2">
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </template>
</div>
