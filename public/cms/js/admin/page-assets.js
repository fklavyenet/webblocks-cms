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

        var hiddenInput = target.closest('.wb-card').querySelector('[data-wb-page-settings-tab-input]');

        if (!hiddenInput || !event.detail || !event.detail.tabId) {
            return;
        }

        if (event.detail.tabId === 'page-management-assets-panel') {
            hiddenInput.value = 'assets';

            return;
        }

        if (event.detail.tabId === 'page-management-overview-panel') {
            hiddenInput.value = 'overview';

            return;
        }

        if (event.detail.tabId === 'page-management-layout-slots-panel') {
            hiddenInput.value = 'layout-slots';

            return;
        }

        hiddenInput.value = 'settings';
    });
}());
