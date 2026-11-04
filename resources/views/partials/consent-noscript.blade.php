<noscript>
    @php
        $texts = $consent['texts'];
        $consentSaveUrl = app(\WebBlocks\Cms\Support\Consent\ConsentPreferencesLink::class)->saveUrl($resolvedSite, $publicLocaleCode ?? app()->getLocale());
    @endphp
    <details open id="cookie-settings" class="wb-container wb-section wb-collapse-panel">
        <summary class="wb-collapse-trigger">{{ $texts['preferences_title'] }}</summary><div class="wb-collapse-body">
        <p>{{ $texts['preferences_body'] }}</p>
        @if (session('cms_consent_saved'))<p class="wb-alert wb-alert-success" role="status">{{ app(\WebBlocks\Cms\Support\Consent\ConsentConfiguration::class)->text($resolvedSite, 'saved', $publicLocaleCode ?? app()->getLocale()) }}</p>@endif
        <form id="cookie-preferences-form" method="POST" action="{{ $consentSaveUrl }}" class="wb-stack-4">
            @csrf
            @if (isset($errors) && $errors->any())<p role="alert" class="wb-alert wb-alert-warning">{{ $texts['sync_error'] }}</p>@endif
            <section class="wb-card"><div><strong>{{ $texts['necessary'] }} · {{ $texts['always_on'] }}</strong><p>{{ $texts['necessary_body'] }}</p></div></section>
            @foreach (\WebBlocks\Cms\Support\Consent\ConsentPresets::CATEGORIES as $category)
                @php($categoryServices = array_filter($consent['services'], fn ($service) => $service['category'] === $category))
                @if ($categoryServices !== [])
                    <section class="wb-card" role="group" aria-labelledby="consent-form-{{ $category }}">
                        <div class="wb-card-header"><strong id="consent-form-{{ $category }}">{{ $texts[$category] }}</strong></div>
                        <div class="wb-stack-3">
                            @foreach ($categoryServices as $handle => $service)
                                <input type="hidden" name="revisions[{{ $handle }}]" value="{{ $service['revision'] }}">
                                <div class="wb-stack-1">
                                    <label class="wb-switch"><input type="checkbox" name="services[{{ $handle }}]" value="1" @checked($category === 'necessary' || ($consent['state']['services'][$handle] ?? false)) @disabled($category === 'necessary')><span class="wb-switch-track"></span><span>{{ $service['name'] }}</span></label>
                                    @if ($service['description'])<p>{{ $service['description'] }}</p>@endif
                                    @if ($service['privacy_url'])<a href="{{ $service['privacy_url'] }}" rel="noopener noreferrer" target="_blank">{{ $texts['privacy'] }}</a>@endif
                                    @if ($service['cookies'] !== [])<details class="wb-collapse-panel"><summary class="wb-collapse-trigger">{{ $texts['cookies'] }}</summary><div class="wb-collapse-body"><ul>@foreach ($service['cookies'] as $knownCookie)<li>{{ $knownCookie['name'] }} · {{ $knownCookie['path'] }} @if ($knownCookie['duration'])· {{ $texts['duration'] }}: {{ $knownCookie['duration'] }}@endif</li>@endforeach</ul></div></details>@endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
            <div class="wb-cluster wb-cluster-2">
                <button type="submit" name="status" value="rejected" class="wb-btn wb-btn-secondary">{{ $texts['reject'] }}</button>
                <button type="submit" name="status" value="custom" class="wb-btn wb-btn-primary">{{ $texts['save'] }}</button>
                <button type="submit" name="status" value="accepted" class="wb-btn wb-btn-secondary">{{ $texts['accept'] }}</button>
            </div>
        </form>
    </div>
    </details>
</noscript>
