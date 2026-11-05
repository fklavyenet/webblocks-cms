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

    function bindAdminHistoryModalLinks() {
        document.addEventListener('click', function (event) {
            var dismiss = event.target.closest('[data-wb-dismiss="modal"]');
            var overlay = dismiss ? dismiss.closest('[data-wb-admin-close-history]') : null;

            if (overlay && dismiss.tagName === 'A') {
                event.preventDefault();
            }
        }, true);
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

        // Recovery/session bookkeeping and the selected editor tab are not edits.
        return Array.from(data.entries()).filter(function (entry) {
            return ['_editor_recovery_session', '_editor_recovery_base', '_editor_form_kind', '_slot_block_tab', '_consent_tab'].indexOf(entry[0]) === -1;
        }).map(function (entry) {
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

        if (overlay.hasAttribute('data-wb-admin-close-history') && window.history && window.history.replaceState) {
            window.history.replaceState({}, '', closeUrl);
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

            if (event.defaultPrevented || !form || !isDirtyForm(form)) {
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

    function bindModalSubmitFeedback() {
        var template = document.querySelector('[data-wb-modal-submit-feedback]');
        if (!template) return;

        var pending = new Map();
        var modalFor = function (element) { return element?.closest('.wb-modal'); };
        var dismissSelector = '.wb-modal-close, [data-wb-dismiss="modal"], [data-wb-picker-close], [data-wb-picker-apply], [data-admin-form-actions-main] a, .wb-modal-footer a.wb-btn-secondary';

        function start(surface, button, operation, progress, nativeSubmit) {
            var label = template.getAttribute('data-' + operation);
            if (pending.has(surface) || !label) return null;

            var feedback = template.content.firstElementChild.cloneNode(true);
            var status = feedback.querySelector('[role="status"]');
            var modal = modalFor(surface);
            var content = modal === surface ? modal.querySelector('.wb-modal-body') : surface;
            var indicator = progress ? feedback : status;
            var original = document.createDocumentFragment();
            var originalLabel = button.getAttribute('aria-label');
            var controls = [button].concat(Array.from(surface.querySelectorAll(dismissSelector))
                .filter(function (control) { return modalFor(control) === modal; }));
            var disabledStates = controls.map(function (control) {
                var previous = control.getAttribute('aria-disabled');
                control.setAttribute('aria-disabled', 'true');
                return previous;
            });

            while (button.firstChild) original.append(button.firstChild);
            button.textContent = label;
            button.classList.add('is-busy');
            if (originalLabel !== null) button.setAttribute('aria-label', label);

            if (progress) feedback.querySelector('progress').setAttribute('aria-label', label);
            (content || surface).append(indicator);
            // Announce in a connected live region. Leave native submitter values intact.
            var announce = window.setTimeout(function () { status.textContent = label; }, 0);
            function finish() {
                window.clearTimeout(announce);
                indicator.remove();
                button.replaceChildren(original);
                button.classList.remove('is-busy');
                controls.forEach(function (control, index) {
                    if (disabledStates[index] === null) control.removeAttribute('aria-disabled');
                    else control.setAttribute('aria-disabled', disabledStates[index]);
                });
                if (originalLabel !== null) button.setAttribute('aria-label', originalLabel);
                pending.delete(surface);
            }
            pending.set(surface, {finish: finish, nativeSubmit: nativeSubmit});
            return finish;
        }

        admin.beginMediaUploadFeedback = function (panel, button) {
            return start(modalFor(panel) || panel, button, 'uploading', true, false);
        };

        // Block duplicates before any form-specific listener can run again.
        document.addEventListener('submit', function (event) {
            if (pending.has(modalFor(event.submitter) || modalFor(event.target))) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);

        // Run after CMS handlers so an intercepted save keeps its own feedback.
        window.addEventListener('submit', function (event) {
            var form = event.target;
            if (event.defaultPrevented || !(form instanceof HTMLFormElement)) return;
            var button = event.submitter || Array.from(form.elements).find(function (control) {
                return control.tagName === 'BUTTON' && control.type === 'submit' && !control.disabled;
            });
            var modal = modalFor(button) || modalFor(form);
            if (!modal || !button || button.tagName !== 'BUTTON') return;
            var method = button.getAttribute('formmethod') || form.getAttribute('method');
            var target = button.getAttribute('formtarget') || form.getAttribute('target');
            if (method?.toLowerCase() !== 'post' || (target && target !== '_self')) return;

            var progress = form.dataset.wbModalSubmitProgress;
            var operation = progress || form.dataset.wbModalSubmitLabel
                || (form.elements.namedItem('_method')?.value === 'DELETE' ? 'deleting' : 'saving');
            start(modal, button, operation, Boolean(progress), true);
        });

        document.addEventListener('wb:overlay:close-request', function (event) {
            if (pending.has(event.target)) event.preventDefault();
        }, true);
        document.addEventListener('click', function (event) {
            var control = event.target.closest(dismissSelector);
            if (!control || (!pending.has(modalFor(control)) && !pending.has(control.closest('[data-wb-picker-panel]')))) return;
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) return;
            pending.forEach(function (request) { if (request.nativeSubmit) request.finish(); });
        });
    }

    function bindSubmitOnChange() {
        document.addEventListener('change', function (event) {
            var field = event.target.closest('[data-wb-submit-on-change]');

            if (field && field.form) {
                field.form.requestSubmit();
            }
        });
    }

    admin.escapeHtml = escapeHtml;
    admin.resetAdminTransientUiState = resetAdminTransientUiState;
    admin.bindAdminTransientUiReset = bindAdminTransientUiReset;
    admin.redirectToLoginFromAdmin = redirectToLoginFromAdmin;
    admin.normalizeSiteHandle = normalizeSiteHandle;
    admin.bindUpdateIndicator = bindUpdateIndicator;
    admin.bindBusySubmitButtons = bindBusySubmitButtons;
    admin.initializeDynamicContent = function (root) {
        bootstrapAdminAutoloadOverlays();
        bindDirtyOverlayGuards();
        bindBusySubmitButtons();

        if (window.WebBlocksCmsAdminRichTextEditor && typeof window.WebBlocksCmsAdminRichTextEditor.init === 'function') {
            window.WebBlocksCmsAdminRichTextEditor.init(root || document);
        }

        [
            'WebBlocksCmsAdminTableEditor',
            'WebBlocksCmsAdminAssetPicker',
            'WebBlocksCmsAdminGalleryItems',
            'WebBlocksCmsAdminIconPicker',
            'WebBlocksCmsAdminBuilderItems',
            'WebBlocksCmsAdminInlineBlockBuilder'
        ].forEach(function (moduleName) {
            var module = window[moduleName];

            if (module && typeof module.init === 'function') {
                module.init(root || document);
            }
        });
    };

    document.querySelectorAll('[data-wb-user-role]').forEach(function (role) {
        var form = role.closest('form');
        if (!form) return;

        function syncAssignedSites() {
            var isSuperAdmin = role.value === role.dataset.wbSuperAdminRole;
            form.querySelectorAll('[data-wb-user-site]').forEach(function (site) {
                site.disabled = isSuperAdmin;
            });
            var help = form.querySelector('[data-wb-user-sites-help]');
            if (help) {
                help.textContent = isSuperAdmin ? help.dataset.wbSuperAdminHelp : help.dataset.wbAssignedSitesHelp;
            }
        }

        role.addEventListener('change', syncAssignedSites);
        syncAssignedSites();
    });

    function bindSeoEvidence() {
        var freshness = document.querySelector('[data-wb-seo-freshness]');
        if (freshness) {
            var stopped = false;
            async function checkFreshness() {
                if (stopped || document.hidden || !freshness.isConnected) return;
                var timeout;
                try {
                    var controller = new AbortController();
                    timeout = window.setTimeout(function () { controller.abort(); }, 8000);
                    var response = await fetch(freshness.dataset.wbSeoFreshness, {
                        credentials: 'same-origin', cache: 'no-store',
                        headers: { Accept: 'application/json' }, signal: controller.signal
                    });
                    window.clearTimeout(timeout);
                    if (!response.ok) { stopped = true; throw new Error(); }
                    var result = await response.json();
                    if (!result || result.ok !== true || typeof result.stale !== 'boolean') throw new Error();
                    var unavailable = document.querySelector('[data-wb-seo-freshness-unavailable]');
                    if (unavailable) unavailable.hidden = true;
                    if (result.stale) {
                        var warning = document.querySelector('[data-wb-seo-stale]');
                        if (warning) warning.hidden = false;
                        stopped = true;
                    }
                } catch (_) {
                    // An unavailable freshness check is not a fresh result.
                    var unavailable = document.querySelector('[data-wb-seo-freshness-unavailable]');
                    if (unavailable) unavailable.hidden = false;
                } finally {
                    window.clearTimeout(timeout);
                }
            }
            var timer = window.setInterval(checkFreshness, 15000);
            window.addEventListener('pagehide', function () { stopped = true; window.clearInterval(timer); }, { once: true });
            checkFreshness();
        }
        document.addEventListener('wb:tabs:change', function (event) {
            var workspace = event.target.closest('[data-wb-seo-workspace]');
            if (!workspace || !event.detail || !event.detail.tabId) return;
            var tab = event.detail.tabId.replace(/^seo-/, '');
            if (['pages', 'findings', 'links'].indexOf(tab) < 0) return;
            workspace.querySelectorAll('[data-wb-seo-tab]').forEach(function (field) { field.value = tab; });
            workspace.querySelectorAll('[data-wb-seo-refresh]').forEach(function (link) {
                var url = new URL(link.href, window.location.href);
                url.searchParams.set('tab', tab);
                link.href = url.href;
            });
        });
    }

    bindSeoEvidence();
    bindAdminTransientUiReset();
    bindAdminHistoryModalLinks();
    bindSiteHandleAutosuggest();
    bootstrapAdminAutoloadOverlays();
    bindDirtyCloseConfirmationActions();
    bindDirtyOverlayGuards();
    bindUpdateIndicator();
    bindBusySubmitButtons();
    bindModalSubmitFeedback();
    bindSubmitOnChange();
}());
