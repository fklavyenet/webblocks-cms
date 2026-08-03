@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.background_media.'.$key, $adminLocale);
    $backgroundSettings = is_array($block->settings) ? $block->settings : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);
    $backgroundPosition = old('background_position', $backgroundSettings['background_position'] ?? 'center');

    // Slide passes this: its overlay sits on top of the slider's, so "no value"
    // is a real state (inherit) rather than a stand-in for the soft default.
    $overlayInherits = ($overlayInherits ?? false) === true;
    $backgroundOverlay = old('background_overlay', $backgroundSettings['background_overlay'] ?? ($overlayInherits ? '' : 'soft'));
    $overlayOptions = array_merge(
        $overlayInherits ? ['' => $adminText('overlay_inherit')] : [],
        [
            'soft' => $adminText('soft'),
            'medium' => $adminText('medium'),
            'strong' => $adminText('strong'),
            'none' => $adminText('none'),
        ],
    );
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-header"><strong>{{ $adminText('title') }}</strong></div>
    <div class="wb-card-body wb-stack wb-gap-4">
        @include('webblocks-cms::admin.media.asset-picker-panel', [
            'name' => 'background-asset',
            'inputId' => 'media_id',
            'fieldName' => 'media_id',
            'selectedAsset' => old('media_id') ? null : ($selectedAsset ?? $block->asset),
            'buttonLabel' => $adminText('choose_media'),
            'replaceLabel' => $adminText('replace_background'),
            'clearLabel' => $adminText('remove'),
            'accept' => 'image',
            'panelMode' => 'overlay',
            'panelTitle' => $adminText('choose_background'),
            'compactControls' => true,
            'resultsVariant' => 'compact-list',
            'showUpload' => false,
            'selectorCard' => false,
            'showPreviewGrid' => true,
            'selectorHelperText' => $adminText('asset_help'),
        ])

        <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
                <label for="background_position">{{ $adminText('position_label') }}</label>
                <select id="background_position" name="background_position" class="wb-select">
                    @foreach ([
                        'center' => $adminText('center'),
                        'top' => $adminText('top'),
                        'bottom' => $adminText('bottom'),
                        'left' => $adminText('left'),
                        'right' => $adminText('right'),
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected($backgroundPosition === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="background_overlay">{{ $adminText('overlay_label') }}</label>
                <select id="background_overlay" name="background_overlay" class="wb-select">
                    @foreach ($overlayOptions as $value => $label)
                        <option value="{{ $value }}" @selected($backgroundOverlay === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="wb-text-sm wb-text-muted">{{ $adminText($overlayInherits ? 'overlay_inherit_help' : 'overlay_help') }}</div>
            </div>
        </div>
    </div>
</div>
