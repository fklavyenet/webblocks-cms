@php($ct = $consent['texts'])
<div data-cms-consent-root data-consent-config="{{ json_encode($consent['payload'], JSON_THROW_ON_ERROR) }}">
    @if ($consent['state']['needs_choice'] && count($consent['services']) > 0)
        <div class="wb-cookie-consent wb-cookie-consent-banner" data-cms-consent-banner data-consent-position="{{ $consent['settings']['position'] }}" hidden>
            <section class="wb-card wb-cookie-consent-card" aria-label="{{ $ct['title'] }}">
                <div class="wb-card-header"><strong>{{ $ct['title'] }}</strong></div>
                <div class="wb-card-body wb-stack-3">
                    <p>{{ $ct['body'] }}</p>
                    @if ($consent['privacyUrl'])<a href="{{ $consent['privacyUrl'] }}">{{ $ct['privacy'] }}</a>@endif
                    <div class="wb-cluster wb-cluster-2">
                        <button type="button" class="wb-btn wb-btn-secondary" data-cms-consent-reject>{{ $ct['reject'] }}</button>
                        <button type="button" class="wb-btn wb-btn-secondary" data-cms-consent-open aria-controls="wbCookiePreferences" aria-haspopup="dialog">{{ $ct['customize'] }}</button>
                        <button type="button" class="wb-btn wb-btn-primary" data-cms-consent-accept>{{ $ct['accept'] }}</button>
                    </div>
                    <p role="status" aria-live="polite" data-cms-consent-error hidden>{{ $ct['sync_error'] }}</p>
                </div>
            </section>
        </div>
    @endif
    <div class="wb-modal wb-cookie-consent-modal" id="wbCookiePreferences" role="dialog" aria-modal="true" aria-labelledby="wbCookiePreferencesTitle" hidden>
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <h2 class="wb-modal-title" id="wbCookiePreferencesTitle">{{ $ct['preferences_title'] }}</h2>
                <button type="button" class="wb-btn wb-btn-secondary wb-btn-icon" data-wb-dismiss="modal" aria-label="{{ $ct['close'] }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
            </div>
            <div class="wb-modal-body wb-stack-3">
                <p>{{ $ct['preferences_body'] }}</p>
                <section class="wb-card wb-card-muted">
                    <div class="wb-card-body"><strong>{{ $ct['necessary'] }} · {{ $ct['always_on'] }}</strong><p>{{ $ct['necessary_body'] }}</p></div>
                </section>
                @foreach (\WebBlocks\Cms\Support\Consent\ConsentPresets::CATEGORIES as $category)
                    @php($categoryServices = array_filter($consent['services'], fn ($service) => $service['category'] === $category))
                    @if ($categoryServices !== [])
                        <section class="wb-card" role="group" aria-labelledby="cms-consent-category-{{ $category }}">
                            <div class="wb-card-header"><strong id="cms-consent-category-{{ $category }}">{{ $ct[$category] }}</strong></div>
                            <div class="wb-card-body wb-stack-3">
                                @foreach ($categoryServices as $handle => $service)
                                    <div class="wb-stack-1">
                                        <label class="wb-switch">
                                            <input type="checkbox" data-cms-consent-service="{{ $handle }}" @checked($category === 'necessary' || ($consent['state']['services'][$handle] ?? false)) @disabled($category === 'necessary')>
                                            <span class="wb-switch-track"></span><span>{{ $service['name'] }}</span>
                                        </label>
                                        @if ($service['description'])<p>{{ $service['description'] }}</p>@endif
                                        @if ($service['privacy_url'])<a href="{{ $service['privacy_url'] }}" target="_blank" rel="noopener noreferrer">{{ $ct['privacy'] }}</a>@endif
                                        @if ($service['cookies'] !== [])
                                            <details><summary>{{ $ct['cookies'] }}</summary><ul>@foreach ($service['cookies'] as $cookie)<li>{{ $cookie['name'] }} · {{ $cookie['path'] }} @if ($cookie['duration'])· {{ $ct['duration'] }}: {{ $cookie['duration'] }}@endif</li>@endforeach</ul></details>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach
                <p role="status" aria-live="polite" data-cms-consent-error hidden>{{ $ct['sync_error'] }}</p>
                <div class="wb-alert wb-alert-warning" data-cms-consent-reload hidden><p>{{ $ct['reload_required'] }}</p><button type="button" class="wb-btn wb-btn-secondary" data-cms-consent-reload-page>{{ $ct['reload'] }}</button></div>
            </div>
            <div class="wb-modal-footer wb-cluster wb-cluster-2">
                <button type="button" class="wb-btn wb-btn-secondary" data-cms-consent-reject>{{ $ct['reject'] }}</button>
                <button type="button" class="wb-btn wb-btn-secondary" data-cms-consent-save>{{ $ct['save'] }}</button>
                <button type="button" class="wb-btn wb-btn-primary" data-cms-consent-accept>{{ $ct['accept'] }}</button>
            </div>
        </div>
    </div>
</div>
@php($consentScript = public_path('cms/js/privacy-consent-sync.js'))
@if (is_file($consentScript))
    <script src="{{ asset('cms/js/privacy-consent-sync.js') }}?v={{ filemtime($consentScript) }}" defer></script>
@endif
