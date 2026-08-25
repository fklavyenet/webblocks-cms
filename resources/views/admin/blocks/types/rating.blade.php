@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.rating.'.$key, $adminLocale);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $isNonDefaultLocale = isset($activeLocale) && ! $isDefaultLocale;
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('system_title') }}</div>
            <div>{{ $adminText('system_help') }}</div>
        </div>
    </div>

    <input type="hidden" name="rating_scale" value="5">

    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="title">{{ $adminText('title_label') }}</label>
        <input type="text" id="title" name="title" class="wb-input" maxlength="255" value="{{ old('title', $block->title ?: ($settings['title'] ?? '')) }}" placeholder="{{ $adminText('title_placeholder') }}">
        <div class="wb-text-sm wb-text-muted">{{ $adminText('title_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">{{ $adminText('subtitle_label') }}</label>
        <textarea id="subtitle" name="subtitle" class="wb-textarea" rows="3" maxlength="255" placeholder="{{ $adminText('subtitle_placeholder') }}">{{ old('subtitle', $block->subtitle) }}</textarea>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('subtitle_help') }}</div>
    </div>

    @if (! $isNonDefaultLocale)
      <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="rating_allow_change">{{ $adminText('changes_label') }}</label>
            <select id="rating_allow_change" name="rating_allow_change" class="wb-select">
                <option value="1" @selected(old('rating_allow_change', ($settings['allow_change'] ?? true) ? '1' : '0') === '1')>{{ $adminText('allow_change') }}</option>
                <option value="0" @selected(old('rating_allow_change', ($settings['allow_change'] ?? true) ? '1' : '0') === '0')>{{ $adminText('keep_first') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="rating_show_summary">{{ $adminText('summary_label') }}</label>
            <select id="rating_show_summary" name="rating_show_summary" class="wb-select">
                <option value="1" @selected(old('rating_show_summary', ($settings['show_summary'] ?? true) ? '1' : '0') === '1')>{{ $adminText('show_summary') }}</option>
                <option value="0" @selected(old('rating_show_summary', ($settings['show_summary'] ?? true) ? '1' : '0') === '0')>{{ $adminText('hide_summary') }}</option>
            </select>
        </div>
      </div>
    @endif
</div>
