<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        use WebBlocks\Cms\Support\WebBlocks;

        $guestCssPath = public_path('cms/css/guest.css');
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('webblocks-cms::partials.head-meta', [
            'title' => $title ?? config('app.name'),
            'metaDescription' => $metaDescription ?? config('app.slogan'),
        ])

        <link rel="stylesheet" href="{{ WebBlocks::uiCssUrl() }}">
        <link rel="stylesheet" href="{{ WebBlocks::iconsCssUrl() }}">
        @if (is_file($guestCssPath))
            <link rel="stylesheet" href="{{ asset('cms/css/guest.css') }}?v={{ filemtime($guestCssPath) }}">
        @endif
    </head>
    <body>
        <main>
            @yield('content')
        </main>

        <script src="{{ WebBlocks::uiJsUrl() }}"></script>
    </body>
</html>
