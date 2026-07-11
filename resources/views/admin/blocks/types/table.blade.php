@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.table.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('title_label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">{{ $adminText('style_label') }}</label>
            <select id="variant" name="variant" class="wb-select">
                <option value="header-row" @selected(old('variant', $block->variant ?: 'header-row') === 'header-row')>{{ $adminText('header_row') }}</option>
                <option value="plain" @selected(old('variant', $block->variant) === 'plain')>{{ $adminText('plain_rows') }}</option>
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">{{ $adminText('rows_label') }}</label>
        <textarea id="content" name="content" class="wb-textarea" rows="8" placeholder="{{ $adminText('rows_placeholder') }}">{{ old('content', $block->content) }}</textarea>
        <div class="wb-text-sm wb-text-muted">{!! $adminText('rows_help') !!}</div>
    </div>
</div>
