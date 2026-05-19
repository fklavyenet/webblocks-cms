(function () {
    function syncButtonLabel(modal) {
        var checkbox = modal.querySelector('[data-wb-delete-descendants-toggle]');
        var submit = modal.querySelector('[data-wb-delete-submit]');

        if (!checkbox || !submit) {
            return;
        }

        submit.textContent = checkbox.checked
            ? (submit.getAttribute('data-recursive-label') || 'Delete block and children')
            : (submit.getAttribute('data-default-label') || 'Delete block');
    }

    function initializeModal(modal) {
        if (modal.getAttribute('data-wb-slot-block-delete-modal-ready') === 'true') {
            syncButtonLabel(modal);

            return;
        }

        modal.setAttribute('data-wb-slot-block-delete-modal-ready', 'true');
        modal.addEventListener('change', function (event) {
            if (!event.target.matches('[data-wb-delete-descendants-toggle]')) {
                return;
            }

            syncButtonLabel(modal);
        });

        window.setTimeout(function () {
            var focusTarget = modal.querySelector('[data-wb-delete-descendants-toggle], .wb-modal-close, .wb-btn');

            if (focusTarget) {
                focusTarget.focus();
            }
        }, 0);

        syncButtonLabel(modal);
    }

    function run() {
        Array.prototype.slice.call(document.querySelectorAll('[data-wb-slot-block-delete-modal]')).forEach(initializeModal);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }

    window.addEventListener('pageshow', run);
}());
