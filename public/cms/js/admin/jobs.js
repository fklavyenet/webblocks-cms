(function () {
    'use strict';
    document.querySelectorAll('[data-wb-job-runner]').forEach(function (host) {
        var button = host.querySelector('[data-wb-job-start]');
        var status = host.querySelector('[data-wb-job-status]');
        if (!button || !status) return;
        var running = false;
        button.addEventListener('click', async function () {
            if (running) return;
            var url = new URL(button.dataset.url, window.location.href);
            if (url.origin !== window.location.origin) return;
            running = true;
            button.disabled = true;
            try {
                while (running) {
                    var response = await fetch(url.href, {
                        method: 'POST', credentials: 'same-origin', redirect: 'error',
                        headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''}
                    });
                    if (!response.ok) throw new Error('step');
                    var result = await response.json();
                    status.textContent = result.message || '';
                    if (Number(result.total) > 0) status.textContent += ' · ' + result.processed + ' / ' + result.total;
                    var summary = host.closest('tr') || document.querySelector('[data-wb-job-summary]');
                    if (summary) {
                        var stateCell = summary.querySelector('[data-wb-job-state]');
                        var progressCell = summary.querySelector('[data-wb-job-progress]');
                        if (stateCell) stateCell.textContent = result.message || '';
                        if (progressCell && Number(result.total) > 0) progressCell.textContent = result.processed + ' / ' + result.total;
                    }
                    if (!['queued', 'running'].includes(result.state)) {
                        running = false;
                        window.location.reload();
                        return;
                    }
                    await new Promise(function (resolve) { window.setTimeout(resolve, 1500); });
                }
            } catch (_) {
                status.textContent = host.dataset.error;
            } finally {
                running = false;
                button.disabled = false;
            }
        });
        if (host.dataset.autostart === 'true') button.click();
        window.addEventListener('pagehide', function () { running = false; });
    });
}());
