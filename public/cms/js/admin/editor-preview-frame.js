(function () {
    'use strict';
    document.documentElement.dataset.wbEditorPreviewFrame = 'ready';
    document.addEventListener('submit', function (event) { event.preventDefault(); }, true);
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a');
        if (!link) return;
        event.preventDefault();
        var url;
        try { url = new URL(link.href); } catch (error) { return; }
        var pagination = {};
        url.searchParams.forEach(function (value, key) {
            if (/^wb_collection_[1-9][0-9]*_page$/.test(key)) pagination[key] = Number(value);
        });
        if (Object.keys(pagination).length) parent.postMessage({ type: 'wb-editor-preview-page', pagination: pagination }, '*');
    }, true);
}());
