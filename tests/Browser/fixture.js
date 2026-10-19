document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('submit', function (event) { event.preventDefault(); });
    var opener = document.querySelector('#open-host');
    if (opener) {
        opener.addEventListener('click', function () {
            var modal = document.querySelector('#host-modal');
            window.WBModal.open(modal, opener);
            if (window.WebBlocksCmsAdminAssetPicker) window.WebBlocksCmsAdminAssetPicker.init(modal);
        });
    }
    if (document.body.dataset.fixtureScenario === 'asset-picker') {
        setInterval(function () {
            document.querySelector('#test-status').textContent = 'Editor scroll: ' + document.querySelector('#host-modal .wb-modal-body').scrollTop
                + '; Materialized rows: ' + document.querySelectorAll('[data-wb-asset-card]').length
                + '; Image requests: ' + performance.getEntriesByType('resource').filter(function (entry) { return entry.initiatorType === 'img'; }).length;
        }, 100);
    }
});
