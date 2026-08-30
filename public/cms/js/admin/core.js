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

    }

    function bindAdminTransientUiReset() {
        resetAdminTransientUiState();

        window.addEventListener('pageshow', function () {
            resetAdminTransientUiState();
        });
    }

    function hideLegacyOverlayShell(overlay) {
        var legacyLayer = overlay ? overlay.closest('.wb-overlay-layer--dialog') : null;

        if (!legacyLayer || legacyLayer.getAttribute('data-wb-admin-legacy-hidden') === 'true') {
            return;
        }

        legacyLayer.hidden = true;
        legacyLayer.setAttribute('data-wb-admin-legacy-hidden', 'true');
    }

    function autoloadModalRuntime() {
        return window.WBModal || null;
    }

    function autoloadDrawerRuntime() {
        return window.WBDrawer || null;
    }

    function bootstrapAdminAutoloadOverlays() {
        document.querySelectorAll('[data-wb-admin-autoload-overlay]').forEach(function (overlay) {
            var modalRuntime = autoloadModalRuntime();
            var drawerRuntime = autoloadDrawerRuntime();

            if (overlay.getAttribute('data-wb-admin-autoload-bound') === 'true') {
                return;
            }

            overlay.setAttribute('data-wb-admin-autoload-bound', 'true');
            hideLegacyOverlayShell(overlay);

            if (overlay.classList.contains('wb-modal') && modalRuntime) {
                if (overlay.getAttribute('data-wb-overlay-runtime') !== 'true' && !overlay.classList.contains('is-open')) {
                    modalRuntime.open(overlay, null);
                }

                return;
            }

            if (overlay.classList.contains('wb-drawer') && drawerRuntime) {
                if (overlay.getAttribute('data-wb-overlay-runtime') !== 'true' && !overlay.classList.contains('is-open')) {
                    drawerRuntime.open(overlay, null);
                }

                return;
            }

            overlay.hidden = false;
            overlay.classList.add('is-open');
        });
    }

    function redirectToLoginFromAdmin() {
        resetAdminTransientUiState();
        window.location.assign(admin.loginUrl || '/login');
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

        overlay.dataset.wbAdminForceClose = 'true';

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

    function dirtyConfirmModalId() {
        return 'wb-admin-dirty-close-confirm-modal';
    }

    function ensureOverlayId(overlay) {
        if (!overlay) {
            return '';
        }

        if (overlay.id) {
            return overlay.id;
        }

        overlay.id = 'wb-admin-overlay-' + String(Date.now()) + '-' + String(Math.floor(Math.random() * 1000));

        return overlay.id;
    }

    function dirtyConfirmModal() {
        return document.getElementById(dirtyConfirmModalId());
    }

    function dirtyConfirmMessageElement() {
        var modal = dirtyConfirmModal();

        return modal ? modal.querySelector('[data-wb-admin-dirty-close-message]') : null;
    }

    function dirtyConfirmModalBody() {
        var overlayRoot = document.getElementById('wb-overlay-root');

        if (!overlayRoot) {
            return null;
        }

        return [
            '<div class="wb-modal wb-modal-sm wb-admin-dirty-close-modal" id="' + dirtyConfirmModalId() + '" role="dialog" aria-modal="true" aria-labelledby="' + dirtyConfirmModalId() + '-title" data-wb-admin-dirty-close-modal>',
            '<div class="wb-modal-dialog">',
            '<div class="wb-modal-header">',
            '<h2 class="wb-modal-title" id="' + dirtyConfirmModalId() + '-title">Discard unsaved changes?</h2>',
            '<button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Keep editing">',
            '<i class="wb-icon wb-icon-x" aria-hidden="true"></i>',
            '</button>',
            '</div>',
            '<div class="wb-modal-body wb-stack wb-gap-2">',
            '<p data-wb-admin-dirty-close-message>Unsaved changes will be lost if you close this overlay.</p>',
            '</div>',
            '<div class="wb-modal-footer wb-flex wb-justify-end wb-gap-2">',
            '<button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">Keep editing</button>',
            '<button type="button" class="wb-btn wb-btn-danger" data-wb-admin-dirty-close-confirm-action>Close without saving</button>',
            '</div>',
            '</div>',
            '</div>'
        ].join('');
    }

    function ensureDirtyCloseConfirmationModal() {
        var overlayRoot = document.getElementById('wb-overlay-root');

        if (!overlayRoot) {
            return null;
        }

        var modal = dirtyConfirmModal();

        if (modal) {
            return modal;
        }

        overlayRoot.insertAdjacentHTML('beforeend', dirtyConfirmModalBody());

        return dirtyConfirmModal();
    }

    function openDirtyCloseConfirmation(overlay, form) {
        var modal = ensureDirtyCloseConfirmationModal();
        var message = dirtyConfirmMessageElement();

        if (!modal || !window.WBModal) {
            return false;
        }

        if (message) {
            message.textContent = dirtyCloseMessage(form) + ' Unsaved changes will be lost.';
        }

        modal.dataset.wbAdminDirtyOverlayId = ensureOverlayId(overlay);
        modal.dataset.wbAdminDirtyCloseUrl = overlay.dataset ? (overlay.dataset.wbAdminCloseUrl || '') : '';

        window.WBModal.open(modal, null);

        return true;
    }

    function bindDirtyCloseConfirmationActions() {
        document.addEventListener('click', function (event) {
            var confirmButton = event.target.closest('[data-wb-admin-dirty-close-confirm-action]');
            var modal = dirtyConfirmModal();
            var overlay;

            if (!confirmButton || !modal) {
                return;
            }

            overlay = modal.dataset.wbAdminDirtyOverlayId !== ''
                ? document.getElementById(modal.dataset.wbAdminDirtyOverlayId)
                : null;

            if (window.WBModal) {
                window.WBModal.close(modal);
            } else {
                modal.hidden = true;
                modal.classList.remove('is-open');
            }

            if (!overlay) {
                return;
            }

            var form = getDirtyFormForOverlay(overlay);

            if (form) {
                markDirtyFormSnapshot(form);
            }

            closeOverlayProgrammatically(overlay);
            handleOverlayCloseUrl(overlay);
        });

        document.addEventListener('wb:modal:close', function (event) {
            var modal = event.target.closest('.wb-modal');

            if (!modal || modal.id !== dirtyConfirmModalId()) {
                return;
            }

            delete modal.dataset.wbAdminDirtyOverlayId;
            delete modal.dataset.wbAdminDirtyCloseUrl;
        });
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

            if (!overlay) {
                return;
            }

            if (overlay.dataset && overlay.dataset.wbAdminForceClose === 'true') {
                delete overlay.dataset.wbAdminForceClose;
                return;
            }

            if (!form || !isDirtyForm(form)) {
                return;
            }

            event.preventDefault();

            if (!openDirtyCloseConfirmation(overlay, form)) {
                return;
            }
        });

        document.addEventListener('wb:modal:close', function (event) {
            var overlay = getOverlayElementFromEventTarget(event.target);

            if (!overlay) {
                return;
            }

            handleOverlayCloseUrl(overlay);
        });
    }

    function bindUpdateIndicator() {
        document.querySelectorAll('[data-wb-update-indicator]').forEach(function (indicator) {
            var url = indicator.getAttribute('data-wb-update-indicator-url');

            if (indicator.getAttribute('data-wb-update-indicator-bound') === 'true' || !url || typeof window.fetch !== 'function') {
                return;
            }

            indicator.setAttribute('data-wb-update-indicator-bound', 'true');

            window.fetch(url, {
                headers: {
                    Accept: 'application/json'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                if (!response.ok) {
                    return null;
                }

                return response.json();
            }).then(function (data) {
                var labelNode;
                var label;
                var state;
                var targetUrl;

                if (!data || data.visible !== true) {
                    indicator.hidden = true;
                    return;
                }

                state = String(data.state || 'unknown');
                label = String(data.label || 'Update available');
                targetUrl = String(data.url || indicator.getAttribute('href') || '');
                labelNode = indicator.querySelector('[data-wb-update-indicator-label]');

                indicator.hidden = false;
                indicator.setAttribute('data-wb-update-indicator-state', state);
                indicator.setAttribute('aria-label', label);
                indicator.setAttribute('title', label);

                if (targetUrl) {
                    indicator.setAttribute('href', targetUrl);
                }

                if (labelNode) {
                    labelNode.textContent = label;
                }
            }).catch(function () {});
        });
    }

    function bindBusySubmitButtons() {
        // The busy-submit behavior ships in WebBlocks UI (WBBusySubmit,
        // data-wb-busy). This wrapper only rebinds after dynamic DOM injection.
        if (window.WBBusySubmit && typeof window.WBBusySubmit.bind === 'function') {
            window.WBBusySubmit.bind();
        }
    }

    admin.escapeHtml = escapeHtml;
    admin.resetAdminTransientUiState = resetAdminTransientUiState;
    admin.bindAdminTransientUiReset = bindAdminTransientUiReset;
    admin.redirectToLoginFromAdmin = redirectToLoginFromAdmin;
    admin.normalizeSiteHandle = normalizeSiteHandle;
    admin.bindUpdateIndicator = bindUpdateIndicator;
    admin.bindBusySubmitButtons = bindBusySubmitButtons;

    bindAdminTransientUiReset();
    bindSiteHandleAutosuggest();
    bootstrapAdminAutoloadOverlays();
    bindDirtyCloseConfirmationActions();
    bindDirtyOverlayGuards();
    bindUpdateIndicator();
    bindBusySubmitButtons();
}());
