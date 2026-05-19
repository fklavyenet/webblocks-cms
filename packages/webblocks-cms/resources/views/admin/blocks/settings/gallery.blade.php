<div class="wb-stack wb-gap-3">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="gallery_variant">Variant</label>
            <select id="gallery_variant" name="gallery_variant" class="wb-select">
                <option value="grid" @selected(old('gallery_variant', $block->setting('variant', 'grid')) === 'grid')>Grid</option>
                <option value="masonry" @selected(old('gallery_variant', $block->setting('variant', 'grid')) === 'masonry')>Masonry</option>
                <option value="collage" @selected(old('gallery_variant', $block->setting('variant', 'grid')) === 'collage')>Collage</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="gallery_columns">Columns</label>
            <select id="gallery_columns" name="gallery_columns" class="wb-select">
                @foreach (['2', '3', '4', '5'] as $columns)
                    <option value="{{ $columns }}" @selected(old('gallery_columns', (string) $block->setting('columns', '3')) === $columns)>{{ $columns }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="gallery_gap">Gap</label>
            <select id="gallery_gap" name="gallery_gap" class="wb-select">
                @foreach (['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('gallery_gap', $block->setting('gap', 'md')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="gallery_aspect_ratio">Aspect Ratio</label>
            <select id="gallery_aspect_ratio" name="gallery_aspect_ratio" class="wb-select">
                @foreach (['auto' => 'Auto', 'square' => 'Square', '4:3' => '4:3', '16:9' => '16:9', 'portrait' => 'Portrait'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('gallery_aspect_ratio', $block->setting('aspect_ratio', 'auto')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="gallery_captions_mode">Captions</label>
            <select id="gallery_captions_mode" name="gallery_captions_mode" class="wb-select">
                @foreach (['hidden' => 'Hidden', 'below' => 'Below', 'overlay' => 'Overlay', 'on-hover' => 'On hover'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('gallery_captions_mode', $block->setting('captions_mode', 'below')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="gallery_overlay_mode">Overlay Mode</label>
            <select id="gallery_overlay_mode" name="gallery_overlay_mode" class="wb-select">
                @foreach (['none' => 'None', 'gradient' => 'Gradient', 'solid' => 'Solid'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('gallery_overlay_mode', $block->setting('overlay_mode', 'gradient')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <label class="wb-cluster wb-cluster-2 wb-items-center" for="gallery_lightbox_enabled">
        <input id="gallery_lightbox_enabled" name="gallery_lightbox_enabled" type="hidden" value="0">
        <input id="gallery_lightbox_enabled" name="gallery_lightbox_enabled" type="checkbox" value="1" @checked((bool) old('gallery_lightbox_enabled', $block->setting('lightbox_enabled', true)))>
        <span>Enable lightbox viewer</span>
    </label>

    <div class="wb-text-sm wb-text-muted">Carousel is deferred until the shipped public WebBlocks gallery support is available in CMS.</div>
</div>
