@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.navbar_navigation.'.$key, $adminLocale);
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
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>{{ $adminText('system_help') }}</div>
    </div>

    @if ($siteId && $navigationItemCount === 0)
        <div class="wb-alert wb-alert-warning">
            <div>{!! $adminText('empty_warning') !!}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="navbar_navigation_menu_key">{{ $adminText('menu_label') }}</label>
        <select id="navbar_navigation_menu_key" name="navbar_navigation_menu_key" class="wb-select" required>
            @foreach (\WebBlocks\Cms\Models\NavigationItem::menuOptions() as $key => $menuLabel)
                <option value="{{ $key }}" @selected(old('navbar_navigation_menu_key', $settings['menu_key'] ?? $block->navbarNavigationMenuKey()) === $key)>{{ $menuLabel }}</option>
            @endforeach
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">{{ $adminText('aria_label') }}</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title ?: $adminText('default_aria_label')) }}">
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="navbar_navigation_active_indicator">{{ $adminText('active_indicator') }}</label>
            <select id="navbar_navigation_active_indicator" name="navbar_navigation_active_indicator" class="wb-select">
                @foreach ([
                    'underline' => $adminText('underline'),
                    'pill' => $adminText('pill'),
                    'dot' => $adminText('accent_dot'),
                    'background' => $adminText('background'),
                    'none' => $adminText('none'),
                ] as $value => $optionLabel)
                    <option value="{{ $value }}" @selected(old('navbar_navigation_active_indicator', $settings['active_indicator'] ?? 'underline') === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">{{ $adminText('indicator_help') }}</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="navbar_navigation_active_matching">{{ $adminText('active_matching') }}</label>
            <select id="navbar_navigation_active_matching" name="navbar_navigation_active_matching" class="wb-select">
                @foreach ([
                    'path' => $adminText('path'),
                    'section' => $adminText('section_path'),
                    'current-page' => $adminText('current_page'),
                    'exact' => $adminText('exact_url'),
                    'off' => $adminText('off'),
                ] as $value => $optionLabel)
                    <option value="{{ $value }}" @selected(old('navbar_navigation_active_matching', $settings['active_matching'] ?? 'path') === $value)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
