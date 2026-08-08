{{--
    The visitor-facing half of the consent feature.

    The server half has shipped for a long time: POST /privacy-consent/sync
    validates a status plus the necessary/preferences/analytics/marketing set and
    returns the consent cookie, and VisitorConsent gates analytics tracking on it.
    What never shipped was any markup to produce that decision, so the endpoint
    was unreachable from a real site and cms/js/privacy-consent-sync.js — which
    bridges the UI runtime to the endpoint — sat in the package without a single
    view including it.

    The markup below is WebBlocks UI's canonical Cookie Consent pattern, not a
    CMS reimplementation: WBCookieConsent upgrades [data-wb-cookie-consent],
    owns the storage keys, and emits wb:cookie-consent:change, which is exactly
    the event privacy-consent-sync.js listens for.
--}}
<div class="wb-cookie-consent wb-cookie-consent-banner" data-wb-cookie-consent hidden>
    <section class="wb-card wb-cookie-consent-card" aria-label="{{ $publicText('cookie_consent.aria_label') }}">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-items-start">
            <div class="wb-stack-1">
                <strong>{{ $publicText('cookie_consent.title') }}</strong>
            </div>
            <button class="wb-btn wb-btn-secondary wb-btn-icon wb-btn-sm" type="button" data-wb-cookie-consent-close aria-label="{{ $publicText('cookie_consent.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <div class="wb-card-body">
            <div class="wb-cluster wb-cluster-between wb-cluster-4 wb-items-center">
                <p class="wb-text-sm wb-text-muted wb-m-0">{{ $publicText('cookie_consent.body') }}</p>
                <div class="wb-cluster wb-cluster-2">
                    <button class="wb-btn wb-btn-secondary wb-btn-sm" type="button" data-wb-cookie-consent-reject>{{ $publicText('cookie_consent.reject') }}</button>
                    <button class="wb-btn wb-btn-secondary wb-btn-sm" type="button" data-wb-cookie-consent-open data-wb-target="#wbCookiePreferences">{{ $publicText('cookie_consent.customize') }}</button>
                    <button class="wb-btn wb-btn-primary wb-btn-sm" type="button" data-wb-cookie-consent-accept>{{ $publicText('cookie_consent.accept') }}</button>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="wb-modal" id="wbCookiePreferences" data-wb-cookie-consent role="dialog" aria-modal="true" aria-labelledby="wbCookiePreferencesTitle" hidden>
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="wbCookiePreferencesTitle">{{ $publicText('cookie_consent.preferences_title') }}</h2>
            <button class="wb-btn wb-btn-secondary wb-btn-icon wb-btn-sm" type="button" data-wb-cookie-consent-close aria-label="{{ $publicText('cookie_consent.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <div class="wb-modal-body">
            <div class="wb-stack-1">
                <p class="wb-text-sm wb-text-muted">{{ $publicText('cookie_consent.preferences_body') }}</p>
            </div>
            <div class="wb-stack-3">
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-4 wb-items-center">
                        <div class="wb-stack-1">
                            <strong>{{ $publicText('cookie_consent.necessary_title') }}</strong>
                            <p class="wb-text-sm wb-text-muted wb-m-0">{{ $publicText('cookie_consent.necessary_body') }}</p>
                        </div>
                        <label class="wb-switch">
                            <input type="checkbox" data-wb-cookie-category="necessary" data-wb-cookie-required="true" checked disabled>
                            <span class="wb-switch-track"></span>
                            <span>{{ $publicText('cookie_consent.always_on') }}</span>
                        </label>
                    </div>
                </div>
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-4 wb-items-center">
                        <div class="wb-stack-1">
                            <strong>{{ $publicText('cookie_consent.preferences_category_title') }}</strong>
                            <p class="wb-text-sm wb-text-muted wb-m-0">{{ $publicText('cookie_consent.preferences_category_body') }}</p>
                        </div>
                        <label class="wb-switch">
                            <input type="checkbox" data-wb-cookie-category="preferences">
                            <span class="wb-switch-track"></span>
                            <span>{{ $publicText('cookie_consent.allow') }}</span>
                        </label>
                    </div>
                </div>
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-4 wb-items-center">
                        <div class="wb-stack-1">
                            <strong>{{ $publicText('cookie_consent.analytics_title') }}</strong>
                            <p class="wb-text-sm wb-text-muted wb-m-0">{{ $publicText('cookie_consent.analytics_body') }}</p>
                        </div>
                        <label class="wb-switch">
                            <input type="checkbox" data-wb-cookie-category="analytics">
                            <span class="wb-switch-track"></span>
                            <span>{{ $publicText('cookie_consent.allow') }}</span>
                        </label>
                    </div>
                </div>
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-4 wb-items-center">
                        <div class="wb-stack-1">
                            <strong>{{ $publicText('cookie_consent.marketing_title') }}</strong>
                            <p class="wb-text-sm wb-text-muted wb-m-0">{{ $publicText('cookie_consent.marketing_body') }}</p>
                        </div>
                        <label class="wb-switch">
                            <input type="checkbox" data-wb-cookie-category="marketing">
                            <span class="wb-switch-track"></span>
                            <span>{{ $publicText('cookie_consent.allow') }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="wb-modal-footer">
            <button class="wb-btn wb-btn-secondary" type="button" data-wb-cookie-consent-reject>{{ $publicText('cookie_consent.reject_all') }}</button>
            <div class="wb-cluster wb-cluster-2">
                <button class="wb-btn wb-btn-secondary" type="button" data-wb-cookie-consent-save>{{ $publicText('cookie_consent.save') }}</button>
                <button class="wb-btn wb-btn-primary" type="button" data-wb-cookie-consent-accept>{{ $publicText('cookie_consent.accept') }}</button>
            </div>
        </div>
    </div>
</div>
