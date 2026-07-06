@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.slider_settings.'.$key, $adminLocale);
    $height = old('slider_height', $block->appearanceSetting('height') ?? 'fill');
    $aspectRatio = old('slider_aspect_ratio', $block->appearanceSetting('aspect_ratio') ?? 'auto');
    $overlay = old('slider_overlay', $block->appearanceSetting('overlay') ?? 'none');
    $contentPosition = old('slider_content_position', $block->appearanceSetting('content_position') ?? 'center');
    $contentWidth = old('slider_content_width', $block->appearanceSetting('content_width') ?? 'medium');
    $textColor = old('slider_text_color', $block->appearanceSetting('text_color') ?? 'auto');
    $backgroundFit = old('slider_background_fit', $block->appearanceSetting('background_fit') ?? 'cover');
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="slider_height">{{ $adminText('height_label') }}</label>
            <select id="slider_height" name="slider_height" class="wb-select">
                @foreach ([
                    'fill' => $adminText('fill_parent'),
                    'viewport' => $adminText('viewport'),
                    'large' => $adminText('large'),
                    'medium' => $adminText('medium'),
                    'small' => $adminText('small'),
                    'auto' => $adminText('auto'),
                    'custom' => $adminText('custom'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($height === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_min_height">{{ $adminText('custom_min_height') }}</label>
            <input id="slider_min_height" name="slider_min_height" class="wb-input" type="text" placeholder="640px or 80vh" value="{{ old('slider_min_height', $block->setting('min_height')) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_aspect_ratio">{{ $adminText('aspect_ratio') }}</label>
            <select id="slider_aspect_ratio" name="slider_aspect_ratio" class="wb-select">
                @foreach ([
                    'auto' => $adminText('auto'),
                    '16/9' => '16:9',
                    '4/3' => '4:3',
                    '1/1' => $adminText('square'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($aspectRatio === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_interval_ms">{{ $adminText('autoplay_interval') }}</label>
            <input id="slider_interval_ms" name="slider_interval_ms" class="wb-input" type="number" min="1000" max="30000" step="500" value="{{ old('slider_interval_ms', $block->setting('interval_ms', 6000)) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_overlay">{{ $adminText('overlay') }}</label>
            <select id="slider_overlay" name="slider_overlay" class="wb-select">
                @foreach ([
                    'none' => $adminText('none'),
                    'soft' => $adminText('soft'),
                    'medium' => $adminText('medium'),
                    'dark' => $adminText('dark'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($overlay === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_content_position">{{ $adminText('content_position') }}</label>
            <select id="slider_content_position" name="slider_content_position" class="wb-select">
                @foreach ([
                    'center' => $adminText('center'),
                    'top-left' => $adminText('top_left'),
                    'top-center' => $adminText('top_center'),
                    'top-right' => $adminText('top_right'),
                    'bottom-left' => $adminText('bottom_left'),
                    'bottom-center' => $adminText('bottom_center'),
                    'bottom-right' => $adminText('bottom_right'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($contentPosition === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_content_width">{{ $adminText('content_width') }}</label>
            <select id="slider_content_width" name="slider_content_width" class="wb-select">
                @foreach ([
                    'narrow' => $adminText('narrow'),
                    'medium' => $adminText('medium'),
                    'wide' => $adminText('wide'),
                    'full' => $adminText('full'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($contentWidth === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_text_color">{{ $adminText('text_color') }}</label>
            <select id="slider_text_color" name="slider_text_color" class="wb-select">
                @foreach ([
                    'auto' => $adminText('auto'),
                    'light' => $adminText('light'),
                    'dark' => $adminText('dark'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($textColor === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_background_fit">{{ $adminText('background_fit') }}</label>
            <select id="slider_background_fit" name="slider_background_fit" class="wb-select">
                <option value="cover" @selected($backgroundFit === 'cover')>{{ $adminText('cover') }}</option>
                <option value="contain" @selected($backgroundFit === 'contain')>{{ $adminText('contain') }}</option>
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        @foreach ([
            'slider_autoplay' => [$adminText('autoplay'), $block->sliderBooleanSetting('autoplay', false)],
            'slider_pause_on_hover' => [$adminText('pause_on_hover'), $block->sliderBooleanSetting('pause_on_hover', true)],
            'slider_show_arrows' => [$adminText('show_arrows'), $block->sliderBooleanSetting('show_arrows', true)],
            'slider_show_dots' => [$adminText('show_dots'), $block->sliderBooleanSetting('show_dots', true)],
            'slider_loop' => [$adminText('loop'), $block->sliderBooleanSetting('loop', true)],
            'slider_swipe' => [$adminText('swipe'), $block->sliderBooleanSetting('swipe', true)],
            'slider_keyboard' => [$adminText('keyboard_navigation'), $block->sliderBooleanSetting('keyboard', true)],
        ] as $field => [$label, $checked])
            <label class="wb-inline-flex wb-items-center wb-gap-2">
                <input type="hidden" name="{{ $field }}" value="0">
                <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $checked) == '1')>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
</div>
