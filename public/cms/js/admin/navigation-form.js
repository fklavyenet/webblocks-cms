document.querySelectorAll('[data-navigation-link-type]').forEach(function (linkType) {
    var form = linkType.closest('form');
    if (!form || form.dataset.navigationFormReady === '1') { return; }
    form.dataset.navigationFormReady = '1';

    var pageField = form.querySelector('[data-navigation-page-field]');
    var urlField = form.querySelector('[data-navigation-url-field]');
    var targetField = form.querySelector('[data-navigation-target-field]');
    var copy = form.querySelector('[data-navigation-link-type-copy]');
    var pageInput = form.querySelector('#page_id');
    var urlInput = form.querySelector('#url');
    var targetInput = form.querySelector('#target');

    function sync() {
        var type = linkType.value;
        var isPage = type === 'page';
        var isUrl = type === 'custom_url';
        var isGroup = type === 'group';

        if (pageField) { pageField.hidden = !isPage; }
        if (urlField) { urlField.hidden = !isUrl; }
        if (targetField) { targetField.hidden = !isUrl; }
        if (pageInput) { pageInput.disabled = !isPage; }
        if (urlInput) { urlInput.disabled = !isUrl; }
        if (targetInput) { targetInput.disabled = !isUrl; }
        if (copy) {
            copy.textContent = isGroup
                ? copy.dataset.navigationCopyGroup
                : (isUrl ? copy.dataset.navigationCopyCustomUrl : copy.dataset.navigationCopyPage);
        }
    }

    linkType.addEventListener('change', sync);
    sync();
});
