@php
    $brandName = config('app.name');
    $brandSlogan = config('app.slogan');
    $resolvedTitle = trim($title ?? '') !== '' ? $title : $brandName;
    $fullTitle = $resolvedTitle === $brandName ? $brandName.' - '.$brandSlogan : $resolvedTitle.' - '.$brandName;
    $metaDescription = trim($metaDescription ?? '') !== '' ? $metaDescription : $brandSlogan;
    $brandImage = asset('brand/og-image.png');
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('brand/favicon-16x16.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('brand/favicon-32x32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('brand/apple-touch-icon.png') }}">
<link rel="icon" href="{{ asset('brand/favicon-32x32.png') }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:image" content="{{ $brandImage }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $brandName }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ $brandImage }}">
