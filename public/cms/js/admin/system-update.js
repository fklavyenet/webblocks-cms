(function () {
    var form = document.querySelector('[data-wb-update-form]');
    var modal = document.querySelector('[data-webblocks-update-progress-modal]');
    if (!modal || !window.fetch) { return; }
    var status = document.getElementById('wb-update-progress-status');
    var elapsed = document.getElementById('wb-update-elapsed');
    var history = document.getElementById('wb-update-history-link');
    var button = form && form.querySelector('[data-wb-update-submit]');
    var storageKey = 'wb-system-update:' + (modal.dataset.wbReturnUrl || window.location.pathname);
    var operation = null, baseline = Number(modal.dataset.wbBaselineRun || 0);
    var started = Date.now(), active = false, done = false, timer = null, pollTimer = null;
    function say(text) { if (status && text) { status.textContent = text; } }
    function save() {
        try { sessionStorage.setItem(storageKey, JSON.stringify({ operation: operation, baseline: baseline, started: started })); } catch (_) {}
    }
    function open() {
        if (active) { return; }
        active = true;
        if (button) { button.disabled = true; }
        if (window.WBModal && typeof window.WBModal.open === 'function') { window.WBModal.open(modal, button); }
        else { modal.hidden = false; modal.classList.add('is-open'); }
        function displayTime() {
            var seconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
            if (elapsed) { elapsed.textContent = (modal.dataset.wbElapsedLabel || '') + ': ' + Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0'); }
        }
        displayTime();
        timer = setInterval(displayTime, 1000);
    }
    modal.addEventListener('wb:overlay:close-request', function (event) { if (active && !done) { event.preventDefault(); } });
    function attention(text) { say(text || modal.dataset.wbUnconfirmedLabel); if (history) { history.hidden = false; } }
    function finish() {
        if (done) { return; }
        done = true;
        clearInterval(timer);
        clearTimeout(pollTimer);
        try { sessionStorage.removeItem(storageKey); } catch (_) {}
        window.location.assign(modal.dataset.wbReturnUrl || window.location.href);
    }
    function schedule() { clearTimeout(pollTimer); if (!done) { pollTimer = setTimeout(poll, 2000); } }
    function request(url, options) {
        var controller = window.AbortController ? new AbortController() : null;
        if (controller) { options.signal = controller.signal; }
        options.headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        options.credentials = 'same-origin';
        return new Promise(function (resolve, reject) {
            var timeout = setTimeout(function () { if (controller) { controller.abort(); } reject(new Error('timeout')); }, 15000);
            fetch(url, options).then(function (response) { clearTimeout(timeout); resolve(response); }, function (error) { clearTimeout(timeout); reject(error); });
        });
    }
    function poll() {
        if (done) { return; }
        var url = new URL(modal.dataset.wbHealthUrl, window.location.href);
        if (operation) { url.searchParams.set('operation', operation); }
        request(url.toString(), { method: 'GET', cache: 'no-store' })
            .then(function (response) { if (response.status === 401 || response.status === 403 || response.redirected) { var error = new Error('authentication'); error.authentication = true; throw error; }
                if (!response.ok) { throw new Error('unavailable'); } return response.json(); })
            .then(function (payload) {
                var progress = payload && payload.progress;
                if (!progress) { throw new Error('unavailable'); }
                // A successful health response is not an update result. Ignore older runs.
                if (operation && progress.operation_id !== operation) { attention(); schedule(); return; }
                if (!operation && !progress.running && (!active || Number(progress.run_id || 0) <= baseline)) { if (active) { if (Date.now() - started > 60000) { attention(); } schedule(); } return; }
                if (!operation && progress.operation_id) { operation = progress.operation_id; save(); }
                else if (!operation && progress.running && progress.run_id) { baseline = Math.min(baseline, Number(progress.run_id) - 1); save(); }
                if (progress.running || operation) {
                    open();
                    if (typeof progress.elapsed_ms === 'number') { started = Date.now() - progress.elapsed_ms; save(); }
                }
                if (progress.terminal) {
                    if ((progress.state === 'success' || progress.state === 'success_with_warnings') && progress.installed_version !== progress.target_version) {
                        say(modal.dataset.wbWaitingLabel); schedule(); return;
                    }
                    finish(); return;
                }
                if (progress.state === 'interrupted' || progress.state === 'unknown') { attention(); }
                else { say(progress.stage_label || modal.dataset.wbWaitingLabel); }
                schedule();
            }).catch(function (error) { if (active) { if (error.authentication) { attention(); } else { say(modal.dataset.wbReconnectingLabel); if (history && Date.now() - started > 60000) { history.hidden = false; } } } schedule(); });
    }
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (active) { return; }
            started = Date.now(); open(); save(); schedule();
            request(form.getAttribute('action'), { method: 'POST', body: new FormData(form), redirect: 'manual' })
                .then(function (response) {
                    if ([502, 503, 504].indexOf(response.status) !== -1 || response.type === 'opaqueredirect') { say(modal.dataset.wbReconnectingLabel); return null; }
                    return response.json().then(function (payload) {
                        if (response.ok && payload.operation_id) { operation = payload.operation_id; save(); }
                        else { attention(payload.error); }
                    });
                }).catch(function () { say(modal.dataset.wbReconnectingLabel); });
        });
    }
    try {
        var saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        if (saved && typeof saved.started === 'number') {
            operation = saved.operation; baseline = Number(saved.baseline || 0); started = saved.started; open();
        }
    } catch (_) {}
    // Resume an active server operation even after the browser/tab was closed.
    poll();
}());
