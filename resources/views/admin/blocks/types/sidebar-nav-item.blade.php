@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.sidebar_nav_item.'.$key, $adminLocale);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $allowedIcons = app(\WebBlocks\Cms\Support\Icons\IconCatalog::class)->navigationPickerOptions(old('sidebar_nav_item_icon', $settings['icon'] ?? ''), $settings['icon'] ?? null);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">{{ $adminText('url') }}</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $settings['url'] ?? $block->url) }}" placeholder="/about" required>
        </div>
    </div>

    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="sidebar_nav_item_icon">{{ $adminText('icon') }}</label>
            <select id="sidebar_nav_item_icon" name="sidebar_nav_item_icon" class="wb-select">
                <option value="">{{ $adminText('no_icon') }}</option>
                @foreach ($allowedIcons as $icon)
                    <option value="{{ $icon['slug'] }}" @selected(old('sidebar_nav_item_icon', $settings['icon'] ?? '') === $icon['slug'])>{{ $icon['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sidebar_nav_item_active_mode">{{ $adminText('active_matching') }}</label>
            <select id="sidebar_nav_item_active_mode" name="sidebar_nav_item_active_mode" class="wb-select">
                @foreach (['path' => $adminText('path'), 'exact' => $adminText('exact_url'), 'current-page' => $adminText('current_page'), 'manual' => $adminText('manual')] as $value => $label)
                    <option value="{{ $value }}" @selected(old('sidebar_nav_item_active_mode', $settings['active_mode'] ?? 'path') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="target">{{ $adminText('target') }}</label>
            <select id="target" name="target" class="wb-select">
                <option value="_self" @selected(old('target', $settings['target'] ?? '_self') === '_self')>{{ $adminText('same_tab') }}</option>
                <option value="_blank" @selected(old('target', $settings['target'] ?? '_self') === '_blank')>{{ $adminText('new_tab') }}</option>
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="sidebar_nav_item_manual_active">{{ $adminText('manual_active_fallback') }}</label>
        <select id="sidebar_nav_item_manual_active" name="sidebar_nav_item_manual_active" class="wb-select">
            <option value="0" @selected((string) old('sidebar_nav_item_manual_active', ($settings['manual_active'] ?? false) ? '1' : '0') === '0')>{{ $adminText('off') }}</option>
            <option value="1" @selected((string) old('sidebar_nav_item_manual_active', ($settings['manual_active'] ?? false) ? '1' : '0') === '1')>{{ $adminText('on') }}</option>
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('manual_active_help') }}</div>
    </div>
</div>
