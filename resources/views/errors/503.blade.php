{{-- Pre-rendered before update application. No site, database, session or plugin
     lookups: the saved HTML must work before the application can boot. --}}
@php
    use WebBlocks\Cms\Support\WebBlocks;

    $maintenanceLocale = str_replace('_', '-', app()->getLocale());
    $maintenanceTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $maintenanceText = static fn (string $key) => $maintenanceTranslator->public('errors.maintenance.'.$key, $maintenanceLocale);
@endphp
<!DOCTYPE html>
<html lang="{{ $maintenanceLocale }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $maintenanceText('title') }}</title>
        <link rel="icon" type="image/svg+xml" href="/cms/brand/favicon.svg">
        <link rel="stylesheet" href="{{ WebBlocks::uiCssUrl() }}">
        <link rel="stylesheet" href="/cms/css/public.css">
    </head>
    <body class="wb-public-body" data-wb-public-theme="canvas">
        <div class="wb-error-shell">
            <main class="wb-error-content wb-stack wb-gap-3">
                <div class="wb-error-code" aria-hidden="true">503</div>
                <span class="wb-eyebrow">{{ $maintenanceText('eyebrow') }}</span>
                <h1>{{ $maintenanceText('title') }}</h1>
                <p>{{ $maintenanceText('body') }}</p>
                <p>
                    {{-- An empty reference retries the visitor's current URL,
                         including its query, rather than the CLI render URL. --}}
                    <a href="" class="wb-btn wb-btn-primary">{{ $maintenanceText('cta') }}</a>
                </p>
            </main>
        </div>
    </body>
</html>
