@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.sidebar_nav_group.'.$key, $adminLocale);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $allowedIcons = app(\WebBlocks\Cms\Support\Icons\IconCatalog::class)->navigationPickerOptions(old('sidebar_nav_group_icon', $settings['icon'] ?? ''), $settings['icon'] ?? null);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>{{ $adminText('system_help') }}</div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('group_label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sidebar_nav_group_icon">{{ $adminText('icon') }}</label>
            <select id="sidebar_nav_group_icon" name="sidebar_nav_group_icon" class="wb-select">
                <option value="">{{ $adminText('no_icon') }}</option>
                @foreach ($allowedIcons as $icon)
                    <option value="{{ $icon['slug'] }}" @selected(old('sidebar_nav_group_icon', $settings['icon'] ?? '') === $icon['slug'])>{{ $icon['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="name">{{ $adminText('admin_label') }}</label>
            <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $settings['layout_name'] ?? '') }}" placeholder="{{ $adminText('admin_placeholder') }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sidebar_nav_group_initially_open">{{ $adminText('initially_open') }}</label>
            <select id="sidebar_nav_group_initially_open" name="sidebar_nav_group_initially_open" class="wb-select">
                <option value="0" @selected((string) old('sidebar_nav_group_initially_open', ($settings['initially_open'] ?? false) ? '1' : '0') === '0')>{{ $adminText('closed') }}</option>
                <option value="1" @selected((string) old('sidebar_nav_group_initially_open', ($settings['initially_open'] ?? false) ? '1' : '0') === '1')>{{ $adminText('open') }}</option>
            </select>
        </div>
    </div>
</div>
