/* Synthetic transport only. The shipped runner, modal and row feedback execute unchanged. */
(function () {
    'use strict';
    var scenario = new URLSearchParams(location.search).get('case') || 'complete';
    var host = document.querySelector('[data-wb-job-runner]');
    var modal = document.querySelector('[data-wb-job-modal]');
    var requests = [], checks = [], posts = 0;
    if (['passive', 'feedback'].includes(scenario)) document.querySelectorAll('[data-wb-job-runner]').forEach(function (runner) { runner.dataset.autostart = 'false'; });
    window.fetch = function (url, options) {
        if (options.method === 'POST' || options.method === 'PATCH') posts++;
        return new Promise(function (resolve, reject) {
            requests.push({url: String(url), options: options, resolve: resolve, reject: reject});
        });
    };
    function wait(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }
    function assert(value, text) {
        if (!value) throw new Error(text);
        checks.push(text);
    }
    async function next(path) {
        for (var i = 0; i < 700; i++) {
            var index = requests.findIndex(function (request) { return new URL(request.url, location.href).pathname === path; });
            if (index !== -1) return requests.splice(index, 1)[0];
            await wait(10);
        }
        throw new Error('Missing request: ' + path);
    }
    function answer(request, data, code) {
        request.resolve({ok: !code || code < 400, status: code || 200, redirected: false, json: function () { return Promise.resolve(data); }});
    }
    function payload(state, processed, total, extra) {
        return Object.assign({run_id: 1, state: state, processed: processed, total: total, message: state,
            notice: null, requested_state: null, pause_url: '/webadmin/jobs/1/transition', progress_url: '/webadmin/jobs/1/progress'}, extra || {});
    }
    async function run() {
        if (scenario === 'feedback') {
            var toggle = document.querySelector('[data-wb-block-status-toggle]');
            var message = document.querySelector('[data-wb-block-status-message]');
            toggle.click();
            answer(await next('/synthetic-block-status'), {success: true, message: 'Saved <strong>literally</strong>'});
            await wait(50);
            assert(toggle.checked && !toggle.disabled && !message.hidden, 'Success stays beside the enabled control');
            assert(message.textContent === 'Saved <strong>literally</strong>' && !message.querySelector('strong'), 'Feedback escapes message markup');
            await wait(2700);
            assert(!message.hidden && message.classList.contains('wb-alert-success'), 'Success does not expire like a toast');
            toggle.click();
            answer(await next('/synthetic-block-status'), {success: false, message: 'Not saved'}, 422);
            await wait(50);
            assert(toggle.checked && !message.hidden && message.classList.contains('wb-alert-danger'), 'Failure restores the prior status and remains visible');
            assert(document.querySelectorAll('.wb-toast').length === 0, 'No toast is produced');
            return;
        }
        if (scenario === 'passive') {
            await wait(1100);
            assert(posts === 0 && !modal.classList.contains('is-open'), 'Passive opening starts neither requests nor a progress dialog');
            host.querySelector('[data-wb-job-start]').click();
        }
        var first = await next('/webadmin/jobs/1/step');
        assert(modal.classList.contains('is-open'), 'The native modal opens on explicit admission');
        assert(!modal.querySelector('[data-wb-job-keep-open]').hidden, 'Keep-tab-open guidance is visible');
        assert(!modal.querySelector('[data-wb-job-bar]').hasAttribute('aria-valuenow'), 'Unknown totals never fabricate a percentage');
        if (['escape', 'outside', 'pause', 'pause-error', 'finish-during-pause'].includes(scenario)) {
            if (scenario === 'escape') document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));
            else if (scenario === 'outside') modal.click();
            else modal.querySelector('[data-wb-job-pause]').click();
            await wait(50);
            assert(modal.classList.contains('is-open') && posts === 1, 'Close waits for the current POST instead of aborting or overlapping it');
            if (scenario === 'finish-during-pause') {
                answer(first, payload('complete', 10, 10));
                await wait(100);
                assert(posts === 1 && host.querySelector('[data-wb-job-status]').textContent.includes('complete'), 'A completed step wins over a late pause without repeating effects');
                return;
            }
            answer(first, payload('running', 3, 10));
            var pause = await next('/webadmin/jobs/1/transition');
            assert(JSON.parse(pause.options.body).action === 'pause', 'Pause uses the authorized transition endpoint');
            if (scenario === 'pause-error') {
                answer(pause, {}, 419);
                await wait(100);
                assert(modal.classList.contains('is-open') && modal.dataset.runState === 'unknown', 'An unconfirmed pause does not claim success or close the result');
                assert(modal.querySelector('[data-wb-job-outcome]').textContent === modal.dataset.pauseError, 'Unconfirmed pause explains saved-result verification');
            } else {
                answer(pause, payload('running', 3, 10, {requested_state: 'paused'}));
                await wait(50);
                assert(modal.classList.contains('is-open'), 'A deferred stop is not reported as paused');
                var progress = await next('/webadmin/jobs/1/progress');
                assert(progress.options.method === 'GET', 'Waiting for the boundary only reads saved progress');
                // Repeated close attempts must not cancel the pending read timer.
                modal.querySelector('[data-wb-job-pause]').click();
                answer(progress, payload('paused', 3, 10));
                await wait(100);
                assert(!modal.classList.contains('is-open') && modal.dataset.runState === 'paused', 'Only a confirmed pause closes the modal');
                assert(host.querySelector('[data-wb-job-status]').textContent.includes('paused'), 'Paused outcome remains in the page');
            }
            await wait(1700);
            assert(posts === 2 && requests.length === 0, 'No further work POST follows pause or pause failure');
            return;
        }
        if (scenario === 'error' || scenario === 'wrong-run') {
            answer(first, payload('running', 0, 10, scenario === 'wrong-run' ? {run_id: 99} : {}), scenario === 'error' ? 500 : 200);
            await wait(1800);
            assert(modal.dataset.runState === 'unknown' && posts === 1, 'Transport or identity failure stops requests without replay');
            assert(!modal.querySelector('[data-wb-job-results]').hidden, 'An unknown outcome links to saved results');
            return;
        }
        answer(first, payload('running', 2, 10));
        await wait(50);
        assert(modal.querySelector('[data-wb-job-bar]').getAttribute('aria-valuenow') === '20', 'Known totals show real progress');
        var last = await next('/webadmin/jobs/1/step');
        answer(last, payload('complete', 10, 10, scenario === 'partial' ? {notice: 'Saved evidence is incomplete.'} : {}));
        await wait(100);
        assert(modal.classList.contains('is-open') && modal.dataset.runState === 'complete', 'Completion remains visible until explicit close');
        assert(modal.querySelector('[data-wb-job-count]').textContent === '10 / 10', 'Terminal counts remain visible');
        if (scenario === 'partial') assert(modal.querySelector('[data-wb-job-outcome]').classList.contains('wb-alert-warning'), 'Partial domain evidence is not presented as an all-clear');
        var elapsed = modal.querySelector('[data-wb-job-elapsed]').textContent;
        await wait(1100);
        assert(modal.querySelector('[data-wb-job-elapsed]').textContent === elapsed, 'Terminal elapsed time freezes');
        modal.querySelector('[data-wb-job-close]').click();
        await wait(100);
        assert(!modal.classList.contains('is-open') && !host.querySelector('[data-wb-job-status]').hidden, 'Closing keeps the page outcome visible');
    }
    document.addEventListener('DOMContentLoaded', function () {
        run().then(function () { document.getElementById('test-status').textContent = 'PASS ' + scenario + '\n' + checks.join('\n'); })
            .catch(function (error) { document.getElementById('test-status').textContent = 'FAIL ' + scenario + '\n' + error.message; });
    });
}());
