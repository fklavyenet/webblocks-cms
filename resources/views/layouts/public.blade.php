<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $cmsPublicCssPath = public_path('assets/webblocks-cms/css/public.css');
        $cmsPublicHeaderActionsJsPath = public_path('assets/webblocks-cms/js/public/header-actions.js');
        $siteCssPath = public_path('site/css/site.css');
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
        @if (is_file($cmsPublicCssPath))
            <link rel="stylesheet" href="{{ asset('assets/webblocks-cms/css/public.css') }}?v={{ filemtime($cmsPublicCssPath) }}">
        @endif
        @if (is_file($siteCssPath))
            <link rel="stylesheet" href="{{ asset('site/css/site.css') }}?v={{ filemtime($siteCssPath) }}">
        @endif
    </head>
    <body class="wb-public-body">
        @php
            $publicShell = $publicShell ?? ['preset' => 'default', 'slots' => $slots ?? collect()];
        @endphp

        @if (($publicShell['preset'] ?? 'default') === 'docs')
            <div class="wb-dashboard-shell">
                <div class="wb-sidebar-backdrop" data-wb-sidebar-backdrop></div>

                @if (! empty($publicShell['sidebar']))
                    @include('pages.partials.slot', ['slot' => $publicShell['sidebar'], 'page' => $page])
                @endif

                <div class="wb-dashboard-body">
                    @if (! empty($publicShell['header']))
                        @include('pages.partials.slot', ['slot' => $publicShell['header'], 'page' => $page])
                    @endif

                    @if (! empty($publicShell['main']))
                        @include('pages.partials.slot', ['slot' => $publicShell['main'], 'page' => $page])
                    @endif

                    @foreach (($publicShell['content_slots'] ?? collect())->reject(fn ($slot) => in_array($slot['slug'], ['main', 'sidebar'], true)) as $slot)
                        @include('pages.partials.slot', ['slot' => $slot, 'page' => $page])
                    @endforeach
                </div>

                @if (! empty($publicShell['footer']))
                    @include('pages.partials.slot', ['slot' => $publicShell['footer'], 'page' => $page])
                @endif
            </div>
        @else
            @foreach ($slots ?? collect() as $slot)
                @include('pages.partials.slot', ['slot' => $slot, 'page' => $page])
            @endforeach
        @endif

        <script src="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js"></script>
        @if (is_file($cmsPublicHeaderActionsJsPath))
            <script src="{{ asset('assets/webblocks-cms/js/public/header-actions.js') }}?v={{ filemtime($cmsPublicHeaderActionsJsPath) }}" defer></script>
        @endif
    </body>
</html>
