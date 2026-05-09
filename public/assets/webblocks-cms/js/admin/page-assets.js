(function () {
    var container = document.querySelector('[data-wb-page-assets]');
    var tabs = document.querySelector('[data-wb-page-settings-tabs]');

    if (!container && !tabs) {
        return;
    }

    if (tabs) {
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
    }

    if (!container) {
        return;
    }

    var list = container.querySelector('[data-wb-page-assets-list]');
    var emptyState = container.querySelector('[data-wb-page-assets-empty]');

    function syncEmptyState() {
        if (!emptyState) {
            return;
        }

        emptyState.hidden = list.querySelectorAll('[data-wb-page-asset-row]').length > 0;
    }

    function nextIndex() {
        return list.querySelectorAll('[data-wb-page-asset-row]').length;
    }

    function replaceNames(html, index) {
        return html.replace(/__NAME__/g, 'page_assets[' + index + ']');
    }

    container.querySelectorAll('[data-wb-page-assets-add]').forEach(function (button) {
        button.addEventListener('click', function () {
            var type = button.getAttribute('data-wb-page-assets-add');
            var template = container.querySelector('[data-wb-page-asset-template="' + type + '"]');

            if (!template) {
                return;
            }

            list.insertAdjacentHTML('beforeend', replaceNames(template.innerHTML, nextIndex()));
            syncEmptyState();
        });
    });

    container.addEventListener('click', function (event) {
        var removeButton = event.target.closest('[data-wb-page-assets-remove]');

        if (!removeButton) {
            return;
        }

        var row = removeButton.closest('[data-wb-page-asset-row]');

        if (row) {
            row.remove();
            syncEmptyState();
        }
    });

    syncEmptyState();
}());
