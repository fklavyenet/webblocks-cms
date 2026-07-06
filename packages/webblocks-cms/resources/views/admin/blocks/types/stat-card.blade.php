@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.stat_card.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">{{ $adminText('eyebrow_label') }}</label>
        <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">{{ $adminText('value') }}</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('value_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">{{ $adminText('description') }}</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4">{{ old('content', $block->content) }}</textarea>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="url">{{ $adminText('optional_url') }}</label>
        <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->url) }}">
    </div>
</div>
