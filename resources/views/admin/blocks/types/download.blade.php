@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.download.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @include('webblocks-cms::admin.media.asset-picker-panel', [
        'name' => 'download-asset',
        'inputId' => 'asset_id',
        'fieldName' => 'asset_id',
        'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->downloadAsset()),
        'buttonLabel' => $adminText('choose_media'),
        'replaceLabel' => $adminText('replace_document'),
        'clearLabel' => $adminText('remove'),
        'accept' => 'file',
        'panelMode' => 'overlay',
        'panelTitle' => $adminText('choose_file'),
        'compactControls' => true,
        'resultsVariant' => 'compact-list',
        'showUpload' => false,
        'selectorCard' => true,
        'selectorCardTitle' => $adminText('file_card_title'),
        'selectorHelperText' => $adminText('file_card_help'),
    ])

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">{{ $adminText('helper_label') }}</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="variant">{{ $adminText('variant_label') }}</label>
        <select id="variant" name="variant" class="wb-select">
            @foreach ([
                'primary' => $adminText('variant_primary'),
                'secondary' => $adminText('variant_secondary'),
                'ghost' => $adminText('variant_ghost'),
            ] as $variant => $label)
                <option value="{{ $variant }}" @selected(old('variant', $block->variant ?: 'secondary') === $variant)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
