(function () {
    var form = document.querySelector('[data-wb-update-form]');
    var modal = document.querySelector('[data-webblocks-update-progress-modal]');
    if (!form || !modal) { return; }
    function openModal(trigger) {
        modal.addEventListener('wb:overlay:close-request', function (event) { event.preventDefault(); });
        if (window.WBModal && typeof window.WBModal.open === 'function') { window.WBModal.open(modal, trigger); }
        else { modal.hidden = false; modal.classList.add('is-open'); }
    }
    if (!window.fetch) { form.addEventListener('submit', function () { openModal(null); }); return; }
    var inFlight = false;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (inFlight) { return; }
        inFlight = true;
        var button = form.querySelector('[data-wb-update-submit]');
        if (button) { button.disabled = true; }
        openModal(button);
        var started = Date.now();
        var settled = false;
        var done = false;
        var status = document.getElementById('wb-update-progress-status');
        var elapsed = document.getElementById('wb-update-elapsed');
        function displayTime() {
            if (!elapsed) { return; }
            var seconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
            elapsed.textContent = (modal.dataset.wbElapsedLabel || '') + ': ' + Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
        }
        displayTime();
        var timer = setInterval(displayTime, 1000);
        function goHome() {
            if (done) { return; }
            done = true;
            clearInterval(timer);
            window.location.assign(modal.dataset.wbReturnUrl || window.location.href);
        }
        function poll() {
            if (done) { return; }
            fetch(modal.dataset.wbHealthUrl || window.location.href, { method: 'GET', cache: 'no-store', credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) { return null; }
                    return response.json();
                }).then(function (payload) {
                    var progress = payload && payload.progress;
                    if (progress && progress.running && progress.stage_label && status && status.textContent !== progress.stage_label) { status.textContent = progress.stage_label; }
                    if (settled && payload && (!progress || !progress.running)) { goHome(); return; }
                    setTimeout(poll, 1500);
                }).catch(function () { setTimeout(poll, 1500); });
        }
        setTimeout(poll, 1000);
        fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), redirect: 'manual', credentials: 'same-origin' })
            .then(function () { settled = true; })
            .catch(function () { settled = true; })
            .then(function () { if (modal.dataset.wbWaitingLabel && status) { status.textContent = modal.dataset.wbWaitingLabel; } });
    });
}());
