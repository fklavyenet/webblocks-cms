(function () {
    function bindNavGroupToggles() {
        document.querySelectorAll('[data-wb-nav-group-toggle]').forEach(function (toggle) {
            if (toggle.dataset.wbNavGroupToggleReady === 'true') {
                return;
            }

            toggle.dataset.wbNavGroupToggleReady = 'true';

            toggle.addEventListener('click', function () {
                var group = toggle.closest('.wb-nav-group');
                var targetId = toggle.getAttribute('aria-controls');
                var items = targetId ? document.getElementById(targetId) : group ? group.querySelector('.wb-nav-group-items') : null;

                if (!group) {
                    return;
                }

                var isOpen = group.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

                if (items) {
                    items.hidden = !isOpen;
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindNavGroupToggles);

        return;
    }

    bindNavGroupToggles();
}());
