(function () {
    'use strict';
    var states = {}, output, realTimeout = window.setTimeout;
    // Exercise the real abort paths without waiting 15/30 seconds per case.
    window.setTimeout = function (callback, delay) {
        return realTimeout(callback, [15000, 30000].includes(delay) ? 80 : delay);
    };
    function assert(value, text) { if (!value) throw new Error(text); }
    function response(data, status, type) {
        return { ok: !status || status === 200, status: status || 200,
            headers: { get: function () { return type || 'application/json'; } },
            json: function () { return Promise.resolve(data); } };
    }
    function until(condition, limit) {
        return new Promise(function (resolve, reject) {
            var attempts = 0;
            function poll() {
                if (condition()) return resolve();
                if (++attempts > (limit || 300)) return reject(new Error('Fixture condition timed out.'));
                realTimeout(poll, 10);
            }
            poll();
        });
    }
    function abortable(options) {
        return new Promise(function (resolve, reject) {
            options.signal.addEventListener('abort', function () { reject(new DOMException('Synthetic timeout', 'AbortError')); }, { once: true });
        });
    }
    window.fetch = function (url, options) {
        var path = new URL(url).pathname, name = path.split('/')[2], state = states[name];
        if (!state) return Promise.reject(new Error('Unexpected fixture request.'));
        if (options.method === 'GET') {
            state.reads += 1;
            if (state.mode === 'initial-offline') return Promise.reject(new TypeError('Synthetic offline'));
            return Promise.resolve(response({ exists: state.mode === 'paused', conflict: false, sequence: 0,
                csrf_token: 'synthetic-current-token', entries: [], saved_at: 'synthetic', expires_at_ms: Date.now() + 86400000 }));
        }
        if (path.startsWith('/synthetic-recovery/')) {
            var payload = JSON.parse(options.body); state.checkpoints.push(payload);
            if (state.mode === 'checkpoint-offline') return Promise.reject(new TypeError('Synthetic offline'));
            if (state.mode === 'checkpoint-timeout') return abortable(options);
            if (state.mode.startsWith('checkpoint-')) return Promise.resolve(response({}, Number(state.mode.split('-')[1])));
            var answer = response({ sequence: payload.sequence, saved_at: 'synthetic', expires_at_ms: Date.now() + 86400000 });
            if (state.mode === 'slow' && state.checkpoints.length === 1) {
                return new Promise(function (resolve) { state.release = function () { resolve(answer); }; });
            }
            return Promise.resolve(answer);
        }
        state.saves.push(options.body);
        state.locked = state.field.disabled;
        var surface = state.form.querySelector('[data-wb-rich-text-surface]');
        if (surface) assert(surface.contentEditable === 'false', 'Rich Text must be locked during Save.');
        assert(options.redirect === 'error', 'Save must not follow an authentication redirect.');
        assert(options.headers['X-WebBlocks-Editor-Save'] === '1', 'Save needs the explicit response contract.');
        if (state.mode === 'save-timeout') return abortable(options);
        if (state.mode === 'save-offline') return Promise.reject(new TypeError('Synthetic response lost'));
        if (state.mode === 'save-html') return Promise.resolve(response(null, 200, 'text/html'));
        if (state.mode === 'save-malformed') return Promise.resolve(response({ saved: true }));
        if (state.mode === 'save-foreign') return Promise.resolve(response({ saved: true, redirect: 'https://example.invalid/' }));
        if (state.mode.startsWith('save-')) return Promise.resolve(response({ errors: { title: ['Synthetic validation <b>error</b>.'] } }, Number(state.mode.split('-')[1])));
        return Promise.resolve(response({ saved: true, redirect: window.location.pathname + '#saved-' + name }));
    };
    document.addEventListener('submit', function (event) {
        var state = states[event.target.dataset.scenario];
        if (state) { state.nativeSubmissions += 1; event.preventDefault(); }
    });
    async function scenario(name, mode) {
        var mount = document.getElementById('save-test-mount');
        mount.replaceChildren(document.getElementById(name.startsWith('block-') ? 'save-test-block-form' : 'save-test-form').content.cloneNode(true));
        var form = mount.querySelector('form'), root = form.querySelector('[data-wb-editor-recovery]');
        form.dataset.scenario = name; form.action = '/synthetic-save/' + name;
        root.dataset.endpoint = '/synthetic-recovery/' + name + '/SESSION';
        root.dataset.identity = 'synthetic-' + name + '-' + crypto.randomUUID();
        if (name === 'empty-gallery') root.dataset.roots += ' gallery_items';
        if (window.WebBlocksCmsAdminRichTextEditor) window.WebBlocksCmsAdminRichTextEditor.init(form);
        var state = states[name] = { field: form.elements.content || form.elements.title, form: form, root: root, mode: mode, reads: 0, checkpoints: [], saves: [], nativeSubmissions: 0 };
        await until(function () { return ['ready', 'failed', 'available'].includes(root.dataset.recoveryState); });
        return state;
    }
    function edit(state, value) {
        var surface = state.form.querySelector('[data-wb-rich-text-surface]');
        if (surface) { surface.innerHTML = '<p>' + value + '</p>'; surface.dispatchEvent(new Event('input', { bubbles: true })); }
        else { state.field.value = value; state.field.dispatchEvent(new Event('input', { bubbles: true })); }
        state.latest = state.field.value;
    }
    async function submit(state) {
        state.form.requestSubmit();
        await until(function () { return state.root.dataset.recoveryState.endsWith('failed'); });
        await until(function () { return state.form.getAttribute('aria-busy') !== 'true'; });
        assert(state.field.value === state.latest, 'Failed save lost the latest title.');
        assert(!state.field.disabled, 'Failed save left the form locked.');
        var surface = state.form.querySelector('[data-wb-rich-text-surface]');
        if (surface) assert(surface.contentEditable === 'true' && !surface.closest('[data-wb-rich-text-editor]').inert, 'Failed save left Rich Text locked.');
        assert(state.nativeSubmissions === 0, 'A failed request must not navigate through a native submission.');
    }
    document.addEventListener('DOMContentLoaded', function () {
        output = document.getElementById('save-test-results');
        document.getElementById('run-save-tests').addEventListener('click', async function (event) {
            event.target.disabled = true; var count = 0;
            try {
                for (var mode of ['checkpoint-offline', 'checkpoint-timeout', 'checkpoint-401', 'checkpoint-403', 'checkpoint-419', 'checkpoint-500', 'initial-offline']) {
                    var state = await scenario(mode, mode === 'initial-offline' ? mode : 'success');
                    state.mode = mode; edit(state, 'Latest ' + mode); await submit(state);
                    assert(state.saves.length === 0, 'Unconfirmed recovery must block the save.');
                    output.textContent = ++count + ' scenarios passed.';
                }
                for (var mode of ['save-offline', 'save-timeout', 'save-401', 'save-403', 'save-419', 'save-422', 'save-500', 'save-html', 'save-malformed', 'save-foreign']) {
                    var state = await scenario(mode, 'success'); state.mode = mode;
                    edit(state, 'Latest ' + mode); await submit(state);
                    assert(state.saves.length === 1 && state.checkpoints.length === 1 && state.locked, 'Save must use a confirmed snapshot and lock editing only during its request.');
                    if (mode === 'save-422') {
                        assert(state.root.querySelector('[data-recovery-errors]').textContent.includes('<b>error</b>'), 'Validation text was lost.');
                        assert(!state.root.querySelector('[data-recovery-errors] b'), 'Validation messages must be inert text.');
                    }
                    if (mode === 'save-419') {
                        assert(!state.root.querySelector('[data-recovery-login]').hidden, 'Session failure needs a sign-in action.');
                        state.mode = 'success'; state.form.requestSubmit();
                        await until(function () { return window.location.hash === '#saved-' + mode; });
                        assert(state.saves[1].get('_token') === 'synthetic-current-token', 'Retry must refresh CSRF after reauthentication.');
                    }
                    output.textContent = ++count + ' scenarios passed.';
                }
                var state = await scenario('block-validation', 'success'); state.mode = 'save-422';
                edit(state, 'Latest block validation'); await submit(state); ++count;
                for (var name of ['slow', 'block-slow']) {
                    var state = await scenario(name, 'slow'); edit(state, 'First edit');
                    state.form.requestSubmit(); state.form.requestSubmit();
                    await until(function () { return !!state.release; });
                    assert(!state.field.disabled, 'Typing must remain available during recovery.');
                    edit(state, 'Typed while waiting'); var latest = state.field.value; state.release();
                    await until(function () { return window.location.hash === '#saved-' + name; });
                    var last = state.checkpoints.at(-1).entries.find(function (entry) { return entry.name === state.field.name; });
                    assert(state.checkpoints.length === 2 && atob(last.value) === latest, 'Latest typing needs another confirmed checkpoint.');
                    assert(state.saves.length === 1 && state.saves[0].get(state.field.name) === latest, 'Duplicate Save or stale payload.');
                    ++count;
                }
                var state = await scenario('empty-gallery', 'success');
                var marker = document.createElement('input'); marker.type = 'hidden'; marker.name = 'gallery_items'; marker.value = ''; state.form.appendChild(marker);
                edit(state, 'Empty gallery'); state.form.requestSubmit();
                await until(function () { return window.location.hash === '#saved-empty-gallery'; });
                assert(state.checkpoints[0].entries.some(function (entry) { return entry.name === 'gallery_items[]' && !entry.present; }), 'An empty gallery needs an explicit recovery value.');
                ++count;
                var state = await scenario('automatic-retry', 'success'); state.mode = 'checkpoint-offline';
                edit(state, 'Background recovery');
                await until(function () { return state.root.dataset.recoveryState === 'failed'; });
                state.mode = 'success';
                await until(function () { return state.root.dataset.recoveryState === 'protected'; }, 1500);
                assert(state.saves.length === 0 && state.checkpoints.length >= 2, 'Background retry must confirm recovery without saving editorial content.');
                ++count;
                var state = await scenario('paused', 'paused');
                state.root.querySelector('[data-recovery-continue]').click(); edit(state, 'Not protected'); state.form.requestSubmit();
                await until(function () { return state.root.dataset.recoveryState === 'paused'; });
                assert(state.checkpoints.length === 0 && state.saves.length === 0, 'Paused recovery must require a deliberate restore or discard before Save.');
                ++count;
                output.textContent = 'PASS: ' + count + ' scenarios. Network, timeout, authentication, validation, server failure, malformed responses, latest typing, duplicate Save, CSRF retry, empty gallery and paused recovery.';
            } catch (error) { output.textContent = 'FAIL after ' + count + ' scenarios: ' + error.message; throw error; }
        });
    });
}());
