@php
    $consentConfig = app(\WebBlocks\Cms\Support\Consent\ConsentConfiguration::class);
    $embedSite = $block->renderSite();
    $embedLocale = $block->renderLocaleCode();
    $embedText = fn ($key) => $embedSite ? $consentConfig->text($embedSite, $key, $embedLocale) : app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('consent.'.$key, $embedLocale);
    if ($embedSite) {
        $consentConfig->registerEmbed($embedSite, $embed['service']);
    }
    $embedAvailable = $embedSite && $consentConfig->settings($embedSite)['enabled'] && ($consentConfig->services($embedSite, $embedLocale)[$embed['service']]['enabled'] ?? false);
    $embedName = app(\WebBlocks\Cms\Support\Consent\ConsentPresets::class)->all()[$embed['service']]['name'];
@endphp
<div data-cms-consent-embed="{{ $embed['service'] }}" class="wb-stack-2">
    <div data-cms-consent-placeholder class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack-2">
            <p>{{ str_replace(':service', $embedName, $embedText('blocked')) }}</p>
            <div class="wb-cluster wb-cluster-2">
                <button type="button" class="wb-btn wb-btn-secondary" data-cms-consent-once @disabled(! $embedAvailable)>{{ $embedText('load_once') }}</button>
                <button type="button" class="wb-btn wb-btn-primary" data-cms-consent-allow-service @disabled(! $embedAvailable)>{{ str_replace(':service', $embedName, $embedText('allow_service')) }}</button>
                @if ($embedAvailable)<a href="#cookie-settings">{{ $embedText('settings') }}</a>@endif
            </div>
            <p data-cms-consent-unavailable @if ($embedAvailable) hidden @endif>{{ $embedText('unavailable') }}</p>
        </div>
    </div>
    <div data-cms-consent-content></div>
    <template data-cms-consent-template><iframe data-src="{{ $embed['url'] }}" title="{{ $block->title ?: $embedName }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></template>
</div>
