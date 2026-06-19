<!DOCTYPE html>
@php
    use WebBlocks\Cms\Support\System\SystemSettings;
    use WebBlocks\Cms\Support\WebBlocks;
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => ['admin.dashboard'], 'icon' => 'wb-icon-layout-dashboard'],
                ['label' => 'Sites', 'route' => 'admin.sites.index', 'active' => ['admin.sites.index', 'admin.sites.create', 'admin.sites.store', 'admin.sites.edit', 'admin.sites.update', 'admin.sites.clone', 'admin.sites.clone.prefill', 'admin.sites.clone.store', 'admin.sites.promote', 'admin.sites.promote.*', 'admin.sites.delete', 'admin.sites.destroy'], 'icon' => 'wb-icon-globe'],
                ['label' => 'Pages', 'route' => 'admin.pages.index', 'active' => ['admin.pages.*'], 'icon' => 'wb-icon-file-text'],
                ['label' => 'Shared Slots', 'route' => 'admin.shared-slots.index', 'active' => ['admin.shared-slots.*'], 'icon' => 'wb-icon-layers'],
                ['label' => 'Navigation', 'route' => 'admin.navigation.index', 'active' => ['admin.navigation.*'], 'icon' => 'wb-icon-menu'],
                ['label' => 'Media', 'route' => 'admin.media.index', 'active' => ['admin.media.*'], 'icon' => 'wb-icon-image'],
                ['label' => 'Contact Messages', 'route' => 'admin.contact-messages.index', 'active' => ['admin.contact-messages.*'], 'icon' => 'wb-icon-mail'],
            ];

            if (! $user?->can('access-system')) {
                $menuItems = array_values(array_filter($menuItems, fn (array $item) => $item['route'] !== 'admin.sites.index'));
            }

            if ($user?->can('access-system')) {
                array_splice($menuItems, 3, 0, [[
                    'label' => 'Blocks',
                    'route' => 'admin.blocks.index',
                    'active' => ['admin.blocks.index'],
                    'icon' => 'wb-icon-box',
                ]]);
            }

            $sidebarGroups = [];

            if ($user?->can('access-system')) {
                $sidebarGroups[] = [
                    'label' => 'System',
                    'icon' => 'wb-icon-palette',
                        'items' => [
                            ['label' => 'Domains', 'route' => 'admin.domains.index', 'active' => ['admin.domains.*', 'admin.sites.domains.*']],
                            ['label' => 'Icons', 'route' => 'admin.system.icons.index', 'active' => ['admin.system.icons.*']],
                            ['label' => 'Users', 'route' => 'admin.users.index', 'active' => ['admin.users.*']],
                            ['label' => 'Locales', 'route' => 'admin.locales.index', 'active' => ['admin.locales.*']],
                            ['label' => 'Page Layouts', 'route' => 'admin.page-layouts.index', 'active' => ['admin.page-layouts.*']],
                            ['label' => 'Slot Types', 'route' => 'admin.slot-types.index', 'active' => ['admin.slot-types.*']],
                            ['label' => 'Block Types', 'route' => 'admin.block-types.index', 'active' => ['admin.block-types.*']],
                            ['label' => 'Settings', 'route' => 'admin.system.settings.edit', 'active' => ['admin.system.settings.*']],
                            ['label' => 'Plugins', 'route' => 'admin.system.plugins.index', 'active' => ['admin.system.plugins.*']],
                            ['label' => 'Visitor Reports', 'route' => 'admin.reports.visitors.index', 'active' => ['admin.reports.visitors.*']],
                        ],
                    ];

                $sidebarGroups[] = [
                    'label' => 'Maintenance',
                    'icon' => 'wb-icon-file',
                    'items' => [
                        ['label' => 'Search Rebuild', 'route' => 'admin.system.search.index', 'active' => ['admin.system.search.*']],
                        ['label' => 'Backups', 'route' => 'admin.system.backups.index', 'active' => ['admin.system.backups.*']],
                        ['label' => 'Export / Import', 'route' => 'admin.site-transfers.exports.index', 'active' => ['admin.site-transfers.*']],
                        ['label' => 'Update', 'route' => 'admin.system.updates.index', 'active' => ['admin.system.updates.*']],
                    ],
                ];
            }

            foreach (app(\WebBlocks\Cms\Support\Plugins\PluginRegistry::class)->menuItems($user) as $pluginMenuItem) {
                $item = $pluginMenuItem['item'];

                if ($item->routeName() === null || ! Route::has($item->routeName())) {
                    continue;
                }

                $groupName = $item->groupName() ?: 'System';
                $groupIndex = collect($sidebarGroups)->search(fn ($group) => $group['label'] === $groupName);

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

                <nav class="wb-sidebar-nav" aria-label="Admin navigation">
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
                        aria-label="Toggle navigation"
                    >
                        <span></span><span></span><span></span>
                    </button>

                     <div class="wb-navbar-identity">
                         <span class="wb-navbar-brand">
                            <span>{{ $adminProjectIdentity['name'] ?? WebBlocks::name() }}</span>
                         </span>
                        @if (($adminProjectIdentity['tagline'] ?? '') !== '')
                            <span class="wb-navbar-context">{{ $adminProjectIdentity['tagline'] }}</span>
                        @endif
                     </div>

                    <div class="wb-navbar-end wb-ms-auto">
                        <div class="wb-navbar-iconbar">
                            <button type="button" class="wb-navbar-icon-trigger" data-wb-mode-cycle aria-label="Color mode" title="Color mode">
                                <i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>
                            </button>

                            <div class="wb-dropdown wb-dropdown-end">
                                <button class="wb-navbar-icon-trigger" type="button" data-wb-toggle="dropdown" data-wb-target="#admin-theme-menu" aria-expanded="false" aria-label="Theme settings" title="Theme settings">
                                    <i class="wb-icon wb-icon-palette" aria-hidden="true"></i>
                                </button>

                                <div class="wb-dropdown-menu" id="admin-theme-menu">
                                    <div class="wb-dropdown-label">Presets</div>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="modern">Modern</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="minimal">Minimal</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="editorial">Editorial</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="playful">Playful</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-preset-set="corporate">Corporate</button>
                                    <hr class="wb-dropdown-divider">
                                    <div class="wb-dropdown-label">Accent</div>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="ocean">Ocean</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="forest">Forest</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="sunset">Sunset</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="royal">Royal</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="mint">Mint</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="amber">Amber</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="rose">Rose</button>
                                    <button type="button" class="wb-dropdown-item" data-wb-accent-set="slate-fire">Slate Fire</button>
                                </div>
                            </div>

                        </div>

                        <div class="wb-dropdown wb-dropdown-end">
                            <button class="wb-navbar-avatar-trigger" type="button" data-wb-toggle="dropdown" data-wb-target="#admin-user-menu" aria-expanded="false" aria-label="User menu" title="{{ $user?->name }}">
                                <span class="wb-navbar-avatar" aria-hidden="true">{{ $userInitials }}</span>
                                <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
                            </button>

                            <div class="wb-dropdown-menu" id="admin-user-menu">
                                @if (Route::has('admin.profile.edit'))
                                    <a href="{{ route('admin.profile.edit') }}" class="wb-dropdown-item">Profile</a>
                                    <hr class="wb-dropdown-divider">
                                @endif
                                <form method="POST" action="{{ route('webblocks.auth.logout') }}">
                                    @csrf
                                    <button type="submit" class="wb-dropdown-item wb-dropdown-item-danger">Logout</button>
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
