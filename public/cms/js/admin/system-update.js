(function () {
    var form = document.querySelector('[data-wb-update-form]');
    var modal = document.querySelector('[data-webblocks-update-progress-modal]');
    if (!form || !modal) { return; }

    function openModal(trigger) {
        modal.addEventListener('wb:overlay:close-request', function (event) { event.preventDefault(); });
        if (window.WBModal && typeof window.WBModal.open === 'function') {
            window.WBModal.open(modal, trigger);
        } else {
            modal.hidden = false;
            modal.classList.add('is-open');
        }
    }

    if (!window.fetch) {
        form.addEventListener('submit', function () { openModal(null); });
        return;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var button = form.querySelector('[data-wb-update-submit]');
        if (button) { button.disabled = true; }
        openModal(button);

        var body = new FormData(form);
        var health = modal.dataset.wbHealthUrl || window.location.href;
        var returnUrl = modal.dataset.wbReturnUrl || window.location.href;
        var waiting = modal.dataset.wbWaitingLabel;
        var status = document.getElementById('wb-update-progress-status');
        var started = Date.now();
        var done = false;

        function goHome() {
            if (done) { return; }
            done = true;
            window.location.assign(returnUrl);
        }
        function poll() {
            if (done) { return; }
            if (Date.now() - started > 180000) { goHome(); return; }
            fetch(health, { method: 'GET', cache: 'no-store', credentials: 'same-origin' })
                .then(function (response) {
                    if (response && response.status >= 200 && response.status < 500) { goHome(); } else { setTimeout(poll, 1500); }
                })
                .catch(function () { setTimeout(poll, 1500); });
        }

        fetch(form.getAttribute('action'), { method: 'POST', body: body, redirect: 'manual', credentials: 'same-origin' })
            .then(function () { if (waiting && status) { status.textContent = waiting; } })
            .catch(function () { if (waiting && status) { status.textContent = waiting; } })
            .then(poll);
    });
}());
