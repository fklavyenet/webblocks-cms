@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.sidebar_navigation.'.$key, $adminLocale);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>{!! $adminText('system_help') !!}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="sidebar_navigation_menu_key">{{ $adminText('menu_label') }}</label>
        <select id="sidebar_navigation_menu_key" name="sidebar_navigation_menu_key" class="wb-select">
            <option value="">{{ $adminText('manual_children') }}</option>
            @foreach (\WebBlocks\Cms\Models\NavigationItem::menuOptions() as $key => $menuLabel)
                <option value="{{ $key }}" @selected(old('sidebar_navigation_menu_key', $settings['menu_key'] ?? '') === $key)>{{ $menuLabel }}</option>
            @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('menu_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">{{ $adminText('aria_label') }}</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title ?: $adminText('default_aria_label')) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="name">{{ $adminText('admin_label') }}</label>
        <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $settings['layout_name'] ?? '') }}" placeholder="{{ $adminText('admin_placeholder') }}">
        <div class="wb-text-sm wb-text-muted">{{ $adminText('admin_help') }}</div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <input type="hidden" name="sidebar_navigation_show_icons" value="0">
            <label class="wb-inline-flex wb-items-center wb-gap-2" for="sidebar_navigation_show_icons">
                <input id="sidebar_navigation_show_icons" name="sidebar_navigation_show_icons" type="checkbox" value="1" @checked(old('sidebar_navigation_show_icons', array_key_exists('show_icons', $settings) ? (bool) $settings['show_icons'] : true))>
                <span>{{ $adminText('show_icons') }}</span>
            </label>
            <div class="wb-text-sm wb-text-muted">{{ $adminText('icons_help') }}</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sidebar_navigation_active_matching">{{ $adminText('active_matching') }}</label>
            <select id="sidebar_navigation_active_matching" name="sidebar_navigation_active_matching" class="wb-select">
                @foreach ([
                    'path' => $adminText('path'),
                    'current-page' => $adminText('current_page'),
                    'exact' => $adminText('exact_url'),
                ] as $value => $optionLabel)
                    <option value="{{ $value }}" @selected(old('sidebar_navigation_active_matching', $settings['active_matching'] ?? 'path') === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
