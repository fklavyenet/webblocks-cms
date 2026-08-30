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

    function modalApi() {
        return window.WBModal || null;
    }

    function setModalOpen(layer, isOpen) {
        if (!layer) {
            return;
        }

        var modal = layer.querySelector('.wb-modal');
        var runtime = modalApi();

        if (modal && runtime) {
            if (isOpen) {
                runtime.open(modal);
            } else {
                runtime.close(modal);
            }
        } else {
            layer.hidden = !isOpen;

            if (modal) {
                modal.classList.toggle('is-open', isOpen);
            }
        }

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

    }

    document.querySelectorAll('[data-wb-page-slot-source-form]').forEach(syncSourceForm);
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
        }
    });

    document.addEventListener('wb:modal:close', function (event) {
        var modal = event.target.closest('.wb-modal');
        var layer = modal ? modal.closest(modalLayerSelector) : null;

        if (!layer) {
            return;
        }

        layer.hidden = true;
    });
}());
