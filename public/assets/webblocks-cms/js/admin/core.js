(function () {
    var admin = window.WebBlocksCmsAdmin = window.WebBlocksCmsAdmin || {};
    var body = document.body;

    if (body && body.dataset && body.dataset.wbAdminLoginUrl) {
        admin.loginUrl = body.dataset.wbAdminLoginUrl;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function resetAdminTransientUiState() {
        if (document.body) {
            document.body.classList.remove('wb-overlay-lock', 'overflow-y-hidden');
            document.body.style.overflow = '';
        }

        var sidebar = document.getElementById('admin-sidebar');

        if (sidebar) {
            sidebar.classList.remove('is-open');
        }

        document.querySelectorAll('[data-wb-sidebar-backdrop]').forEach(function (backdrop) {
            backdrop.classList.remove('is-open');
        });

        document.querySelectorAll('[data-wb-toggle="sidebar"]').forEach(function (trigger) {
            trigger.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        });

        var overlayRoot = document.getElementById('wb-overlay-root');

        if (!overlayRoot) {
            return;
        }

        var dialogBackdrop = overlayRoot.querySelector('.wb-overlay-layer--dialog > .wb-overlay-backdrop');

        if (dialogBackdrop) {
            dialogBackdrop.hidden = true;
            dialogBackdrop.className = 'wb-overlay-backdrop';
            delete dialogBackdrop.dataset.wbOverlayOwner;
        }
    }

    function bindAdminTransientUiReset() {
        resetAdminTransientUiState();

        window.addEventListener('pageshow', function () {
            resetAdminTransientUiState();
        });
    }

    function redirectToLoginFromAdmin() {
        resetAdminTransientUiState();
        window.location.assign(admin.loginUrl || '/login');
    }

    function bindNavGroupToggles() {
        document.querySelectorAll('[data-wb-nav-group-toggle]').forEach(function (toggle) {
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

    admin.escapeHtml = escapeHtml;
    admin.resetAdminTransientUiState = resetAdminTransientUiState;
    admin.bindAdminTransientUiReset = bindAdminTransientUiReset;
    admin.redirectToLoginFromAdmin = redirectToLoginFromAdmin;

    bindAdminTransientUiReset();
    bindNavGroupToggles();
}());
