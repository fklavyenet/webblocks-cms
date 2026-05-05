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

        if (!sourceSelect || !sharedSlotField || !sharedSlotSelect) {
            return;
        }

        var sourceType = sourceSelect.value;

        sharedSlotField.hidden = sourceType !== 'shared_slot';
        sharedSlotSelect.disabled = sourceType !== 'shared_slot';
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
