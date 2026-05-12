@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $siteId = $block->page?->site_id ?? \App\Models\Page::query()->whereKey($block->page_id)->value('site_id');
    $navigationItemCount = $siteId
        ? \App\Models\NavigationItem::query()->forSite($siteId)->visible()->count()
        : 0;
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Brand text is translated per locale. Menu selection, layout mode, logo, and visual settings stay shared.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">System Navigation</div>
            <div>Sticky Navbar is a system-owned navigation block. It renders CMS Navigation items from the selected menu instead of editorial JSON link lists.</div>
        </div>
    </div>

    @if ($siteId && $navigationItemCount === 0)
        <div class="wb-alert wb-alert-warning">
            <div>No visible navigation items exist for this site yet. Create them in <code>Admin -&gt; Navigation</code> before saving this navbar.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="sticky_navbar_menu_key">Navigation Menu</label>
            <select id="sticky_navbar_menu_key" name="sticky_navbar_menu_key" class="wb-select" required>
                @foreach (\App\Models\NavigationItem::menuOptions() as $key => $menuLabel)
                    <option value="{{ $key }}" @selected(old('sticky_navbar_menu_key', $settings['menu_key'] ?? $block->stickyNavbarMenuKey()) === $key)>{{ $menuLabel }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">Recommended for header Shared Slots. Navigation item order and visibility come from the site menu.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="title">Brand Text</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" placeholder="Falls back to the site name">
            <div class="wb-text-sm wb-text-muted">Leave empty to use the current site display name when available.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label>Logo</label>
            @include('admin.media.asset-picker-panel', [
                'name' => 'sticky-navbar-logo',
                'inputId' => 'asset_id',
                'fieldName' => 'asset_id',
                'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
                'buttonLabel' => 'Choose from Media',
                'replaceLabel' => 'Replace Logo',
                'clearLabel' => 'Remove',
                'accept' => 'image',
            ])
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sticky_navbar_logo_path">Logo Path Override</label>
            <input id="sticky_navbar_logo_path" name="sticky_navbar_logo_path" class="wb-input" type="text" value="{{ old('sticky_navbar_logo_path', $settings['logo_path'] ?? '') }}" placeholder="/site/example/logo.svg">
            <div class="wb-text-sm wb-text-muted">Optional direct public path or URL. Media logo is used first when both are present.</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="sticky_navbar_brand_url">Brand URL</label>
        <input id="sticky_navbar_brand_url" name="sticky_navbar_brand_url" class="wb-input" type="text" value="{{ old('sticky_navbar_brand_url', $settings['brand_url'] ?? '') }}" placeholder="Falls back to the site home URL">
    </div>
</div>
