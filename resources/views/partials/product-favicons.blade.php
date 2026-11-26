@php
    $productIconUrl = static fn (string $filename) => \WebBlocks\Cms\Support\WebBlocks::faviconUrl($filename, rootRelative: $rootRelative ?? false);
@endphp
<link rel="icon" type="image/svg+xml" sizes="any" href="{{ $productIconUrl('favicon.svg') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ $productIconUrl('favicon-16x16.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ $productIconUrl('favicon-32x32.png') }}">
<link rel="icon" type="image/x-icon" sizes="16x16 32x32 48x48" href="{{ $productIconUrl('favicon.ico') }}">
<link rel="apple-touch-icon" type="image/png" sizes="180x180" href="{{ $productIconUrl('apple-touch-icon.png') }}">
