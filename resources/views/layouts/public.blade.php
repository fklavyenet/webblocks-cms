<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        use App\Support\WebBlocks;

        $cmsPublicCssPath = public_path('assets/webblocks-cms/css/public.css');
        $siteCssPath = public_path('site/css/site.css');
        $publicJsAssets = [
            'header-actions' => public_path('assets/webblocks-cms/js/public/header-actions.js'),
            'public-search-modal' => public_path('assets/webblocks-cms/js/public/public-search-modal.js'),
            'sidebar-navigation' => public_path('assets/webblocks-cms/js/public/sidebar-navigation.js'),
        ];
        $resolvedSite = isset($page) ? $page->site : ($site ?? ($resolvedPublicSite ?? null));
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

        @include('partials.head-meta', [
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

        <link rel="stylesheet" href="{{ WebBlocks::uiCssUrl() }}">
        <link rel="stylesheet" href="{{ WebBlocks::iconsCssUrl() }}">
        @if (is_file($cmsPublicCssPath))
            <link rel="stylesheet" href="{{ asset('assets/webblocks-cms/css/public.css') }}?v={{ filemtime($cmsPublicCssPath) }}">
        @endif
        @if (is_file($siteCssPath))
            <link rel="stylesheet" href="{{ asset('site/css/site.css') }}?v={{ filemtime($siteCssPath) }}">
        @endif
        @foreach (($headPageAssets ?? collect()) as $pageAsset)
            <link rel="stylesheet" href="{{ $pageAsset->path }}">
        @endforeach
    </head>
    <body class="wb-public-body">
        @if (! isset($page))
            @yield('content')
        @else
        @php
            $publicShell = $page->publicShellPreset();
            $slotCollection = collect($slots ?? []);
            $renderSlot = function (array $slot) use ($page) {
                $wrapper = is_array($slot['wrapper'] ?? null) ? $slot['wrapper'] : [];
                $tag = $wrapper['element'] ?? 'div';
                $attributes = collect($wrapper['attributes'] ?? [])
                    ->map(fn ($value, $name) => e((string) $name).'="'.e((string) $value).'"')
                    ->values()
                    ->all();

                return '<'.$tag.' '.implode(' ', $attributes).'>'
                    .view('pages.partials.slot', ['slot' => $slot, 'page' => $page, 'renderWrapper' => false])->render()
                    .'</'.$tag.'>';
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

        @include('search.partials.modal')

        <script src="{{ WebBlocks::uiJsUrl() }}"></script>
        @if (is_file($publicJsAssets['header-actions']))
            <script src="{{ asset('assets/webblocks-cms/js/public/header-actions.js') }}?v={{ filemtime($publicJsAssets['header-actions']) }}" defer></script>
        @endif
        @if (is_file($publicJsAssets['public-search-modal']))
            <script src="{{ asset('assets/webblocks-cms/js/public/public-search-modal.js') }}?v={{ filemtime($publicJsAssets['public-search-modal']) }}" defer></script>
        @endif
        @if (is_file($publicJsAssets['sidebar-navigation']))
            <script src="{{ asset('assets/webblocks-cms/js/public/sidebar-navigation.js') }}?v={{ filemtime($publicJsAssets['sidebar-navigation']) }}" defer></script>
        @endif
        @foreach (($bodyEndPageAssets ?? collect()) as $pageAsset)
            <script src="{{ $pageAsset->path }}" @if ($pageAsset->is_module) type="module" @endif @if ($pageAsset->is_async) async @endif @if ($pageAsset->is_defer && ! $pageAsset->is_async) defer @endif></script>
        @endforeach
    </body>
</html>
