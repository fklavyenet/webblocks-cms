@php
    $height = old('slider_height', $block->appearanceSetting('height') ?? 'fill');
    $aspectRatio = old('slider_aspect_ratio', $block->appearanceSetting('aspect_ratio') ?? 'auto');
    $transition = old('slider_transition', $block->appearanceSetting('transition') ?? 'slide');
    $overlay = old('slider_overlay', $block->appearanceSetting('overlay') ?? 'none');
    $contentPosition = old('slider_content_position', $block->appearanceSetting('content_position') ?? 'center');
    $contentWidth = old('slider_content_width', $block->appearanceSetting('content_width') ?? 'medium');
    $textColor = old('slider_text_color', $block->appearanceSetting('text_color') ?? 'auto');
    $backgroundFit = old('slider_background_fit', $block->appearanceSetting('background_fit') ?? 'cover');
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="slider_height">Height</label>
            <select id="slider_height" name="slider_height" class="wb-select">
                @foreach ([
                    'fill' => 'Fill parent',
                    'viewport' => 'Viewport',
                    'large' => 'Large',
                    'medium' => 'Medium',
                    'small' => 'Small',
                    'auto' => 'Auto',
                    'custom' => 'Custom',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($height === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_min_height">Custom Min Height</label>
            <input id="slider_min_height" name="slider_min_height" class="wb-input" type="text" placeholder="640px or 80vh" value="{{ old('slider_min_height', $block->setting('min_height')) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_aspect_ratio">Aspect Ratio</label>
            <select id="slider_aspect_ratio" name="slider_aspect_ratio" class="wb-select">
                @foreach ([
                    'auto' => 'Auto',
                    '16/9' => '16:9',
                    '4/3' => '4:3',
                    '1/1' => 'Square',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($aspectRatio === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_transition">Transition</label>
            <select id="slider_transition" name="slider_transition" class="wb-select">
                <option value="slide" @selected($transition === 'slide')>Slide</option>
                <option value="fade" @selected($transition === 'fade')>Fade</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_interval_ms">Autoplay Interval</label>
            <input id="slider_interval_ms" name="slider_interval_ms" class="wb-input" type="number" min="1000" max="30000" step="500" value="{{ old('slider_interval_ms', $block->setting('interval_ms', 6000)) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_overlay">Overlay</label>
            <select id="slider_overlay" name="slider_overlay" class="wb-select">
                @foreach ([
                    'none' => 'None',
                    'soft' => 'Soft',
                    'medium' => 'Medium',
                    'dark' => 'Dark',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($overlay === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_content_position">Content Position</label>
            <select id="slider_content_position" name="slider_content_position" class="wb-select">
                @foreach ([
                    'center' => 'Center',
                    'top-left' => 'Top Left',
                    'top-center' => 'Top Center',
                    'top-right' => 'Top Right',
                    'bottom-left' => 'Bottom Left',
                    'bottom-center' => 'Bottom Center',
                    'bottom-right' => 'Bottom Right',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($contentPosition === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_content_width">Content Width</label>
            <select id="slider_content_width" name="slider_content_width" class="wb-select">
                @foreach ([
                    'narrow' => 'Narrow',
                    'medium' => 'Medium',
                    'wide' => 'Wide',
                    'full' => 'Full',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($contentWidth === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_text_color">Text Color</label>
            <select id="slider_text_color" name="slider_text_color" class="wb-select">
                @foreach ([
                    'auto' => 'Auto',
                    'light' => 'Light',
                    'dark' => 'Dark',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($textColor === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slider_background_fit">Background Fit</label>
            <select id="slider_background_fit" name="slider_background_fit" class="wb-select">
                <option value="cover" @selected($backgroundFit === 'cover')>Cover</option>
                <option value="contain" @selected($backgroundFit === 'contain')>Contain</option>
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        @foreach ([
            'slider_autoplay' => ['Autoplay', $block->sliderBooleanSetting('autoplay', false)],
            'slider_pause_on_hover' => ['Pause on hover', $block->sliderBooleanSetting('pause_on_hover', true)],
            'slider_show_arrows' => ['Show arrows', $block->sliderBooleanSetting('show_arrows', true)],
            'slider_show_dots' => ['Show dots', $block->sliderBooleanSetting('show_dots', true)],
            'slider_loop' => ['Loop', $block->sliderBooleanSetting('loop', true)],
            'slider_swipe' => ['Swipe', $block->sliderBooleanSetting('swipe', true)],
            'slider_keyboard' => ['Keyboard navigation', $block->sliderBooleanSetting('keyboard', true)],
        ] as $field => [$label, $checked])
            <label class="wb-inline-flex wb-items-center wb-gap-2">
                <input type="hidden" name="{{ $field }}" value="0">
                <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $checked) == '1')>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
</div>
