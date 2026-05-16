(function () {
    if (!document.querySelector('#slot-block-picker-modal, #slot-block-editor-modal, [data-wb-slot-block-tabs]')) {
        return;
    }

    function modalApi() {
        return window.WBModal || null;
    }

    function openAutoloadModal() {
        var modal = document.querySelector('[data-wb-slot-block-modal-autoload]:not([data-wb-admin-autoload-bound="true"])');
        var runtime = modalApi();

        if (!modal || !runtime) {
            return;
        }

        if (modal.getAttribute('data-wb-overlay-runtime') === 'true' || modal.classList.contains('is-open')) {
            return;
        }

        runtime.open(modal, null);
    }

    openAutoloadModal();

    document.addEventListener('wb:tabs:change', function (event) {
        var container = event.target;

        if (container && container.matches('[data-wb-slot-block-picker-tabs]')) {
            var pickerTabInput = document.querySelector('[data-wb-slot-block-picker-tab-input]');

            if (pickerTabInput && event.detail && event.detail.tabId) {
                pickerTabInput.value = event.detail.tabId.replace('slot-block-picker-panel-', '');
            }

            return;
        }

        if (!container || !container.matches('[data-wb-slot-block-tabs]')) {
            return;
        }

        var hiddenInput = container.querySelector('[data-wb-slot-block-tab-input]');

        if (hiddenInput && event.detail && event.detail.tabId) {
            hiddenInput.value = event.detail.tabId === 'slot-block-info-panel' ? 'block-info' : 'block-fields';
        }
    });
}());
