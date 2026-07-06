@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.slide_settings.'.$key, $adminLocale);
    $contentPosition = old('slide_content_position', $block->appearanceSetting('content_position') ?? 'center');
    $contentWidth = old('slide_content_width', $block->appearanceSetting('content_width') ?? 'medium');
    $textColor = old('slide_text_color', $block->appearanceSetting('text_color') ?? 'auto');
    $backgroundFit = old('slide_background_fit', $block->appearanceSetting('background_fit') ?? 'cover');
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="slide_content_position">{{ $adminText('content_position') }}</label>
            <select id="slide_content_position" name="slide_content_position" class="wb-select">
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
            <label for="slide_content_width">{{ $adminText('content_width') }}</label>
            <select id="slide_content_width" name="slide_content_width" class="wb-select">
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
            <label for="slide_text_color">{{ $adminText('text_color') }}</label>
            <select id="slide_text_color" name="slide_text_color" class="wb-select">
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
            <label for="slide_background_fit">{{ $adminText('background_fit') }}</label>
            <select id="slide_background_fit" name="slide_background_fit" class="wb-select">
                <option value="cover" @selected($backgroundFit === 'cover')>{{ $adminText('cover') }}</option>
                <option value="contain" @selected($backgroundFit === 'contain')>{{ $adminText('contain') }}</option>
            </select>
        </div>
    </div>
</div>
