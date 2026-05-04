<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $adminCssPath = public_path('assets/webblocks-cms/css/admin.css');
        $adminJsAssets = [
            'core' => public_path('assets/webblocks-cms/js/admin/core.js'),
            'password-fields' => public_path('assets/webblocks-cms/js/admin/password-fields.js'),
            'asset-picker' => public_path('assets/webblocks-cms/js/admin/asset-picker.js'),
            'admin-sortable-list' => public_path('assets/webblocks-cms/js/admin-sortable-list.js'),
            'inline-block-builder' => public_path('assets/webblocks-cms/js/admin/inline-block-builder.js'),
            'builder-items' => public_path('assets/webblocks-cms/js/admin/builder-items.js'),
            'slot-builder' => public_path('assets/webblocks-cms/js/admin/slot-builder.js'),
            'page-builder-modals' => public_path('assets/webblocks-cms/js/admin/page-builder-modals.js'),
            'rich-text-editor' => public_path('assets/webblocks-cms/js/admin/rich-text-editor.js'),
        ];
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta', [
            'title' => $title ?? config('app.name'),
            'metaDescription' => $metaDescription ?? config('app.slogan'),
        ])

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-icons.css">
        @if (is_file($adminCssPath))
            <link rel="stylesheet" href="{{ asset('assets/webblocks-cms/css/admin.css') }}?v={{ filemtime($adminCssPath) }}">
        @endif
        @stack('styles')
    </head>
        <body data-wb-admin-login-url="{{ route('login') }}">
        @php
            $user = auth()->user();
            $userInitials = collect(preg_split('/\s+/', trim($user?->name ?? 'User')))
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_substr($part, 0, 1))
                ->implode('');

            $menuItems = [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => 'wb-icon-layout-dashboard'],
                ['label' => 'Pages', 'route' => 'admin.pages.index', 'active' => 'admin.pages.*', 'icon' => 'wb-icon-file-text'],
                ['label' => 'Navigation', 'route' => 'admin.navigation.index', 'active' => 'admin.navigation.*', 'icon' => 'wb-icon-menu'],
                ['label' => 'Media', 'route' => 'admin.media.index', 'active' => 'admin.media.*', 'icon' => 'wb-icon-image'],
                ['label' => 'Contact Messages', 'route' => 'admin.contact-messages.index', 'active' => 'admin.contact-messages.*', 'icon' => 'wb-icon-mail'],
            ];

            $sidebarGroups = [];

            if ($user?->can('access-system')) {
                $sidebarGroups[] = [
                    'label' => 'System',
                    'icon' => 'wb-icon-palette',
                    'items' => [
                        ['label' => 'Users', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
                        ['label' => 'Sites', 'route' => 'admin.sites.index', 'active' => 'admin.sites.*'],
                        ['label' => 'Locales', 'route' => 'admin.locales.index', 'active' => 'admin.locales.*'],
                        ['label' => 'Slot Types', 'route' => 'admin.slot-types.index', 'active' => 'admin.slot-types.*'],
                        ['label' => 'Block Types', 'route' => 'admin.block-types.index', 'active' => 'admin.block-types.*'],
                    ],
                ];

                $sidebarGroups[] = [
                    'label' => 'Maintenance',
                    'icon' => 'wb-icon-file',
                    'items' => [
                        ['label' => 'Visitor Reports', 'route' => 'admin.reports.visitors.index', 'active' => 'admin.reports.visitors.*'],
                        ['label' => 'Settings', 'route' => 'admin.system.settings.edit', 'active' => 'admin.system.settings.*'],
                        ['label' => 'Backups', 'route' => 'admin.system.backups.index', 'active' => 'admin.system.backups.*'],
                        ['label' => 'Export / Import', 'route' => 'admin.site-transfers.exports.index', 'active' => 'admin.site-transfers.*'],
                        ['label' => 'Update', 'route' => 'admin.system.updates.index', 'active' => 'admin.system.updates.*'],
                    ],
                ];
            }
        @endphp

        <div class="wb-dashboard-shell">
            <div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>

            <aside class="wb-sidebar" id="admin-sidebar">
                <a href="{{ route('admin.dashboard') }}" class="wb-sidebar-brand">
                    <img src="{{ asset('brand/logo-64.png') }}" alt="{{ config('app.name') }} logo" class="wb-sidebar-brand-logo">
                    <span class="wb-sidebar-brand-copy">
                        <x-brand-copy slogan-class="wb-sidebar-brand-note" />
                    </span>
                </a>

                <nav class="wb-sidebar-nav" aria-label="Admin navigation">
                    <div class="wb-stack wb-stack-1">
                        @foreach ($menuItems as $item)
                            <a
                                href="{{ route($item['route']) }}"
                                class="wb-sidebar-link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
                                @if (request()->routeIs($item['active'])) aria-current="page" @endif
                            >
                                <i class="wb-icon {{ $item['icon'] }} wb-sidebar-icon" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>

                    <hr class="wb-divider">

                    @foreach ($sidebarGroups as $group)
                        @php($groupIsActive = collect($group['items'])->contains(fn ($item) => request()->routeIs($item['active'])))
                        <div class="wb-nav-group {{ $groupIsActive ? 'is-open' : '' }}">
                            <button type="button" class="wb-nav-group-toggle {{ $groupIsActive ? 'is-active' : '' }}" aria-expanded="{{ $groupIsActive ? 'true' : 'false' }}" data-wb-nav-group-toggle>
                                <i class="wb-icon {{ $group['icon'] }} wb-nav-group-icon" aria-hidden="true"></i>
                                <span class="wb-nav-group-label">{{ $group['label'] }}</span>
                                <span class="wb-nav-group-arrow" aria-hidden="true"></span>
                            </button>

                            <div class="wb-nav-group-items">
                                @foreach ($group['items'] as $item)
                                    <a
                                        href="{{ route($item['route']) }}"
                                        class="wb-nav-group-item {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
                                        @if (request()->routeIs($item['active'])) aria-current="page" @endif
                                    >
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="wb-sidebar-footer">
                    <div class="wb-text-sm wb-text-muted">{{ config('app.name') }} v{{ $installedVersionDisplay ?? config('app.version') }}</div>
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
                            <span>{{ config('app.name') }}</span>
                        </span>
                        <span class="wb-navbar-context">{{ $heading ?? config('app.slogan') }}</span>
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
                                <a href="{{ route('profile.edit') }}" class="wb-dropdown-item">Profile</a>
                                <hr class="wb-dropdown-divider">
                                <form method="POST" action="{{ route('logout') }}">
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

        <script src="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js"></script>
        @if (is_file($adminJsAssets['core']))
            <script src="{{ asset('assets/webblocks-cms/js/admin/core.js') }}?v={{ filemtime($adminJsAssets['core']) }}" defer></script>
        @endif
        @if (is_file($adminJsAssets['password-fields']))
            <script src="{{ asset('assets/webblocks-cms/js/admin/password-fields.js') }}?v={{ filemtime($adminJsAssets['password-fields']) }}" defer></script>
        @endif
        @if (is_file($adminJsAssets['asset-picker']))
            <script src="{{ asset('assets/webblocks-cms/js/admin/asset-picker.js') }}?v={{ filemtime($adminJsAssets['asset-picker']) }}" defer></script>
        @endif
        @if (is_file($adminJsAssets['admin-sortable-list']))
            <script src="{{ asset('assets/webblocks-cms/js/admin-sortable-list.js') }}?v={{ filemtime($adminJsAssets['admin-sortable-list']) }}" defer></script>
        @endif
        @if (is_file($adminJsAssets['inline-block-builder']))
            <script src="{{ asset('assets/webblocks-cms/js/admin/inline-block-builder.js') }}?v={{ filemtime($adminJsAssets['inline-block-builder']) }}" defer></script>
        @endif
        @if (is_file($adminJsAssets['builder-items']))
            <script src="{{ asset('assets/webblocks-cms/js/admin/builder-items.js') }}?v={{ filemtime($adminJsAssets['builder-items']) }}" defer></script>
        @endif
        @if (is_file($adminJsAssets['slot-builder']))
            <script src="{{ asset('assets/webblocks-cms/js/admin/slot-builder.js') }}?v={{ filemtime($adminJsAssets['slot-builder']) }}" defer></script>
        @endif
        @if (is_file($adminJsAssets['page-builder-modals']))
            <script src="{{ asset('assets/webblocks-cms/js/admin/page-builder-modals.js') }}?v={{ filemtime($adminJsAssets['page-builder-modals']) }}" defer></script>
        @endif
        @if (is_file($adminJsAssets['rich-text-editor']))
            <script src="{{ asset('assets/webblocks-cms/js/admin/rich-text-editor.js') }}?v={{ filemtime($adminJsAssets['rich-text-editor']) }}" defer></script>
        @endif
        @stack('scripts')
    </body>
</html>
