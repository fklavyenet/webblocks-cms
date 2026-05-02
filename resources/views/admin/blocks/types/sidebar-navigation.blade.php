@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Navigation ARIA label is translated per locale. Menu selection and display settings stay shared.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>This block renders <code>nav.wb-sidebar-nav</code> and one inner <code>div.wb-sidebar-section</code>. Choose a shared navigation menu or leave it empty to keep manual child blocks.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="sidebar_navigation_menu_key">Navigation Menu</label>
        <select id="sidebar_navigation_menu_key" name="sidebar_navigation_menu_key" class="wb-select">
            <option value="">Manual child blocks</option>
            @foreach (\App\Models\NavigationItem::menuOptions() as $key => $menuLabel)
                <option value="{{ $key }}" @selected(old('sidebar_navigation_menu_key', $settings['menu_key'] ?? '') === $key)>{{ $menuLabel }}</option>
            @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">When a menu is selected, public rendering comes from Navigation items instead of child Sidebar Nav blocks.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">Navigation ARIA Label</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title ?: 'Documentation navigation') }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="name">Admin Label</label>
        <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $settings['layout_name'] ?? '') }}" placeholder="Docs navigation">
        <div class="wb-text-sm wb-text-muted">Editor-only label for tree navigation. Not rendered publicly.</div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <input type="hidden" name="sidebar_navigation_show_icons" value="0">
            <label class="wb-inline-flex wb-items-center wb-gap-2" for="sidebar_navigation_show_icons">
                <input id="sidebar_navigation_show_icons" name="sidebar_navigation_show_icons" type="checkbox" value="1" @checked(old('sidebar_navigation_show_icons', array_key_exists('show_icons', $settings) ? (bool) $settings['show_icons'] : true))>
                <span>Show icons</span>
            </label>
            <div class="wb-text-sm wb-text-muted">Uses optional icon slugs stored on Navigation items.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sidebar_navigation_active_matching">Active Matching</label>
            <select id="sidebar_navigation_active_matching" name="sidebar_navigation_active_matching" class="wb-select">
                @foreach (['path' => 'Path', 'current-page' => 'Current page', 'exact' => 'Exact URL'] as $value => $optionLabel)
                    <option value="{{ $value }}" @selected(old('sidebar_navigation_active_matching', $settings['active_matching'] ?? 'path') === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
