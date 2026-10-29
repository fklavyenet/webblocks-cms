@php
    $cookieSite = $block->renderSite();
    $cookieEnabled = $cookieSite && app(\WebBlocks\Cms\Support\Consent\ConsentConfiguration::class)->settings($cookieSite)['enabled'];
    $cookieLink = app(\WebBlocks\Cms\Support\Consent\ConsentPreferencesLink::class);
    $cookieVariant = in_array($block->variant, ['primary', 'secondary'], true) ? $block->variant : 'text';
@endphp
@if ($cookieEnabled)
    <a href="{{ $cookieLink->url($cookieSite, $block->renderLocaleCode()) }}" class="{{ $cookieVariant === 'text' ? 'wb-link' : 'wb-btn wb-btn-'.$cookieVariant }}" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}" data-cms-consent-open aria-controls="wbCookiePreferences" aria-haspopup="dialog">{{ $cookieLink->label($cookieSite, $block->renderLocaleCode()) }}</a>
@endif
