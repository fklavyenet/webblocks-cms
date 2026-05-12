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
            <div>Navigation ARIA label is translated per locale. Menu selection stays shared.</div>
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
            @foreach (\App\Models\NavigationItem::menuOptions() as $key => $menuLabel)
                <option value="{{ $key }}" @selected(old('navbar_navigation_menu_key', $settings['menu_key'] ?? $block->navbarNavigationMenuKey()) === $key)>{{ $menuLabel }}</option>
            @endforeach
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">Navigation ARIA Label</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title ?: 'Primary navigation') }}">
    </div>
</div>
