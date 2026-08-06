{{-- Branded public 404. Rendered by the package's NotFoundHttpException
     renderable for non-JSON requests when the host app ships no
     resources/views/errors/404.blade.php of its own. Everything is resolved
     defensively: a 404 must never escalate into a 500, so any lookup that
     fails just falls back to the plainest working value. --}}
@php
    use WebBlocks\Cms\Support\WebBlocks;

    $notFoundRequest = $request ?? request();

    try {
        $notFoundSite = app(\WebBlocks\Cms\Support\Sites\SiteResolver::class)->current($notFoundRequest);
    } catch (\Throwable) {
        $notFoundSite = null;
    }

    try {
        $localeResolver = app(\WebBlocks\Cms\Support\Locales\LocaleResolver::class);
        $firstSegment = (string) ($notFoundRequest->segments()[0] ?? '');
        $notFoundLocale = ($firstSegment !== '' ? $localeResolver->enabled($firstSegment, $notFoundSite) : null)
            ?? $localeResolver->default();
        $notFoundLocaleCode = $notFoundLocale->code;
    } catch (\Throwable) {
        $notFoundLocaleCode = null;
    }

    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $notFoundText = fn (string $key) => $translator->public('errors.not_found.'.$key, $notFoundLocaleCode);

    try {
        $notFoundHomePath = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class)
            ->homePath($notFoundLocaleCode, $notFoundSite) ?? '/';
    } catch (\Throwable) {
        $notFoundHomePath = '/';
    }

    $notFoundSiteName = trim((string) ($notFoundSite?->publicDisplayName() ?? config('app.name')));
    $notFoundThemePreset = $notFoundSite?->resolvedPublicThemePreset() ?? \WebBlocks\Cms\Models\Site::PUBLIC_THEME_CANVAS;

    try {
        $notFoundBrandPaletteCss = app(\WebBlocks\Cms\Support\Theme\BrandPaletteRenderer::class)->render($notFoundSite);
    } catch (\Throwable) {
        $notFoundBrandPaletteCss = '';
    }

    $notFoundSiteCssPath = app(\WebBlocks\Cms\Support\PublicRendering\SiteAssetResolver::class)->cssPathFor($notFoundSite);
    $notFoundPublicCssPath = public_path('cms/css/public.css');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $notFoundLocaleCode ?? app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $notFoundText('title') }}@if ($notFoundSiteName !== '') · {{ $notFoundSiteName }}@endif</title>
        <meta name="robots" content="noindex, nofollow">
        <link rel="stylesheet" href="{{ WebBlocks::uiCssUrl() }}">
        @if (is_file($notFoundPublicCssPath))
            <link rel="stylesheet" href="{{ asset('cms/css/public.css') }}?v={{ filemtime($notFoundPublicCssPath) }}">
        @endif
        @if ($notFoundBrandPaletteCss !== '')
            <style>{!! $notFoundBrandPaletteCss !!}</style>
        @endif
        @if ($notFoundSiteCssPath)
            <link rel="stylesheet" href="{{ $notFoundSiteCssPath }}">
        @endif
    </head>
    <body class="wb-public-body" data-wb-public-theme="{{ $notFoundThemePreset }}">
        <div class="wb-error-shell">
            <main class="wb-stack wb-gap-3" style="max-width: 32rem;">
                <div class="wb-error-code" aria-hidden="true">404</div>
                <span class="wb-eyebrow">{{ $notFoundText('eyebrow') }}</span>
                <h1>{{ $notFoundText('title') }}</h1>
                <p>{{ $notFoundText('body') }}</p>
                <p>
                    <a href="{{ $notFoundHomePath }}" class="wb-btn wb-btn-primary">{{ $notFoundText('cta') }}</a>
                </p>
            </main>
        </div>
    </body>
</html>
