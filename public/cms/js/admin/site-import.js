(function () {
    var form = document.querySelector('[data-wb-import-form]');
    var modal = document.querySelector('[data-wb-import-progress-modal]');
    if (!form || !modal || !window.fetch) { return; }

    var phaseEl = modal.querySelector('[data-wb-import-phase]');
    var counterEl = modal.querySelector('[data-wb-import-counter]');
    var fillEl = modal.querySelector('[data-wb-import-fill]');
    var barEl = modal.querySelector('[data-wb-import-bar]');
    var errorEl = modal.querySelector('[data-wb-import-error]');
    var errorMessageEl = modal.querySelector('[data-wb-import-error-message]');
    var actionsEl = modal.querySelector('[data-wb-import-actions]');
    var retryEl = modal.querySelector('[data-wb-import-retry]');
    var phaseLabels = JSON.parse(window.atob(modal.dataset.wbPhaseLabels));
    var stepUrl = form.dataset.wbStepUrl;
    var returnUrl = modal.dataset.wbReturnUrl;
    var busyLabel = modal.dataset.wbBusyLabel;

    function openModal(trigger) {
        modal.addEventListener('wb:overlay:close-request', function (event) { event.preventDefault(); });
        if (window.WBModal && typeof window.WBModal.open === 'function') {
            window.WBModal.open(modal, trigger);
        } else {
            modal.hidden = false;
            modal.classList.add('is-open');
        }
    }
    function render(state) {
        var percent = Math.max(0, Math.min(100, state.percent || 0));
        fillEl.style.width = percent + '%';
        phaseEl.textContent = phaseLabels[state.phase] || phaseLabels.starting;
        counterEl.textContent = state.total ? (state.done + ' / ' + state.total + ' (' + percent + '%)') : '';
    }
    function fail(message) {
        barEl.classList.add('wb-progress-bar-danger');
        errorMessageEl.textContent = message || '';
        errorEl.hidden = false;
        actionsEl.hidden = false;
    }
    function finish() {
        barEl.classList.add('wb-progress-bar-success');
        fillEl.style.width = '100%';
        window.location.assign(returnUrl);
    }
    function stepOnce() {
        return fetch(stepUrl, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.json().then(function (state) { return { status: response.status, state: state }; });
        });
    }
    function run() {
        errorEl.hidden = true;
        actionsEl.hidden = true;
        barEl.classList.remove('wb-progress-bar-danger');
        stepOnce().then(function (result) {
            var state = result.state || {};
            if (result.status === 409) {
                phaseEl.textContent = busyLabel;
                setTimeout(run, 2000);
                return;
            }
            render(state);
            if (state.failed) { fail(state.failure_message); return; }
            if (state.finished) { finish(); return; }
            run();
        }).catch(function () { setTimeout(run, 3000); });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var button = form.querySelector('[data-wb-import-submit]');
        if (button) { button.disabled = true; }
        openModal(button);
        run();
    });
    if (retryEl) { retryEl.addEventListener('click', run); }
}());
