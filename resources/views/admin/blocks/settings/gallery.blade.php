@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $inputPrefix = $inputPrefix ?? null;
    $oldPrefix = $inputPrefix ? str_replace(['[', ']'], ['.', ''], $inputPrefix).'.' : '';
    $idPrefix = $inputPrefix ? str_replace(['[', ']'], ['-', ''], $inputPrefix).'-' : '';
    $name = fn (string $field) => $inputPrefix ? $inputPrefix.'['.$field.']' : $field;
    $fieldValue = fn (string $field, mixed $default) => old($oldPrefix.$field, $default);
    $layout = \WebBlocks\Cms\Support\Media\GalleryLayout::variant($fieldValue('gallery_variant', $block->galleryVariant()));
    $columns = $fieldValue('gallery_columns', $block->galleryColumns());
    $weights = (array) $fieldValue('gallery_weights', $block->setting('gallery_weights', []));
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.blocks.gallery_settings.'.$key, $adminLocale, $replace);
@endphp

<div class="wb-stack wb-gap-3" data-cms-gallery-settings="{{ $inputPrefix }}">
    <div class="wb-stack wb-gap-1">
        <label for="{{ $idPrefix }}gallery_viewer_title">{{ $adminText('viewer_title') }}</label>
        <input
            id="{{ $idPrefix }}gallery_viewer_title"
            name="{{ $name('gallery_viewer_title') }}"
            type="text"
            value="{{ $fieldValue('gallery_viewer_title', $block->setting('viewer_title')) }}"
            class="wb-input"
            maxlength="255"
        >
        <div class="wb-text-sm wb-text-muted">{{ $adminText('viewer_title_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-2">
        <strong id="{{ $idPrefix }}gallery-layout">{{ $adminText('layout') }}</strong>
        <div class="wb-radio-group wb-cms-gallery-layout-choices" role="radiogroup" aria-labelledby="{{ $idPrefix }}gallery-layout">
            @foreach (\WebBlocks\Cms\Support\Media\GalleryLayout::VARIANTS as $option)
                <label class="wb-radio-card">
                    <input type="radio" name="{{ $name('gallery_variant') }}" value="{{ $option }}" data-cms-gallery-variant @checked($layout === $option)>
                    <span class="wb-radio-card-body">
                        <strong>{{ $adminText('layout_'.$option) }}</strong>
                        <span class="wb-cms-gallery-diagram" data-layout="{{ $option }}" aria-hidden="true">
                            <span></span><span></span><span></span><span></span><span></span><span></span>
                        </span>
                        <span class="wb-text-sm wb-text-muted">{{ $adminText('layout_'.$option.'_help') }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1" data-cms-gallery-columns-field @if ($layout === 'physical-scale') hidden @endif>
            <label for="{{ $idPrefix }}gallery_columns" data-cms-gallery-columns-label data-columns="{{ $adminText('columns') }}" data-per-row="{{ $adminText('items_per_row') }}">
                {{ $adminText($layout === 'equal-height' ? 'items_per_row' : 'columns') }}
            </label>
            <select id="{{ $idPrefix }}gallery_columns" name="{{ $name('gallery_columns') }}" class="wb-select" data-cms-gallery-columns>
                @foreach (['2', '3', '4', '5'] as $count)
                    <option value="{{ $count }}" @selected((string) $columns === $count)>{{ $count }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-2" data-cms-gallery-physical @if ($layout !== 'physical-scale') hidden @endif>
        <div class="wb-grid wb-grid-2">
            <label class="wb-stack wb-gap-1">
                <span>{{ $adminText('physical_unit') }}</span>
                <select class="wb-select" name="{{ $name('gallery_unit') }}" data-cms-gallery-unit @disabled($layout !== 'physical-scale')>
                    @foreach (\WebBlocks\Cms\Support\Media\GalleryLayout::UNITS as $unit => $factor)
                        <option value="{{ $unit }}" data-factor="{{ $factor }}" @selected($fieldValue('gallery_unit', $block->setting('gallery_unit', 'cm')) === $unit)>{{ $unit }}</option>
                    @endforeach
                </select>
            </label>
            <label class="wb-stack wb-gap-1">
                <span>{{ $adminText('physical_vertical_alignment') }}</span>
                <select class="wb-select" name="{{ $name('gallery_vertical_alignment') }}" data-cms-gallery-vertical-alignment @disabled($layout !== 'physical-scale')>
                    @foreach (\WebBlocks\Cms\Support\Media\GalleryLayout::VERTICAL_ALIGNMENTS as $alignment)
                        <option value="{{ $alignment }}" @selected($fieldValue('gallery_vertical_alignment', $block->galleryVerticalAlignment()) === $alignment)>{{ $adminText('physical_align_'.$alignment) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="wb-table-wrap" data-cms-gallery-measurements hidden>
            <table class="wb-table">
                <thead><tr>
                    <th scope="col">{{ $adminText('physical_item') }}</th>
                    <th scope="col" data-cms-gallery-dimension-label="physical_width_um">{{ $adminText('physical_width') }}</th>
                    <th scope="col" data-cms-gallery-dimension-label="physical_height_um">{{ $adminText('physical_height') }}</th>
                </tr></thead>
                <tbody data-cms-gallery-measurement-rows data-max="{{ \WebBlocks\Cms\Support\Media\GalleryLayout::MAX_DIMENSION }}"></tbody>
            </table>
        </div>
        <p class="wb-text-sm wb-text-muted">{{ $adminText('physical_help') }}</p>
    </div>

    <div class="wb-stack wb-gap-1" data-cms-gallery-weights @if ($layout !== 'weighted') hidden @endif>
        <strong id="{{ $idPrefix }}gallery-weights">{{ $adminText('weights') }}</strong>
        <div class="wb-cluster wb-cluster-4 wb-flex-wrap" role="group" aria-labelledby="{{ $idPrefix }}gallery-weights">
            @for ($position = 0; $position < 5; $position++)
                <label class="wb-stack wb-gap-1" data-cms-gallery-weight @if ($position >= (int) $columns) hidden @endif>
                    <span>{{ $adminText('weight_column', ['column' => $position + 1]) }}</span>
                    <input type="number" name="{{ $name('gallery_weights') }}[]" class="wb-input"
                        min="{{ \WebBlocks\Cms\Support\Media\GalleryLayout::MIN_WEIGHT }}"
                        max="{{ \WebBlocks\Cms\Support\Media\GalleryLayout::MAX_WEIGHT }}"
                        step="any" value="{{ $weights[$position] ?? 1 }}" required @disabled($layout !== 'weighted' || $position >= (int) $columns)>
                </label>
            @endfor
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="{{ $idPrefix }}gallery_gap">{{ $adminText('gap') }}</label>
            <select id="{{ $idPrefix }}gallery_gap" name="{{ $name('gallery_gap') }}" class="wb-select" data-cms-gallery-gap>
                @foreach ([
                    'none' => $adminText('none'),
                    'sm' => $adminText('small'),
                    'md' => $adminText('medium'),
                    'lg' => $adminText('large'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($fieldValue('gallery_gap', $block->setting('gap', 'md')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1" data-cms-gallery-aspect-field @if (in_array($layout, ['equal-height', 'physical-scale'], true)) hidden @endif>
            <label for="{{ $idPrefix }}gallery_aspect_ratio">{{ $adminText('aspect_ratio') }}</label>
            <select id="{{ $idPrefix }}gallery_aspect_ratio" name="{{ $name('gallery_aspect_ratio') }}" class="wb-select" data-cms-gallery-aspect>
                @foreach ([
                    'auto' => $adminText('auto'),
                    'square' => $adminText('square'),
                    '4:3' => '4:3',
                    '16:9' => '16:9',
                    'portrait' => $adminText('portrait'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($fieldValue('gallery_aspect_ratio', $block->setting('aspect_ratio', 'auto')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="{{ $idPrefix }}gallery_captions_mode">{{ $adminText('captions') }}</label>
            <select id="{{ $idPrefix }}gallery_captions_mode" name="{{ $name('gallery_captions_mode') }}" class="wb-select" data-cms-gallery-captions>
                @foreach ([
                    'hidden' => $adminText('hidden'),
                    'below' => $adminText('below'),
                    'overlay' => $adminText('overlay'),
                    'on-hover' => $adminText('on_hover'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($fieldValue('gallery_captions_mode', $block->setting('captions_mode', 'below')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="{{ $idPrefix }}gallery_overlay_mode">{{ $adminText('overlay_mode') }}</label>
            <select id="{{ $idPrefix }}gallery_overlay_mode" name="{{ $name('gallery_overlay_mode') }}" class="wb-select" data-cms-gallery-overlay>
                @foreach ([
                    'none' => $adminText('none'),
                    'gradient' => $adminText('gradient'),
                    'solid' => $adminText('solid'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($fieldValue('gallery_overlay_mode', $block->setting('overlay_mode', 'gradient')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <label class="wb-cluster wb-cluster-2 wb-items-center" for="{{ $idPrefix }}gallery_lightbox_enabled">
        <input name="{{ $name('gallery_lightbox_enabled') }}" type="hidden" value="0">
        <input id="{{ $idPrefix }}gallery_lightbox_enabled" name="{{ $name('gallery_lightbox_enabled') }}" type="checkbox" value="1" @checked((bool) $fieldValue('gallery_lightbox_enabled', $block->setting('lightbox_enabled', true)))>
        <span>{{ $adminText('enable_lightbox') }}</span>
    </label>

    <div class="wb-stack wb-gap-2">
        <div class="wb-cluster wb-cluster-between wb-flex-wrap">
            <strong id="{{ $idPrefix }}gallery-preview">{{ $adminText('preview') }}</strong>
            <div class="wb-radio-group" role="radiogroup" aria-labelledby="{{ $idPrefix }}gallery-preview">
                @foreach (['wide', 'narrow'] as $width)
                    <label class="wb-radio-card">
                        <input type="radio" name="{{ $name('gallery_preview_width') }}" value="{{ $width }}" data-cms-gallery-preview-width @checked($width === 'wide')>
                        <span class="wb-radio-card-body">{{ $adminText('preview_'.$width) }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <p class="wb-text-sm wb-text-muted" data-cms-gallery-preview-empty>{{ $adminText('preview_empty') }}</p>
        <p class="wb-alert wb-alert-info" data-cms-gallery-preview-missing role="status" hidden>{{ $adminText('physical_missing') }}</p>
        <div class="wb-cms-gallery-preview" data-cms-gallery-preview-frame hidden>
            <div class="wb-gallery" data-cms-gallery-preview data-physical-row-size="{{ \WebBlocks\Cms\Support\Media\GalleryLayout::ITEMS_PER_PHYSICAL_ROW }}"
                aria-labelledby="{{ $idPrefix }}gallery-preview">
                <div class="wb-gallery-grid"></div>
            </div>
        </div>
    </div>
</div>
