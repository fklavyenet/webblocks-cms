/* WebBlocks UI owns the dialog. This adapter advances one authorized manual run. */
(function () {
    'use strict';
    var modal = document.querySelector('[data-wb-job-modal]');
    if (!modal || !window.WBModal || !window.fetch) return;
    var bar = modal.querySelector('[data-wb-job-bar]');
    var fill = modal.querySelector('[data-wb-job-fill]');
    var state = modal.querySelector('[data-wb-job-modal-state]');
    var count = modal.querySelector('[data-wb-job-count]');
    var elapsed = modal.querySelector('[data-wb-job-elapsed]');
    var outcome = modal.querySelector('[data-wb-job-outcome]');
    var pauseButton = modal.querySelector('[data-wb-job-pause]');
    var results = modal.querySelector('[data-wb-job-results]');
    var closeButton = modal.querySelector('[data-wb-job-close]');
    var keepOpen = modal.querySelector('[data-wb-job-keep-open]');
    var active = null;
    var liveStates = ['queued', 'running'];
    var states = liveStates.concat(['paused', 'complete', 'partial', 'failed', 'stale', 'cancelled']);

    function localUrl(value) {
        if (!value) return null;
        try {
            var url = new URL(value, window.location.href);
            return url.origin === window.location.origin ? url.href : null;
        } catch (_) { return null; }
    }
    function valid(data) {
        return data && states.includes(data.state) && Number.isSafeInteger(data.run_id) && data.run_id > 0
            && Number.isSafeInteger(data.processed) && data.processed >= 0
            && (data.total === null || (Number.isSafeInteger(data.total) && data.total >= 0))
            && typeof data.message === 'string';
    }
    async function read(url, method, body, seconds) {
        var controller = window.AbortController ? new AbortController() : null;
        var timeout = controller ? setTimeout(function () { controller.abort(); }, seconds * 1000) : null;
        try {
            var response = await fetch(url, {
                method: method, credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                headers: {Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
                body: body ? JSON.stringify(body) : undefined, signal: controller ? controller.signal : undefined
            });
            if (!response.ok || response.redirected) throw new Error('request');
            var data = await response.json();
            if (!valid(data)) throw new Error('outcome');
            return data;
        } finally { clearTimeout(timeout); }
    }
    function tick(current) {
        var seconds = Math.floor(Math.max(0, Date.now() - current.started) / 1000);
        elapsed.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
    }
    function render(current, data) {
        if (current.runId && current.runId !== data.run_id) throw new Error('run');
        if (current.host.dataset.subjectId && Number(current.host.dataset.subjectId) !== data.subject_id) throw new Error('subject');
        current.runId = data.run_id;
        current.pauseUrl = localUrl(data.pause_url);
        current.progressUrl = localUrl(data.progress_url);
        var text = data.message + (data.phase_label ? ' · ' + data.phase_label : '');
        state.textContent = current.pausing && liveStates.includes(data.state) ? modal.dataset.pausing : text;
        count.textContent = data.total === null ? modal.dataset.preparing : data.processed + ' / ' + data.total;
        var known = data.total !== null && data.total > 0;
        bar.classList.toggle('wb-progress-bar-indeterminate', !known);
        if (known) {
            var percent = Math.max(0, Math.min(100, data.processed / data.total * 100));
            bar.setAttribute('aria-valuemin', '0');
            bar.setAttribute('aria-valuemax', '100');
            bar.setAttribute('aria-valuenow', String(Math.round(percent)));
            fill.style.width = percent + '%';
        } else {
            bar.removeAttribute('aria-valuenow');
            fill.style.removeProperty('width');
        }
        var summary = current.host.closest('tr') || document.querySelector('[data-wb-job-summary]');
        if (summary) {
            var stateCell = summary.querySelector('[data-wb-job-state]');
            var progressCell = summary.querySelector('[data-wb-job-progress]');
            if (stateCell) stateCell.textContent = text;
            if (progressCell) progressCell.textContent = count.textContent;
        }
    }
    function finish(current, data, error) {
        current.ended = true;
        clearInterval(current.clock);
        clearTimeout(current.next);
        tick(current);
        var type = error || data?.state === 'failed' ? 'danger'
            : (!data?.notice && data?.state === 'complete' ? 'success' : 'warning');
        var message = error || data.notice || data.message;
        modal.dataset.runState = error ? 'unknown' : data.state;
        if (error) state.textContent = error;
        bar.hidden = true;
        keepOpen.hidden = true;
        pauseButton.hidden = true;
        results.hidden = false;
        closeButton.hidden = false;
        outcome.className = 'wb-alert wb-alert-' + type;
        outcome.textContent = message;
        outcome.hidden = false;
        current.status.className = 'wb-alert wb-alert-' + type;
        current.status.textContent = message + ' ';
        var link = document.createElement('a');
        link.className = 'wb-link';
        link.href = results.href;
        link.textContent = modal.dataset.resultsLabel;
        current.status.append(link);
        current.button.hidden = true;
        var controls = current.host.closest('[data-wb-job-controls]');
        if (controls) controls.querySelectorAll('[data-wb-job-transition]').forEach(function (form) { form.hidden = true; });
        if (current.exit && !error) window.WBModal.close(modal);
    }
    async function pauseAtBoundary(current, alreadyRequested) {
        if (current.ended || current.pauseBusy) return;
        current.pauseBusy = true;
        current.pausing = true;
        pauseButton.disabled = true;
        state.textContent = modal.dataset.pausing;
        clearTimeout(current.next);
        try {
            if (!current.pauseUrl || !current.progressUrl) throw new Error('pause');
            var data = alreadyRequested ? await read(current.progressUrl, 'GET', null, 10)
                : await read(current.pauseUrl, 'POST', {action: 'pause'}, 10);
            render(current, data);
            var deadline = Date.now() + 60000;
            while (liveStates.includes(data.state)) {
                if (Date.now() >= deadline) throw new Error('pause');
                await new Promise(function (resolve) { current.next = setTimeout(resolve, 1000); });
                if (current.ended) return;
                data = await read(current.progressUrl, 'GET', null, 10);
                render(current, data);
            }
            finish(current, data);
        } catch (_) { if (!current.ended) finish(current, null, modal.dataset.pauseError); }
    }
    function requestPause() {
        var current = active;
        if (!current || current.ended) return;
        current.exit = true;
        if (current.pauseBusy) return;
        current.pausing = true;
        pauseButton.disabled = true;
        state.textContent = modal.dataset.pausing;
        clearTimeout(current.next);
        if (!current.inFlight) pauseAtBoundary(current, false);
    }
    async function advance(current) {
        if (current.ended || current.pausing) return;
        current.inFlight = true;
        try {
            var data = await read(current.url, 'POST', null, 60);
            current.inFlight = false;
            if (current.ended) return;
            render(current, data);
            if (!liveStates.includes(data.state)) finish(current, data);
            else if (current.pausing || data.requested_state) pauseAtBoundary(current, !!data.requested_state);
            else current.next = setTimeout(function () { advance(current); }, 1500);
        } catch (_) {
            current.inFlight = false;
            if (!current.ended) finish(current, null, current.pausing ? modal.dataset.pauseError : modal.dataset.error);
        }
    }
    pauseButton.addEventListener('click', requestPause);
    modal.addEventListener('wb:overlay:close-request', function (event) {
        if (!active || active.ended) return;
        event.preventDefault();
        requestPause();
    });
    modal.addEventListener('wb:modal:close', function () {
        if (active && active.ended) active.status.querySelector('a')?.focus();
    });
    document.querySelectorAll('[data-wb-job-runner]').forEach(function (host) {
        var button = host.querySelector('[data-wb-job-start]');
        var status = host.querySelector('[data-wb-job-status]');
        if (!button || !status) return;
        button.addEventListener('click', function () {
            if (active && !active.ended) return;
            var url = localUrl(button.dataset.url);
            if (!url) return;
            active = {host: host, button: button, status: status, url: url, started: Date.now(),
                runId: Number(host.dataset.runId) || null, pauseUrl: localUrl(host.dataset.pauseUrl), progressUrl: localUrl(host.dataset.progressUrl)};
            var current = active;
            button.disabled = true;
            status.textContent = '';
            modal.querySelector('[data-wb-job-name]').textContent = host.dataset.label || button.textContent;
            state.textContent = modal.dataset.preparing;
            count.textContent = modal.dataset.preparing;
            bar.hidden = false;
            bar.classList.add('wb-progress-bar-indeterminate');
            bar.removeAttribute('aria-valuenow');
            fill.style.removeProperty('width');
            keepOpen.hidden = false;
            outcome.hidden = true;
            results.href = localUrl(host.dataset.resultsUrl) || window.location.href;
            results.hidden = true;
            closeButton.hidden = true;
            pauseButton.hidden = false;
            pauseButton.disabled = false;
            modal.dataset.runState = 'running';
            tick(current);
            current.clock = setInterval(function () { tick(current); }, 1000);
            window.WBModal.open(modal, button);
            advance(current);
        });
        if (host.dataset.autostart === 'true' && !active) button.click();
    });
    window.addEventListener('pagehide', function () {
        if (!active) return;
        active.ended = true;
        clearInterval(active.clock);
        clearTimeout(active.next);
    });
}());
