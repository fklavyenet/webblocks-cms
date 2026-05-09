(function () {
    var tabs = document.querySelector('[data-wb-page-settings-tabs]');

    if (!tabs) {
        return;
    }

    document.addEventListener('wb:tabs:change', function (event) {
        var target = event.target;

        if (!target || !target.matches('[data-wb-page-settings-tabs]')) {
            return;
        }

        var hiddenInput = target.closest('form').querySelector('[data-wb-page-settings-tab-input]');

        if (!hiddenInput || !event.detail || !event.detail.tabId) {
            return;
        }

        hiddenInput.value = event.detail.tabId === 'page-settings-assets-panel' ? 'page-assets' : 'general';
    });
}());
