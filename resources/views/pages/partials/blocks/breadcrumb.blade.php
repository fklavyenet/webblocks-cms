@php
  $page = $block->renderPage();
  $routeResolver = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class);
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
    ? \WebBlocks\Cms\Models\PageTranslation::query()
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
    $a11y = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)
      ->get('blocks.a11y.'.$key, strtolower((string) ($block->renderLocaleCode() ?? app()->getLocale())));
@endphp

@if ($isHomePage)
  @if ($includeCurrent && $currentLabel)
    <nav class="wb-breadcrumb" aria-label="{{ $a11y('breadcrumb') }}">
      <ol class="wb-breadcrumb-list">
        <li class="wb-breadcrumb-item">
          <span class="wb-breadcrumb-current" aria-current="page">{{ $currentLabel }}</span>
        </li>
      </ol>
    </nav>
  @endif
@elseif ($currentLabel || $homeLabel)
  <nav class="wb-breadcrumb" aria-label="{{ $a11y('breadcrumb') }}">
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
