(function () {
    if (!document.querySelector('#slot-block-picker-modal, #slot-block-editor-modal, [data-wb-slot-block-tabs]')) {
        return;
    }

    function syncVisibleModals() {
        var pickerModal = document.getElementById('slot-block-picker-modal');
        var pickerLayer = pickerModal ? pickerModal.closest('.wb-overlay-layer--dialog') : null;
        var editorModal = document.getElementById('slot-block-editor-modal');

        if (!pickerLayer || !editorModal) {
            return;
        }

        pickerLayer.hidden = true;
    }

    document.addEventListener('wb:tabs:change', function (event) {
        var container = event.target;

        if (!container || !container.matches('[data-wb-slot-block-tabs]')) {
            return;
        }

        var hiddenInput = container.querySelector('[data-wb-slot-block-tab-input]');

        if (hiddenInput && event.detail && event.detail.tabId) {
            hiddenInput.value = event.detail.tabId === 'slot-block-info-panel' ? 'block-info' : 'block-fields';
        }
    });

    syncVisibleModals();
}());
