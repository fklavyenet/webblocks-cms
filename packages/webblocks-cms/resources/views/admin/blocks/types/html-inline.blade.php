@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.trusted_html.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-1">
    <label for="block_{{ $index }}_content">{{ $adminText('raw_label') }}</label>
    <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="10" required>{{ old("{$prefix}.content", $block->content) }}</textarea>
</div>
