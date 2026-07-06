@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.gallery_settings.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="gallery_viewer_title">{{ $adminText('viewer_title') }}</label>
        <input
            id="gallery_viewer_title"
            name="gallery_viewer_title"
            type="text"
            value="{{ old('gallery_viewer_title', $block->setting('viewer_title')) }}"
            class="wb-input"
            maxlength="255"
        >
        <div class="wb-text-sm wb-text-muted">{{ $adminText('viewer_title_help') }}</div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="gallery_variant">{{ $adminText('variant') }}</label>
            <select id="gallery_variant" name="gallery_variant" class="wb-select">
                <option value="grid" @selected(old('gallery_variant', $block->setting('variant', 'grid')) === 'grid')>{{ $adminText('grid') }}</option>
                <option value="masonry" @selected(old('gallery_variant', $block->setting('variant', 'grid')) === 'masonry')>{{ $adminText('masonry') }}</option>
                <option value="collage" @selected(old('gallery_variant', $block->setting('variant', 'grid')) === 'collage')>{{ $adminText('collage') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="gallery_columns">{{ $adminText('columns') }}</label>
            <select id="gallery_columns" name="gallery_columns" class="wb-select">
                @foreach (['2', '3', '4', '5'] as $columns)
                    <option value="{{ $columns }}" @selected(old('gallery_columns', (string) $block->setting('columns', '3')) === $columns)>{{ $columns }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="gallery_gap">{{ $adminText('gap') }}</label>
            <select id="gallery_gap" name="gallery_gap" class="wb-select">
                @foreach ([
                    'none' => $adminText('none'),
                    'sm' => $adminText('small'),
                    'md' => $adminText('medium'),
                    'lg' => $adminText('large'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('gallery_gap', $block->setting('gap', 'md')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="gallery_aspect_ratio">{{ $adminText('aspect_ratio') }}</label>
            <select id="gallery_aspect_ratio" name="gallery_aspect_ratio" class="wb-select">
                @foreach ([
                    'auto' => $adminText('auto'),
                    'square' => $adminText('square'),
                    '4:3' => '4:3',
                    '16:9' => '16:9',
                    'portrait' => $adminText('portrait'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('gallery_aspect_ratio', $block->setting('aspect_ratio', 'auto')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="gallery_captions_mode">{{ $adminText('captions') }}</label>
            <select id="gallery_captions_mode" name="gallery_captions_mode" class="wb-select">
                @foreach ([
                    'hidden' => $adminText('hidden'),
                    'below' => $adminText('below'),
                    'overlay' => $adminText('overlay'),
                    'on-hover' => $adminText('on_hover'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('gallery_captions_mode', $block->setting('captions_mode', 'below')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="gallery_overlay_mode">{{ $adminText('overlay_mode') }}</label>
            <select id="gallery_overlay_mode" name="gallery_overlay_mode" class="wb-select">
                @foreach ([
                    'none' => $adminText('none'),
                    'gradient' => $adminText('gradient'),
                    'solid' => $adminText('solid'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('gallery_overlay_mode', $block->setting('overlay_mode', 'gradient')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <label class="wb-cluster wb-cluster-2 wb-items-center" for="gallery_lightbox_enabled">
        <input id="gallery_lightbox_enabled" name="gallery_lightbox_enabled" type="hidden" value="0">
        <input id="gallery_lightbox_enabled" name="gallery_lightbox_enabled" type="checkbox" value="1" @checked((bool) old('gallery_lightbox_enabled', $block->setting('lightbox_enabled', true)))>
        <span>{{ $adminText('enable_lightbox') }}</span>
    </label>

    <div class="wb-text-sm wb-text-muted">{{ $adminText('carousel_deferred') }}</div>
</div>
