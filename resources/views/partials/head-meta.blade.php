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
{{-- hreflang is keyed on an explicitly passed $hreflangPage, not the inherited
     $page: this partial is shared with the admin and guest layouts, whose views
     also have a $page in scope, and only the public layout opts in. --}}
@if (($hreflangPage ?? null) instanceof \WebBlocks\Cms\Models\Page)
    @php
        $hreflangRouteResolver = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class);
        $hreflangSite = ($hreflangSite ?? null) ?? $hreflangPage->site;
        $hreflangLinks = ($hreflangSite ? $hreflangSite->enabledLocales()->get() : collect())
            ->map(fn ($hreflangLocale) => [
                'locale' => $hreflangLocale,
                'url' => $hreflangRouteResolver->urlFor($hreflangPage, $hreflangLocale, $hreflangSite),
            ])
            ->filter(fn (array $entry) => $entry['url'] !== null)
            ->values();
    @endphp
    @if ($hreflangLinks->count() > 1)
        @foreach ($hreflangLinks as $hreflangEntry)
            <link rel="alternate" hreflang="{{ $hreflangEntry['locale']->code }}" href="{{ $hreflangEntry['url'] }}">
        @endforeach
        @php $hreflangDefaultEntry = $hreflangLinks->first(fn (array $entry) => $entry['locale']->is_default); @endphp
        @if ($hreflangDefaultEntry)
            <link rel="alternate" hreflang="x-default" href="{{ $hreflangDefaultEntry['url'] }}">
        @endif
    @endif
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
    <link rel="shortcut icon" href="{{ asset('cms/brand/favicon.ico') }}">
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
