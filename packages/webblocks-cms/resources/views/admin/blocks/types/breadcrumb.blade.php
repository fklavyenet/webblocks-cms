@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.breadcrumb.'.$key, $adminLocale);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $homeLabel = old('breadcrumb_home_label', $settings['home_label'] ?? '');
    $includeCurrent = old('breadcrumb_include_current', ($settings['include_current'] ?? true) ? '1' : '0');
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('system_title') }}</div>
            <div>{{ $adminText('system_help') }}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="breadcrumb_home_label">{{ $adminText('home_label') }}</label>
            <input id="breadcrumb_home_label" name="breadcrumb_home_label" class="wb-input" type="text" value="{{ $homeLabel }}" placeholder="{{ $adminText('home_placeholder') }}">
            <span class="wb-text-sm wb-text-muted">{{ $adminText('home_help') }}</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="breadcrumb_include_current">{{ $adminText('current_label') }}</label>
            <select id="breadcrumb_include_current" name="breadcrumb_include_current" class="wb-select">
                <option value="1" @selected((string) $includeCurrent === '1')>{{ $adminText('include_current') }}</option>
                <option value="0" @selected((string) $includeCurrent === '0')>{{ $adminText('hide_current') }}</option>
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('current_help') }}</span>
        </div>
    </div>
</div>
