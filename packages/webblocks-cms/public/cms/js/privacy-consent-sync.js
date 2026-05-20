(function () {
    var config = window.WebBlocksCmsPrivacyConsent;

    if (!config || !config.syncUrl || !config.reportsEnabled) {
        return;
    }

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
        if (!window.localStorage) {
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
        if (!window.localStorage) {
            return;
        }

        if (window.localStorage.getItem(consentStatusKey)) {
            return;
        }

        if (choice === 'accepted') {
            window.localStorage.setItem(consentStatusKey, 'accepted');
            window.localStorage.setItem(consentPreferencesKey, JSON.stringify({ necessary: true, preferences: true, analytics: true, marketing: true }));
            return;
        }

        if (choice === 'declined') {
            window.localStorage.setItem(consentStatusKey, 'rejected');
            window.localStorage.setItem(consentPreferencesKey, JSON.stringify({ necessary: true, preferences: false, analytics: false, marketing: false }));
        }
    }

    function syncServerConsent(detail) {
        if (!detail || !detail.preferences || !window.fetch) {
            return Promise.resolve();
        }

        return window.fetch(config.syncUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken || '',
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
                setCookie(config.consentCookieName, payload.server_decision, config.consentLifetimeDays);
            }
        }).catch(function () {
            setCookie(config.consentCookieName, detail.preferences.analytics ? 'accepted' : 'declined', config.consentLifetimeDays);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncClientStateFromServerChoice(config.initialServerChoice);

        var localState = readLocalState();

        if (localState && serverDecisionFor(localState) !== config.initialServerChoice && getCookie(config.consentCookieName) !== serverDecisionFor(localState)) {
            syncServerConsent(localState);
        }

        document.documentElement.addEventListener('wb:cookie-consent:change', function (event) {
            syncServerConsent(event.detail);
        });
    });
})();
