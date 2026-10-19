const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

// Exercise the shared ProgressAssets runtime using the CMS server admission contract.
const source = fs.readFileSync(process.argv[2] || path.join(__dirname,
    '../../src/Support/Updates/Client/Resources/js/update-progress.js'), 'utf8');
const operation = '11111111-1111-4111-8111-111111111111';
const otherOperation = '22222222-2222-4222-8222-222222222222';
const storageKey = 'publisher-client.operation:/health';

function fixture({saved = null, active = '', withForm = true} = {}) {
    const timers = new Map(), intervals = new Map(), requests = [], navigation = [];
    const storage = new Map(saved ? [[storageKey, saved]] : []);
    let now = 1000000, sequence = 0;
    function element() {
        return {
            hidden: false, textContent: '', children: [], events: {},
            addEventListener(name, handler) { this.events[name] = handler; },
            append(...children) { this.children.push(...children); },
            appendChild(child) { this.children.push(child); },
            replaceChildren() { this.children = []; },
            click() { this.events.click?.({preventDefault() {}}); },
        };
    }
    const parts = Object.fromEntries(['message', 'loading', 'footer', 'elapsed', 'steps', 'recheck', 'return']
        .map(name => [`[data-update-${name}]`, element()]));
    const returnLink = parts['[data-update-return]'];
    returnLink.href = '/updates';
    const attributes = {
        'data-pc-health-url': '/health', 'data-pc-admission': 'server', 'data-active-operation': active,
        'data-progress-running': 'Running', 'data-progress-unknown': 'Unknown',
        'data-progress-unreachable': 'Reconnecting', 'data-progress-interrupted': 'Unconfirmed',
        'data-progress-auth': 'Authentication required', 'data-progress-complete': 'Complete',
        'data-progress-failed': 'Failed', 'data-progress-step-running': 'In progress',
        'data-progress-step-interrupted': 'Interrupted',
    };
    const opener = {...element(), getAttribute: name => attributes[name] || null};
    const modal = {querySelector: selector => {
        assert.ok(parts[selector], `known modal selector: ${selector}`);
        return parts[selector];
    }};
    const button = {disabled: false};
    const form = {...element(), action: '/apply', querySelectorAll: () => [button]};
    let ready;
    class FakeFormData extends Map {
        constructor(input) { super(); assert.equal(input, form); }
    }
    class FakeAbortController {
        constructor() { this.signal = {aborted: false}; }
        abort() {
            this.signal.aborted = true;
            requests.find(request => request.options.signal === this.signal)?.reject(new Error('Aborted'));
        }
    }
    const context = {
        document: {
            addEventListener(name, handler) { assert.equal(name, 'DOMContentLoaded'); ready = handler; },
            querySelector(selector) {
                if (selector === '[data-pc-update-form]') { return withForm ? form : null; }
                assert.equal(selector, '[data-pc-update-modal], [data-herne-update-modal]');
                return modal;
            },
            getElementById(id) { assert.equal(id, 'pc-update-progress-open'); return opener; },
            createElement: () => element(),
        },
        window: {
            fetch: true, AbortController: FakeAbortController,
            location: {href: 'https://example.test/updates', pathname: '/updates', assign: url => navigation.push(url)},
            crypto: {getRandomValues: bytes => bytes.fill(3)},
        },
        URL, Date: {now: () => now}, FormData: FakeFormData, AbortController: FakeAbortController,
        sessionStorage: {
            getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value),
            removeItem: key => storage.delete(key),
        },
        setTimeout: (fn, ms) => { const id = ++sequence; timers.set(id, {fn, ms}); return id; },
        clearTimeout: id => timers.delete(id),
        setInterval: fn => { const id = ++sequence; intervals.set(id, fn); return id; },
        clearInterval: id => intervals.delete(id),
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({url, options, resolve, reject})),
    };
    vm.runInNewContext(source, context);
    assert.equal(typeof ready, 'function');
    ready();
    const flush = async () => { for (let i = 0; i < 30; i++) { await Promise.resolve(); } };
    const runTimer = ms => {
        const entry = [...timers].find(([, timer]) => timer.ms === ms);
        assert.ok(entry, `timer scheduled: ${ms} ms`);
        timers.delete(entry[0]);
        entry[1].fn();
    };
    return {
        requests, navigation, storage, timers, intervals, button, flush,
        message: parts['[data-update-message]'], elapsed: parts['[data-update-elapsed]'],
        steps: parts['[data-update-steps]'], recheck: parts['[data-update-recheck]'], returnLink,
        submit: () => form.events.submit({preventDefault() {}}),
        advance: ms => { now += ms; intervals.forEach(fn => fn()); },
        poll: () => runTimer(1500), timeout: () => runTimer(10000),
        async reply(index, data, status = 200) {
            assert.ok(requests[index], `request exists: ${index}`);
            requests[index].resolve({ok: status >= 200 && status < 300, status, json: async () => data});
            await flush();
        },
        async progress(index, overrides = {}, status = 200) {
            await this.reply(index, {progress: {
                operation_id: operation, state: 'running', terminal: false, elapsed_ms: 0, steps: [], ...overrides,
            }}, status);
        },
    };
}

(async () => {
    const f = fixture();
    assert.equal(f.requests.length, 0, 'do not poll or adopt old history before admission');
    f.submit(); f.submit();
    assert.equal(f.requests.length, 1, 'duplicate submit must not create another POST');
    assert.equal(f.requests[0].options.method, 'POST');
    assert.equal(f.requests[0].url, '/apply');
    assert.match(f.requests[0].options.body.get('operation_id'), /^[a-f0-9-]{36}$/i);
    assert.equal(f.button.disabled, true);
    await f.reply(0, {operation_id: operation}, 202);
    assert.equal(f.requests.length, 2, 'poll after server admission');
    assert.equal(new URL(f.requests[1].url).searchParams.get('operation'), operation,
        'track the server operation, not the proposed client UUID');
    assert.equal(f.storage.get(storageKey), operation);
    assert.equal(f.requests[1].options.credentials, 'same-origin');
    assert.equal(f.requests[1].options.cache, 'no-store');
    await f.progress(1, {}, 503);
    assert.equal(f.message.textContent, 'Reconnecting');
    assert.equal(f.navigation.length, 0);
    f.poll();
    await f.progress(2, {operation_id: otherOperation, state: 'success', terminal: true});
    assert.equal(f.message.textContent, 'Unknown');
    assert.equal(f.navigation.length, 0, 'another operation cannot complete this one');
    f.poll();
    await f.progress(3, {message: 'Migrating', elapsed_ms: 1200000, steps: [
        {label: 'Backup', state: 'complete', elapsed_ms: 2000},
        {label: 'Migrations', state: 'running', elapsed_ms: 61000},
    ]});
    assert.equal(f.message.textContent, 'Migrating');
    f.advance(1000);
    assert.equal(f.elapsed.textContent, '20:01');
    assert.equal(f.steps.children.length, 2);
    assert.equal(f.steps.children[0].children[0].textContent, 'Backup');
    assert.equal(f.steps.children[0].children[1].textContent, 'Complete · 0:02');
    assert.equal(f.steps.children[1].children[1].textContent, 'In progress · 1:01');
    f.poll();
    await f.progress(4, {state: 'interrupted'});
    assert.equal(f.message.textContent, 'Unconfirmed');
    assert.equal(f.recheck.hidden, false);
    assert.equal(f.returnLink.hidden, false);
    assert.equal(f.intervals.size, 0);
    assert.equal(f.timers.size, 0, 'interruption waits for manual recheck');
    assert.equal(f.storage.get(storageKey), operation, 'retain interrupted operation for resume');
    f.recheck.click();
    assert.equal(new URL(f.requests[5].url).searchParams.get('operation'), operation);
    await f.progress(5, {state: 'success', terminal: true, target_version: '2', installed_version: '1'});
    assert.equal(f.navigation.length, 0, 'verify installed target before completing');
    assert.equal(f.storage.get(storageKey), operation);
    f.poll();
    await f.progress(6, {state: 'success', terminal: true, target_version: '2', installed_version: '2'});
    assert.equal(f.storage.size, 0);
    assert.equal(f.intervals.size, 0);
    assert.equal(f.navigation.length, 0, 'allow terminal status to render before returning');
    f.poll();
    assert.deepEqual(f.navigation, ['/updates']);
    assert.equal(f.timers.size, 0);
    assert.equal(f.requests.filter(request => request.options.method === 'POST').length, 1);

    const resumed = fixture({saved: operation, withForm: false});
    assert.equal(new URL(resumed.requests[0].url).searchParams.get('operation'), operation);
    await resumed.progress(0, {state: 'restored', terminal: true, message: 'Restored'});
    assert.equal(resumed.message.textContent, 'Restored');
    assert.equal(resumed.returnLink.hidden, false);
    assert.equal(resumed.storage.size, 0);
    assert.equal(resumed.timers.size, 0);
    assert.equal(resumed.intervals.size, 0);
    assert.equal(resumed.navigation.length, 0, 'restored outcome stays visible for review');

    const active = fixture({saved: otherOperation, active: operation});
    assert.equal(new URL(active.requests[0].url).searchParams.get('operation'), operation,
        'server active operation takes precedence over session storage');
    active.submit();
    assert.equal(active.requests.length, 1, 'resuming must never submit another update');
    active.timeout();
    await active.flush();
    assert.equal(active.requests[0].options.signal.aborted, true);
    assert.equal(active.message.textContent, 'Reconnecting');
    active.poll();
    await active.progress(1);
    active.advance(15 * 60 * 1000);
    active.poll();
    await active.progress(2, {}, 502);
    assert.equal(active.recheck.hidden, false, 'bounded monitoring offers manual recheck');
    assert.equal(active.timers.size, 0);
    active.recheck.click();
    assert.equal(new URL(active.requests[3].url).searchParams.get('operation'), operation);

    for (const invalid of [{operation_id: otherOperation}, {terminal: 'true'}, {elapsed_ms: null},
        {steps: null}, {state: 'idle'}]) {
        const invalidProgress = fixture({saved: operation});
        await invalidProgress.progress(0, {state: 'success', terminal: true, ...invalid});
        assert.equal(invalidProgress.message.textContent, 'Unknown');
        assert.equal(invalidProgress.navigation.length, 0);
        assert.equal(invalidProgress.storage.get(storageKey), operation);
    }

    for (const status of [401, 403, 419]) {
        const auth = fixture({saved: operation});
        await auth.reply(0, {}, status);
        assert.equal(auth.message.textContent, 'Authentication required');
        assert.equal(auth.recheck.hidden, true);
        assert.equal(auth.timers.size, 0);
        assert.equal(auth.intervals.size, 0);
    }

    for (const outcome of ['failed', 'cancelled', 'success_with_warnings']) {
        const terminal = fixture({saved: operation});
        await terminal.progress(0, {state: outcome, terminal: true, message: outcome,
            target_version: '2', installed_version: '2'});
        assert.equal(terminal.message.textContent, outcome);
        assert.equal(terminal.storage.size, 0);
        assert.equal(terminal.intervals.size, 0);
        if (outcome === 'success_with_warnings') {
            terminal.poll();
            assert.deepEqual(terminal.navigation, ['/updates']);
        } else {
            assert.equal(terminal.timers.size, 0);
            assert.equal(terminal.navigation.length, 0);
        }
    }

    const rejected = fixture();
    rejected.submit();
    await rejected.reply(0, {message: 'Admission rejected'}, 422);
    rejected.submit();
    assert.equal(rejected.requests.length, 1, 'rejected admission must never be reposted');
    assert.equal(rejected.message.textContent, 'Admission rejected');
    assert.equal(rejected.timers.size, 0);

    const lostAdmission = fixture();
    lostAdmission.submit();
    lostAdmission.requests[0].reject(new Error('Connection lost'));
    await lostAdmission.flush();
    lostAdmission.submit();
    assert.equal(lostAdmission.requests.length, 1, 'lost admission response must never be reposted');
    assert.equal(lostAdmission.message.textContent, 'Unknown');
    assert.equal(lostAdmission.recheck.hidden, false);

    console.log('PASS: shared progress asset, server admission, duplicate submit, reconnect, exact operation/target, step durations, interruption/recheck, resume, timeout, authentication and terminal outcomes.');
})().catch(error => { console.error(error); process.exitCode = 1; });
