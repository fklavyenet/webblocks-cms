(function () {
    'use strict';

    function initialize() {
        var root = document.querySelector('[data-cms-consent-root]');
        if (!root || root.dataset.consentInitialized) return;
        root.dataset.consentInitialized = '1';
        var config;
        try { config = JSON.parse(root.dataset.consentConfig); } catch (error) { return; }
        var services = config.services || {};
        var state = config.state || { services: {}, status: null, needs_choice: true };
        var modal = document.getElementById('wbCookiePreferences');
        var banner = root.querySelector('[data-cms-consent-banner]');
        var once = new WeakSet();
        var loadedScripts = {};
        var pending = false;
        var reloadRequired = false;

        function allowed(handle) {
            return !!services[handle] && (services[handle].category === 'necessary' || state.services[handle] === true);
        }

        function categoryPreferences() {
            var preferences = { necessary: true, preferences: false, analytics: false, marketing: false, external: false };
            Object.keys(services).forEach(function (handle) {
                var category = services[handle].category;
                preferences[category] = preferences[category] || allowed(handle);
            });
            return preferences;
        }

        function controls() {
            document.querySelectorAll('[data-cms-consent-service]').forEach(function (control) {
                control.checked = allowed(control.dataset.cmsConsentService);
            });
        }

        function open(trigger) {
            if (!modal || !window.WBModal) return false;
            controls();
            modal.hidden = false;
            window.WBModal.open(modal, trigger || null);
            return true;
        }

        function embeds() {
            document.querySelectorAll('[data-cms-consent-embed]').forEach(function (embed) {
                var handle = embed.dataset.cmsConsentEmbed;
                var content = embed.querySelector('[data-cms-consent-content]');
                var placeholder = embed.querySelector('[data-cms-consent-placeholder]');
                var enabled = !!services[handle] && (allowed(handle) || once.has(embed)) && !config.preview;
                if (!content || !placeholder) return;
                if (enabled && !content.firstElementChild) {
                    var template = embed.querySelector('[data-cms-consent-template]');
                    if (template) {
                        var fragment = template.content.cloneNode(true);
                        fragment.querySelectorAll('[data-src]').forEach(function (element) {
                            element.setAttribute('src', element.dataset.src);
                            element.removeAttribute('data-src');
                        });
                        content.appendChild(fragment);
                    }
                }
                if (!enabled) content.replaceChildren();
                placeholder.hidden = enabled;
                embed.querySelectorAll('[data-cms-consent-once], [data-cms-consent-allow-service]').forEach(function (button) {
                    button.disabled = !services[handle] || config.preview;
                });
                var unavailable = embed.querySelector('[data-cms-consent-unavailable]');
                if (unavailable) unavailable.hidden = !!services[handle];
            });
        }

        function scripts() {
            if (config.preview) return;
            Object.keys(services).forEach(function (handle) {
                var service = services[handle];
                if (!service.script_url) return;
                if (allowed(handle) && !loadedScripts[handle]) {
                    var script = document.createElement('script');
                    script.src = service.script_url;
                    script.async = true;
                    script.dataset.cmsConsentScript = handle;
                    document.head.appendChild(script);
                    loadedScripts[handle] = script;
                } else if (!allowed(handle) && loadedScripts[handle]) {
                    loadedScripts[handle].remove();
                    reloadRequired = true;
                }
            });
        }

        function buttons(disabled) {
            document.querySelectorAll('[data-cms-consent-accept], [data-cms-consent-reject], [data-cms-consent-save]').forEach(function (button) {
                button.disabled = disabled;
            });
        }

        function persist(grants, status) {
            if (pending) return Promise.resolve(false);
            pending = true;
            buttons(true);
            document.querySelectorAll('[data-cms-consent-error]').forEach(function (error) { error.hidden = true; });
            var revisions = {};
            Object.keys(services).forEach(function (handle) { revisions[handle] = services[handle].revision; });
            var request = config.preview ? Promise.resolve({ services: grants, status: status }) : window.fetch(config.syncUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ services: grants, revisions: revisions, status: status })
            }).then(function (response) {
                if (!response.ok) throw new Error('Consent sync failed');
                return response.json();
            });
            return request.then(function (payload) {
                Object.keys(services).forEach(function (handle) {
                    if (allowed(handle) && !payload.services[handle]) {
                        once = new WeakSet();
                        (services[handle].cookies || []).forEach(function (cookie) {
                            document.cookie = cookie.name + '=; path=' + cookie.path + '; max-age=0; samesite=lax';
                        });
                    }
                });
                once = new WeakSet();
                state = { services: payload.services, status: payload.status, needs_choice: false };
                controls();
                embeds();
                scripts();
                if (banner) banner.hidden = true;
                if (reloadRequired) {
                    modal.querySelector('[data-cms-consent-reload]').hidden = false;
                    open();
                } else if (window.WBModal && window.WBModal.getActive() === modal) {
                    window.WBModal.close(modal);
                }
                document.documentElement.dispatchEvent(new CustomEvent('wb:cookie-consent:change', {
                    bubbles: true, detail: { cms: true, status: state.status, preferences: categoryPreferences(), services: Object.assign({}, state.services), hasConsent: true }
                }));
                if (reloadRequired && !config.preview) {
                    var reloadEvent = new CustomEvent('cms:consent:reload-required', { bubbles: true, cancelable: true });
                    if (document.documentElement.dispatchEvent(reloadEvent)) window.location.reload();
                }
                return true;
            }).catch(function () {
                document.querySelectorAll('[data-cms-consent-error]').forEach(function (error) { error.hidden = false; });
                if (!window.WBModal || window.WBModal.getActive() !== modal) open();
                return false;
            }).finally(function () { pending = false; buttons(false); });
        }

        function all(enabled) {
            var grants = {};
            Object.keys(services).forEach(function (handle) { grants[handle] = enabled || services[handle].category === 'necessary'; });
            return persist(grants, enabled ? 'accepted' : 'rejected');
        }

        // Capture the compatibility anchor before any site-specific bridge.
        document.addEventListener('click', function (event) {
            if (!(event.target instanceof Element)) return;
            var link = event.target.closest('a[href="#cookie-settings"], [data-cms-consent-open], [data-wb-cookie-consent-open]');
            if (link && !link.closest('pre, code, template, [data-wb-demo-static]')) {
                if (link.hasAttribute('data-wb-cookie-consent-open') && link.getAttribute('data-wb-target') && link.getAttribute('data-wb-target') !== '#wbCookiePreferences') return;
                if (link.disabled || link.getAttribute('aria-disabled') === 'true') return;
                event.preventDefault();
                event.stopImmediatePropagation();
                open(link);
                return;
            }
            var accept = event.target.closest('[data-cms-consent-accept]');
            var reject = event.target.closest('[data-cms-consent-reject]');
            var save = event.target.closest('[data-cms-consent-save]');
            if (accept || reject || save) {
                event.preventDefault();
                if (accept) all(true);
                else if (reject) all(false);
                else {
                    var grants = {};
                    document.querySelectorAll('[data-cms-consent-service]').forEach(function (control) { grants[control.dataset.cmsConsentService] = control.checked; });
                    persist(grants, 'custom');
                }
                return;
            }
            var action = event.target.closest('[data-cms-consent-once], [data-cms-consent-allow-service]');
            if (action && !action.disabled) {
                event.preventDefault();
                var embed = action.closest('[data-cms-consent-embed]');
                if (!embed) return;
                var handle = embed.dataset.cmsConsentEmbed;
                if (!services[handle]) return;
                if (action.hasAttribute('data-cms-consent-once')) {
                    once.add(embed);
                    embeds();
                } else {
                    persist(Object.assign({}, state.services, { [handle]: true }), 'custom');
                }
            }
            if (event.target.closest('[data-cms-consent-reload-page]')) window.location.reload();
        }, true);

        document.documentElement.addEventListener('wb:cookie-consent:change', function (event) {
            if (!event.detail || event.detail.cms || !event.detail.preferences) return;
            var grants = {};
            Object.keys(services).forEach(function (handle) { grants[handle] = !!event.detail.preferences[services[handle].category]; });
            persist(grants, event.detail.status || 'custom');
        });
        document.querySelectorAll('a[href="#cookie-settings"]').forEach(function (link) {
            link.setAttribute('aria-controls', 'wbCookiePreferences');
            link.setAttribute('aria-haspopup', 'dialog');
        });
        controls();
        embeds();
        scripts();
        if (banner) banner.hidden = !state.needs_choice;
        window.WebBlocksCmsConsent = {
            open: open,
            get: function () { return { status: state.status, services: Object.assign({}, state.services) }; },
            has: allowed,
            set: function (grants) { return persist(grants, 'custom'); }
        };
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
