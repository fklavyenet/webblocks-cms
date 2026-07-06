@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.image_inline.'.$key, $adminLocale);
@endphp

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_asset_id">{{ $adminText('image_asset_id') }}</label>
        <input id="block_{{ $index }}_asset_id" name="{{ $prefix }}[asset_id]" class="wb-input" type="number" min="1" value="{{ old("{$prefix}.asset_id", $block->asset_id) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_subtitle">{{ $adminText('alt_text') }}</label>
        <input id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-input" type="text" value="{{ old("{$prefix}.subtitle", $block->subtitle) }}">
    </div>
</div>

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">{{ $adminText('caption') }}</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_url">{{ $adminText('link_url') }}</label>
        <input id="block_{{ $index }}_url" name="{{ $prefix }}[url]" class="wb-input" type="text" value="{{ old("{$prefix}.url", $block->url) }}">
    </div>
</div>
