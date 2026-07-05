<!DOCTYPE html>
@php
    use WebBlocks\Cms\Support\System\SystemSettings;
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;
    use WebBlocks\Cms\Support\WebBlocks;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key) => $adminTranslator->admin($key, $adminLocale);
@endphp
<html lang="{{ str_replace('_', '-', $adminLocale) }}">
    @php
        $resolvedAdminBrowserTitle = app(SystemSettings::class)->adminBrowserTitle($adminBrowserTitle ?? $title ?? null);
        $adminCssPath = public_path('cms/css/admin.css');
        $adminJsAssets = [
            'core' => public_path('cms/js/admin/core.js'),
        ];
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('webblocks-cms::partials.head-meta', [
            'title' => $resolvedAdminBrowserTitle,
            'metaDescription' => $metaDescription ?? WebBlocks::slogan(),
        ])

        <link rel="stylesheet" href="{{ WebBlocks::uiCssUrl() }}">
        <link rel="stylesheet" href="{{ WebBlocks::iconsCssUrl() }}">
        @if (is_file($adminCssPath))
            <link rel="stylesheet" href="{{ asset('cms/css/admin.css') }}?v={{ filemtime($adminCssPath) }}">
        @endif
        @stack('styles')
    </head>
        <body data-wb-admin-login-url="{{ route('webblocks.auth.login') }}">
        @php
            $user = auth()->user();
            $userInitials = collect(preg_split('/\s+/', trim($user?->name ?? 'User')))
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_substr($part, 0, 1))
                ->implode('');

            $menuItems = [
                ['label' => $adminText('navigation.dashboard'), 'route' => 'admin.dashboard', 'active' => ['admin.dashboard'], 'icon' => 'wb-icon-layout-dashboard'],
                ['label' => $adminText('navigation.sites'), 'route' => 'admin.sites.index', 'active' => ['admin.sites.index', 'admin.sites.create', 'admin.sites.store', 'admin.sites.edit', 'admin.sites.update', 'admin.sites.clone', 'admin.sites.clone.prefill', 'admin.sites.clone.store', 'admin.sites.promote', 'admin.sites.promote.*', 'admin.sites.delete', 'admin.sites.destroy'], 'icon' => 'wb-icon-globe'],
                ['label' => $adminText('navigation.pages'), 'route' => 'admin.pages.index', 'active' => ['admin.pages.*'], 'icon' => 'wb-icon-file-text'],
                ['label' => $adminText('navigation.shared_slots'), 'route' => 'admin.shared-slots.index', 'active' => ['admin.shared-slots.*'], 'icon' => 'wb-icon-layers'],
                ['label' => $adminText('navigation.navigation'), 'route' => 'admin.navigation.index', 'active' => ['admin.navigation.*'], 'icon' => 'wb-icon-menu'],
                ['label' => $adminText('navigation.media'), 'route' => 'admin.media.index', 'active' => ['admin.media.*'], 'icon' => 'wb-icon-image'],
                ['label' => $adminText('navigation.contact_messages'), 'route' => 'admin.contact-messages.index', 'active' => ['admin.contact-messages.*'], 'icon' => 'wb-icon-mail'],
                ['label' => $adminText('navigation.engagement'), 'route' => 'admin.engagement.comments.index', 'active' => ['admin.engagement.*'], 'icon' => 'wb-icon-star'],
            ];

            if (! $user?->can('access-system')) {
                $menuItems = array_values(array_filter($menuItems, fn (array $item) => $item['route'] !== 'admin.sites.index'));
            }

            if ($user?->can('access-system')) {
                array_splice($menuItems, 3, 0, [[
                    'label' => $adminText('navigation.blocks'),
                    'route' => 'admin.blocks.index',
                    'active' => ['admin.blocks.index'],
                    'icon' => 'wb-icon-box',
                ]]);
            }

            $sidebarGroups = [];

            if ($user?->can('access-system')) {
                $sidebarGroups[] = [
                    'key' => 'system',
                    'label' => $adminText('navigation.system'),
                    'icon' => 'wb-icon-palette',
                        'items' => [
                            ['label' => $adminText('navigation.domains'), 'route' => 'admin.domains.index', 'active' => ['admin.domains.*', 'admin.sites.domains.*']],
                            ['label' => $adminText('navigation.icons'), 'route' => 'admin.system.icons.index', 'active' => ['admin.system.icons.*']],
                            ['label' => $adminText('navigation.users'), 'route' => 'admin.users.index', 'active' => ['admin.users.*']],
                            ['label' => $adminText('navigation.locales'), 'route' => 'admin.locales.index', 'active' => ['admin.locales.*']],
                            ['label' => $adminText('navigation.page_layouts'), 'route' => 'admin.page-layouts.index', 'active' => ['admin.page-layouts.*']],
                            ['label' => $adminText('navigation.slot_types'), 'route' => 'admin.slot-types.index', 'active' => ['admin.slot-types.*']],
                            ['label' => $adminText('navigation.block_types'), 'route' => 'admin.block-types.index', 'active' => ['admin.block-types.*']],
                            ['label' => $adminText('navigation.settings'), 'route' => 'admin.system.settings.edit', 'active' => ['admin.system.settings.*']],
                            ['label' => $adminText('navigation.api_tokens'), 'route' => 'admin.system.api-tokens.index', 'active' => ['admin.system.api-tokens.*']],
                            ['label' => $adminText('navigation.plugins'), 'route' => 'admin.system.plugins.index', 'active' => ['admin.system.plugins.*']],
                            ['label' => $adminText('navigation.visitor_reports'), 'route' => 'admin.reports.visitors.index', 'active' => ['admin.reports.visitors.*']],
                        ],
                    ];

                $sidebarGroups[] = [
                    'key' => 'maintenance',
                    'label' => $adminText('navigation.maintenance'),
                    'icon' => 'wb-icon-file',
                    'items' => [
                        ['label' => $adminText('navigation.search_rebuild'), 'route' => 'admin.system.search.index', 'active' => ['admin.system.search.*']],
                        ['label' => $adminText('navigation.backups'), 'route' => 'admin.system.backups.index', 'active' => ['admin.system.backups.*']],
                        ['label' => $adminText('navigation.export_import'), 'route' => 'admin.site-transfers.exports.index', 'active' => ['admin.site-transfers.*']],
                        ['label' => $adminText('navigation.update'), 'route' => 'admin.system.updates.index', 'active' => ['admin.system.updates.*']],
                    ],
                ];
            }

            foreach (app(\WebBlocks\Cms\Support\Plugins\PluginRegistry::class)->menuItems($user) as $pluginMenuItem) {
                $item = $pluginMenuItem['item'];

                if ($item->routeName() === null || ! Route::has($item->routeName())) {
                    continue;
                }

                $groupName = $item->groupName() ?: 'System';
                $groupKey = $groupName === 'System' ? 'system' : null;
                $groupIndex = collect($sidebarGroups)->search(
                    fn ($group) => ($groupKey !== null && ($group['key'] ?? null) === $groupKey) || $group['label'] === $groupName
                );

                if ($groupIndex === false) {
                    $sidebarGroups[] = [
                        'label' => $groupName,
                        'icon' => 'wb-icon-plug',
                        'items' => [],
                    ];
                    $groupIndex = array_key_last($sidebarGroups);
                }

                $sidebarGroups[$groupIndex]['items'][] = [
                    'label' => $item->labelText(),
                    'route' => $item->routeName(),
                    'url' => route($item->routeName(), [], false),
                    'active' => [$pluginMenuItem['plugin']->routeNamePrefix().'.*'],
                    'icon' => $item->iconClass(),
                ];
            }

            $matchesActiveRoute = static fn (array $item): bool => collect($item['active'] ?? [])->contains(
                fn (string $pattern) => request()->routeIs($pattern)
            );

            $adminNavbarBreadcrumb = $breadcrumb ?? null;

            if (! $adminNavbarBreadcrumb) {
                $currentTitle = $heading ?? $title ?? $adminText('navigation.dashboard');

                if ($currentTitle === 'Dashboard') {
                    $currentTitle = $adminText('navigation.dashboard');
                }
                $activeTopItem = collect($menuItems)->first(fn (array $item) => $matchesActiveRoute($item));
                $activeGroup = collect($sidebarGroups)
                    ->map(function (array $group) use ($matchesActiveRoute) {
                        $activeItem = collect($group['items'])->first(fn (array $item) => $matchesActiveRoute($item));

                        return $activeItem ? ['group' => $group, 'item' => $activeItem] : null;
                    })
                    ->filter()
                    ->first();
                $breadcrumbItems = [];

                if (! request()->routeIs('admin.dashboard')) {
                    $breadcrumbItems[] = ['label' => $adminText('navigation.dashboard'), 'url' => route('admin.dashboard')];
                }

                if ($activeGroup) {
                    $breadcrumbItems[] = ['label' => $activeGroup['group']['label'], 'url' => null];
                    $activeItemLabel = $activeGroup['item']['label'] ?? $currentTitle;

                    if ($activeItemLabel !== $currentTitle) {
                        $breadcrumbItems[] = [
                            'label' => $activeItemLabel,
                            'url' => $activeGroup['item']['url'] ?? (isset($activeGroup['item']['route']) ? route($activeGroup['item']['route']) : null),
                        ];
                    }
                } elseif ($activeTopItem) {
                    $activeItemLabel = $activeTopItem['label'] ?? $currentTitle;

                    if ($activeItemLabel !== $currentTitle) {
                        $breadcrumbItems[] = ['label' => $activeItemLabel, 'url' => route($activeTopItem['route'])];
                    }
                }

                $breadcrumbItems[] = ['label' => $currentTitle, 'url' => null];

                $adminNavbarBreadcrumb = '<nav class="wb-breadcrumb wb-navbar-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list">';

                foreach ($breadcrumbItems as $index => $item) {
                    $isLast = $index === array_key_last($breadcrumbItems);
                    $adminNavbarBreadcrumb .= '<li class="wb-breadcrumb-item">';

                    if (! $isLast && ! empty($item['url'])) {
                        $adminNavbarBreadcrumb .= '<a class="wb-breadcrumb-link" href="'.e($item['url']).'">'.e($item['label']).'</a>';
                    } else {
                        $adminNavbarBreadcrumb .= '<span class="wb-breadcrumb-current"'.($isLast ? ' aria-current="page"' : '').'>'.e($item['label']).'</span>';
                    }

                    $adminNavbarBreadcrumb .= '</li>';
                }

                $adminNavbarBreadcrumb .= '</ol></nav>';
            }
        @endphp

        <div class="wb-dashboard-shell">
            <div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>

            <aside class="wb-sidebar" id="admin-sidebar">
                <a href="{{ route('admin.dashboard') }}" class="wb-sidebar-brand" aria-label="{{ WebBlocks::name() }}">
                    <x-webblocks-cms::brand-mark surface="sidebar" class="wb-sidebar-brand-logo wb-sidebar-brand-logo-inline" decorative="true" />
                    <span class="wb-sidebar-brand-copy">
                        <span>{{ WebBlocks::name() }}</span>
                        <span class="wb-sidebar-brand-note">{{ WebBlocks::slogan() }}</span>
                    </span>
                </a>

                <nav class="wb-sidebar-nav" aria-label="{{ $adminText('navigation.aria') }}">
                    <div class="wb-stack wb-stack-1">
                        @foreach ($menuItems as $item)
                            <a
                                href="{{ route($item['route']) }}"
                                class="wb-sidebar-link {{ $matchesActiveRoute($item) ? 'is-active' : '' }}"
                                @if ($matchesActiveRoute($item)) aria-current="page" @endif
                            >
                                <i class="wb-icon {{ $item['icon'] }} wb-sidebar-icon" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>

                    <hr class="wb-divider">

                    @foreach ($sidebarGroups as $group)
                        @php($groupIsActive = collect($group['items'])->contains(fn ($item) => $matchesActiveRoute($item)))
                        <div class="wb-nav-group {{ $groupIsActive ? 'is-open' : '' }}">
                            <button type="button" class="wb-nav-group-toggle {{ $groupIsActive ? 'is-active' : '' }}" aria-expanded="{{ $groupIsActive ? 'true' : 'false' }}" data-wb-nav-group-toggle>
                                <i class="wb-icon {{ $group['icon'] }} wb-nav-group-icon" aria-hidden="true"></i>
                                <span class="wb-nav-group-label">{{ $group['label'] }}</span>
                                <span class="wb-nav-group-arrow" aria-hidden="true"></span>
                            </button>

                            <div class="wb-nav-group-items">
                                @foreach ($group['items'] as $item)
                                    <a
                                        href="{{ $item['url'] ?? route($item['route']) }}"
                                        class="wb-nav-group-item {{ $matchesActiveRoute($item) ? 'is-active' : '' }}"
                                        @if ($matchesActiveRoute($item)) aria-current="page" @endif
                                    >
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="wb-sidebar-footer">
                    <div class="wb-text-sm wb-text-muted wb-text-center">{{ WebBlocks::name() }} v{{ WebBlocks::VERSION }}</div>
                </div>
            </aside>

            <div class="wb-dashboard-body">
                <header class="wb-navbar">
                    <button
                        class="wb-navbar-toggle"
                        type="button"
                        data-wb-toggle="sidebar"
                        data-wb-target="#admin-sidebar"
                        aria-expanded="false"
                        aria-controls="admin-sidebar"
                        aria-label="{{ $adminText('navigation.toggle') }}"
                    >
                        <span></span><span></span><span></span>
                    </button>

                    <div class="wb-navbar-identity wb-navbar-breadcrumb-wrap">
                        {!! $adminNavbarBreadcrumb !!}
                    </div>

                    <div class="wb-navbar-end wb-ms-auto">
                        <div class="wb-navbar-iconbar">
                            @if ($user?->can('access-system') && Route::has('admin.system.updates.indicator'))
                                <a
                                    href="{{ route('admin.system.updates.index') }}"
                                    class="wb-navbar-icon-trigger wb-navbar-update-indicator"
                                    data-wb-update-indicator
                                    data-wb-update-indicator-url="{{ route('admin.system.updates.indicator') }}"
                                    data-wb-update-indicator-state="unknown"
                                    aria-label="{{ $adminText('topbar.system_updates') }}"
                                    title="{{ $adminText('topbar.system_updates') }}"
                                    hidden
                                >
                                    <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                                    <span class="wb-navbar-update-dot" aria-hidden="true"></span>
                                    <span class="wb-sr-only" data-wb-update-indicator-label>{{ $adminText('topbar.system_updates') }}</span>
                                </a>
                            @endif

                            <button type="button" class="wb-navbar-icon-trigger" data-wb-mode-cycle aria-label="{{ $adminText('topbar.color_mode') }}" title="{{ $adminText('topbar.color_mode') }}">
                                <i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>
                            </button>

                            <div class="wb-dropdown wb-dropdown-end">
                                <button class="wb-navbar-icon-trigger" type="button" data-wb-toggle="dropdown" data-wb-target="#admin-theme-menu" aria-expanded="false" aria-label="{{ $adminText('topbar.theme_settings') }}" title="{{ $adminText('topbar.theme_settings') }}">
                                    <i class="wb-icon wb-icon-palette" aria-hidden="true"></i>
                                </button>

                                <div class="wb-dropdown-menu" id="admin-theme-menu">
                                    <div class="wb-dropdown-label">{{ $adminText('topbar.presets') }}</div>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="modern">{{ $adminText('topbar.modern') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="minimal">{{ $adminText('topbar.minimal') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="editorial">{{ $adminText('topbar.editorial') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="playful">{{ $adminText('topbar.playful') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="corporate">{{ $adminText('topbar.corporate') }}</button>
                                    <hr class="wb-dropdown-divider">
                                    <div class="wb-dropdown-label">{{ $adminText('topbar.accent') }}</div>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="ocean">{{ $adminText('topbar.ocean') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="forest">{{ $adminText('topbar.forest') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="sunset">{{ $adminText('topbar.sunset') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="royal">{{ $adminText('topbar.royal') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="mint">{{ $adminText('topbar.mint') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="amber">{{ $adminText('topbar.amber') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="rose">{{ $adminText('topbar.rose') }}</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="slate-fire">{{ $adminText('topbar.slate_fire') }}</button>
                                </div>
                            </div>

                        </div>

                        <div class="wb-dropdown wb-dropdown-end">
                            <button class="wb-navbar-avatar-trigger" type="button" data-wb-toggle="dropdown" data-wb-target="#admin-user-menu" aria-expanded="false" aria-label="{{ $adminText('topbar.user_menu') }}" title="{{ $user?->name }}">
                                <span class="wb-navbar-avatar" aria-hidden="true">{{ $userInitials }}</span>
                                <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
                            </button>

                            <div class="wb-dropdown-menu" id="admin-user-menu">
                                @if (Route::has('admin.profile.edit'))
                                    <a href="{{ route('admin.profile.edit') }}" class="wb-dropdown-item">{{ $adminText('topbar.profile') }}</a>
                                    <hr class="wb-dropdown-divider">
                                @endif
                                <form method="POST" action="{{ route('webblocks.auth.logout') }}">
                                    @csrf
                                    <button type="submit" class="wb-dropdown-item wb-dropdown-item-danger">{{ $adminText('topbar.logout') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="wb-dashboard-main">
                    <div class="wb-stack wb-stack-6">
                        @yield('content')
                    </div>
                </main>
            </div>
        </div>

        <div id="wb-overlay-root" class="wb-overlay-root">
            @stack('overlays')
        </div>

        <script src="{{ WebBlocks::uiJsUrl() }}" defer></script>
        @if (is_file($adminJsAssets['core']))
            <script src="{{ asset('cms/js/admin/core.js') }}?v={{ filemtime($adminJsAssets['core']) }}" defer></script>
        @endif
        @stack('admin-scripts')
        @stack('scripts')
    </body>
</html>
