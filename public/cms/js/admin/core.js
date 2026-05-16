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

    function normalizeSiteHandle(value) {
        return String(value || '')
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^A-Za-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-{2,}/g, '-')
            .toLowerCase();
    }

    function bindSiteHandleAutosuggest() {
        var handleInput = document.querySelector('[data-site-handle-input]');
        var nameInput = document.querySelector('[data-site-name-input]');

        if (!handleInput || !nameInput) {
            return;
        }

        if (handleInput.dataset.siteHandleAutosuggest !== 'on') {
            return;
        }

        var manuallyEdited = String(handleInput.value || '').trim() !== '';

        handleInput.addEventListener('input', function () {
            manuallyEdited = true;
            handleInput.value = normalizeSiteHandle(handleInput.value);
        });

        nameInput.addEventListener('input', function () {
            if (manuallyEdited) {
                return;
            }

            handleInput.value = normalizeSiteHandle(nameInput.value);
        });

        if (!manuallyEdited) {
            handleInput.value = normalizeSiteHandle(nameInput.value);
        }
    }

    function getOverlayElementFromEventTarget(target) {
        return target && typeof target.closest === 'function'
            ? target.closest('.wb-modal, .wb-drawer, [data-wb-picker-panel][data-wb-picker-panel-mode="overlay"]')
            : null;
    }

    function getDirtyFormForOverlay(overlay) {
        return overlay ? overlay.querySelector('[data-wb-admin-dirty-form]') : null;
    }

    function getFormSnapshot(form) {
        var data;

        if (!form || typeof window.FormData === 'undefined') {
            return '';
        }

        data = new window.FormData(form);

        return Array.prototype.map.call(Array.from(data.entries()), function (entry) {
            var value = entry[1];

            if (value && typeof value === 'object' && typeof value.name === 'string') {
                return entry[0] + '=' + value.name;
            }

            return entry[0] + '=' + String(value);
        }).join('&');
    }

    function markDirtyFormSnapshot(form) {
        if (!form) {
            return;
        }

        form.dataset.wbAdminDirtyInitial = getFormSnapshot(form);
    }

    function isDirtyForm(form) {
        if (!form) {
            return false;
        }

        if (!Object.prototype.hasOwnProperty.call(form.dataset, 'wbAdminDirtyInitial')) {
            markDirtyFormSnapshot(form);
        }

        return form.dataset.wbAdminDirtyInitial !== getFormSnapshot(form);
    }

    function closeOverlayProgrammatically(overlay) {
        if (!overlay) {
            return;
        }

        if (window.WBModal && overlay.classList.contains('wb-modal')) {
            window.WBModal.close(overlay);
            return;
        }

        overlay.hidden = true;
        overlay.classList.remove('is-open');
    }

    function handleOverlayCloseUrl(overlay) {
        var closeUrl = overlay && overlay.dataset ? overlay.dataset.wbAdminCloseUrl : '';

        if (!closeUrl) {
            return;
        }

        window.location.assign(closeUrl);
    }

    function dirtyCloseMessage(form) {
        return form && form.dataset && form.dataset.wbAdminDirtyCloseConfirm
            ? form.dataset.wbAdminDirtyCloseConfirm
            : 'Discard unsaved changes?';
    }

    function bindDirtyOverlayGuards() {
        document.querySelectorAll('[data-wb-admin-dirty-form]').forEach(function (form) {
            if (form.getAttribute('data-wb-admin-dirty-bound') === 'true') {
                return;
            }

            markDirtyFormSnapshot(form);
            form.setAttribute('data-wb-admin-dirty-bound', 'true');
            form.addEventListener('submit', function () {
                form.dataset.wbAdminDirtyInitial = getFormSnapshot(form);
            });
        });

        document.addEventListener('wb:overlay:close-request', function (event) {
            var overlay = getOverlayElementFromEventTarget(event.target) || (event.detail ? event.detail.overlay : null);
            var form = getDirtyFormForOverlay(overlay);

            if (!overlay || !form || !isDirtyForm(form)) {
                return;
            }

            event.preventDefault();

            if (!window.confirm(dirtyCloseMessage(form))) {
                return;
            }

            markDirtyFormSnapshot(form);
            closeOverlayProgrammatically(overlay);
            handleOverlayCloseUrl(overlay);
        });

        document.addEventListener('wb:modal:close', function (event) {
            var overlay = getOverlayElementFromEventTarget(event.target);

            if (!overlay) {
                return;
            }

            handleOverlayCloseUrl(overlay);
        });
    }

    admin.escapeHtml = escapeHtml;
    admin.resetAdminTransientUiState = resetAdminTransientUiState;
    admin.bindAdminTransientUiReset = bindAdminTransientUiReset;
    admin.redirectToLoginFromAdmin = redirectToLoginFromAdmin;
    admin.normalizeSiteHandle = normalizeSiteHandle;

    bindAdminTransientUiReset();
    bindNavGroupToggles();
    bindSiteHandleAutosuggest();
    bindDirtyOverlayGuards();
}());
