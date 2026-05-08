(function () {
    function bindNavGroupToggles() {
        document.querySelectorAll('[data-wb-nav-group-toggle]').forEach(function (toggle) {
            if (toggle.dataset.wbNavGroupToggleReady === 'true') {
                return;
            }

            toggle.dataset.wbNavGroupToggleReady = 'true';

            toggle.addEventListener('click', function () {
                var group = toggle.closest('.wb-nav-group');

                if (!group) {
                    return;
                }

                var isOpen = group.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindNavGroupToggles);

        return;
    }

    bindNavGroupToggles();
}());
