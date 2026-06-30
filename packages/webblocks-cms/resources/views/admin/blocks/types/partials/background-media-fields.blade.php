@php
    $backgroundSettings = is_array($block->settings) ? $block->settings : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);
    $backgroundPosition = old('background_position', $backgroundSettings['background_position'] ?? 'center');
    $backgroundOverlay = old('background_overlay', $backgroundSettings['background_overlay'] ?? 'soft');
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-header"><strong>Background Media</strong></div>
    <div class="wb-card-body wb-stack wb-gap-4">
        @include('webblocks-cms::admin.media.asset-picker-panel', [
            'name' => 'background-asset',
            'inputId' => 'media_id',
            'fieldName' => 'media_id',
            'selectedAsset' => old('media_id') ? null : ($selectedAsset ?? $block->asset),
            'buttonLabel' => 'Choose from Media',
            'replaceLabel' => 'Replace Background',
            'clearLabel' => 'Remove',
            'accept' => 'image',
            'panelMode' => 'overlay',
            'panelTitle' => 'Choose Background Image',
            'compactControls' => true,
            'resultsVariant' => 'compact-list',
            'showUpload' => false,
            'selectorCard' => false,
            'showPreviewGrid' => true,
            'selectorHelperText' => 'Choose an internal image asset from the shared media library.',
        ])

        <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
                <label for="background_position">Background Position</label>
                <select id="background_position" name="background_position" class="wb-select">
                    @foreach ([
                        'center' => 'Center',
                        'top' => 'Top',
                        'bottom' => 'Bottom',
                        'left' => 'Left',
                        'right' => 'Right',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected($backgroundPosition === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="background_overlay">Overlay</label>
                <select id="background_overlay" name="background_overlay" class="wb-select">
                    @foreach ([
                        'soft' => 'Soft',
                        'medium' => 'Medium',
                        'strong' => 'Strong',
                        'none' => 'None',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected($backgroundOverlay === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="wb-text-sm wb-text-muted">The renderer publishes media as a background image; public CSS controls cover, position, and overlay behavior.</div>
            </div>
        </div>
    </div>
</div>
