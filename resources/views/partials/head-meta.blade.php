@php
    $brandName = trim((string) ($brandName ?? config('app.name')));
    $siteName = trim((string) ($siteName ?? $brandName));
    $siteTagline = trim((string) ($siteTagline ?? config('app.slogan')));
    $resolvedTitle = trim((string) ($title ?? ''));
    $fullTitle = $resolvedTitle !== ''
        ? $resolvedTitle
        : ($siteName !== '' ? $siteName : $brandName);
    $metaDescription = trim((string) ($metaDescription ?? ''));
    $metaKeywords = trim((string) ($metaKeywords ?? ''));
    $faviconUrl = trim((string) ($faviconUrl ?? ''));
    $ogTitle = trim((string) ($ogTitle ?? $fullTitle));
    $ogDescription = trim((string) ($ogDescription ?? $metaDescription));
    $ogImage = trim((string) ($ogImage ?? ''));
    $canonicalUrl = trim((string) ($canonicalUrl ?? ''));
    $ogUrl = trim((string) ($ogUrl ?? $canonicalUrl));
    $ogSiteName = trim((string) ($ogSiteName ?? $siteName));
@endphp

<title>{{ $fullTitle }}</title>
@if ($metaDescription !== '')
    <meta name="description" content="{{ $metaDescription }}">
@endif
@if ($metaKeywords !== '')
    <meta name="keywords" content="{{ $metaKeywords }}">
@endif
@if ($canonicalUrl !== '')
    <link rel="canonical" href="{{ $canonicalUrl }}">
@endif
@if ($faviconUrl !== '')
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="shortcut icon" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
@else
    <link rel="icon" type="image/svg+xml" href="{{ asset('cms/brand/favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('cms/brand/favicon-16x16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('cms/brand/favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('cms/brand/apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('cms/brand/favicon-32x32.png') }}">
@endif
@if ($ogTitle !== '')
    <meta property="og:title" content="{{ $ogTitle }}">
@endif
@if ($ogDescription !== '')
    <meta property="og:description" content="{{ $ogDescription }}">
@endif
@if ($ogImage !== '')
    <meta property="og:image" content="{{ $ogImage }}">
@endif
@if ($ogUrl !== '')
    <meta property="og:url" content="{{ $ogUrl }}">
@endif
<meta property="og:type" content="website">
@if ($ogSiteName !== '')
    <meta property="og:site_name" content="{{ $ogSiteName }}">
@endif
<meta name="twitter:card" content="summary_large_image">
@if ($ogTitle !== '')
    <meta name="twitter:title" content="{{ $ogTitle }}">
@endif
@if ($ogDescription !== '')
    <meta name="twitter:description" content="{{ $ogDescription }}">
@endif
@if ($ogImage !== '')
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif
