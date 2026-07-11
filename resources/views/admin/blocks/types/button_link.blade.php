@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.button_link.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="label">{{ $adminText('label') }}</label>
        <input id="label" name="label" class="wb-input" type="text" value="{{ old('label', $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="url">{{ $adminText('url') }}</label>
        <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->buttonLinkUrl()) }}" placeholder="/start-here" required>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('url_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="target">{{ $adminText('target') }}</label>
        <select id="target" name="target" class="wb-select">
            <option value="_self" @selected(old('target', $block->buttonLinkTarget()) === '_self')>{{ $adminText('same_tab') }}</option>
            <option value="_blank" @selected(old('target', $block->buttonLinkTarget()) === '_blank')>{{ $adminText('new_tab') }}</option>
        </select>
    </div>
</div>
