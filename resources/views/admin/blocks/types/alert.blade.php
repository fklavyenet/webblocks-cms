@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.alert.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="title">{{ $adminText('title') }}</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">{{ $adminText('content') }}</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4" required>{{ old('content', $block->content) }}</textarea>
    </div>
</div>
