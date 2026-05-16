(function () {
    if (!document.querySelector('#slot-block-picker-modal, #slot-block-editor-modal, [data-wb-slot-block-tabs]')) {
        return;
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
}());
