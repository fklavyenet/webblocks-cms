(function () {
    var modalLayerSelector = '[data-wb-page-slot-source-modal]';

    if (!document.querySelector(modalLayerSelector)) {
        return;
    }

    function visibleModalLayers() {
        return Array.prototype.slice.call(document.querySelectorAll(modalLayerSelector)).filter(function (layer) {
            return !layer.hidden;
        });
    }

    function updateBodyLock() {
        var hasOpenModal = visibleModalLayers().length > 0;

        document.body.classList.toggle('wb-overlay-lock', hasOpenModal);
        document.body.classList.toggle('overflow-y-hidden', hasOpenModal);
        document.body.style.overflow = hasOpenModal ? 'hidden' : '';
    }

    function setModalOpen(layer, isOpen) {
        if (!layer) {
            return;
        }

        var modal = layer.querySelector('.wb-modal');

        layer.hidden = !isOpen;

        if (modal) {
            modal.classList.toggle('is-open', isOpen);
        }

        updateBodyLock();

        if (!isOpen) {
            return;
        }

        window.setTimeout(function () {
            var focusTarget = layer.querySelector('select, button, input:not([type="hidden"]), textarea, a[href]');

            if (focusTarget) {
                focusTarget.focus();
            }
        }, 0);
    }

    function syncSourceForm(form) {
        if (!form) {
            return;
        }

        var sourceSelect = form.querySelector('[data-wb-slot-source-type]:checked');
        var sharedSlotField = form.querySelector('[data-wb-shared-slot-field]');
        var sharedSlotSelect = form.querySelector('[data-wb-shared-slot-select]');
        var sourceHelper = form.querySelector('[data-wb-slot-source-helper]');

        if (!sourceSelect || !sharedSlotField || !sharedSlotSelect) {
            return;
        }

        var sourceType = sourceSelect.value;
        var helperTextBySource = {
            page: "This slot renders this page's own blocks.",
            shared_slot: 'This slot renders reusable Shared Slot content.',
            disabled: 'This slot renders nothing publicly.'
        };

        sharedSlotField.hidden = sourceType !== 'shared_slot';
        sharedSlotSelect.disabled = sourceType !== 'shared_slot';

        if (sourceHelper) {
            sourceHelper.textContent = helperTextBySource[sourceType] || helperTextBySource.page;
        }

        form.querySelectorAll('[data-wb-slot-source-option]').forEach(function (option) {
            var input = option.querySelector('[data-wb-slot-source-type]');
            var isActive = input && input.checked;

            option.classList.toggle('wb-btn-primary', !!isActive);
            option.classList.toggle('is-active', !!isActive);
            option.classList.toggle('wb-btn-secondary', !isActive);
            option.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    document.querySelectorAll('[data-wb-page-slot-source-form]').forEach(syncSourceForm);
    updateBodyLock();

    document.addEventListener('change', function (event) {
        if (!event.target.matches('[data-wb-slot-source-type]')) {
            return;
        }

        syncSourceForm(event.target.closest('[data-wb-page-slot-source-form]'));
    });

    document.addEventListener('click', function (event) {
        var openTrigger = event.target.closest('[data-wb-page-slot-source-open][data-wb-page-slot-source-target^="#slot-source-modal-"]');

        if (openTrigger) {
            var modalSelector = openTrigger.getAttribute('data-wb-page-slot-source-target');
            var modal = modalSelector ? document.querySelector(modalSelector) : null;

            if (modal) {
                event.preventDefault();
                setModalOpen(modal.closest(modalLayerSelector), true);
            }

            return;
        }

        var closeTrigger = event.target.closest('[data-wb-page-slot-source-modal-close]');

        if (closeTrigger) {
            event.preventDefault();
            setModalOpen(closeTrigger.closest(modalLayerSelector), false);
            return;
        }

        var modalLayer = event.target.closest(modalLayerSelector);

        if (modalLayer && event.target === modalLayer) {
            setModalOpen(modalLayer, false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        var openLayers = visibleModalLayers();

        if (openLayers.length === 0) {
            return;
        }

        setModalOpen(openLayers[openLayers.length - 1], false);
    });
}());
