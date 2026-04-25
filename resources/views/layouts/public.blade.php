<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $siteCssPath = public_path('site/css/site.css');
        $siteJsPath = public_path('site/js/site.js');
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta', [
            'title' => $title ?? config('app.name'),
            'metaDescription' => $metaDescription ?? config('app.slogan'),
        ])

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-icons.css">
        @if (is_file($siteCssPath))
            <link rel="stylesheet" href="{{ asset('site/css/site.css') }}?v={{ filemtime($siteCssPath) }}">
        @endif
        <style>
            .wb-public-footer-fallback {
                padding-top: 0;
            }

            .wb-public-footer .wb-footer-cookie-settings-link {
                padding-inline: 0;
                min-height: auto;
            }
        </style>
    </head>
    <body class="wb-public-body">
        @yield('content')

        @include('partials.public-privacy-consent')

        <div id="wb-overlay-root" class="wb-overlay-root">
            @if (($visitorPrivacy['banner_enabled'] ?? false) === true)
                <div class="wb-modal wb-cookie-consent-modal" id="wb-cookie-consent-preferences" data-wb-cookie-consent role="dialog" aria-modal="true" aria-labelledby="wb-cookie-consent-preferences-title" hidden aria-hidden="true">
                    <div class="wb-modal-dialog">
                        <div class="wb-modal-header">
                            <h2 class="wb-modal-title" id="wb-cookie-consent-preferences-title">Cookie preferences</h2>
                            <button class="wb-btn wb-btn-secondary wb-btn-icon wb-btn-sm" type="button" data-wb-cookie-consent-close aria-label="Close cookie settings">
                                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="wb-modal-body">
                            <div class="wb-stack-1">
                                <p class="wb-text-sm wb-text-muted wb-m-0">Necessary cookies are always on. Accept, Reject, or Save preferences stores the user choice. Close does not save consent.</p>
                            </div>
                            <div class="wb-stack-3">
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-3 wb-items-center">
                                        <div class="wb-stack-1">
                                            <strong>Necessary</strong>
                                            <p class="wb-text-sm wb-text-muted wb-m-0">Required for core site rendering, forms, and security behavior.</p>
                                        </div>
                                        <label class="wb-switch">
                                            <input type="checkbox" data-wb-cookie-category="necessary" data-wb-cookie-required="true" checked disabled>
                                            <span class="wb-switch-track"></span>
                                            <span>Always on</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-3 wb-items-center">
                                        <div class="wb-stack-1">
                                            <strong>Preferences</strong>
                                            <p class="wb-text-sm wb-text-muted wb-m-0">Remember language and interface preferences for returning visitors.</p>
                                        </div>
                                        <label class="wb-switch">
                                            <input type="checkbox" data-wb-cookie-category="preferences">
                                            <span class="wb-switch-track"></span>
                                            <span>Allow</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-3 wb-items-center">
                                        <div class="wb-stack-1">
                                            <strong>Analytics</strong>
                                            <p class="wb-text-sm wb-text-muted wb-m-0">Measure usage, sessions, referrers, and content performance for Visitor Reports.</p>
                                        </div>
                                        <label class="wb-switch">
                                            <input type="checkbox" data-wb-cookie-category="analytics">
                                            <span class="wb-switch-track"></span>
                                            <span>Allow</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="wb-card wb-card-muted">
                                    <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-3 wb-items-center">
                                        <div class="wb-stack-1">
                                            <strong>Marketing</strong>
                                            <p class="wb-text-sm wb-text-muted wb-m-0">Support campaign attribution preferences for future public-site integrations.</p>
                                        </div>
                                        <label class="wb-switch">
                                            <input type="checkbox" data-wb-cookie-category="marketing">
                                            <span class="wb-switch-track"></span>
                                            <span>Allow</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wb-modal-footer">
                            <button class="wb-btn wb-btn-secondary" type="button" data-wb-cookie-consent-reject>Reject all</button>
                            <div class="wb-cluster wb-cluster-2">
                                <button class="wb-btn wb-btn-secondary" type="button" data-wb-cookie-consent-save>Save preferences</button>
                                <button class="wb-btn wb-btn-primary" type="button" data-wb-cookie-consent-accept>Accept all</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="wb-modal wb-modal-xl" id="wb-gallery-viewer" role="dialog" aria-modal="true" aria-label="Gallery viewer">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-body">
                        <div class="wb-gallery-viewer">
                            <div class="wb-gallery-viewer-toolbar">
                                <button type="button" class="wb-btn wb-btn-secondary wb-gallery-viewer-prev" aria-label="Previous image">Previous</button>
                                <div class="wb-gallery-viewer-counter" aria-live="polite"></div>
                                <button type="button" class="wb-btn wb-btn-secondary wb-gallery-viewer-next" aria-label="Next image">Next</button>
                            </div>
                            <figure class="wb-gallery-viewer-media">
                                <img src="" alt="" class="wb-gallery-viewer-image">
                                <figcaption id="wb-gallery-viewer-caption" class="wb-gallery-viewer-caption" hidden></figcaption>
                                <p class="wb-gallery-viewer-meta wb-text-sm wb-text-muted" hidden></p>
                            </figure>
                            <div class="wb-cluster wb-cluster-end">
                                <button type="button" class="wb-btn wb-btn-secondary wb-gallery-viewer-close" data-wb-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js"></script>
        @if (is_file($siteJsPath))
            <script src="{{ asset('site/js/site.js') }}?v={{ filemtime($siteJsPath) }}"></script>
        @endif
        @if (($visitorPrivacy['banner_enabled'] ?? false) === true || ($visitorPrivacy['server_choice'] ?? null))
            <script>
                window.WebBlocksCmsPrivacyConsent = {
                    syncUrl: @json(route('public.privacy-consent.sync')),
                    csrfToken: @json(csrf_token()),
                    consentCookieName: @json(config('cms.visitor_reports.consent_cookie_name', 'webblocks_visitor_consent')),
                    consentLifetimeDays: @json(config('cms.visitor_reports.consent_cookie_lifetime_days', 180)),
                    initialServerChoice: @json($visitorPrivacy['server_choice'] ?? null),
                    reportsEnabled: @json((bool) config('cms.visitor_reports.enabled', true)),
                    utmEnabled: @json((bool) config('cms.visitor_reports.utm_enabled', true)),
                };
            </script>
            <script src="{{ asset('assets/webblocks-cms/js/privacy-consent-sync.js') }}" defer></script>
        @endif
        <script src="{{ asset('assets/webblocks-cms/js/public-slider.js') }}" defer></script>
    </body>
</html>
