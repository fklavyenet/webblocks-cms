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
        @foreach ($slots ?? collect() as $slot)
            @php
                $tag = match ($slot['slug']) {
                    'header' => 'header',
                    'sidebar' => 'aside',
                    'main' => 'main',
                    'footer' => 'footer',
                    default => 'div',
                };

                $attributes = 'data-wb-slot="'.$slot['slug'].'"';

                if ($slot['slug'] === 'main') {
                    $attributes .= ' id="main-content"';
                }
            @endphp

            <{{ $tag }} {!! $attributes !!}>
                @include('pages.partials.slot', ['slot' => $slot, 'page' => $page])
            </{{ $tag }}>
        @endforeach

        <script src="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js"></script>
    </body>
</html>
