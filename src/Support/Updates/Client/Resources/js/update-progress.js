/* WebBlocks owns the modal; this adapter tracks one authorized product operation. */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-pc-update-form]');
    var opener = document.getElementById('pc-update-progress-open');
    var modal = document.querySelector('[data-pc-update-modal], [data-herne-update-modal]');
    if (!opener || !modal || !opener.getAttribute('data-pc-health-url') || !window.fetch) { return; }

    var message = modal.querySelector('[data-update-message]');
    var loading = modal.querySelector('[data-update-loading]');
    var footer = modal.querySelector('[data-update-footer]');
    var elapsed = modal.querySelector('[data-update-elapsed]');
    var steps = modal.querySelector('[data-update-steps]');
    var stage = modal.querySelector('[data-update-stage]');
    var recheck = modal.querySelector('[data-update-recheck]');
    var returnLink = modal.querySelector('[data-update-return]');
    var health = opener.getAttribute('data-pc-health-url');
    var operation = null;
    var stopped = true;
    var submitted = false;
    var timer = null;
    var pollTimer = null;
    var elapsedBase = 0;
    var sampleAt = clock();
    var localStart = false;
    var elapsedOffset = null;
    var deadline = 0;
    var storageKey = 'publisher-client.operation:' + (health || window.location.pathname);
    var serverAdmission = opener.getAttribute('data-pc-admission') === 'server';

    function label(name) { return opener.getAttribute('data-progress-' + name) || ''; }
    function remember(id) { try { if (id) { sessionStorage.setItem(storageKey, id); } else { sessionStorage.removeItem(storageKey); } } catch (ignore) {} }
    function remembered() { try { return sessionStorage.getItem(storageKey); } catch (ignore) { return null; } }
    function duration(ms) {
        var seconds = Math.floor(Math.max(0, Number(ms) || 0) / 1000);
        return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
    }
    function clock() { return window.performance && window.performance.now ? window.performance.now() : Date.now(); }
    function elapsedNow() { return elapsedBase + (timer === null ? 0 : Math.max(0, clock() - sampleAt)); }
    function tick() { elapsed.textContent = duration(elapsedNow()); }
    function startClock() {
        if (timer !== null) { return; }
        sampleAt = clock();
        timer = setInterval(tick, 1000);
        tick();
    }
    function showModal() {
        if (!modal.classList.contains('is-open')) { opener.click(); }
    }
    function syncElapsed(ms) {
        var current = elapsedNow();
        if (elapsedOffset === null) { elapsedOffset = localStart ? Math.max(0, current - ms) : 0; }
        elapsedBase = Math.max(current, ms + elapsedOffset);
        sampleAt = clock();
        tick();
    }
    function halt(text, retry) {
        stopped = true;
        elapsedBase = elapsedNow();
        clearInterval(timer);
        timer = null;
        tick();
        clearTimeout(pollTimer);
        if (text) { message.textContent = text; message.hidden = false; }
        recheck.hidden = !retry;
        returnLink.hidden = false;
        loading.hidden = true;
        footer.hidden = false;
    }
    function render(progress) {
        syncElapsed(Math.max(0, progress.elapsed_ms));
        loading.hidden = false;
        message.textContent = progress.message || label('running');
        message.hidden = true;
        if (stage) {
            var latest = progress.steps[progress.steps.length - 1];
            stage.textContent = progress.stage_label || (latest && latest.label) || progress.message || label('running');
        }
        // Retain compatibility with product-owned views using the older list contract.
        if (steps) { steps.replaceChildren(); }
        (steps ? progress.steps : []).forEach(function (step) {
            var row = document.createElement('li');
            row.className = 'wb-cluster wb-cluster-between';
            var title = document.createElement('span');
            title.textContent = step.label;
            var state = document.createElement('span');
            state.className = 'wb-text-sm wb-text-muted';
            var stateLabel = step.state === 'complete' ? label('complete')
                : (step.state === 'failed' ? label('failed')
                    : (step.state === 'interrupted' ? label('step-interrupted') : label('step-running')));
            state.textContent = stateLabel + ' · ' + duration(step.elapsed_ms);
            row.append(title, state);
            steps.appendChild(row);
        });
        if (progress.terminal === true) {
            if ((progress.state === 'success' || progress.state === 'success_with_warnings') && progress.installed_version && progress.target_version && progress.installed_version !== progress.target_version) {
                message.textContent = label('unknown'); message.hidden = false; loading.hidden = true; return;
            }
            halt(progress.message || label(progress.state), false);
            remember(null);
            // Completion comes only from this operation and any product health gate.
            if (progress.state === 'success' || progress.state === 'success_with_warnings') {
                setTimeout(function () { window.location.assign(returnLink.href); }, 1500);
            }
        } else if (progress.state === 'interrupted') {
            halt(label('interrupted'), true);
        }
    }
    function retryPoll() {
        if (stopped) { return; }
        if (Date.now() >= deadline) { halt(label('unreachable'), true); return; }
        pollTimer = setTimeout(poll, 1500);
    }
    function poll() {
        if (stopped || !operation) { return; }
        var url = new URL(health, window.location.href);
        url.searchParams.set('operation', operation);
        var controller = window.AbortController ? new AbortController() : null;
        var timeout = controller ? setTimeout(function () { controller.abort(); }, 10000) : null;
        fetch(url.href, {
            credentials: 'same-origin', cache: 'no-store', redirect: 'manual',
            headers: { 'Accept': 'application/json' }, signal: controller ? controller.signal : undefined
        }).then(function (response) {
            if (response.type === 'opaqueredirect' || [401, 403, 419].indexOf(response.status) !== -1) {
                halt(label('auth'), false);
                return null;
            }
            if (!response.ok || response.redirected) { throw new Error('Unavailable'); }
            return response.json();
        }).then(function (data) {
            if (stopped || !data) { return; }
            var progress = data.progress;
            if (!progress || progress.operation_id !== operation
                || typeof progress.terminal !== 'boolean' || !Number.isFinite(progress.elapsed_ms)
                || !Array.isArray(progress.steps)
                || ['queued', 'running', 'success', 'success_with_warnings', 'failed', 'restored', 'cancelled', 'interrupted'].indexOf(progress.state) === -1) {
                // Admission may still be starting. Never adopt another run or infer success.
                message.textContent = label('unknown');
                message.hidden = false;
                loading.hidden = true;
            } else { render(progress); }
        }).catch(function () {
            if (!stopped) { message.textContent = label('unreachable'); message.hidden = false; loading.hidden = true; }
        }).finally(function () { clearTimeout(timeout); retryPoll(); });
    }
    function monitor(id) {
        if (form) { form.querySelectorAll('button[type="submit"]').forEach(function (button) { button.disabled = true; }); }
        operation = id;
        remember(id);
        stopped = false;
        deadline = Date.now() + 15 * 60 * 1000;
        recheck.hidden = true;
        returnLink.hidden = true;
        loading.hidden = false;
        footer.hidden = true;
        clearTimeout(pollTimer);
        startClock();
        showModal();
        poll();
    }
    function uuid() {
        if (!window.crypto || !window.crypto.getRandomValues) { return null; }
        var bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        var hex = Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }
    recheck.addEventListener('click', function (event) { event.preventDefault(); if (operation) { monitor(operation); } else { window.location.assign(returnLink.href); } });
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (submitted) { return; }
            var id = uuid();
            if (!id) { showModal(); halt(label('unknown'), false); return; }
            submitted = true;
            form.querySelectorAll('button[type="submit"]').forEach(function (button) { button.disabled = true; });
            var body = new FormData(form);
            body.set('operation_id', id);
            elapsedBase = 0;
            localStart = true;
            elapsedOffset = null;
            startClock();
            // Poll concurrently; the update POST can take minutes or lose its connection.
            fetch(form.action, {
                method: 'POST', body: body, redirect: 'manual', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                if (response.type === 'opaqueredirect' || [401, 403, 419].indexOf(response.status) !== -1) {
                    remember(null);
                    halt(label('auth'), false);
                    return null;
                } else if (response.status >= 400 && response.status < 500) {
                    remember(null);
                    return response.json().then(
                        function (data) { halt(data.message || label('failed'), false); },
                        function () { halt(label('failed'), false); }
                    );
                }
                if (serverAdmission && response.ok) {
                    return response.json().then(function (data) {
                        if (typeof data.operation_id === 'string' && /^[a-f0-9-]{36}$/i.test(data.operation_id)) { monitor(data.operation_id); }
                        else { halt(label('unknown'), true); }
                    });
                }
                if (serverAdmission) { halt(label('unknown'), true); }
                // HTTP health alone never confirms an update.
                return null;
            }).catch(function () { if (serverAdmission) { halt(label('unknown'), true); } /* Never repost. */ });
            if (serverAdmission) {
                showModal(); message.textContent = label('running');
            }
            else { monitor(id); }
        });
    }
    var active = opener.getAttribute('data-active-operation') || remembered();
    if (active && /^[a-f0-9-]{36}$/i.test(active)) { submitted = true; monitor(active); }
});
