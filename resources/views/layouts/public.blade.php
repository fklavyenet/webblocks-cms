<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        use App\Support\WebBlocks;

        $cmsPublicCssPath = public_path('assets/webblocks-cms/css/public.css');
        $siteCssPath = public_path('site/css/site.css');
        $publicJsAssets = [
            'header-actions' => public_path('assets/webblocks-cms/js/public/header-actions.js'),
            'public-search-modal' => public_path('assets/webblocks-cms/js/public/public-search-modal.js'),
        ];
        $resolvedSite = isset($page) ? $page->site : ($site ?? ($resolvedPublicSite ?? null));
        $siteName = $resolvedSite?->publicDisplayName() ?? config('app.name');
        $siteTagline = trim((string) ($resolvedSite?->tagline ?? config('app.slogan')));
        $siteSeoTitle = trim((string) ($resolvedSite?->seo_title ?? ''));
        $siteSeoDescription = trim((string) ($resolvedSite?->seo_description ?? ''));
        $siteSeoKeywords = trim((string) ($resolvedSite?->seo_keywords ?? ''));
        $faviconUrl = $resolvedSite?->faviconAsset?->url();
        $socialImageUrl = $resolvedSite?->socialImageAsset?->url();
        $resolvedTitle = trim((string) ($title ?? ''));
        $headTitle = $resolvedTitle !== '' ? $resolvedTitle : ($siteSeoTitle !== '' ? $siteSeoTitle : $siteName);
        $headDescription = trim((string) ($metaDescription ?? ''));
        $headDescription = $headDescription !== '' ? $headDescription : $siteSeoDescription;
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta', [
            'brandName' => WebBlocks::name(),
            'siteName' => $siteName,
            'siteTagline' => $siteTagline,
            'title' => $headTitle,
            'metaDescription' => $headDescription,
            'metaKeywords' => $siteSeoKeywords,
            'faviconUrl' => $faviconUrl,
            'ogTitle' => $siteSeoTitle !== '' ? $siteSeoTitle : $headTitle,
            'ogDescription' => $siteSeoDescription !== '' ? $siteSeoDescription : $headDescription,
            'ogImage' => $socialImageUrl,
            'ogSiteName' => $siteName,
        ])

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-icons.css">
        @if (is_file($cmsPublicCssPath))
            <link rel="stylesheet" href="{{ asset('assets/webblocks-cms/css/public.css') }}?v={{ filemtime($cmsPublicCssPath) }}">
        @endif
        @if (is_file($siteCssPath))
            <link rel="stylesheet" href="{{ asset('site/css/site.css') }}?v={{ filemtime($siteCssPath) }}">
        @endif
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

        <script src="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js"></script>
        @if (is_file($publicJsAssets['header-actions']))
            <script src="{{ asset('assets/webblocks-cms/js/public/header-actions.js') }}?v={{ filemtime($publicJsAssets['header-actions']) }}" defer></script>
        @endif
        @if (is_file($publicJsAssets['public-search-modal']))
            <script src="{{ asset('assets/webblocks-cms/js/public/public-search-modal.js') }}?v={{ filemtime($publicJsAssets['public-search-modal']) }}" defer></script>
        @endif
    </body>
</html>
