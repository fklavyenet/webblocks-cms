<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $cmsPublicCssPath = public_path('assets/webblocks-cms/css/public.css');
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
        @if (! isset($page))
            @yield('content')
        @else
        @php
            $publicShell = $page->publicShellPreset();
            $slotCollection = collect($slots ?? []);
            $renderSlot = function (array $slot) use ($page) {
                $wrapper = is_array($slot['wrapper'] ?? null) ? $slot['wrapper'] : [];
                $tag = $wrapper['element'] ?? ($slot['wrapper_element'] ?? 'div');
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

        <script src="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js"></script>
    </body>
</html>
