(function () {
    var form = document.querySelector('[data-wb-import-form]');
    var modal = document.querySelector('[data-wb-import-progress-modal]');
    if (!form || !modal || !window.fetch || !window.WBModal) return;

    var button = form.querySelector('[data-wb-import-submit]');
    var phase = modal.querySelector('[data-wb-import-phase]');
    var count = modal.querySelector('[data-wb-import-counter]');
    var bar = modal.querySelector('[data-wb-import-bar]');
    var elapsed = modal.querySelector('[data-wb-import-elapsed]');
    var keepOpen = modal.querySelector('[data-wb-import-keep-open]');
    var outcome = modal.querySelector('[data-wb-import-outcome]');
    var pause = modal.querySelector('[data-wb-import-pause]');
    var results = modal.querySelector('[data-wb-import-results]');
    var close = modal.querySelector('[data-wb-import-close]');
    var phaseLabels = JSON.parse(window.atob(modal.dataset.wbPhaseLabels));
    var startedAt, timer, pauseRequested, ended, leftPage;

    function tick() {
        var seconds = Math.floor((Date.now() - startedAt) / 1000);
        elapsed.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
    }

    function render(state) {
        var label = phaseLabels[state.phase] || phaseLabels.starting;
        if (phase.textContent !== label) phase.textContent = label;
        if (state.total > 0) {
            bar.value = state.percent;
            count.textContent = state.done + ' / ' + state.total + ' (' + state.percent + '%)';
        } else {
            bar.removeAttribute('value');
            count.textContent = modal.dataset.preparing;
        }
    }

    function finish(type, message) {
        ended = true;
        clearInterval(timer);
        tick();
        if (count.textContent === modal.dataset.preparing) count.textContent = type === 'success' ? '100%' : '—';
        phase.closest('tr').hidden = true;
        bar.hidden = true;
        keepOpen.hidden = true;
        pause.hidden = true;
        outcome.classList.add('wb-alert-' + type);
        outcome.textContent = message;
        outcome.hidden = false;
        results.hidden = false;
        close.hidden = false;
        results.focus();
    }

    function requestPause() {
        if (ended || pauseRequested) return;
        pauseRequested = true;
        pause.disabled = true;
        phase.textContent = modal.dataset.pausing;
    }

    async function advance(body) {
        var controller = new AbortController();
        var timeout = setTimeout(function () { controller.abort(); }, 90000);
        try {
            var response = await fetch(form.dataset.wbStepUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                redirect: 'error',
                signal: controller.signal,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            var state = await response.json();
            if (leftPage || ended) return;
            if (response.status === 409 && state?.busy === true) {
                finish('warning', modal.dataset.busy);
                return;
            }
            if (response.status === 422) {
                finish('danger', typeof state?.message === 'string' ? state.message : modal.dataset.failed);
                return;
            }
            if (state?.failed === true) {
                finish('danger', state.failure_message || modal.dataset.failed);
                return;
            }
            if (!response.ok || typeof state?.finished !== 'boolean' || !Number.isInteger(state.total)
                || !Number.isInteger(state.done) || !Number.isInteger(state.percent)) throw new Error();

            render(state);
            if (state.finished) finish('success', modal.dataset.complete);
            else if (pauseRequested) finish('warning', modal.dataset.paused);
            else if (state.status === 'partial') advance(body);
            else throw new Error();
        } catch (_) {
            if (!leftPage && !ended) finish('warning', modal.dataset.unknown);
        } finally {
            clearTimeout(timeout);
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (startedAt) return;
        var body = new FormData(form);
        button.disabled = true;
        startedAt = Date.now();
        timer = setInterval(tick, 1000);
        window.WBModal.open(modal, button);
        advance(body);
    });
    pause.addEventListener('click', requestPause);
    modal.addEventListener('wb:overlay:close-request', function (event) {
        if (ended) return;
        event.preventDefault();
        requestPause();
    });
    modal.addEventListener('wb:modal:close', function () {
        if (ended) {
            // Show the saved outcome on the same details screen after dismissal.
            window.location.assign(results.href);
        }
    });
    window.addEventListener('pagehide', function () {
        leftPage = true;
        clearInterval(timer);
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted && startedAt && !ended) finish('warning', modal.dataset.unknown);
    });
}());
