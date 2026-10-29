@php
    $cookieAdminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $cookieAdminText = fn ($key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('cookie_settings_block.'.$key, $cookieAdminLocale);
    $cookieSite = $block->page?->site ?? ($page->site ?? null);
@endphp
<div class="wb-stack-3">
    <p>{{ $cookieAdminText('help') }}</p>
    @if ($cookieSite && ! app(\WebBlocks\Cms\Support\Consent\ConsentConfiguration::class)->settings($cookieSite)['enabled'])
        <div class="wb-alert wb-alert-warning">{{ $cookieAdminText('disabled') }}</div>
    @endif
</div>
