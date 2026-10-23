/* Synthetic browser regression: no update request leaves this page. */
(function () {
    'use strict';
    var form = document.querySelector('[data-pc-update-form]');
    var modal = document.querySelector('[data-pc-update-modal]');
    var opener = document.getElementById('pc-update-progress-open');
    var elapsed = modal.querySelector('[data-update-elapsed]');
    var bar = modal.querySelector('[data-update-loading]');
    var stage = modal.querySelector('[data-update-stage]');
    var message = modal.querySelector('[data-update-message]');
    var operation = '00000000-0000-4000-8000-000000000015';
    var scenario = new URLSearchParams(window.location.search).get('case') || 'admission';
    var requests = [];
    var posts = 0;
    var opens = 0;
    var checks = [];
    var storageKey = 'publisher-client.operation:/health';
    sessionStorage.removeItem(storageKey);
    modal.addEventListener('wb:modal:open', function () { opens += 1; });
    if (scenario === 'resume') { opener.setAttribute('data-active-operation', operation); }
    if (scenario === 'client' || scenario === 'legacy') { opener.removeAttribute('data-pc-admission'); }
    if (scenario === 'legacy') {
        var list = document.createElement('ol');
        list.setAttribute('data-update-steps', '');
        stage.replaceWith(list);
    }
    window.fetch = function (url, options) {
        var method = options.method || 'GET';
        if (method === 'POST') { posts += 1; }
        return new Promise(function (resolve, reject) {
            requests.push({ method: method, url: String(url), options: options, resolve: resolve, reject: reject });
        });
    };
    function wait(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }
    function seconds() { var parts = elapsed.textContent.split(':'); return Number(parts[0]) * 60 + Number(parts[1]); }
    function assert(condition, text) {
        if (!condition) { throw new Error(text); }
        checks.push(text);
        document.getElementById('test-status').textContent = checks.join('\n');
    }
    async function request(method) {
        for (var attempt = 0; attempt < 500; attempt += 1) {
            var index = requests.findIndex(function (item) { return item.method === method; });
            if (index !== -1) { return requests.splice(index, 1)[0]; }
            await wait(10);
        }
        throw new Error('Missing ' + method + ' request');
    }
    function response(pending, status, body) {
        pending.resolve({ status: status, ok: status >= 200 && status < 300, redirected: false, json: function () { return Promise.resolve(body); } });
    }
    function progress(ms, name, extra) {
        return Object.assign({ operation_id: operation, elapsed_ms: ms, terminal: false, state: 'running', message: 'Synthetic operation running',
            steps: [{ label: 'Download', state: 'complete', elapsed_ms: 1000 }, { label: name, state: 'running', elapsed_ms: 100 }], stage_label: name }, extra || {});
    }
    async function poll(body, status) {
        var pending = await request('GET');
        assert(new URL(pending.url).searchParams.get('operation') === operation, 'Poll stays bound to the admitted operation');
        response(pending, status || 200, { progress: body });
        await wait(40);
    }
    async function run() {
        if (scenario === 'resume') {
            await poll(progress(65000, 'Restore session'));
            assert(seconds() >= 65, 'Reload adopts the existing server elapsed time');
            assert(posts === 0 && opens === 1, 'Reload opens once and never repeats the update POST');
        } else {
            form.requestSubmit();
            var post = await request('POST');
            assert(opens === 1 && modal.classList.contains('is-open'), 'Modal opens immediately once');
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            assert(posts === 1, 'Duplicate submit never starts another update');
            if (scenario === 'auth' || scenario === 'rejected') {
                response(post, scenario === 'auth' ? 419 : 409, { message: 'Synthetic admission rejected' });
                await wait(50);
                var failedAt = seconds();
                await wait(1100);
                assert(seconds() === failedAt && bar.hidden, 'Rejected admission freezes the timer and stops activity');
                assert(!modal.querySelector('[data-update-return]').hidden, 'Rejected admission offers a return link');
                if (scenario === 'auth') { assert(message.textContent === opener.getAttribute('data-progress-auth') && modal.querySelector('[data-update-recheck]').hidden, 'Authentication failure retains its exact message without retrying'); }
                assert(opens === 1 && posts === 1 && requests.length === 0, 'Rejected admission neither reopens nor polls nor reposts');
                document.getElementById('test-status').textContent = 'PASS ' + scenario + '\n' + checks.join('\n');
                return;
            }
            if (scenario === 'client' || scenario === 'legacy') {
                operation = post.options.body.get('operation_id');
                response(post, 200, {});
            } else {
                await wait(2200);
                var before = seconds();
                assert(before >= 2, 'Timer runs during delayed server admission');
                response(post, 202, { operation_id: operation });
                await wait(40);
                assert(opens === 1 && seconds() >= before, 'Admission does not reopen the modal or reset the timer');
            }
            await poll(progress(0, 'Verify'));
            assert(opens === 1 && !bar.hidden, 'First progress keeps the original modal active');
            if (scenario === 'legacy') {
                assert(modal.querySelectorAll('[data-update-steps] li').length === 2, 'Existing product step-list views remain compatible');
            } else {
                assert(stage.textContent === 'Verify' && !bar.hasAttribute('aria-valuenow'), 'Native bar shows the current phase without a fabricated percentage');
                assert(!modal.querySelector('[data-update-steps]'), 'Compact modal contains no growing step list');
            }
        }
        var beforePoll = seconds();
        var height = modal.querySelector('.wb-modal-dialog').offsetHeight;
        await poll(progress(0, 'Backup', { steps: Array.from({ length: 12 }, function () { return { label: 'Backup', state: 'complete', elapsed_ms: 1 }; }) }));
        assert(seconds() >= beforePoll, 'Lower server samples never move elapsed time backwards');
        if (scenario !== 'legacy') { assert(modal.querySelector('.wb-modal-dialog').offsetHeight === height, 'Additional recorded phases do not grow the modal'); }
        await poll(progress(0, 'Foreign', { operation_id: '00000000-0000-4000-8000-000000000099' }));
        assert(bar.hidden && message.textContent === opener.getAttribute('data-progress-unknown'), 'Foreign operation cannot claim activity or completion');
        await poll(null, 503);
        assert(bar.hidden && message.textContent === opener.getAttribute('data-progress-unreachable'), 'Unavailable status hides unconfirmed activity');
        await poll(progress(100, 'Apply'));
        assert(!bar.hidden, 'Confirmed progress restores the activity indicator');
        await poll(progress(100, 'Apply', { state: 'interrupted' }));
        var pausedAt = seconds();
        await wait(1100);
        assert(seconds() === pausedAt && bar.hidden, 'Interrupted operation freezes elapsed time and activity');
        modal.querySelector('[data-update-recheck]').click();
        await poll(progress(200, 'Health check'));
        assert(opens === 1 && posts === (scenario === 'resume' ? 0 : 1), 'Read-only recheck neither reopens nor reposts');
        await poll(progress(250, 'Health check', { terminal: true, state: 'success', installed_version: '1.0.0', target_version: '2.0.0' }));
        assert(bar.hidden && sessionStorage.getItem(storageKey) === operation, 'Version mismatch cannot confirm terminal success');
        await poll(progress(300, 'Complete', { terminal: true, state: 'success', installed_version: '2.0.0', target_version: '2.0.0' }));
        var finishedAt = seconds();
        await wait(1700);
        assert(bar.hidden && seconds() === finishedAt, 'Verified completion stops both activity and elapsed time');
        assert(sessionStorage.getItem(storageKey) === null && window.location.hash === '#completed', 'Only verified success clears the receipt and returns');
        assert(opens === 1, 'Entire operation uses one modal opening');
        document.getElementById('test-status').textContent = 'PASS ' + scenario + '\n' + checks.join('\n');
    }
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            run().catch(function (error) { document.getElementById('test-status').textContent = 'FAIL ' + scenario + ': ' + error.message + '\n' + checks.join('\n'); });
        }, 0);
    });
})();
