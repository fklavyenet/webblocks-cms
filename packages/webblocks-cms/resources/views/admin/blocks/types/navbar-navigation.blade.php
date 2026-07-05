@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $siteId = $block->page?->site_id ?? \WebBlocks\Cms\Models\Page::query()->whereKey($block->page_id)->value('site_id');
    $navigationItemCount = $siteId
        ? \WebBlocks\Cms\Models\NavigationItem::query()->forSite($siteId)->visible()->count()
        : 0;
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Navigation ARIA label is translated per locale. Menu selection and active-state display settings stay shared.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>This block renders navbar links from a selected CMS Navigation menu using WebBlocks UI navbar classes. Place it inside a Navbar block.</div>
    </div>

    @if ($siteId && $navigationItemCount === 0)
        <div class="wb-alert wb-alert-warning">
            <div>No visible navigation items exist for this site yet. Create them in <code>Admin -&gt; Navigation</code> before saving this block.</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="navbar_navigation_menu_key">Navigation Menu</label>
        <select id="navbar_navigation_menu_key" name="navbar_navigation_menu_key" class="wb-select" required>
            @foreach (\WebBlocks\Cms\Models\NavigationItem::menuOptions() as $key => $menuLabel)
                <option value="{{ $key }}" @selected(old('navbar_navigation_menu_key', $settings['menu_key'] ?? $block->navbarNavigationMenuKey()) === $key)>{{ $menuLabel }}</option>
            @endforeach
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">Navigation ARIA Label</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title ?: 'Primary navigation') }}">
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="navbar_navigation_active_indicator">Active Indicator</label>
            <select id="navbar_navigation_active_indicator" name="navbar_navigation_active_indicator" class="wb-select">
                @foreach (['underline' => 'Underline', 'pill' => 'Pill', 'dot' => 'Accent dot', 'background' => 'Background', 'none' => 'None'] as $value => $optionLabel)
                    <option value="{{ $value }}" @selected(old('navbar_navigation_active_indicator', $settings['active_indicator'] ?? 'underline') === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">Uses WebBlocks UI active navbar classes on the rendered menu.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="navbar_navigation_active_matching">Active Matching</label>
            <select id="navbar_navigation_active_matching" name="navbar_navigation_active_matching" class="wb-select">
                @foreach (['path' => 'Path', 'section' => 'Section path', 'current-page' => 'Current page', 'exact' => 'Exact URL', 'off' => 'Off'] as $value => $optionLabel)
                    <option value="{{ $value }}" @selected(old('navbar_navigation_active_matching', $settings['active_matching'] ?? 'path') === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
