(function () {
    document.querySelectorAll('[data-wb-policy-send-code]').forEach(function (button) {
        var form = button.closest('form');
        var status = form && form.querySelector('[data-wb-policy-delivery-status]');
        var code = document.getElementById(button.dataset.codeId);
        if (!form || !status || !code) return;
        var label = button.textContent;
        var pending = false;
        var retryAt = 0;
        var timer;

        function updateButton() {
            var remaining = Math.max(0, Math.ceil((retryAt - Date.now()) / 1000));
            button.disabled = pending || remaining > 0;
            button.textContent = pending ? button.dataset.sending : (remaining > 0 ? button.dataset.retry.replace(':seconds', remaining) : label);
            if (!remaining) window.clearInterval(timer);
        }

        function showStatus(message, failed) {
            status.textContent = message;
            status.classList.toggle('wb-field-error', failed);
            status.classList.toggle('wb-text-muted', !failed);
        }

        button.addEventListener('click', async function () {
            if (pending || retryAt > Date.now()) return;
            pending = true;
            button.setAttribute('aria-busy', 'true');
            updateButton();
            showStatus(button.dataset.sending, false);
            var controller = new AbortController();
            var timeout = window.setTimeout(function () { controller.abort(); }, 30000);
            try {
                // Never serialize the policy form: passwords, codes and role choices stay here.
                var body = new URLSearchParams({
                    _token: form.querySelector('input[name="_token"]').value,
                    purpose: 'action',
                    tab: 'policy'
                });
                var response = await fetch(button.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body,
                    signal: controller.signal
                });
                var data = await response.json();
                var knownStatus = ['sent', 'send_failed', 'send_throttled', 'invalid'].includes(data.status);
                var seconds = Number(data.retry_after || response.headers.get('Retry-After') || 0);
                if (response.status === 429 && !seconds) seconds = 60;
                if (Number.isFinite(seconds) && seconds > 0) {
                    retryAt = Date.now() + Math.min(seconds, 3600) * 1000;
                    window.clearInterval(timer);
                    timer = window.setInterval(updateButton, 250);
                }
                showStatus(knownStatus ? data.message : (response.status === 429 ? button.dataset.throttled : button.dataset.failed), !response.ok);
                if (response.ok && data.status === 'sent') code.focus();
            } catch (error) {
                showStatus(button.dataset.failed, true);
            } finally {
                window.clearTimeout(timeout);
                pending = false;
                button.removeAttribute('aria-busy');
                updateButton();
            }
        });
    });
}());
