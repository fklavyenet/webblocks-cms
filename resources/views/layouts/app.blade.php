<!DOCTYPE html>
@php
    use App\Support\WebBlocks;
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta', [
            'title' => $title ?? config('app.name'),
            'metaDescription' => $metaDescription ?? config('app.slogan'),
        ])

        <link rel="stylesheet" href="{{ WebBlocks::uiCssUrl() }}">
        <link rel="stylesheet" href="{{ WebBlocks::iconsCssUrl() }}">
    </head>
    <body>
        <div class="wb-section">
            <div class="wb-container wb-container-lg">
                {{ $slot }}
            </div>
        </div>

        <script src="{{ WebBlocks::uiJsUrl() }}"></script>
    </body>
</html>
