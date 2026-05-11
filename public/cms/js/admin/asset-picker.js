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

    function updatePickerSummary(root) {
        if (!root) {
            return;
        }

        var summary = root.querySelector('[data-wb-picker-summary]');
        var clearButton = root.querySelector('[data-wb-picker-clear]');
        var mode = root.getAttribute('data-wb-picker-mode');
        var inputs = Array.prototype.slice.call(root.querySelectorAll('[data-wb-picker-selected-input]'));

        if (!summary) {
            return;
        }

        if (mode === 'multiple') {
            var previews = Array.prototype.slice.call(root.querySelectorAll('[data-wb-picker-preview]'));
            var labels = previews.map(function (preview) {
                var title = preview.querySelector('strong');
                return title ? title.textContent.trim() : '';
            }).filter(Boolean);

            if (inputs.length === 0) {
                summary.innerHTML = '<strong>No assets selected</strong><div class="wb-text-sm wb-text-muted">Choose internal assets from the shared media library.</div>';
            } else {
                summary.innerHTML = '<strong>' + inputs.length + ' assets selected</strong><div class="wb-text-sm wb-text-muted">' + escapeHtml(labels.join(', ')) + '</div>';
            }

            if (clearButton) {
                clearButton.disabled = inputs.length === 0;
            }

            return;
        }

        var input = root.querySelector('[data-wb-picker-selected-input]');
        var previewCard = root.querySelector('[data-wb-picker-preview]');

        if (!input || !input.value || !previewCard) {
            summary.innerHTML = '<strong>No asset selected</strong><div class="wb-text-sm wb-text-muted">Choose an internal asset from the shared media library.</div>';

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

        summary.innerHTML = html;

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

        root.querySelectorAll('[data-wb-asset-toggle]').forEach(function (button) {
            var asset = parseAssetPayload(button.getAttribute('data-wb-asset'));

            if (String(asset.id) === String(assetId)) {
                button.textContent = 'Select';
            }
        });

        updatePickerSummary(root);
    }

    function filterPickerAssets(root) {
        if (!root) {
            return;
        }

        var searchValue = String((root.querySelector('[data-wb-picker-search]') || {}).value || '').toLowerCase().trim();
        var folderValue = String((root.querySelector('[data-wb-picker-folder]') || {}).value || '');
        var kindValue = String((root.querySelector('[data-wb-picker-kind]') || {}).value || '');
        var visibleCount = 0;

        root.querySelectorAll('[data-wb-asset-card]').forEach(function (card) {
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

        var emptyState = root.querySelector('[data-wb-picker-empty]');

        if (emptyState) {
            emptyState.hidden = visibleCount !== 0;
        }
    }

    function closePickerPanel(root) {
        var panel = root ? root.querySelector('[data-wb-picker-panel]') : null;

        if (panel) {
            panel.hidden = true;
        }
    }

    function openPickerPanel(root) {
        var panel = root ? root.querySelector('[data-wb-picker-panel]') : null;

        if (panel) {
            panel.hidden = false;
        }

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

            root.querySelectorAll('[data-wb-asset-toggle]').forEach(function (button) {
                button.textContent = 'Select';
            });
            updatePickerSummary(root);
            return;
        }

        clearSinglePickerSelection(root);
    }

    function initializePicker(root) {
        updatePickerSummary(root);
        filterPickerAssets(root);

        if (root.getAttribute('data-wb-picker-mode') === 'multiple') {
            var selectedIds = Array.prototype.slice.call(root.querySelectorAll('[data-wb-picker-selected-input]')).map(function (input) {
                return input.value;
            });

            root.querySelectorAll('[data-wb-asset-toggle]').forEach(function (button) {
                var asset = parseAssetPayload(button.getAttribute('data-wb-asset'));
                button.textContent = selectedIds.indexOf(String(asset.id)) !== -1 ? 'Selected' : 'Select';
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
            openPickerPanel(openButton.closest('[data-wb-asset-picker-panel]'));
            return;
        }

        if (closeButton) {
            closePickerPanel(closeButton.closest('[data-wb-asset-picker-panel]'));
            return;
        }

        if (clearButton) {
            resetPickerSelection(clearButton.closest('[data-wb-asset-picker-panel]'));
            return;
        }

        if (selectButton) {
            var pickerRoot = selectButton.closest('[data-wb-asset-picker-panel]');
            var asset = parseAssetPayload(selectButton.getAttribute('data-wb-asset'));

            setSinglePickerSelection(pickerRoot, asset);
            closePickerPanel(pickerRoot);
            return;
        }

        if (toggleButton) {
            var multiRoot = toggleButton.closest('[data-wb-asset-picker-panel]');
            var multiAsset = parseAssetPayload(toggleButton.getAttribute('data-wb-asset'));
            var isSelected = toggleButton.textContent.trim() === 'Selected';

            if (isSelected) {
                removeMultiSelection(multiRoot, multiAsset.id);
                toggleButton.textContent = 'Select';
            } else {
                appendMultiSelection(multiRoot, multiAsset);
                toggleButton.textContent = 'Selected';
            }

            return;
        }

        if (removePreviewButton) {
            removeMultiSelection(removePreviewButton.closest('[data-wb-asset-picker-panel]'), removePreviewButton.getAttribute('data-asset-id'));
            return;
        }

        if (applyButton) {
            closePickerPanel(applyButton.closest('[data-wb-asset-picker-panel]'));
            return;
        }

        if (!uploadButton) {
            return;
        }

        var uploadRoot = uploadButton.closest('[data-wb-asset-picker-panel]');
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
            filterPickerAssets(pickerSearch.closest('[data-wb-asset-picker-panel]'));
        }
    });

    document.addEventListener('change', function (event) {
        var pickerFolder = event.target.closest('[data-wb-picker-folder]');
        var pickerKind = event.target.closest('[data-wb-picker-kind]');

        if (pickerFolder || pickerKind) {
            filterPickerAssets(event.target.closest('[data-wb-asset-picker-panel]'));
            return;
        }

        var uploadInput = event.target.closest('[data-wb-picker-upload-input]');

        if (!uploadInput) {
            return;
        }

        var pickerRoot = uploadInput.closest('[data-wb-asset-picker-panel]');
        var status = pickerRoot ? pickerRoot.querySelector('[data-wb-picker-upload-status]') : null;

        if (status) {
            status.textContent = uploadInput.files && uploadInput.files[0]
                ? uploadInput.files[0].name + ' ready to upload.'
                : 'Select a file to upload it to the shared media library.';
        }
    });
}());
