@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.header_actions.'.$key, $adminLocale);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $showModeToggle = old('header_actions_show_mode_toggle', ($settings['show_mode_toggle'] ?? true) ? '1' : '0');
    $showAccentToggle = old('header_actions_show_accent_toggle', ($settings['show_accent_toggle'] ?? false) ? '1' : '0');
    $showSearch = old('header_actions_show_search', ($settings['show_search'] ?? true) ? '1' : '0');
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('system_title') }}</div>
            <div>{{ $adminText('system_help') }}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="header_actions_show_mode_toggle">{{ $adminText('mode_label') }}</label>
            <select id="header_actions_show_mode_toggle" name="header_actions_show_mode_toggle" class="wb-select">
                <option value="1" @selected((string) $showModeToggle === '1')>{{ $adminText('show_mode') }}</option>
                <option value="0" @selected((string) $showModeToggle === '0')>{{ $adminText('hide_mode') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="header_actions_show_accent_toggle">{{ $adminText('accent_label') }}</label>
            <select id="header_actions_show_accent_toggle" name="header_actions_show_accent_toggle" class="wb-select">
                <option value="1" @selected((string) $showAccentToggle === '1')>{{ $adminText('show_accent') }}</option>
                <option value="0" @selected((string) $showAccentToggle === '0')>{{ $adminText('hide_accent') }}</option>
            </select>
            <div class="wb-text-sm wb-text-muted">{{ $adminText('accent_help') }}</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="header_actions_show_search">{{ $adminText('search_label') }}</label>
            <select id="header_actions_show_search" name="header_actions_show_search" class="wb-select">
                <option value="1" @selected((string) $showSearch === '1')>{{ $adminText('show_search') }}</option>
                <option value="0" @selected((string) $showSearch === '0')>{{ $adminText('hide_search') }}</option>
            </select>
        </div>
    </div>
</div>
