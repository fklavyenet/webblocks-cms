(function () {
    if (!document.querySelector('[data-wb-gallery-items-editor]')) {
        return;
    }

    var admin = window.WebBlocksCmsAdmin || {};
    var escapeHtml = admin.escapeHtml || function (value) {
        return String(value || '');
    };

    function editorRootFromPicker(pickerRoot) {
        return pickerRoot ? pickerRoot.closest('[data-wb-gallery-items-editor]') : null;
    }

    function itemFieldName(prefix, index, field) {
        if (prefix) {
            return prefix + '[gallery_items][' + index + '][' + field + ']';
        }

        return 'gallery_items[' + index + '][' + field + ']';
    }

    function syncEditor(editor) {
        if (!editor) {
            return;
        }

        var rows = Array.prototype.slice.call(editor.querySelectorAll('[data-wb-gallery-item-row]')).filter(function (row) {
            return !row.hidden;
        });
        var prefix = String(editor.getAttribute('data-wb-gallery-field-prefix') || '');

        rows.forEach(function (row, index) {
            var mediaId = String(row.getAttribute('data-media-id') || '');
            var inputs = row.querySelectorAll('[data-wb-gallery-field]');
            var orderInput = row.querySelector('[data-admin-sortable-order]');
            var indexLabel = row.querySelector('[data-wb-gallery-item-index]');
            var updatableNames = {
                media_id: itemFieldName(prefix, index, 'media_id'),
                sort_order: itemFieldName(prefix, index, 'sort_order'),
                alt_text: itemFieldName(prefix, index, 'alt_text'),
                caption: itemFieldName(prefix, index, 'caption'),
                overlay_title: itemFieldName(prefix, index, 'overlay_title'),
                overlay_text: itemFieldName(prefix, index, 'overlay_text')
            };

            Array.prototype.slice.call(inputs).forEach(function (input) {
                var field = input.getAttribute('data-wb-gallery-field');

                if (field === 'media_id') {
                    input.name = updatableNames.media_id;
                    return;
                }

                if (field === 'sort_order' || input === orderInput) {
                    input.name = updatableNames.sort_order;
                    input.value = String(index);
                    return;
                }

                if (field === 'alt_text') {
                    input.name = updatableNames.alt_text;
                    return;
                }

                if (field === 'caption') {
                    input.name = updatableNames.caption;
                    return;
                }

                if (field === 'overlay_title') {
                    input.name = updatableNames.overlay_title;
                    return;
                }

                if (field === 'overlay_text') {
                    input.name = updatableNames.overlay_text;
                }
            });

            if (indexLabel) {
                indexLabel.textContent = String(index + 1);
            }
        });

        syncEditorCount(editor, rows.length);
        syncEditorStates(editor, rows.length);
    }

    function syncEditorCount(editor, count) {
        if (!editor) {
            return;
        }

        editor.querySelectorAll('[data-wb-gallery-items-count]').forEach(function (badge) {
            badge.textContent = String(count) + ' ' + (count === 1 ? 'item' : 'items');
        });
    }

    function syncEditorStates(editor, count) {
        if (!editor) {
            return;
        }

        var emptyState = editor.querySelector('[data-wb-gallery-items-empty]');
        var tableWrap = editor.querySelector('[data-wb-gallery-items-table]');

        if (emptyState) {
            emptyState.hidden = count !== 0;
        }

        if (tableWrap) {
            tableWrap.hidden = count === 0;
        }
    }

    function modalIdFor(editor, assetId) {
        var prefix = String(editor.getAttribute('data-wb-gallery-field-prefix') || 'gallery');

        return 'gallery-item-modal-' + prefix.replace(/[^A-Za-z0-9_-]+/g, '-') + '-' + String(assetId);
    }

    function modalRoot() {
        return document.getElementById('wb-overlay-root');
    }

    function syncModalFromRow(row) {
        if (!row) {
            return;
        }

        var editor = row.closest('[data-wb-gallery-items-editor]');
        var mediaId = row.getAttribute('data-media-id');
        var modal = document.getElementById(modalIdFor(editor, mediaId));

        if (!modal) {
            return;
        }

        Array.prototype.slice.call(modal.querySelectorAll('[data-wb-gallery-modal-field]')).forEach(function (field) {
            var source = row.querySelector('[data-wb-gallery-field="' + field.getAttribute('data-wb-gallery-modal-field') + '"]');

            if (source) {
                field.value = source.value || '';
            }
        });
    }

    function bindModalFieldSync(modal, row) {
        if (!modal || !row) {
            return;
        }

        Array.prototype.slice.call(modal.querySelectorAll('[data-wb-gallery-modal-field]')).forEach(function (field) {
            field.addEventListener('input', function () {
                var key = field.getAttribute('data-wb-gallery-modal-field');
                var hiddenField = row.querySelector('[data-wb-gallery-field="' + key + '"]');
                var summarySelector = key === 'alt_text'
                    ? '[data-wb-gallery-alt-summary]'
                    : (key === 'caption'
                        ? '[data-wb-gallery-caption-summary]'
                        : ((key === 'overlay_title' || key === 'overlay_text') ? '[data-wb-gallery-overlay-summary]' : null));

                if (hiddenField) {
                    hiddenField.value = field.value;
                }

                if (summarySelector) {
                    var summary = row.querySelector(summarySelector);

                    if (summary) {
                        if (key === 'overlay_title' || key === 'overlay_text') {
                            var overlayTitleField = row.querySelector('[data-wb-gallery-field="overlay_title"]');
                            var overlayTextField = row.querySelector('[data-wb-gallery-field="overlay_text"]');
                            summary.textContent = ((overlayTitleField && overlayTitleField.value.trim()) || (overlayTextField && overlayTextField.value.trim()) || 'No overlay title');
                            return;
                        }

                        summary.textContent = field.value.trim() || (key === 'alt_text'
                            ? 'No alt text'
                            : 'No caption');
                    }
                }
            });
        });
    }

    function bindExistingRowModal(editor, row) {
        if (!editor || !row) {
            return;
        }

        var mediaId = row.getAttribute('data-media-id');
        var modal = document.getElementById(modalIdFor(editor, mediaId));

        if (!modal || modal.getAttribute('data-wb-gallery-modal-bound') === 'true') {
            syncModalFromRow(row);
            return;
        }

        bindModalFieldSync(modal, row);
        modal.setAttribute('data-wb-gallery-modal-bound', 'true');
        syncModalFromRow(row);
    }

    function appendModal(editor, asset, row) {
        var template = editor.querySelector('[data-wb-gallery-item-modal-template]');
        var overlay = modalRoot();

        if (!template || !overlay || !asset || !asset.id || document.getElementById(modalIdFor(editor, asset.id))) {
            syncModalFromRow(row);
            return;
        }

        var modalId = modalIdFor(editor, asset.id);
        var wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML
            .replaceAll('__MODAL_ID__', escapeHtml(modalId))
            .replaceAll('__MEDIA_ID__', escapeHtml(asset.id))
            .replaceAll('__ITEM_LABEL__', escapeHtml(asset.title || asset.filename || 'Selected image'))
            .trim();

        if (!wrapper.firstElementChild) {
            return;
        }

        overlay.appendChild(wrapper.firstElementChild);
        syncModalFromRow(row);
        bindModalFieldSync(wrapper.firstElementChild, row);
    }

    function buildRowHtml(editor, asset, index) {
        var template = editor.querySelector('[data-wb-gallery-item-template]');
        var prefix = String(editor.getAttribute('data-wb-gallery-field-prefix') || '');

        if (!template) {
            return '';
        }

        var previewHtml = asset.previewable && asset.url
            ? '<img src="' + escapeHtml(asset.url) + '" alt="' + escapeHtml(asset.title || asset.filename || 'Selected image') + '" width="72" height="48">'
            : '<span class="wb-text-sm wb-text-muted">No preview</span>';
        var modalId = 'gallery-item-modal-' + escapeHtml(prefix.replace(/[^A-Za-z0-9_-]+/g, '-')) + '-' + escapeHtml(asset.id);

        return template.innerHTML
            .replaceAll('__MEDIA_ID__', escapeHtml(asset.id))
            .replaceAll('__INDEX_LABEL__', escapeHtml(index + 1))
            .replaceAll('__SORT_VALUE__', escapeHtml(index))
            .replaceAll('__MEDIA_NAME__', escapeHtml(itemFieldName(prefix, index, 'media_id')))
            .replaceAll('__SORT_NAME__', escapeHtml(itemFieldName(prefix, index, 'sort_order')))
            .replaceAll('__ALT_NAME__', escapeHtml(itemFieldName(prefix, index, 'alt_text')))
            .replaceAll('__CAPTION_NAME__', escapeHtml(itemFieldName(prefix, index, 'caption')))
            .replaceAll('__OVERLAY_TITLE_NAME__', escapeHtml(itemFieldName(prefix, index, 'overlay_title')))
            .replaceAll('__OVERLAY_TEXT_NAME__', escapeHtml(itemFieldName(prefix, index, 'overlay_text')))
            .replaceAll('__PREVIEW_HTML__', previewHtml)
            .replaceAll('__ITEM_LABEL__', escapeHtml(asset.title || asset.filename || 'Selected image'))
            .replaceAll('__ITEM_META__', escapeHtml([asset.kind, asset.original_name].filter(Boolean).join(' | ')))
            .replaceAll('__MODAL_ID__', modalId);
    }

    function appendRow(editor, asset) {
        if (!editor || !asset || !asset.id) {
            return;
        }

        var list = editor.querySelector('[data-wb-gallery-items-list]');

        if (!list || list.querySelector('[data-media-id="' + String(asset.id) + '"]')) {
            return;
        }

        var rowCount = list.querySelectorAll('[data-wb-gallery-item-row]').length;
        var wrapper = document.createElement('tbody');
        wrapper.innerHTML = buildRowHtml(editor, asset, rowCount).trim();

        if (wrapper.firstElementChild) {
            list.appendChild(wrapper.firstElementChild);
            appendModal(editor, asset, wrapper.firstElementChild);
            syncEditor(editor);

            if (window.WebBlocksAdminSortableList && typeof window.WebBlocksAdminSortableList.init === 'function') {
                window.WebBlocksAdminSortableList.init(editor);
            }
        }
    }

    function removeRow(editor, assetId) {
        if (!editor) {
            return;
        }

        var row = editor.querySelector('[data-wb-gallery-item-row][data-media-id="' + String(assetId) + '"]');

        if (row) {
            var modal = document.getElementById(modalIdFor(editor, assetId));

            if (modal) {
                modal.remove();
            }

            row.remove();
            syncEditor(editor);
        }
    }

    document.querySelectorAll('[data-wb-gallery-items-editor]').forEach(function (editor) {
        syncEditor(editor);
        Array.prototype.slice.call(editor.querySelectorAll('[data-wb-gallery-item-row]')).forEach(function (row) {
            bindExistingRowModal(editor, row);
        });
        syncEditor(editor);
    });

    document.addEventListener('admin-sortable-list:reordered', function (event) {
        var editor = event.target.closest('[data-wb-gallery-items-editor]');

        if (editor) {
            syncEditor(editor);
        }
    });

    document.addEventListener('click', function (event) {
        var removeButton = event.target.closest('[data-wb-gallery-item-remove]');

        if (!removeButton) {
            return;
        }

        removeRow(removeButton.closest('[data-wb-gallery-items-editor]'), removeButton.getAttribute('data-asset-id'));
    });

    document.addEventListener('wb:asset-picker-selection-added', function (event) {
        var detail = event.detail || {};
        appendRow(editorRootFromPicker(detail.root), detail.asset || {});
    });

    document.addEventListener('wb:asset-picker-selection-removed', function (event) {
        var detail = event.detail || {};
        removeRow(editorRootFromPicker(detail.root), detail.assetId);
    });

    document.addEventListener('wb:asset-picker-selection-reset', function (event) {
        var detail = event.detail || {};
        var editor = editorRootFromPicker(detail.root);

        if (!editor) {
            return;
        }

        editor.querySelectorAll('[data-wb-gallery-item-row]').forEach(function (row) {
            var modal = document.getElementById(modalIdFor(editor, row.getAttribute('data-media-id')));

            if (modal) {
                modal.remove();
            }

            row.remove();
        });
        syncEditor(editor);
    });
}());
