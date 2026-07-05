<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $publicLocaleCode ?? app()->getLocale()) }}">
    @php
        use WebBlocks\Cms\Support\Blocks\PublicBodyEndRegistry;
        use WebBlocks\Cms\Support\Blocks\PublicOverlayRegistry;
        use WebBlocks\Cms\Support\Plugins\PluginPublicAsset;
        use WebBlocks\Cms\Support\Plugins\PluginPublicAssetRegistry;
        use WebBlocks\Cms\Support\PublicRendering\SiteAssetResolver;
        use WebBlocks\Cms\Support\Translations\CmsTranslator;
        use WebBlocks\Cms\Support\WebBlocks;

        $publicTranslator = app(CmsTranslator::class);
        $publicText = static fn (string $key) => $publicTranslator->public($key, $publicLocaleCode ?? app()->getLocale());
        $cmsPublicCssPath = public_path('cms/css/public.css');
        $publicJsAssets = [
            'public-search-modal' => public_path('cms/js/public/public-search-modal.js'),
            'sidebar-navigation' => public_path('cms/js/public/sidebar-navigation.js'),
        ];
        $headPageAssets = collect($headPageAssets ?? collect());
        $bodyEndPageAssets = collect($bodyEndPageAssets ?? collect());
        $headCssPageAssets = $headPageAssets->where('type', 'css')->values();
        $headJsPageAssets = $headPageAssets->where('type', 'js')->values();
        $pluginPublicAssets = app(PluginPublicAssetRegistry::class);
        $headPluginAssets = collect($pluginPublicAssets->head());
        $bodyEndPluginAssets = collect($pluginPublicAssets->bodyEnd());
        $headCssPluginAssets = $headPluginAssets->filter(fn ($asset) => $asset->type() === PluginPublicAsset::TYPE_CSS)->values();
        $headJsPluginAssets = $headPluginAssets->filter(fn ($asset) => $asset->type() === PluginPublicAsset::TYPE_JS)->values();
        $deferredHeadJsPageAssets = $headJsPageAssets
            ->concat($bodyEndPageAssets->where('type', 'js')->values())
            ->values();
        $resolvedSite = isset($page) ? $page->site : ($site ?? ($resolvedPublicSite ?? null));
        $siteAssetResolver = app(SiteAssetResolver::class);
        $siteCssPath = $siteAssetResolver->cssPathFor($resolvedSite);
        $siteJsPath = $siteAssetResolver->jsPathFor($resolvedSite);
        $publicThemePreset = $resolvedSite?->resolvedPublicThemePreset() ?? \WebBlocks\Cms\Models\Site::PUBLIC_THEME_CANVAS;
        $publicMeta = $publicMeta ?? [
            'site_name' => $resolvedSite?->publicDisplayName() ?? config('app.name'),
            'site_label' => trim((string) ($resolvedSite?->display_name ?? $resolvedSite?->seo_title ?? $resolvedSite?->name ?? config('app.name'))),
            'site_tagline' => trim((string) ($resolvedSite?->tagline ?? config('app.slogan'))),
            'title' => trim((string) ($title ?? ($resolvedSite?->display_name ?? $resolvedSite?->seo_title ?? $resolvedSite?->name ?? config('app.name')))),
            'canonical_url' => trim((string) ($canonicalUrl ?? '')),
            'meta_description' => trim((string) ($metaDescription ?? ($resolvedSite?->seo_description ?? ''))),
            'meta_keywords' => trim((string) ($metaKeywords ?? ($resolvedSite?->seo_keywords ?? ''))),
            'favicon_url' => $resolvedSite?->faviconAsset?->url(),
            'og_title' => trim((string) ($ogTitle ?? $title ?? ($resolvedSite?->display_name ?? $resolvedSite?->seo_title ?? $resolvedSite?->name ?? config('app.name')))),
            'og_description' => trim((string) ($ogDescription ?? ($metaDescription ?? $resolvedSite?->seo_description ?? ''))),
            'og_image' => trim((string) ($ogImage ?? ($resolvedSite?->socialImageAsset?->url() ?? ''))),
            'og_url' => trim((string) ($ogUrl ?? ($canonicalUrl ?? ''))),
            'og_site_name' => $resolvedSite?->publicDisplayName() ?? config('app.name'),
        ];
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('webblocks-cms::partials.head-meta', [
            'brandName' => WebBlocks::name(),
            'siteName' => $publicMeta['site_name'] ?? null,
            'siteTagline' => $publicMeta['site_tagline'] ?? null,
            'title' => $publicMeta['title'] ?? null,
            'canonicalUrl' => $publicMeta['canonical_url'] ?? null,
            'metaDescription' => $publicMeta['meta_description'] ?? null,
            'metaKeywords' => $publicMeta['meta_keywords'] ?? null,
            'faviconUrl' => $publicMeta['favicon_url'] ?? null,
            'ogTitle' => $publicMeta['og_title'] ?? null,
            'ogDescription' => $publicMeta['og_description'] ?? null,
            'ogImage' => $publicMeta['og_image'] ?? null,
            'ogUrl' => $publicMeta['og_url'] ?? null,
            'ogSiteName' => $publicMeta['og_site_name'] ?? null,
        ])
        @if ($previewMode ?? false)
            <meta name="robots" content="noindex, nofollow">
        @endif

        {{-- Public CSS assets --}}
        <link rel="stylesheet" href="{{ WebBlocks::uiCssUrl() }}">
        <link rel="stylesheet" href="{{ WebBlocks::iconsCssUrl() }}">
        @if (is_file($cmsPublicCssPath))
            <link rel="stylesheet" href="{{ asset('cms/css/public.css') }}?v={{ filemtime($cmsPublicCssPath) }}">
        @endif
        @if ($siteCssPath)
            <link rel="stylesheet" href="{{ $siteCssPath }}">
        @endif
        @foreach ($headCssPageAssets as $pageAsset)
            <link rel="stylesheet" href="{{ $pageAsset->path }}">
        @endforeach
        @foreach ($headCssPluginAssets as $pluginAsset)
            <link rel="stylesheet" href="{{ $pluginAsset->url() }}" data-plugin-asset="{{ $pluginAsset->handle() }}" data-plugin-handle="{{ $pluginAsset->pluginHandle() }}">
        @endforeach

        {{-- Public JS assets --}}
        <script src="{{ WebBlocks::uiJsUrl() }}" defer></script>
        @if (is_file($publicJsAssets['public-search-modal']))
            <script src="{{ asset('cms/js/public/public-search-modal.js') }}?v={{ filemtime($publicJsAssets['public-search-modal']) }}" defer></script>
        @endif
        @if (is_file($publicJsAssets['sidebar-navigation']))
            <script src="{{ asset('cms/js/public/sidebar-navigation.js') }}?v={{ filemtime($publicJsAssets['sidebar-navigation']) }}" defer></script>
        @endif
        @if ($siteJsPath)
            <script src="{{ $siteJsPath }}" defer></script>
        @endif
        @foreach ($deferredHeadJsPageAssets as $pageAsset)
            <script src="{{ $pageAsset->path }}" @if ($pageAsset->is_module) type="module" @endif @if ($pageAsset->is_async) async @else defer @endif></script>
        @endforeach
        @foreach ($headJsPluginAssets as $pluginAsset)
            <script src="{{ $pluginAsset->url() }}" data-plugin-asset="{{ $pluginAsset->handle() }}" data-plugin-handle="{{ $pluginAsset->pluginHandle() }}" @if ($pluginAsset->isModule()) type="module" @endif @if ($pluginAsset->isAsync()) async @else defer @endif></script>
        @endforeach
    </head>
    <body class="{{ $publicBodyClass ?? 'wb-public-body' }}" data-wb-public-theme="{{ $publicThemePreset }}">
        @if ($previewMode ?? false)
            <div class="wb-alert wb-alert-warning wb-m-0" role="status">
                <strong>{{ $publicText('preview.title') }}</strong> - {{ $publicText('preview.body') }}
            </div>
        @endif

        @if (! isset($page))
            @yield('content')
        @else
        @php
            $publicShell = $page->resolvedPublicShellType();
            $slotCollection = collect($slots ?? []);
            $renderSlot = function (array $slot) use ($page) {
                $wrapper = is_array($slot['wrapper'] ?? null) ? $slot['wrapper'] : [];
                $tag = $wrapper['element'] ?? 'div';
                $beforeHtml = $wrapper['before_html'] ?? null;
                $startHtml = $wrapper['start_html'] ?? null;
                $endHtml = $wrapper['end_html'] ?? null;
                $afterHtml = $wrapper['after_html'] ?? null;
                $attributes = collect($wrapper['attributes'] ?? [])
                    ->map(fn ($value, $name) => e((string) $name).'="'.e((string) $value).'"')
                    ->values()
                    ->all();

                return ($beforeHtml ? $beforeHtml : '')
                    .'<'.$tag.' '.implode(' ', $attributes).'>'
                    .($startHtml ? $startHtml : '')
                    .view('webblocks-cms::pages.partials.slot', ['slot' => $slot, 'page' => $page, 'renderWrapper' => false])->render()
                    .($endHtml ? $endHtml : '')
                    .'</'.$tag.'>'
                    .($afterHtml ? $afterHtml : '');
            };
        @endphp

        @if ($publicShell === 'docs')
            <div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>
            <div class="wb-dashboard-shell">
                @foreach ($slotCollection->where('slug', 'sidebar') as $slot)
                    {!! $renderSlot($slot) !!}
                @endforeach

                <div class="wb-dashboard-body wb-w-full">
                    @foreach (['header', 'main', 'footer'] as $slug)
                        @foreach ($slotCollection->where('slug', $slug) as $slot)
                            {!! $renderSlot($slot) !!}
                        @endforeach
                    @endforeach

                    @foreach ($slotCollection->reject(fn ($slot) => in_array($slot['slug'] ?? null, ['sidebar', 'header', 'main', 'footer'], true)) as $slot)
                        {!! $renderSlot($slot) !!}
                    @endforeach
                </div>
            </div>
        @else
            @foreach ($slotCollection as $slot)
                {!! $renderSlot($slot) !!}
            @endforeach
        @endif
        @endif

        @php
            $publicBodyEnd = app(PublicBodyEndRegistry::class)->all();
            $publicOverlays = app(PublicOverlayRegistry::class)->all();
        @endphp
        @foreach ($publicBodyEnd as $bodyEndHtml)
            {!! $bodyEndHtml !!}
        @endforeach
        @foreach ($bodyEndPluginAssets as $pluginAsset)
            @if ($pluginAsset->type() === PluginPublicAsset::TYPE_CSS)
                <link rel="stylesheet" href="{{ $pluginAsset->url() }}" data-plugin-asset="{{ $pluginAsset->handle() }}" data-plugin-handle="{{ $pluginAsset->pluginHandle() }}">
            @else
                <script src="{{ $pluginAsset->url() }}" data-plugin-asset="{{ $pluginAsset->handle() }}" data-plugin-handle="{{ $pluginAsset->pluginHandle() }}" @if ($pluginAsset->isModule()) type="module" @endif @if ($pluginAsset->isAsync()) async @else defer @endif></script>
            @endif
        @endforeach
        <div id="wb-overlay-root" class="wb-overlay-root">
            <div class="wb-toast-container wb-toast-container-top-right">
                @if (session('contact_form_success_message'))
                    <div class="wb-toast wb-toast-success" role="status" aria-live="polite">
                        <div class="wb-toast-body">
                            <strong class="wb-toast-title">{{ $publicText('toast.contact_message_sent') }}</strong>
                            <span>{{ session('contact_form_success_message') }}</span>
                        </div>
                        <button type="button" class="wb-toast-close" aria-label="{{ $publicText('toast.dismiss_message') }}" data-wb-dismiss="toast"></button>
                    </div>
                @endif
            </div>

            @include('webblocks-cms::search.partials.modal')

            @foreach ($publicOverlays as $overlayHtml)
                {!! $overlayHtml !!}
            @endforeach
        </div>
    </body>
</html>
