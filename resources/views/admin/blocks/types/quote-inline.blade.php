@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.quote_inline.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_content">{{ $adminText('quote_text') }}</label>
        <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="6" required>{{ old("{$prefix}.content", $block->content) }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_title">{{ $adminText('author') }}</label>
            <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_subtitle">{{ $adminText('source') }}</label>
            <input id="block_{{ $index }}_subtitle" name="{{ $prefix }}[subtitle]" class="wb-input" type="text" value="{{ old("{$prefix}.subtitle", $block->subtitle) }}">
        </div>
    </div>
</div>
