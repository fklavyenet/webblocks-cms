(function () {
    function controlledItems(group) {
        if (!group) {
            return null;
        }

        var toggle = group.querySelector('.wb-nav-group-toggle');
        var targetId = toggle ? toggle.getAttribute('aria-controls') : null;

        return targetId ? document.getElementById(targetId) : group.querySelector('.wb-nav-group-items');
    }

    function syncGroup(group) {
        var items = controlledItems(group);

        if (!items) {
            return;
        }

        items.hidden = !group.classList.contains('is-open');
    }

    function syncAllGroups() {
        document.querySelectorAll('[data-wb-nav-group]').forEach(syncGroup);
    }

    document.addEventListener('wb:navgroup:open', function (event) {
        syncGroup(event.target);
    });

    document.addEventListener('wb:navgroup:close', function (event) {
        syncGroup(event.target);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncAllGroups);

        return;
    }

    syncAllGroups();
}());
