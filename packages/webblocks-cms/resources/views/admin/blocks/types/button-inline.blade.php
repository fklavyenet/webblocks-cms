@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.button.'.$key, $adminLocale);
@endphp

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">{{ $adminText('label') }}</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_url">{{ $adminText('url') }}</label>
        <input id="block_{{ $index }}_url" name="{{ $prefix }}[url]" class="wb-input" type="text" value="{{ old("{$prefix}.url", $block->url) }}" required>
    </div>
</div>

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_subtitle">{{ $adminText('target') }}</label>
        <select id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-select">
            <option value="_self" @selected(old("{$prefix}.subtitle", $block->subtitle ?: '_self') === '_self')>{{ $adminText('same_tab') }}</option>
            <option value="_blank" @selected(old("{$prefix}.subtitle", $block->subtitle) === '_blank')>{{ $adminText('new_tab') }}</option>
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_variant">{{ $adminText('variant') }}</label>
        <select id="block_{{ $index }}_variant" name="{{ $prefix }}[variant]" class="wb-select">
            @foreach (['primary', 'secondary', 'ghost', 'danger'] as $variant)
                <option value="{{ $variant }}" @selected(old("{$prefix}.variant", $block->variant ?: 'primary') === $variant)>{{ $adminText('variant_'.$variant) }}</option>
            @endforeach
        </select>
    </div>
</div>
