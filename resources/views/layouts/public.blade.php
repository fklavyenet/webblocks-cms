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

        <script>
            (function () {
                var initialServerChoice = @json($visitorPrivacy['server_choice'] ?? null);

                if (! initialServerChoice || !window.localStorage) {
                    return;
                }

                var consentStatusKey = 'wb-cookie-consent';
                var consentPreferencesKey = 'wb-cookie-consent-preferences';

                if (window.localStorage.getItem(consentStatusKey)) {
                    return;
                }

                if (initialServerChoice === 'accepted') {
                    window.localStorage.setItem(consentStatusKey, 'accepted');
                    window.localStorage.setItem(consentPreferencesKey, JSON.stringify({ necessary: true, preferences: true, analytics: true, marketing: true }));
                    return;
                }

                if (initialServerChoice === 'declined') {
                    window.localStorage.setItem(consentStatusKey, 'rejected');
                    window.localStorage.setItem(consentPreferencesKey, JSON.stringify({ necessary: true, preferences: false, analytics: false, marketing: false }));
                }
            })();
        </script>

        <script src="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js"></script>
        @if (is_file($siteJsPath))
            <script src="{{ asset('site/js/site.js') }}?v={{ filemtime($siteJsPath) }}"></script>
        @endif
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var consentSyncUrl = @json(route('public.privacy-consent.sync'));
                var consentCookieName = @json(config('cms.visitor_reports.consent_cookie_name', 'webblocks_visitor_consent'));
                var initialServerChoice = @json($visitorPrivacy['server_choice'] ?? null);
                var consentLifetimeDays = @json(config('cms.visitor_reports.consent_cookie_lifetime_days', 180));
                var consentStatusKey = 'wb-cookie-consent';
                var consentPreferencesKey = 'wb-cookie-consent-preferences';

                function setCookie(name, value, days) {
                    var maxAge = Math.max(1, Number(days || 180)) * 24 * 60 * 60;
                    document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; samesite=lax';
                }

                function getCookie(name) {
                    var cookies = document.cookie ? document.cookie.split('; ') : [];

                    for (var index = 0; index < cookies.length; index += 1) {
                        var parts = cookies[index].split('=');

                        if (parts[0] === name) {
                            return decodeURIComponent(parts.slice(1).join('='));
                        }
                    }

                    return null;
                }

                function readLocalState() {
                    if (! window.localStorage) {
                        return null;
                    }

                    var status = String(window.localStorage.getItem(consentStatusKey) || '').trim();
                    var preferences = null;

                    try {
                        preferences = JSON.parse(window.localStorage.getItem(consentPreferencesKey) || 'null');
                    } catch (error) {
                        preferences = null;
                    }

                    if (!status || !preferences || typeof preferences !== 'object') {
                        return null;
                    }

                    return {
                        status: status,
                        preferences: preferences
                    };
                }

                function serverDecisionFor(detail) {
                    return detail && detail.preferences && detail.preferences.analytics ? 'accepted' : 'declined';
                }

                function syncClientStateFromServerChoice(choice) {
                    if (! window.WBStorage) {
                        return;
                    }

                    if (choice === 'accepted') {
                        WBStorage.set(consentStatusKey, 'accepted');
                        WBStorage.set(consentPreferencesKey, JSON.stringify({ necessary: true, preferences: true, analytics: true, marketing: true }));
                        return;
                    }

                    if (choice === 'declined') {
                        WBStorage.set(consentStatusKey, 'rejected');
                        WBStorage.set(consentPreferencesKey, JSON.stringify({ necessary: true, preferences: false, analytics: false, marketing: false }));
                    }
                }

                function syncServerConsent(detail) {
                    if (! detail || !detail.preferences || !window.fetch) {
                        return Promise.resolve();
                    }

                    return window.fetch(consentSyncUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            status: detail.status,
                            preferences: detail.preferences
                        })
                    }).then(function (response) {
                        if (!response.ok) {
                            throw new Error('Cookie consent sync failed.');
                        }

                        return response.json();
                    }).then(function (payload) {
                        if (payload && payload.server_decision) {
                            setCookie(consentCookieName, payload.server_decision, consentLifetimeDays);
                        }
                    }).catch(function () {
                        setCookie(consentCookieName, detail.preferences.analytics ? 'accepted' : 'declined', consentLifetimeDays);
                    });
                }

                if (window.WBStorage && !WBStorage.get(consentStatusKey) && initialServerChoice) {
                    syncClientStateFromServerChoice(initialServerChoice);
                }

                var localState = readLocalState();

                if (localState && serverDecisionFor(localState) !== initialServerChoice && getCookie(consentCookieName) !== serverDecisionFor(localState)) {
                    syncServerConsent(localState);
                }

                document.documentElement.addEventListener('wb:cookie-consent:change', function (event) {
                    syncServerConsent(event.detail);
                });

                document.querySelectorAll('[data-wb-slider]').forEach(function (slider) {
                    var track = slider.querySelector('[data-wb-slider-track]');
                    var slides = Array.prototype.slice.call(slider.querySelectorAll('[data-wb-slider-slide]'));
                    var previous = slider.querySelector('[data-wb-slider-prev]');
                    var next = slider.querySelector('[data-wb-slider-next]');
                    var dots = Array.prototype.slice.call(slider.querySelectorAll('[data-wb-slider-dot]'));

                    if (!track || slides.length < 2) {
                        return;
                    }

                    var activeIndex = 0;

                    function render(index) {
                        activeIndex = (index + slides.length) % slides.length;
                        track.style.transform = 'translateX(' + (activeIndex * -100) + '%)';

                        dots.forEach(function (dot, dotIndex) {
                            dot.classList.toggle('is-active', dotIndex === activeIndex);
                            dot.setAttribute('aria-selected', dotIndex === activeIndex ? 'true' : 'false');
                        });
                    }

                    if (previous) {
                        previous.addEventListener('click', function () {
                            render(activeIndex - 1);
                        });
                    }

                    if (next) {
                        next.addEventListener('click', function () {
                            render(activeIndex + 1);
                        });
                    });

                    dots.forEach(function (dot, dotIndex) {
                        dot.addEventListener('click', function () {
                            render(dotIndex);
                        });
                    });

                    render(0);
                });
            });
        </script>
    </body>
</html>
