(function () {
    if (!document.querySelector('[data-wb-asset-picker-panel]')) {
        return;
    }

    var admin = window.WebBlocksCmsAdmin || {};
    var escapeHtml = admin.escapeHtml || function (value) {
        return String(value || '');
    };

    function parseAssetPayload(value) {
        try {
            return JSON.parse(value || '{}');
        } catch (error) {
            return {};
        }
    }

    function setSelectionButtonState(button, isSelected) {
        if (!button) {
            return;
        }

        button.textContent = isSelected ? 'Selected' : 'Select';
        button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        button.classList.toggle('wb-btn-primary', isSelected);
        button.classList.toggle('wb-btn-secondary', !isSelected);
    }

    function setAssetCardSelectionState(card, isSelected) {
        if (!card) {
            return;
        }

        card.classList.toggle('is-selected', isSelected);
        card.setAttribute('data-wb-asset-selected', isSelected ? 'true' : 'false');
    }

    function pickerContext(root) {
        if (!root) {
            return null;
        }

        return pickerActiveRuntimePanel(root) || root;
    }

    function setPickerError(root, message) {
        var context = pickerContext(root);

        if (!context) {
            return;
        }

        var grid = context.querySelector('[data-wb-picker-grid]');
        var emptyState = context.querySelector('[data-wb-picker-empty]');
        var errorState = context.querySelector('[data-wb-picker-error]');
        var errorText = context.querySelector('[data-wb-picker-error-text]');

        if (errorText && message) {
            errorText.textContent = message;
        }

        if (errorState) {
            errorState.hidden = !message;
        }

        if (grid) {
            grid.hidden = !!message;
        }

        if (emptyState && message) {
            emptyState.hidden = true;
        }
    }

    function updatePickerSummary(root) {
        if (!root) {
            return;
        }

        var summary = root.querySelector('[data-wb-picker-summary]');
        var openButton = root.querySelector('[data-wb-picker-open]');
        var clearButton = root.querySelector('[data-wb-picker-clear]');
        var mode = root.getAttribute('data-wb-picker-mode');
        var buttonLabel = root.getAttribute('data-wb-picker-button-label') || 'Choose from Media';
        var replaceLabel = root.getAttribute('data-wb-picker-replace-label') || 'Replace';
        var inputs = Array.prototype.slice.call(root.querySelectorAll('[data-wb-picker-selected-input]'));

        if (mode === 'multiple') {
            var previews = Array.prototype.slice.call(root.querySelectorAll('[data-wb-picker-preview]'));
            var labels = previews.map(function (preview) {
                var title = preview.querySelector('strong');
                return title ? title.textContent.trim() : '';
            }).filter(Boolean);

            if (summary) {
                if (inputs.length === 0) {
                    summary.innerHTML = '<strong>No assets selected</strong><div class="wb-text-sm wb-text-muted">Choose internal assets from the shared media library.</div>';
                } else {
                    summary.innerHTML = '<strong>' + inputs.length + ' assets selected</strong><div class="wb-text-sm wb-text-muted">' + escapeHtml(labels.join(', ')) + '</div>';
                }
            }

            if (openButton) {
                openButton.textContent = inputs.length === 0 ? buttonLabel : replaceLabel;
            }

            if (clearButton) {
                clearButton.disabled = inputs.length === 0;
            }

            return;
        }

        var input = root.querySelector('[data-wb-picker-selected-input]');
        var previewCard = root.querySelector('[data-wb-picker-preview]');

        if (!input || !input.value || !previewCard) {
            if (summary) {
                summary.innerHTML = '<strong>No asset selected</strong><div class="wb-text-sm wb-text-muted">Choose an internal asset from the shared media library.</div>';
            }

            if (openButton) {
                openButton.textContent = buttonLabel;
            }

            if (clearButton) {
                clearButton.disabled = true;
            }

            return;
        }

        var titleElement = previewCard.querySelector('strong');
        var metaElement = previewCard.querySelector('[data-wb-picker-preview-meta]');
        var image = previewCard.querySelector('img');
        var html = '';

        if (image) {
            html += '<img src="' + escapeHtml(image.getAttribute('src')) + '" alt="' + escapeHtml(image.getAttribute('alt')) + '" width="96" height="64">';
        }

        html += '<strong>' + escapeHtml(titleElement ? titleElement.textContent.trim() : 'Selected asset') + '</strong>';

        if (metaElement) {
            html += '<div class="wb-text-sm wb-text-muted">' + escapeHtml(metaElement.textContent.trim()) + '</div>';
        }

        if (summary) {
            summary.innerHTML = html;
        }

        if (openButton) {
            openButton.textContent = replaceLabel;
        }

        if (clearButton) {
            clearButton.disabled = false;
        }
    }

    function buildSinglePreview(asset) {
        var preview = document.createElement('div');
        preview.className = 'wb-card';
        preview.setAttribute('data-wb-picker-preview', '');
        preview.setAttribute('data-wb-picker-preview-id', String(asset.id || ''));

        var html = '<div class="wb-card-body wb-stack wb-gap-2">';

        if (asset.previewable && asset.url) {
            html += '<img src="' + escapeHtml(asset.url) + '" alt="' + escapeHtml(asset.title || asset.filename || 'Selected asset') + '" width="120" height="84">';
        }

        html += '<strong>' + escapeHtml(asset.title || asset.filename || 'Selected asset') + '</strong>';
        html += '<div class="wb-text-sm wb-text-muted" data-wb-picker-preview-meta>' + escapeHtml([asset.kind, asset.original_name].filter(Boolean).join(' | ')) + '</div>';
        html += '</div>';
        preview.innerHTML = html;

        return preview;
    }

    function buildMultiPreview(asset) {
        var preview = document.createElement('div');
        preview.className = 'wb-card';
        preview.setAttribute('data-wb-picker-preview', '');
        preview.setAttribute('data-wb-picker-preview-id', String(asset.id || ''));

        var html = '<div class="wb-card-body wb-stack wb-gap-2">';

        if (asset.previewable && asset.url) {
            html += '<img src="' + escapeHtml(asset.url) + '" alt="' + escapeHtml(asset.title || asset.filename || 'Selected asset') + '" width="120" height="84">';
        }

        html += '<strong>' + escapeHtml(asset.title || asset.filename || 'Selected asset') + '</strong>';
        html += '<button type="button" class="wb-btn wb-btn-secondary" data-wb-picker-remove-preview data-asset-id="' + escapeHtml(asset.id) + '">Remove</button>';
        html += '</div>';
        preview.innerHTML = html;

        return preview;
    }

    function setSinglePickerSelection(root, asset) {
        if (!root) {
            return;
        }

        var input = root.querySelector('[data-wb-picker-selected-input]');
        var previewGrid = root.querySelector('[data-wb-picker-preview-grid]');

        if (!input || !previewGrid) {
            return;
        }

        input.value = asset && asset.id ? asset.id : '';
        previewGrid.innerHTML = '';

        if (asset && asset.id) {
            previewGrid.appendChild(buildSinglePreview(asset));
        }

        updatePickerSummary(root);
    }

    function clearSinglePickerSelection(root) {
        if (!root) {
            return;
        }

        var input = root.querySelector('[data-wb-picker-selected-input]');
        var previewGrid = root.querySelector('[data-wb-picker-preview-grid]');

        if (input) {
            input.value = '';
        }

        if (previewGrid) {
            previewGrid.innerHTML = '';
        }

        updatePickerSummary(root);
    }

    function appendMultiSelection(root, asset) {
        if (!root) {
            return;
        }

        var selectedList = root.querySelector('[data-wb-picker-selected-list]');
        var previewGrid = root.querySelector('[data-wb-picker-preview-grid]');
        var existing = selectedList ? selectedList.querySelector('[value="' + String(asset.id) + '"]') : null;
        var fieldName = root.getAttribute('data-wb-picker-field-name') || 'gallery_asset_ids';

        if (!selectedList || !previewGrid || existing) {
            return;
        }

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = fieldName + '[]';
        input.value = String(asset.id);
        input.setAttribute('data-wb-picker-selected-input', '');
        selectedList.appendChild(input);
        previewGrid.appendChild(buildMultiPreview(asset));
        updatePickerSummary(root);
        document.dispatchEvent(new CustomEvent('wb:asset-picker-selection-added', {
            detail: { root: root, asset: asset }
        }));
    }

    function removeMultiSelection(root, assetId) {
        if (!root) {
            return;
        }

        root.querySelectorAll('[data-wb-picker-selected-input]').forEach(function (input) {
            if (input.value === String(assetId)) {
                input.remove();
            }
        });

        root.querySelectorAll('[data-wb-picker-preview]').forEach(function (preview) {
            if (preview.getAttribute('data-wb-picker-preview-id') === String(assetId)) {
                preview.remove();
            }
        });

        (pickerContext(root) || root).querySelectorAll('[data-wb-asset-toggle]').forEach(function (button) {
            var asset = parseAssetPayload(button.getAttribute('data-wb-asset'));

            if (String(asset.id) === String(assetId)) {
                setSelectionButtonState(button, false);
                setAssetCardSelectionState(button.closest('[data-wb-asset-card]'), false);
            }
        });

        updatePickerSummary(root);
        document.dispatchEvent(new CustomEvent('wb:asset-picker-selection-removed', {
            detail: { root: root, assetId: assetId }
        }));
    }

    function filterPickerAssets(root) {
        var context = pickerContext(root);

        if (!root || !context) {
            return;
        }

        setPickerError(root, '');

        var searchValue = String((context.querySelector('[data-wb-picker-search]') || {}).value || '').toLowerCase().trim();
        var folderValue = String((context.querySelector('[data-wb-picker-folder]') || {}).value || '');
        var kindValue = String((context.querySelector('[data-wb-picker-kind]') || {}).value || '');
        var visibleCount = 0;

        context.querySelectorAll('[data-wb-asset-card]').forEach(function (card) {
            var text = String(card.getAttribute('data-wb-asset-search') || '');
            var folderId = String(card.getAttribute('data-wb-asset-folder-id') || '');
            var kind = String(card.getAttribute('data-wb-asset-kind') || '');
            var matchesSearch = searchValue === '' || text.indexOf(searchValue) !== -1;
            var matchesFolder = folderValue === '' || folderId === folderValue;
            var matchesKind = kindValue === '' || kind === kindValue;
            var visible = matchesSearch && matchesFolder && matchesKind;

            card.hidden = !visible;

            if (visible) {
                visibleCount += 1;
            }
        });

        var emptyState = context.querySelector('[data-wb-picker-empty]');
        var grid = context.querySelector('[data-wb-picker-grid]');

        if (emptyState) {
            emptyState.hidden = visibleCount !== 0;
        }

        if (grid) {
            grid.hidden = false;
        }
    }

    function pickerPanelMode(root) {
        return root ? (root.getAttribute('data-wb-picker-panel-mode') || 'inline') : 'inline';
    }

    function pickerOwnerId(root) {
        return root ? String(root.getAttribute('data-wb-picker-owner-id') || '') : '';
    }

    function pickerPanelElement(root) {
        if (!root) {
            return null;
        }

        var ownerId = pickerOwnerId(root);

        if (pickerPanelMode(root) === 'overlay' && ownerId !== '') {
            return document.querySelector('[data-wb-picker-panel][data-wb-picker-owner-id="' + ownerId + '"]');
        }

        return root.querySelector('[data-wb-picker-panel]');
    }

    function pickerActiveRuntimePanel(root) {
        var panel = pickerPanelElement(root);

        if (!panel) {
            return null;
        }

        if (panel.getAttribute('data-wb-overlay-runtime') === 'true') {
            return panel;
        }

        var ownerId = pickerOwnerId(root);

        if (ownerId === '') {
            return panel;
        }

        return document.querySelector('[data-wb-picker-panel][data-wb-picker-owner-id="' + ownerId + '"][data-wb-overlay-runtime="true"]') || panel;
    }

    function pickerModalElement(root) {
        var panel = pickerActiveRuntimePanel(root);

        return panel && panel.matches('.wb-modal') ? panel : (panel ? panel.querySelector('.wb-modal') : null);
    }

    function pickerRootFromChild(element) {
        var pickerRoot = element ? element.closest('[data-wb-asset-picker-panel]') : null;

        if (pickerRoot) {
            return pickerRoot;
        }

        var ownerId = element ? String(element.getAttribute('data-wb-picker-owner-id') || '') : '';

        if (ownerId === '' && element) {
            var panel = element.closest('[data-wb-picker-panel]');
            ownerId = panel ? String(panel.getAttribute('data-wb-picker-owner-id') || '') : '';
        }

        return ownerId === '' ? null : document.getElementById(ownerId);
    }

    function modalApi() {
        return window.WBModal || null;
    }

    function setPickerPanelOpen(root, isOpen) {
        if (!root) {
            return;
        }

        var panel = pickerPanelElement(root);
        var openButton = root.querySelector('[data-wb-picker-open]');
        var modal = pickerModalElement(root);
        var modalRuntime = modalApi();

        if (!panel) {
            return;
        }

        if (pickerPanelMode(root) === 'overlay' && modal && modalRuntime) {
            if (isOpen) {
                modalRuntime.open(modal, openButton || null);
            } else {
                modalRuntime.close(modal);
            }
        } else {
            panel.hidden = !isOpen;

            if (modal) {
                modal.classList.toggle('is-open', isOpen);
            }
        }

        if (openButton) {
            openButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        if (!isOpen) {
            if (openButton) {
                window.setTimeout(function () {
                    openButton.focus();
                }, 0);
            }

            return;
        }

        window.setTimeout(function () {
            var focusTarget = panel.querySelector('[data-wb-picker-search], button, input:not([type="hidden"]), select, textarea, a[href]');

            if (focusTarget) {
                focusTarget.focus();
            }
        }, 0);
    }

    function closePickerPanel(root) {
        setPickerPanelOpen(root, false);
    }

    function openPickerPanel(root) {
        setPickerPanelOpen(root, true);
        filterPickerAssets(root);
    }

    function resetPickerSelection(root) {
        if (!root) {
            return;
        }

        var mode = root.getAttribute('data-wb-picker-mode');

        if (mode === 'multiple') {
            root.querySelectorAll('[data-wb-picker-selected-input]').forEach(function (input) {
                input.remove();
            });

            var previewGrid = root.querySelector('[data-wb-picker-preview-grid]');

            if (previewGrid) {
                previewGrid.innerHTML = '';
            }

            (pickerContext(root) || root).querySelectorAll('[data-wb-asset-toggle]').forEach(function (button) {
                setSelectionButtonState(button, false);
                setAssetCardSelectionState(button.closest('[data-wb-asset-card]'), false);
            });
            updatePickerSummary(root);
            document.dispatchEvent(new CustomEvent('wb:asset-picker-selection-reset', {
                detail: { root: root }
            }));
            return;
        }

        clearSinglePickerSelection(root);
    }

    function initializePicker(root) {
        updatePickerSummary(root);
        filterPickerAssets(root);
        closePickerPanel(root);

        if (root.getAttribute('data-wb-picker-mode') === 'multiple') {
            var selectedIds = Array.prototype.slice.call(root.querySelectorAll('[data-wb-picker-selected-input]')).map(function (input) {
                return input.value;
            });

            (pickerContext(root) || root).querySelectorAll('[data-wb-asset-toggle]').forEach(function (button) {
                var asset = parseAssetPayload(button.getAttribute('data-wb-asset'));
                var isSelected = selectedIds.indexOf(String(asset.id)) !== -1;

                setSelectionButtonState(button, isSelected);
                setAssetCardSelectionState(button.closest('[data-wb-asset-card]'), isSelected);
            });
        }
    }

    document.querySelectorAll('[data-wb-asset-picker-panel]').forEach(initializePicker);

    document.addEventListener('click', function (event) {
        var openButton = event.target.closest('[data-wb-picker-open]');
        var closeButton = event.target.closest('[data-wb-picker-close]');
        var clearButton = event.target.closest('[data-wb-picker-clear]');
        var selectButton = event.target.closest('[data-wb-asset-select]');
        var toggleButton = event.target.closest('[data-wb-asset-toggle]');
        var removePreviewButton = event.target.closest('[data-wb-picker-remove-preview]');
        var applyButton = event.target.closest('[data-wb-picker-apply]');
        var uploadButton = event.target.closest('[data-wb-picker-upload-submit]');

        if (openButton) {
            openPickerPanel(pickerRootFromChild(openButton));
            return;
        }

        if (closeButton) {
            closePickerPanel(pickerRootFromChild(closeButton));
            return;
        }

        if (clearButton) {
            resetPickerSelection(pickerRootFromChild(clearButton));
            return;
        }

        if (selectButton) {
            var pickerRoot = pickerRootFromChild(selectButton);
            var asset = parseAssetPayload(selectButton.getAttribute('data-wb-asset'));

            setSinglePickerSelection(pickerRoot, asset);
            closePickerPanel(pickerRoot);
            return;
        }

        if (toggleButton) {
            var multiRoot = pickerRootFromChild(toggleButton);
            var multiAsset = parseAssetPayload(toggleButton.getAttribute('data-wb-asset'));
            var isSelected = toggleButton.textContent.trim() === 'Selected';

            if (isSelected) {
                removeMultiSelection(multiRoot, multiAsset.id);
                setSelectionButtonState(toggleButton, false);
                setAssetCardSelectionState(toggleButton.closest('[data-wb-asset-card]'), false);
            } else {
                appendMultiSelection(multiRoot, multiAsset);
                setSelectionButtonState(toggleButton, true);
                setAssetCardSelectionState(toggleButton.closest('[data-wb-asset-card]'), true);
            }

            return;
        }

        if (removePreviewButton) {
            removeMultiSelection(pickerRootFromChild(removePreviewButton), removePreviewButton.getAttribute('data-asset-id'));
            return;
        }

        if (applyButton) {
            closePickerPanel(pickerRootFromChild(applyButton));
            return;
        }

        if (!uploadButton) {
            return;
        }

        var uploadRoot = pickerRootFromChild(uploadButton);
        var fileInput = uploadRoot ? uploadRoot.querySelector('[data-wb-picker-upload-input]') : null;
        var titleInput = uploadRoot ? uploadRoot.querySelector('[data-wb-picker-upload-title]') : null;
        var status = uploadRoot ? uploadRoot.querySelector('[data-wb-picker-upload-status]') : null;

        if (!uploadRoot || !fileInput || !fileInput.files || !fileInput.files[0]) {
            if (status) {
                status.textContent = 'Choose a file before uploading.';
            }
            return;
        }

        var formData = new FormData();
        formData.append('file', fileInput.files[0]);

        if (titleInput && titleInput.value.trim() !== '') {
            formData.append('title', titleInput.value.trim());
        }

        var token = document.querySelector('meta[name="csrf-token"]');

        fetch('/admin/media', {
            method: 'POST',
            headers: token ? { 'X-CSRF-TOKEN': token.getAttribute('content') } : {},
            body: formData,
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (response.redirected) {
                    if (admin.redirectToLoginFromAdmin) {
                        admin.redirectToLoginFromAdmin();
                    }
                    return;
                }

                if (response.status === 401 || response.status === 403 || response.status === 419) {
                    if (admin.redirectToLoginFromAdmin) {
                        admin.redirectToLoginFromAdmin();
                    }
                    return;
                }

                if (!response.ok) {
                    throw new Error('Upload failed');
                }

                window.location.reload();
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Upload failed. Refresh and try again.';
                }
            });
    });

    document.addEventListener('input', function (event) {
        var pickerSearch = event.target.closest('[data-wb-picker-search]');

        if (pickerSearch) {
            filterPickerAssets(pickerRootFromChild(pickerSearch));
        }
    });

    document.addEventListener('change', function (event) {
        var pickerFolder = event.target.closest('[data-wb-picker-folder]');
        var pickerKind = event.target.closest('[data-wb-picker-kind]');

        if (pickerFolder || pickerKind) {
            filterPickerAssets(pickerRootFromChild(event.target));
            return;
        }

        var uploadInput = event.target.closest('[data-wb-picker-upload-input]');

        if (!uploadInput) {
            return;
        }

        var pickerRoot = pickerRootFromChild(uploadInput);
        var status = pickerRoot ? pickerRoot.querySelector('[data-wb-picker-upload-status]') : null;

        if (status) {
            status.textContent = uploadInput.files && uploadInput.files[0]
                ? uploadInput.files[0].name + ' ready to upload.'
                : 'Select a file to upload it to the shared media library.';
        }
    });

    document.addEventListener('wb:modal:close', function (event) {
        var modal = event.target.closest('.wb-modal');
        var panel = modal && modal.matches('[data-wb-picker-panel][data-wb-picker-panel-mode="overlay"]')
            ? modal
            : (modal ? modal.closest('[data-wb-picker-panel][data-wb-picker-panel-mode="overlay"]') : null);
        var root = pickerRootFromChild(panel);
        var openButton = root ? root.querySelector('[data-wb-picker-open]') : null;

        if (!panel || !root) {
            return;
        }

        panel.hidden = true;

        if (openButton) {
            openButton.setAttribute('aria-expanded', 'false');
        }
    });

    window.addEventListener('error', function (event) {
        var target = event.target;

        if (!target || target.tagName !== 'IMG') {
            return;
        }

        var card = target.closest('[data-wb-asset-card]');
        var root = pickerRootFromChild(card);
        var context = pickerContext(root);

        if (!card || !root || !context || card.hidden) {
            return;
        }

        var otherVisibleImages = Array.prototype.slice.call(context.querySelectorAll('[data-wb-asset-card] img')).filter(function (image) {
            return image !== target && !image.closest('[data-wb-asset-card]').hidden;
        });

        if (otherVisibleImages.length === 0) {
            setPickerError(root, 'Media rows could not be rendered. Refresh the page and try again.');
        }
    }, true);
}());
