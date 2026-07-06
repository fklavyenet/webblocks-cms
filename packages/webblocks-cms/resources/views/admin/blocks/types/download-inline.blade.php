@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.download_inline.'.$key, $adminLocale);
@endphp

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">{{ $adminText('label') }}</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_subtitle">{{ $adminText('helper_label') }}</label>
        <input id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-input" type="text" value="{{ old("{$prefix}.subtitle", $block->subtitle) }}">
    </div>
</div>

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_asset_id">{{ $adminText('asset_id_label') }}</label>
        <input id="block_{{ $index }}_asset_id" name="{{ $prefix }}[asset_id]" class="wb-input" type="number" min="1" value="{{ old("{$prefix}.asset_id", $block->asset_id) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_variant">{{ $adminText('variant_label') }}</label>
        <select id="block_{{ $index }}_variant" name="{{ $prefix }}[variant]" class="wb-select">
            @foreach ([
                'primary' => $adminText('variant_primary'),
                'secondary' => $adminText('variant_secondary'),
                'ghost' => $adminText('variant_ghost'),
            ] as $variant => $label)
                <option value="{{ $variant }}" @selected(old("{$prefix}.variant", $block->variant ?: 'secondary') === $variant)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
