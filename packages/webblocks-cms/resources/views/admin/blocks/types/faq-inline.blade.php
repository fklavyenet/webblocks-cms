@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.faq.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">{{ $adminText('question') }}</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_content">{{ $adminText('answer') }}</label>
        <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="6" required>{{ old("{$prefix}.content", $block->content) }}</textarea>
    </div>
</div>
