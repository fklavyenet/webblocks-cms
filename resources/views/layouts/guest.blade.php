<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $guestCssPath = public_path('site/css/guest.css');
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta', [
            'title' => $title ?? config('app.name'),
            'metaDescription' => $metaDescription ?? config('app.slogan'),
        ])

        <link rel="stylesheet" href="https://webblocksui.com/packages/webblocks/dist/webblocks-ui.css">
        <link rel="stylesheet" href="https://webblocksui.com/packages/webblocks/dist/webblocks-icons.css">
        @if (is_file($guestCssPath))
            <link rel="stylesheet" href="{{ asset('site/css/guest.css') }}?v={{ filemtime($guestCssPath) }}">
        @endif
    </head>
    <body>
        <main>
            {{ $slot }}
        </main>

        <script src="https://webblocksui.com/packages/webblocks/dist/webblocks-ui.js"></script>
    </body>
</html>
