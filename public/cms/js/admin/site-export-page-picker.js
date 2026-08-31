(function () {
    document.querySelectorAll('[data-wb-export-pages]').forEach(function (root) {
        var modal = root.closest('.wb-modal');
        var select = modal ? modal.querySelector('[data-wb-export-site-select]') : null;

        function groups() { return root.querySelectorAll('[data-wb-export-page-group]'); }
        function activeGroup() {
            if (!select) { return groups().length === 1 ? groups()[0] : null; }
            return root.querySelector('[data-wb-export-page-group="' + select.value + '"]');
        }
        function boxes(group) { return group ? group.querySelectorAll('input[type="checkbox"]') : []; }
        function updateCount(group) {
            var label = root.querySelector('[data-wb-export-pages-count]');
            if (!label) { return; }
            if (!group) { label.textContent = ''; return; }
            var all = boxes(group);
            var on = 0;
            all.forEach(function (box) { if (box.checked) { on += 1; } });
            label.textContent = on + ' / ' + all.length;
        }
        function sync() {
            var active = activeGroup();
            groups().forEach(function (group) {
                var isActive = group === active;
                group.hidden = !isActive;
                boxes(group).forEach(function (box) { box.disabled = !isActive; });
            });
            updateCount(active);
        }
        function setAll(predicate) {
            var group = activeGroup();
            boxes(group).forEach(function (box) {
                box.checked = predicate(box.getAttribute('data-wb-export-page-status'));
            });
            updateCount(group);
        }

        if (select) { select.addEventListener('change', sync); }
        root.addEventListener('change', function (event) {
            if (event.target.matches('input[type="checkbox"]')) { updateCount(activeGroup()); }
        });
        root.querySelector('[data-wb-export-pages-all]').addEventListener('click', function () { setAll(function () { return true; }); });
        root.querySelector('[data-wb-export-pages-none]').addEventListener('click', function () { setAll(function () { return false; }); });
        root.querySelector('[data-wb-export-pages-published]').addEventListener('click', function () { setAll(function (status) { return status === 'published'; }); });
        sync();
    });
}());
