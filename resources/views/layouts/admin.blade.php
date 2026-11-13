<!DOCTYPE html>
@php
    use WebBlocks\Cms\Support\System\SystemSettings;
    use WebBlocks\Cms\Support\Admin\DocumentationUrlResolver;
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;
    use WebBlocks\Cms\Support\WebBlocks;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
@endphp
<html lang="{{ str_replace('_', '-', $adminLocale) }}">
    @php
        $resolvedAdminBrowserTitle = app(SystemSettings::class)->adminBrowserTitle($adminBrowserTitle ?? $title ?? null);
        $adminCssPath = public_path('cms/css/admin.css');
        $publicCssPath = public_path('cms/css/public.css');
        $adminJsAssets = [
            'core' => public_path('cms/js/admin/core.js'),
            'recovery' => public_path('cms/js/admin/editor-recovery.js'),
            'preview' => public_path('cms/js/admin/editor-preview.js'),
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
        {{-- Public theme tokens, scoped to [data-wb-public-theme-preview] so the
             Appearance preview shows the real preset colours. Nothing here
             matches admin chrome. --}}
        @if (is_file($publicCssPath))
            <link rel="stylesheet" href="{{ asset('cms/css/public.css') }}?v={{ filemtime($publicCssPath) }}">
        @endif
        @stack('styles')
    </head>
        <body class="wb-admin-dashboard-page" data-wb-admin-login-url="{{ route('webblocks.auth.login') }}" @foreach (['new_block', 'block', 'block_type', 'inline_block_editor', 'slot_type_id', 'status', 'kind', 'system_block', 'content_block', 'no_blocks_yet', 'add_first_block', 'collapse_children', 'expand_children', 'no_items_yet', 'add_first_item', 'new_item', 'collapse_item', 'expand_item', 'selected', 'select', 'selected_asset', 'choose_file_before_upload', 'upload_failed', 'select_file_to_upload', 'no_overlay_title', 'no_alt_text', 'no_caption', 'selected_image', 'item', 'items', 'heading_text', 'heading_level', 'section_title', 'section_variant', 'section_intro', 'cta_title', 'tone', 'cta_content', 'content', 'button_label', 'download_label', 'document_asset_id', 'published', 'draft'] as $javascriptLabel) data-wb-i18n-{{ str_replace('_', '-', $javascriptLabel) }}="{{ $adminText('javascript.'.$javascriptLabel) }}" @endforeach>
        @php
            $user = auth()->user();
            $userInitials = collect(preg_split('/\s+/', trim($user?->name ?? 'User')))
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_substr($part, 0, 1))
                ->implode('');

            ['menuItems' => $menuItems, 'sidebarGroups' => $sidebarGroups] = app(\WebBlocks\Cms\Support\Admin\AdminNavigation::class)->build($user, $adminLocale);

            $matchesActiveRoute = static fn (array $item): bool => collect($item['active'] ?? [])->contains(
                fn (string $pattern) => request()->routeIs($pattern)
            );

            $adminNavbarBreadcrumb = $breadcrumb ?? null;

            if (! $adminNavbarBreadcrumb) {
                $activeTopItem = collect($menuItems)->first(fn (array $item) => $matchesActiveRoute($item));
                $activeGroup = collect($sidebarGroups)
                    ->map(function (array $group) use ($matchesActiveRoute) {
                        $activeItem = collect($group['items'])->first(fn (array $item) => $matchesActiveRoute($item));

                        return $activeItem ? ['group' => $group, 'item' => $activeItem] : null;
                    })
                    ->filter()
                    ->first();
                $currentTitle = $heading ?? $title ?? $activeGroup['item']['label'] ?? $activeTopItem['label'] ?? $adminText('navigation.dashboard');

                if ($currentTitle === 'Dashboard') {
                    $currentTitle = $adminText('navigation.dashboard');
                }
                $breadcrumbItems = [];

                if (! request()->routeIs('admin.dashboard')) {
                    $breadcrumbItems[] = ['label' => $adminText('navigation.dashboard'), 'url' => route('admin.dashboard')];
                }

                if ($activeGroup) {
                    if ($activeGroup['group']['label'] !== $currentTitle) {
                    $breadcrumbItems[] = ['label' => $activeGroup['group']['label'], 'url' => null];
                    }
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

                $adminNavbarBreadcrumb = '<nav class="wb-breadcrumb" aria-label="'.e($adminText('page_layouts.breadcrumb')).'"><ol class="wb-breadcrumb-list">';

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
                        <details class="wb-nav-group" data-wb-nav-group @if ($groupIsActive) open @endif>
                            <summary class="wb-nav-group-toggle {{ $groupIsActive ? 'is-active' : '' }}">
                                <i class="wb-icon {{ $group['icon'] }} wb-nav-group-icon" aria-hidden="true"></i>
                                <span class="wb-nav-group-label">{{ $group['label'] }}</span>
                                <span class="wb-nav-group-arrow" aria-hidden="true"></span>
                            </summary>

                            <div class="wb-nav-group-items">
                                @foreach ($group['items'] as $item)
                                    <a
                                        href="{{ $item['url'] ?? route($item['route']) }}"
                                        class="wb-nav-group-item {{ $matchesActiveRoute($item) ? 'is-active' : '' }}"
                                        @if ($item['external'] ?? false) target="_blank" rel="noopener noreferrer" @endif
                                        @if ($matchesActiveRoute($item)) aria-current="page" @endif
                                    >
                                        <i class="wb-icon {{ ($item['icon'] ?? null) ?: $group['icon'] }}" aria-hidden="true"></i>
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                    <a href="{{ app(DocumentationUrlResolver::class)->url($adminLocale) }}" class="wb-sidebar-link" target="_blank" rel="noopener noreferrer">
                        <i class="wb-icon wb-icon-help-circle wb-sidebar-icon" aria-hidden="true"></i>
                        <span>{{ $adminText('navigation.help') }}</span>
                    </a>
                </nav>

                <div class="wb-sidebar-footer">
                    <div class="wb-text-sm wb-text-muted wb-text-center">{{ WebBlocks::name() }} v{{ WebBlocks::VERSION }}</div>
                    <div class="wb-text-sm wb-text-muted wb-text-center">
                        {!! $adminTranslator->get('admin.navigation.developed_by', $adminLocale, [
                            'company' => '<a href="https://fklavye.net" target="_blank" rel="noopener noreferrer">fklavye.net</a>',
                        ]) !!}
                    </div>
                </div>
            </aside>

            <div class="wb-dashboard-body">
                <header class="wb-navbar wb-admin-navbar">
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

                    <div class="wb-navbar-identity">
                        {!! $adminNavbarBreadcrumb !!}
                    </div>

                    <div class="wb-navbar-end wb-ms-auto">
                        <div class="wb-cluster wb-cluster-2">
                            @include('webblocks-cms::admin.partials.panel-notifications-indicator')
                            @include('webblocks-cms::admin.partials.quick-settings')
                            @if ($user?->can('access-system') && Route::has('admin.system.updates.indicator'))
                                <a
                                    href="{{ route('admin.system.updates.index') }}"
                                    class="wb-btn wb-btn-ghost wb-btn-icon"
                                    data-wb-update-indicator
                                    data-wb-update-indicator-url="{{ route('admin.system.updates.indicator') }}"
                                    data-wb-update-indicator-state="unknown"
                                    aria-label="{{ $adminText('topbar.system_updates') }}"
                                    title="{{ $adminText('topbar.system_updates') }}"
                                    hidden
                                >
                                    <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                                    <span class="wb-btn-dot" aria-hidden="true"></span>
                                    <span class="wb-sr-only" data-wb-update-indicator-label>{{ $adminText('topbar.system_updates') }}</span>
                                </a>
                            @endif

                            <button type="button" class="wb-btn wb-btn-ghost wb-btn-icon" data-wb-mode-cycle
                                data-wb-mode-label-light="{{ $adminText('topbar.mode_light') }}"
                                data-wb-mode-label-dark="{{ $adminText('topbar.mode_dark') }}"
                                data-wb-mode-label-auto="{{ $adminText('topbar.mode_auto') }}"
                                aria-label="{{ $adminText('topbar.color_mode') }}" title="{{ $adminText('topbar.color_mode') }}">
                                <i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>
                            </button>

                            @include('webblocks-cms::admin.partials.public-site-menu')

                            @if (app(AdminLocaleResolver::class)->userPreferencesAvailable())
                                <div class="wb-language-switcher wb-language-switcher--code wb-dropdown wb-dropdown-end">
                                    <button
                                        class="wb-btn wb-btn-ghost wb-language-switcher-trigger"
                                        type="button"
                                        data-wb-toggle="dropdown"
                                        data-wb-target="#admin-language-menu"
                                        aria-label="{{ $adminText('topbar.choose_language') }}"
                                        aria-expanded="false"
                                    >
                                        <span class="wb-language-switcher-code" lang="{{ $adminLocale }}" aria-hidden="true">{{ strtoupper($adminLocale) }}</span>
                                        <i class="wb-icon wb-icon-chevron-down wb-language-switcher-chevron" aria-hidden="true"></i>
                                    </button>

                                    <div class="wb-dropdown-menu" id="admin-language-menu">
                                        @foreach (app(AdminLocaleResolver::class)->options() as $localeCode => $localeLabel)
                                            <form method="POST" action="{{ route('admin.profile.locale.update') }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="admin_locale" value="{{ $localeCode }}">
                                                <button
                                                    type="submit"
                                                    class="wb-dropdown-item @if ($adminLocale === $localeCode) is-active @endif"
                                                    lang="{{ $localeCode }}"
                                                    @if ($adminLocale === $localeCode) aria-current="true" @endif
                                                >
                                                    <span class="wb-language-switcher-item-code">{{ strtoupper($localeCode) }}</span>
                                                    {{ in_array($localeCode, AdminLocaleResolver::SUPPORTED_LOCALES, true) ? $adminText('topbar.language_'.$localeCode) : \Illuminate\Support\Str::after($localeLabel, ' - ') }}
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                        </div>

                        <div class="wb-user-menu wb-dropdown wb-dropdown-end">
                            <button class="wb-btn wb-btn-ghost wb-user-menu-trigger wb-gap-1" type="button" data-wb-toggle="dropdown" data-wb-target="#admin-user-menu" aria-controls="admin-user-menu" aria-expanded="false" aria-label="{{ $adminText('topbar.user_menu') }}">
                                <span class="wb-avatar wb-avatar-sm" aria-hidden="true">{{ $userInitials }}</span>
                                <i class="wb-icon wb-icon-chevron-down wb-user-menu-chevron" aria-hidden="true"></i>
                            </button>

                            <div class="wb-dropdown-menu" id="admin-user-menu">
                                @if (Route::has('admin.profile.edit'))
                                    <a href="{{ route('admin.profile.edit') }}" class="wb-dropdown-item"><i class="wb-icon wb-icon-user" aria-hidden="true"></i>{{ $adminText('topbar.profile') }}</a>
                                    <hr class="wb-dropdown-divider">
                                @endif
                                <form class="wb-user-menu-form" method="POST" action="{{ route('webblocks.auth.logout') }}">
                                    @csrf
                                    <button type="submit" class="wb-dropdown-item wb-dropdown-item-danger"><i class="wb-icon wb-icon-log-out" aria-hidden="true"></i>{{ $adminText('topbar.logout') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="wb-dashboard-main">
                    <div class="wb-stack wb-stack-6">
                        <div data-wb-page-feedback hidden></div>
                        @yield('content')
                        @if ($pluginAppearanceCard)
                            @include('webblocks-cms::admin.system.plugins.partials.appearance')
                        @endif
                    </div>
                </main>
            </div>
        </div>

        <div id="wb-overlay-root" class="wb-overlay-root">
            @stack('overlays')
        </div>

        <template data-wb-modal-submit-feedback
            @foreach (['saving', 'deleting', 'exporting', 'importing', 'uploading', 'fetching', 'sending', 'backing_up', 'restoring', 'cleaning', 'updating', 'uninstalling', 'starting'] as $message)
                data-{{ $message }}="{{ $adminTranslator->get('modal-submit.'.$message, $adminLocale) }}"
            @endforeach
        >
            <div class="wb-stack wb-gap-2 wb-mt-3">
                <p class="wb-sr-only" role="status"></p>
                <progress class="wb-progress-bar"></progress>
                <p class="wb-text-sm wb-text-muted wb-m-0">{{ $adminTranslator->get('modal-submit.keep_open', $adminLocale) }}</p>
            </div>
        </template>

        <script src="{{ WebBlocks::uiJsUrl() }}" defer></script>
        @if (is_file($adminJsAssets['core']))
            <script src="{{ asset('cms/js/admin/core.js') }}?v={{ filemtime($adminJsAssets['core']) }}" defer></script>
        @endif
        @if (is_file($adminJsAssets['recovery']))
            <script src="{{ asset('cms/js/admin/editor-recovery.js') }}?v={{ filemtime($adminJsAssets['recovery']) }}" defer></script>
        @endif
        @stack('admin-scripts')
        @if (is_file($adminJsAssets['preview']))
            <script src="{{ asset('cms/js/admin/editor-preview.js') }}?v={{ filemtime($adminJsAssets['preview']) }}" defer></script>
        @endif
        @stack('scripts')
    </body>
</html>
