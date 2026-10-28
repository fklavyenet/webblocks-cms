@php
    $locale = request('locale', app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale());
    $t = fn ($key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('consent.'.$key, $locale);
    $consent = app(\WebBlocks\Cms\Support\Consent\ConsentPresenter::class)->forSite($site, $locale, true);
@endphp
<!DOCTYPE html><html lang="{{ $locale }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $t('preview') }}</title><link rel="stylesheet" href="{{ \WebBlocks\Cms\Support\WebBlocks::uiCssUrl() }}"><link rel="stylesheet" href="{{ \WebBlocks\Cms\Support\WebBlocks::iconsCssUrl() }}"><script src="{{ \WebBlocks\Cms\Support\WebBlocks::uiJsUrl() }}" defer></script></head>
<body><main class="wb-container wb-stack-4"><h1>{{ $site->name }} · {{ $t('preview') }}</h1><p>{{ $t('preview_help') }}</p><div class="wb-cluster wb-cluster-2">@foreach (['first', 'accepted', 'rejected'] as $choice)<a class="wb-btn wb-btn-secondary" href="{{ route('admin.consent.preview', ['site' => $site->id, 'choice' => $choice, 'locale' => $locale]) }}">{{ $t('preview_'.$choice) }}</a>@endforeach<a href="#cookie-settings" class="wb-btn wb-btn-primary">{{ $t('settings') }}</a></div></main><div id="wb-overlay-root" class="wb-overlay-root">@include('webblocks-cms::partials.cookie-consent')</div></body></html>
