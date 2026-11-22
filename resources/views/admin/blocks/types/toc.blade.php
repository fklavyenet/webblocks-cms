@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.toc.'.$key, $adminLocale);
    $localeCode = ($activeLocale ?? null)?->code ?? $block->renderLocaleCode()
        ?? app(\WebBlocks\Cms\Support\Locales\LocaleResolver::class)->default()->code;
    $defaultTitle = $block::tocDefaultTitle($localeCode);
    $title = $block->exists ? $block->tocTitle($localeCode) : $defaultTitle;
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-stack wb-gap-1" data-wb-toc-title-editor>
        <label for="title">{{ $adminText('title_label') }}</label>
        <input id="title" name="title" class="wb-input" type="text" maxlength="255" value="{{ old('title', $title ?? $defaultTitle) }}" data-wb-toc-title-default="{{ $defaultTitle }}" aria-describedby="toc_title_help">
        <div id="toc_title_help" class="wb-text-sm wb-text-muted">{{ $adminText('title_help') }}</div>
        <div class="wb-cluster">
            <input type="hidden" name="toc_hide_title" value="0">
            <label class="wb-check">
                <input type="checkbox" name="toc_hide_title" value="1" data-wb-toc-title-hidden @checked(old('toc_hide_title', $title === null))>
                <span>{{ $adminText('hide_title') }}</span>
            </label>
            <button type="button" class="wb-btn wb-btn-outline wb-btn-sm" data-wb-toc-title-reset aria-controls="title">{{ $adminText('reset_title') }}</button>
        </div>
    </div>
    <div class="wb-stack wb-gap-1">
        <label for="toc_position">{{ $adminText('position_label') }}</label>
        <select id="toc_position" name="toc_position" class="wb-select">
            <option value="right" @selected(old('toc_position', $block->tocPosition()) === 'right')>{{ $adminText('position_right') }}</option>
            <option value="left" @selected(old('toc_position', $block->tocPosition()) === 'left')>{{ $adminText('position_left') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('position_help') }}</div>
    </div>
</div>
