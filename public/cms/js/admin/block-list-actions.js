(function () {
    'use strict';

    function csrfToken() {
        var token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }

    function toast(message, type) {
        if (window.WBToast && typeof window.WBToast.show === 'function') {
            window.WBToast.show(message, { type: type, duration: type === 'success' ? 2500 : 4000 });
        }
    }

    function renderStatus(form, published) {
        var label = form.querySelector('[data-wb-block-status-label]');
        if (!label) return;
        label.textContent = published ? form.dataset.publishedLabel : form.dataset.draftLabel;
        label.classList.toggle('wb-status-active', published);
        label.classList.toggle('wb-status-pending', !published);
    }

    document.addEventListener('change', function (event) {
        var toggle = event.target.closest('[data-wb-block-status-toggle]');
        if (!toggle || typeof window.fetch !== 'function') return;

        var form = toggle.closest('[data-wb-block-status-form]');
        var published = toggle.checked;
        toggle.disabled = true;

        window.fetch(form.action, {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ status: published ? 'published' : 'draft' })
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok || data.success === false) throw new Error(data.message || form.dataset.errorMessage);
                renderStatus(form, published);
                toast(data.message || form.dataset.successMessage, 'success');
            });
        }).catch(function (error) {
            toggle.checked = !published;
            renderStatus(form, !published);
            toast(error.message || form.dataset.errorMessage, 'danger');
        }).then(function () {
            toggle.disabled = false;
        });
    });
}());
