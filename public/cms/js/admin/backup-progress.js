(function () {
    'use strict';
    var status = document.querySelector('[data-wb-backup-progress]');
    if (!status || !window.fetch) return;
    var message = status.querySelector('[data-wb-backup-message]');
    var bar = status.querySelector('[data-wb-backup-bar]');
    var badge = document.querySelector('[data-wb-backup-badge]');
    var timer;

    function finish(state) {
        message.textContent = status.dataset[state];
        status.classList.replace('wb-alert-info', state === 'completed' ? 'wb-alert-success' : 'wb-alert-warning');
        bar.hidden = true;
        if (badge && badge.dataset[state]) {
            badge.textContent = badge.dataset[state];
            badge.classList.replace('wb-status-pending', state === 'completed' ? 'wb-status-active' : 'wb-status-danger');
        }
    }

    async function poll() {
        try {
            var response = await fetch(status.dataset.wbBackupProgress, {
                credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'},
                signal: window.AbortSignal?.timeout?.(8000)
            });
            if (!response.ok || response.redirected) throw new Error('Backup status unavailable');
            var data = await response.json();
            if (data.state === 'running') timer = setTimeout(poll, 2500);
            else finish(['completed', 'failed', 'interrupted'].includes(data.state) ? data.state : 'unavailable');
        } catch (_) {
            finish('unavailable');
        }
    }

    timer = setTimeout(poll, 1000);
    window.addEventListener('pagehide', function () { clearTimeout(timer); });
}());
