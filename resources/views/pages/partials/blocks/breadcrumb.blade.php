@php
    $page = $block->renderPage();
    $routeResolver = app(\App\Support\Pages\PageRouteResolver::class);
    $site = $block->renderSite();
    $localeCode = $block->renderLocaleCode();
    $currentTranslation = $page?->currentTranslation
        ?? ($page ? $routeResolver->translationFor($page, $localeCode, $site) : null);
    $localeId = $currentTranslation?->locale_id;
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $includeCurrent = ($settings['include_current'] ?? true) !== false;
    $homePath = $routeResolver->homePath($localeCode, $site) ?? '/';

    $homeTranslation = $site && $localeId
        ? \App\Models\PageTranslation::query()
            ->where('site_id', $site->id)
            ->where('locale_id', $localeId)
            ->where('path', '/')
            ->first()
        : null;

    $homeLabel = trim((string) ($settings['home_label'] ?? ''));
    if ($homeLabel === '') {
        $homeLabel = $homeTranslation?->name ?: 'Home';
    }

    $currentLabel = $currentTranslation?->name ?: $page?->title;
    $isHomePage = ($currentTranslation?->path ?? null) === '/';
@endphp

@if ($isHomePage)
    @if ($includeCurrent && $currentLabel)
        <nav class="wb-breadcrumb" aria-label="Breadcrumb">
            <ol class="wb-breadcrumb-list">
                <li class="wb-breadcrumb-item">
                    <span class="wb-breadcrumb-current" aria-current="page">{{ $currentLabel }}</span>
                </li>
            </ol>
        </nav>
    @endif
@elseif ($currentLabel || $homeLabel)
    <nav class="wb-breadcrumb" aria-label="Breadcrumb">
        <ol class="wb-breadcrumb-list">
            <li class="wb-breadcrumb-item">
                <a class="wb-breadcrumb-link" href="{{ $homePath }}">{{ $homeLabel }}</a>
            </li>
            @if ($includeCurrent && $currentLabel)
                <li class="wb-breadcrumb-item">
                    <span class="wb-breadcrumb-current" aria-current="page">{{ $currentLabel }}</span>
                </li>
            @endif
        </ol>
    </nav>
@endif
