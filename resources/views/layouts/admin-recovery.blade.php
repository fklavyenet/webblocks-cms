@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;
    use WebBlocks\Cms\Support\WebBlocks;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $translator = app(CmsTranslator::class);
    $text = static fn (string $key) => $translator->admin($key, $adminLocale);
    $adminCssPath = public_path('cms/css/admin.css');
    $pluginsUrl = Route::has('admin.system.plugins.index') ? route('admin.system.plugins.index') : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $adminLocale) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('webblocks-cms::partials.head-meta', ['title' => $title.' | '.WebBlocks::name()])
        <link rel="stylesheet" href="{{ WebBlocks::uiCssUrl() }}">
        <link rel="stylesheet" href="{{ WebBlocks::iconsCssUrl() }}">
        @if (is_file($adminCssPath))
            <link rel="stylesheet" href="{{ asset('cms/css/admin.css') }}?v={{ filemtime($adminCssPath) }}">
        @endif
    </head>
    <body class="wb-admin-dashboard-page">
        {{-- Use CMS-owned chrome here. The normal admin layout resolves extension and site features. --}}
        <div class="wb-dashboard-shell">
            <div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>
            <aside class="wb-sidebar" id="admin-sidebar">
                <div class="wb-flex wb-items-center wb-border-bottom wb-px-4">
                    <div class="wb-sidebar-brand wb-flex-1 wb-min-w-0 wb-border-0 wb-p-0">
                        <x-webblocks-cms::brand-mark surface="sidebar" class="wb-sidebar-brand-logo" decorative="true" />
                        <span class="wb-sidebar-brand-copy"><span class="wb-truncate">{{ WebBlocks::name() }}</span></span>
                    </div>
                    <button class="wb-btn wb-btn-ghost wb-btn-icon wb-btn-sm wb-visible-mobile" type="button"
                            data-wb-toggle="sidebar" data-wb-target="#admin-sidebar" aria-controls="admin-sidebar"
                            aria-expanded="false" data-wb-tooltip="{{ $text('navigation.close') }}" aria-label="{{ $text('navigation.close') }}">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </button>
                </div>
                <nav class="wb-sidebar-nav" aria-label="{{ $text('navigation.aria') }}">
                    <details class="wb-nav-group" data-wb-nav-group open>
                        <summary class="wb-nav-group-toggle is-active">
                            <i class="wb-icon wb-icon-package wb-nav-group-icon" aria-hidden="true"></i>
                            <span class="wb-nav-group-label">{{ $text('navigation.extensions') }}</span>
                            <span class="wb-nav-group-arrow" aria-hidden="true"></span>
                        </summary>
                        <div class="wb-nav-group-items">
                            @if ($pluginsUrl)
                                <a class="wb-nav-group-item" href="{{ $pluginsUrl }}">
                                    <i class="wb-icon wb-icon-plug" aria-hidden="true"></i>
                                    <span>{{ $text('navigation.plugins') }}</span>
                                </a>
                            @endif
                            <a class="wb-nav-group-item is-active" href="{{ route('admin.plugins.recovery.index') }}" aria-current="page">
                                <i class="wb-icon wb-icon-shield-check" aria-hidden="true"></i>
                                <span>{{ $title }}</span>
                            </a>
                        </div>
                    </details>
                </nav>
            </aside>

            <div class="wb-dashboard-body">
                <header class="wb-navbar wb-admin-navbar">
                    <button class="wb-navbar-toggle" type="button" data-wb-toggle="sidebar" data-wb-target="#admin-sidebar"
                            aria-controls="admin-sidebar" aria-expanded="false" data-wb-tooltip="{{ $text('navigation.toggle') }}" aria-label="{{ $text('navigation.toggle') }}">
                        <i class="wb-icon wb-icon-menu wb-icon-lg" aria-hidden="true"></i>
                    </button>
                    <div class="wb-navbar-identity">
                        <nav class="wb-breadcrumb" aria-label="{{ $text('page_layouts.breadcrumb') }}">
                            <ol class="wb-breadcrumb-list">
                                <li class="wb-breadcrumb-item">{{ $text('navigation.extensions') }}</li>
                                @if ($pluginsUrl)
                                    <li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="{{ $pluginsUrl }}">{{ $text('navigation.plugins') }}</a></li>
                                @endif
                                <li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">{{ $title }}</span></li>
                            </ol>
                        </nav>
                    </div>
                </header>
                <main class="wb-dashboard-main">
                    <div class="wb-stack wb-stack-4">
                        @yield('content')
                    </div>
                </main>
            </div>
        </div>
        <div id="wb-overlay-root" class="wb-overlay-root">@stack('overlays')</div>
        <script src="{{ WebBlocks::uiJsUrl() }}" defer></script>
    </body>
</html>
