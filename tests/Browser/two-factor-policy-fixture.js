(function () {
    var output = document.getElementById('test-status');
    var form = document.getElementById('two-factor-policy');
    var button = form.querySelector('[data-wb-policy-send-code]');
    var status = form.querySelector('[data-wb-policy-delivery-status]');
    var password = form.querySelector('[name="current_password"]');
    var code = form.querySelector('[name="code"]');
    var root = form.querySelector('[name="require_super_admin"][type="checkbox"]');
    var roles = Array.from(form.querySelectorAll('[name="role_ids[]"]'));
    var calls = 0;
    var submits = 0;
    var mode = 'sent';
    var originalUrl = window.location.href;
    var delay = function (ms) { return new Promise(function (resolve) { window.setTimeout(resolve, ms); }); };
    function assert(condition, message) { if (!condition) throw new Error(message); }
    async function waitUntil(predicate) {
        var deadline = Date.now() + 15000;
        while (!predicate()) {
            assert(Date.now() < deadline, 'Timed out waiting for UI state');
            await delay(25);
        }
    }
    function delivered() { return waitUntil(function () { return !button.hasAttribute('aria-busy'); }); }
    function unlocked() { return waitUntil(function () { return !button.disabled; }); }

    form.addEventListener('submit', function (event) { event.preventDefault(); submits++; });
    password.value = 'synthetic-fixture-password';
    code.value = 'synthetic-recovery-code';
    root.checked = true;
    roles.forEach(function (role, index) { role.checked = index % 2 === 0; });
    function preserved() {
        assert(password.value === 'synthetic-fixture-password' && code.value === 'synthetic-recovery-code', 'Credential entry lost');
        assert(root.checked && roles.every(function (role, index) { return role.checked === (index % 2 === 0); }), 'Role selection lost');
        assert(window.location.href === originalUrl && submits === 0, 'Unexpected navigation or policy submission');
    }
    window.fetch = async function (url, options) {
        calls++;
        assert(new URL(url).pathname === '/webadmin/two-factor/email-code', 'Wrong endpoint');
        assert(options.method === 'POST' && options.credentials === 'same-origin' && options.headers.Accept === 'application/json', 'Request contract');
        var payload = options.body;
        assert(Array.from(payload.keys()).sort().join(',') === '_token,purpose,tab', 'Policy or credential data sent');
        assert(payload.get('purpose') === 'action' && payload.get('tab') === 'policy' && payload.get('_token'), 'Missing send metadata');
        await delay(30);
        if (mode === 'offline') throw new Error('Synthetic connection failure');
        if (mode === 'html') return new Response('<!doctype html><title>Session expired</title>', { headers: { 'Content-Type': 'text/html' } });
        var failed = mode === 'send_failed';
        var throttled = mode === 'send_throttled';
        return new Response(JSON.stringify({ status: mode, message: 'Synthetic ' + mode, retry_after: failed ? 0 : 1 }), {
            status: failed ? 503 : (throttled ? 429 : 200), headers: { 'Content-Type': 'application/json' }
        });
    };
    (async function () {
        button.click();
        button.click();
        assert(button.disabled && button.getAttribute('aria-busy') === 'true', 'Missing pending state');
        await delivered();
        assert(calls === 1 && status.textContent === 'Synthetic sent' && !status.classList.contains('wb-field-error'), 'Delivery success or duplicate suppression');
        assert(document.activeElement === code && button.disabled, 'Code focus or resend lock');
        preserved();
        await unlocked();
        assert(!button.disabled, 'Countdown did not unlock');
        for (var next of ['offline', 'html', 'send_failed', 'send_throttled']) {
            mode = next;
            button.click();
            await delivered();
            assert(status.classList.contains('wb-field-error') && !button.hasAttribute('aria-busy'), 'Missing delivery error');
            preserved();
            if (next === 'send_throttled') {
                assert(button.disabled, 'Server throttle did not disable resend');
                await unlocked();
            }
            assert(!button.disabled, 'Resend did not recover');
        }
        mode = 'sent';
        button.click();
        await delivered();
        preserved();
        assert(!status.classList.contains('wb-field-error') && calls === 6, 'Recovery after delivery failure');
        output.textContent = 'PASS: async delivery, minimal request, retained password/code/roles, no navigation, duplicate suppression, countdown, offline/HTML/mail/throttle failures and retry.';
        output.dataset.result = 'pass';
    }()).catch(function (error) {
        output.textContent = 'FAIL: ' + error.message;
        output.dataset.result = 'fail';
    });
}());
