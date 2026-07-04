@php
    $contentPosition = old('slide_content_position', $block->appearanceSetting('content_position') ?? 'center');
    $contentWidth = old('slide_content_width', $block->appearanceSetting('content_width') ?? 'medium');
    $textColor = old('slide_text_color', $block->appearanceSetting('text_color') ?? 'auto');
    $backgroundFit = old('slide_background_fit', $block->appearanceSetting('background_fit') ?? 'cover');
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="slide_content_position">Content Position</label>
            <select id="slide_content_position" name="slide_content_position" class="wb-select">
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
            <label for="slide_content_width">Content Width</label>
            <select id="slide_content_width" name="slide_content_width" class="wb-select">
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
            <label for="slide_text_color">Text Color</label>
            <select id="slide_text_color" name="slide_text_color" class="wb-select">
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
            <label for="slide_background_fit">Background Fit</label>
            <select id="slide_background_fit" name="slide_background_fit" class="wb-select">
                <option value="cover" @selected($backgroundFit === 'cover')>Cover</option>
                <option value="contain" @selected($backgroundFit === 'contain')>Contain</option>
            </select>
        </div>
    </div>
</div>
