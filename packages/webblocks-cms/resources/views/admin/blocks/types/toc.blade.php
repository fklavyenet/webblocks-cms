@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.toc.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1">
        <label for="title">{{ $adminText('title_label') }}</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        <div class="wb-text-sm wb-text-muted">{{ $adminText('title_help') }}</div>
    </div>
</div>
