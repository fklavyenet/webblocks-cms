@php
    $settings = $block->settings ?? [];
    $showModeToggle = old('header_actions_show_mode_toggle', ($settings['show_mode_toggle'] ?? true) ? '1' : '0');
    $showAccentToggle = old('header_actions_show_accent_toggle', ($settings['show_accent_toggle'] ?? true) ? '1' : '0');
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">System Header Actions</div>
            <div>Header Actions renders compact public utility controls such as color mode and accent selection. It pairs naturally with Breadcrumb in the Header slot and is not a navigation menu.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="header_actions_show_mode_toggle">Color mode control</label>
            <select id="header_actions_show_mode_toggle" name="header_actions_show_mode_toggle" class="wb-select">
                <option value="1" @selected((string) $showModeToggle === '1')>Show mode toggle</option>
                <option value="0" @selected((string) $showModeToggle === '0')>Hide mode toggle</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="header_actions_show_accent_toggle">Accent control</label>
            <select id="header_actions_show_accent_toggle" name="header_actions_show_accent_toggle" class="wb-select">
                <option value="1" @selected((string) $showAccentToggle === '1')>Show accent toggle</option>
                <option value="0" @selected((string) $showAccentToggle === '0')>Hide accent toggle</option>
            </select>
        </div>
    </div>
</div>
