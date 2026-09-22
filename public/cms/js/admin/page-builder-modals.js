(function () {
    if (!document.querySelector('#slot-block-picker-modal, #slot-block-editor-modal, [data-wb-slot-block-tabs]')) {
        return;
    }

    function modalApi() {
        return window.WBModal || null;
    }

    function openAutoloadModal() {
        var modal = document.querySelector('[data-wb-slot-block-modal-autoload]:not([data-wb-admin-autoload-bound="true"])');
        var runtime = modalApi();

        if (!modal || !runtime) {
            return;
        }

        if (modal.getAttribute('data-wb-overlay-runtime') === 'true' || modal.classList.contains('is-open')) {
            return;
        }

        runtime.open(modal, null);
    }

    function modalFragmentUrl(link) {
        return link.getAttribute('href') || '';
    }

    function replaceEditorModal(markup, url) {
        var template = document.createElement('template');
        var overlayRoot = document.getElementById('wb-overlay-root');
        var existingModal = document.getElementById('slot-block-editor-modal');
        var fragmentOverlays;
        var modal;

        template.innerHTML = String(markup || '').trim();
        modal = template.content.querySelector('#slot-block-editor-modal');
        fragmentOverlays = template.content.querySelector('[data-wb-slot-block-fragment-overlays]');

        if (!overlayRoot || !modal) {
            throw new Error('The block editor modal fragment is unavailable.');
        }

        if (existingModal) {
            existingModal.remove();
        }

        if (fragmentOverlays && fragmentOverlays.content) {
            Array.prototype.slice.call(fragmentOverlays.content.children).forEach(function (overlay) {
                if (overlay.id && document.getElementById(overlay.id)) {
                    return;
                }

                overlayRoot.appendChild(overlay);
            });
        }

        overlayRoot.appendChild(modal);

        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', url);
        }

        if (window.WebBlocksCmsAdmin && typeof window.WebBlocksCmsAdmin.initializeDynamicContent === 'function') {
            window.WebBlocksCmsAdmin.initializeDynamicContent(modal);
        } else if (modalApi()) {
            modalApi().open(modal, null);
        }
    }

    function loadEditorModal(link) {
        var url = modalFragmentUrl(link);

        if (!url || typeof window.fetch !== 'function') {
            return;
        }

        link.setAttribute('aria-busy', 'true');

        window.fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-WebBlocks-Modal-Fragment': 'slot-block-editor'
            },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('The block editor modal could not be loaded.');
            }

            return response.text();
        }).then(function (markup) {
            replaceEditorModal(markup, url);
        }).catch(function () {
            window.location.assign(url);
        }).finally(function () {
            link.removeAttribute('aria-busy');
        });
    }

    openAutoloadModal();

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-wb-slot-block-link]');
        var url;

        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        url = modalFragmentUrl(link);

        if (!url || (url.indexOf('edit=') === -1 && url.indexOf('block_type_id=') === -1)) {
            return;
        }

        event.preventDefault();
        loadEditorModal(link);
    });

    document.addEventListener('wb:tabs:change', function (event) {
        var container = event.target;

        if (container && container.matches('[data-wb-slot-block-picker-tabs]')) {
            var pickerTabInput = document.querySelector('[data-wb-slot-block-picker-tab-input]');
            if (pickerTabInput && event.detail && event.detail.tabId) {
                pickerTabInput.value = event.detail.tabId.replace('slot-block-picker-panel-', '');
            }

            return;
        }

        if (!container || !container.matches('[data-wb-slot-block-tabs]')) {
            return;
        }

        var hiddenInput = container.querySelector('[data-wb-slot-block-tab-input]');

        if (hiddenInput && event.detail && event.detail.tabId) {
            hiddenInput.value = event.detail.tabId === 'slot-block-info-panel' ? 'block-info' : 'block-fields';
        }
    });
}());
