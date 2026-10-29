(function () {
    'use strict';
    var root = document.querySelector('[data-cms-consent-root]');
    var config = JSON.parse(root.dataset.consentConfig);
    var choice = new URLSearchParams(location.search).get('choice');
    if (['accepted', 'rejected', 'custom'].includes(choice)) {
        config.state.status = choice;
        config.state.needs_choice = false;
        config.state.services = {};
        Object.keys(config.services).forEach(function (handle) { config.state.services[handle] = choice === 'accepted' || (choice === 'custom' && handle === 'youtube'); });
        root.dataset.consentConfig = JSON.stringify(config);
    }
    if (new URLSearchParams(location.search).has('storage-blocked')) {
        Object.defineProperty(window, 'localStorage', { configurable: true, get: function () { throw new Error('Synthetic blocked storage'); } });
    }
    var direct = new URLSearchParams(location.search).has('direct') || location.hash === '#cookie-settings';
    if (new URLSearchParams(location.search).has('direct')) root.setAttribute('data-cms-consent-auto-open', '');
    var requests = [];
    var changes = 0;
    var fail = false;
    var reloadRequests = 0;
    window.fetch = function (url, options) {
        requests.push({ url: url, options: options, body: JSON.parse(options.body) });
        var body = JSON.parse(options.body);
        return Promise.resolve({ ok: !fail, json: function () { return Promise.resolve({ ok: true, status: body.status, services: body.services }); } });
    };
    document.documentElement.addEventListener('cms:consent:reload-required', function (event) { reloadRequests += 1; event.preventDefault(); });
    document.documentElement.addEventListener('wb:cookie-consent:change', function () { changes += 1; });
    document.addEventListener('DOMContentLoaded', function () {
        requestAnimationFrame(async function () {
            var output = document.getElementById('test-status');
            var count = 0;
            function check(value, label) { if (!value) throw new Error(label); count += 1; }
            function frame() { return new Promise(function (resolve) { requestAnimationFrame(resolve); }); }
            function button(selector) { return document.querySelector(selector); }
            var modal = document.getElementById('wbCookiePreferences');
            var link = document.getElementById('settings-link');
            try {
                check(!!window.WebBlocksCmsConsent, 'CMS consent runtime initialized');
                check(modal.classList.contains('is-open') === direct, 'Only an explicit settings entry auto-opens the preference center');
                if (direct) { button('[data-wb-dismiss="modal"]').click(); await frame(); }
                check(requests.length === 0 && changes === 0, 'Initialization writes no consent');
                if (choice === 'accepted') check(!!document.querySelector('[data-cms-consent-script="synthetic-script"]'), 'A stored script permission starts the managed source');
                check(root.querySelector('[data-cms-consent-banner]').hidden === !!choice, 'Banner respects previous choice');
                if (!choice) check(!document.querySelector('[data-cms-consent-content] iframe'), 'No iframe before consent');
                document.querySelector('#native-settings a').click(); await frame();
                check(modal.classList.contains('is-open'), 'Native system block opens the single preference center');
                check(requests.length === 0, 'Native system block grants nothing');
                button('[data-wb-dismiss="modal"]').click(); await frame();
                var menu = document.createElement('a'); menu.href = config.preferencesUrl; menu.textContent = 'Synthetic menu entry'; document.querySelector('main').appendChild(menu);
                menu.click(); await frame();
                check(modal.classList.contains('is-open'), 'Generated permanent menu URL opens preferences in place');
                button('[data-wb-dismiss="modal"]').click(); await frame();
                link.focus(); link.click(); await frame();
                check(modal.classList.contains('is-open'), 'Compatibility anchor opens preference modal');
                check(requests.length === 0 && changes === 0, 'Opening does not change consent or sync');
                check(modal.contains(document.activeElement), 'Focus enters modal');
                var youtube = button('[data-cms-consent-service="youtube"]');
                var previous = youtube.checked;
                youtube.checked = !previous;
                button('[data-wb-dismiss="modal"]').click(); await frame();
                link.click(); await frame();
                check(youtube.checked === previous, 'Unsaved edits are discarded on reopen');
                check(requests.length === 0, 'Closing unsaved edits does not sync');
                youtube.checked = true;
                button('[data-cms-consent-save]').click(); await frame();
                check(requests.length === 1 && requests[0].body.status === 'custom', 'Saving uses existing sync contract with services');
                check(requests[0].options.credentials === 'same-origin', 'Server request uses same-origin credentials');
                check(!!document.querySelector('[data-cms-consent-content] iframe'), 'Saved service loads its embed');
                check(changes === 1, 'Saving emits exactly one consent event');
                link.click(); await frame();
                check(youtube.checked, 'Reopen displays saved preference');
                button('[data-cms-consent-reject]').click(); await frame();
                check(!document.querySelector('[data-cms-consent-content] iframe'), 'Withdrawal removes embedded content');
                var savedRequests = requests.length;
                document.querySelector('[data-cms-consent-once]').click(); await frame();
                check(!!document.querySelector('[data-cms-consent-content] iframe'), 'One-time action loads content');
                check(requests.length === savedRequests && !window.WebBlocksCmsConsent.has('youtube'), 'One-time permission is not persistent service permission');
                var dynamic = document.createElement('a'); dynamic.href = '#cookie-settings'; dynamic.textContent = 'Dynamic settings'; document.querySelector('main').appendChild(dynamic); dynamic.click(); await frame();
                check(modal.classList.contains('is-open'), 'Dynamic anchor uses same delegated action');
                fail = true;
                button('[data-cms-consent-accept]').click(); await frame();
                check(!window.WebBlocksCmsConsent.has('youtube'), 'Failed sync does not grant permission');
                check(!modal.querySelector('[data-cms-consent-error]').hidden, 'Failed save displays accessible feedback');
                fail = false;
                button('[data-cms-consent-reject]').click(); await frame();
                check(!window.WebBlocksCmsConsent.has('youtube'), 'Reject remains effective');
                check(!document.querySelector('[data-cms-consent-content] iframe'), 'Persistent rejection clears one-time grants');
                link.click(); await frame();
                button('[data-cms-consent-accept]').click(); await frame();
                check(!!document.querySelector('[data-cms-consent-script="synthetic-script"]') || reloadRequests > 0, 'Managed script starts after permission or requires the pending reload');
                link.click(); await frame();
                button('[data-cms-consent-reject]').click(); await frame();
                check(reloadRequests > 0, 'Revoking an executed managed script requests a page reload');
                check(!document.querySelector('[data-cms-consent-script="synthetic-script"]'), 'Revoked script element is removed');
                output.textContent = 'PASS: ' + count + ' consent checks · ' + (choice || 'first visit');
            } catch (error) { output.textContent = 'FAIL: ' + error.message; throw error; }
        });
    });
})();
