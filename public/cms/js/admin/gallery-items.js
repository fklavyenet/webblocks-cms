(function () {
    function i18n(key) { return document.body.getAttribute('data-wb-i18n-' + key) || ''; }
    var admin = window.WebBlocksCmsAdmin || {};
    var physicalFields = ['physical_width_um', 'physical_height_um'];
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

    function galleryPeer(source, selector, key) {
        var scope = source.closest('form') || document;
        return Array.from(scope.querySelectorAll(selector)).find(function (candidate) {
            return candidate.getAttribute(selector.slice(1, -1)) === key;
        });
    }

    function galleryPreviewItem(row, captionMode, physical) {
        var thumbnail = row.querySelector('[data-wb-gallery-preview-image]');
        if (!thumbnail) return null;

        function field(name) {
            var input = row.querySelector('[data-wb-gallery-field="' + name + '"]');
            return input ? input.value.trim() : '';
        }

        var figure = document.createElement('figure');
        var image = document.createElement('img');
        var overlay = captionMode === 'overlay' || captionMode === 'on-hover';
        figure.className = 'wb-gallery-item';
        figure.dataset.wbGalleryPhysicalWidth = field('physical_width_um');
        figure.dataset.wbGalleryPhysicalHeight = field('physical_height_um');
        image.className = 'wb-gallery-media';
        image.src = thumbnail.dataset.galleryPreviewUrl || thumbnail.src;
        image.loading = 'lazy';
        image.alt = field('alt_text') || thumbnail.alt;
        if (Number(thumbnail.dataset.width) > 0) image.width = Number(thumbnail.dataset.width);
        if (Number(thumbnail.dataset.height) > 0) image.height = Number(thumbnail.dataset.height);

        var media = figure;
        if (physical || overlay) {
            media = document.createElement('div');
            media.className = 'wb-gallery-trigger';
            figure.append(media);
        }
        media.append(image);

        var caption = overlay ? field('overlay_title') || field('caption') : field('caption');
        var meta = overlay ? field('overlay_text') : '';
        if (captionMode !== 'hidden' && (caption || meta)) {
            var captionElement = document.createElement(overlay ? 'div' : 'figcaption');
            captionElement.className = 'wb-gallery-caption';
            captionElement.textContent = caption;
            if (meta) {
                var metaElement = document.createElement('span');
                metaElement.className = 'wb-gallery-meta';
                metaElement.textContent = meta;
                captionElement.append(metaElement);
            }
            (overlay ? media : figure).append(captionElement);
        }
        return figure;
    }

    function renderMeasurements(settings, rows, active) {
        var table = settings.querySelector('[data-cms-gallery-measurements]');
        var body = settings.querySelector('[data-cms-gallery-measurement-rows]');
        table.hidden = !active || rows.length === 0;
        body.replaceChildren();
        if (table.hidden) return;

        var unit = settings.querySelector('[data-cms-gallery-unit]').selectedOptions[0];
        var factor = Number(unit.dataset.factor);
        rows.forEach(function (row, index) {
            var title = row.querySelector('[data-wb-gallery-item-title]')?.textContent.trim() || String(index + 1);
            var line = body.insertRow();
            var heading = document.createElement('th');
            heading.scope = 'row';
            heading.textContent = title;
            line.append(heading);
            physicalFields.forEach(function (field) {
                var source = row.querySelector('[data-wb-gallery-field="' + field + '"]');
                if (!source) return;
                var label = table.querySelector('[data-cms-gallery-dimension-label="' + field + '"]').textContent.trim();
                var input = document.createElement('input');
                input.type = 'number';
                input.className = 'wb-input';
                input.step = 'any';
                input.min = 1 / factor;
                input.max = Number(body.dataset.max) / factor;
                input.setAttribute('data-cms-gallery-measurement-input', '');
                input.value = source.value ? Number((Number(source.value) / factor).toPrecision(12)) : '';
                input.setAttribute('aria-label', title + ' — ' + label + ' (' + unit.value + ')');
                input.addEventListener('input', function () {
                    source.value = input.value && input.validity.valid ? Math.round(input.valueAsNumber * factor) : '';
                    renderGalleryPreview(settings, row.closest('[data-wb-gallery-items-editor]'), true);
                });
                line.insertCell().append(input);
            });
        });
    }

    function renderGalleryPreview(settings, editor, keepMeasurements) {
        var find = function (selector) { return settings.querySelector(selector); };
        var variant = find('[data-cms-gallery-variant]:checked').value;
        var columns = find('[data-cms-gallery-columns]').value;
        var aspect = find('[data-cms-gallery-aspect]').value;
        var gap = find('[data-cms-gallery-gap]').value;
        var captions = find('[data-cms-gallery-captions]').value;
        var overlay = find('[data-cms-gallery-overlay]').value;
        var gallery = find('[data-cms-gallery-preview]');
        var frame = find('[data-cms-gallery-preview-frame]');
        var weighted = variant === 'weighted';
        var equalHeight = variant === 'equal-height';
        var physical = variant === 'physical-scale';
        var verticalAlignment = find('[data-cms-gallery-vertical-alignment]');
        var weights = [];
        find('[data-cms-gallery-weights]').hidden = !weighted;
        find('[data-cms-gallery-aspect-field]').hidden = equalHeight || physical;
        find('[data-cms-gallery-columns-field]').hidden = physical;
        find('[data-cms-gallery-physical]').hidden = !physical;
        find('[data-cms-gallery-unit]').disabled = !physical;
        verticalAlignment.disabled = !physical;
        var columnsLabel = find('[data-cms-gallery-columns-label]');
        columnsLabel.textContent = equalHeight ? columnsLabel.dataset.perRow : columnsLabel.dataset.columns;
        settings.querySelectorAll('[data-cms-gallery-weight]').forEach(function (label, index) {
            label.hidden = index >= Number(columns);
            var input = label.querySelector('input');
            input.disabled = !weighted || label.hidden;
            if (!input.disabled) weights.push(input.valueAsNumber || 1);
        });

        frame.dataset.width = find('[data-cms-gallery-preview-width]:checked').value;
        gallery.className = 'wb-gallery wb-gallery--cols-' + columns + ' wb-gallery--gap-' + gap +
            ' wb-gallery--aspect-' + aspect.replace(':', '-');
        if (variant !== 'grid') gallery.classList.add('wb-gallery--' + variant);
        if (captions === 'on-hover') gallery.classList.add('wb-gallery--captions-hover');
        if (captions === 'overlay') gallery.classList.add('wb-gallery--captions-overlay');
        if (overlay !== 'gradient') gallery.classList.add('wb-gallery--overlay-' + overlay);
        gallery.dataset.wbGalleryWeights = weights.join(' ');
        gallery.dataset.wbGalleryItemsPerRow = columns;
        if (physical) gallery.dataset.wbGalleryVerticalAlignment = verticalAlignment.value;
        else delete gallery.dataset.wbGalleryVerticalAlignment;

        var rows = editor ? Array.from(editor.querySelectorAll('[data-wb-gallery-item-row]:not([hidden])')) : [];
        if (!keepMeasurements) renderMeasurements(settings, rows, physical);
        var items = rows.map(function (row) { return galleryPreviewItem(row, captions, physical); }).filter(Boolean);
        var missingMeasurements = physical && items.some(function (item) {
            return !(Number(item.dataset.wbGalleryPhysicalWidth) > 0 && Number(item.dataset.wbGalleryPhysicalHeight) > 0);
        });
        var grid = gallery.querySelector('.wb-gallery-grid');
        grid.replaceChildren();
        if (physical && !missingMeasurements) {
            var rowSize = Number(gallery.dataset.physicalRowSize);
            for (var index = 0; index < items.length; index += rowSize) {
                var row = document.createElement('div');
                row.className = 'wb-gallery-row';
                row.append(...items.slice(index, index + rowSize));
                grid.append(row);
            }
        } else if (!physical) grid.append(...items);
        find('[data-cms-gallery-preview-empty]').hidden = items.length > 0;
        find('[data-cms-gallery-preview-missing]').hidden = !missingMeasurements;
        frame.hidden = items.length === 0 || missingMeasurements;
        if (window.WBGallery) window.WBGallery.update(gallery);
    }

    function updateGalleryPreview(editor) {
        var settings = galleryPeer(editor, '[data-cms-gallery-settings]', editor.dataset.wbGalleryFieldPrefix);
        if (settings) renderGalleryPreview(settings, editor);
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
                overlay_text: itemFieldName(prefix, index, 'overlay_text'),
                physical_width_um: itemFieldName(prefix, index, 'physical_width_um'),
                physical_height_um: itemFieldName(prefix, index, 'physical_height_um')
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
                if (physicalFields.includes(field)) input.name = updatableNames[field];
            });

            if (indexLabel) {
                indexLabel.textContent = String(index + 1);
            }
        });

        syncEditorCount(editor, rows.length);
        syncEditorStates(editor, rows.length);
        updateGalleryPreview(editor);
    }

    function syncEditorCount(editor, count) {
        if (!editor) {
            return;
        }

        editor.querySelectorAll('[data-wb-gallery-items-count]').forEach(function (badge) {
            badge.textContent = String(count) + ' ' + (count === 1 ? i18n('item') : i18n('items'));
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
                            summary.textContent = ((overlayTitleField && overlayTitleField.value.trim()) || (overlayTextField && overlayTextField.value.trim()) || i18n('no-overlay-title'));
                            updateGalleryPreview(row.closest('[data-wb-gallery-items-editor]'));
                            return;
                        }

                        summary.textContent = field.value.trim() || (key === 'alt_text'
                            ? i18n('no-alt-text')
                            : i18n('no-caption'));
                    }
                }
                updateGalleryPreview(row.closest('[data-wb-gallery-items-editor]'));
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
            .replaceAll('__ITEM_LABEL__', escapeHtml(asset.title || asset.filename || i18n('selected-image')))
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
            ? '<img src="' + escapeHtml(asset.url) + '" alt="' + escapeHtml(asset.title || asset.filename || i18n('selected-image')) + '" width="72" height="48" data-wb-gallery-preview-image data-gallery-preview-url="' + escapeHtml(asset.gallery_preview_url) + '" data-width="' + (Number(asset.width) || '') + '" data-height="' + (Number(asset.height) || '') + '">'
            : '<span class="wb-text-sm wb-text-muted">No preview</span>';
        var modalId = 'gallery-item-modal-' + escapeHtml(prefix.replace(/[^A-Za-z0-9_-]+/g, '-')) + '-' + escapeHtml(asset.id);
        var itemLabel = String(asset.title ?? '').trim() || asset.filename || i18n('selected-image');

        return template.innerHTML
            .replaceAll('__MEDIA_ID__', escapeHtml(asset.id))
            .replaceAll('__INDEX_LABEL__', escapeHtml(index + 1))
            .replaceAll('__SORT_VALUE__', escapeHtml(index))
            .replaceAll('__MEDIA_NAME__', escapeHtml(itemFieldName(prefix, index, 'media_id')))
            .replaceAll('__PHYSICAL_WIDTH_NAME__', escapeHtml(itemFieldName(prefix, index, 'physical_width_um')))
            .replaceAll('__PHYSICAL_HEIGHT_NAME__', escapeHtml(itemFieldName(prefix, index, 'physical_height_um')))
            .replaceAll('__SORT_NAME__', escapeHtml(itemFieldName(prefix, index, 'sort_order')))
            .replaceAll('__ALT_NAME__', escapeHtml(itemFieldName(prefix, index, 'alt_text')))
            .replaceAll('__CAPTION_NAME__', escapeHtml(itemFieldName(prefix, index, 'caption')))
            .replaceAll('__OVERLAY_TITLE_NAME__', escapeHtml(itemFieldName(prefix, index, 'overlay_title')))
            .replaceAll('__OVERLAY_TEXT_NAME__', escapeHtml(itemFieldName(prefix, index, 'overlay_text')))
            .replaceAll('__PREVIEW_HTML__', previewHtml)
            .replaceAll('__ITEM_LABEL__', escapeHtml(itemLabel))
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

    function initializeEditors(context) {
        var scope = context || document;
        var editors = scope.matches && scope.matches('[data-wb-gallery-items-editor]') ? [scope] : [];

        if (scope.querySelectorAll) {
            editors = editors.concat(Array.prototype.slice.call(scope.querySelectorAll('[data-wb-gallery-items-editor]')));
        }

        editors.forEach(function (editor) {
            syncEditor(editor);
            Array.prototype.slice.call(editor.querySelectorAll('[data-wb-gallery-item-row]')).forEach(function (row) {
                bindExistingRowModal(editor, row);
            });
            syncEditor(editor);
        });
    }

    initializeEditors(document);

    window.WebBlocksCmsAdminGalleryItems = { init: initializeEditors };

    function onGallerySettingsChange(event) {
        var settings = event.target.closest('[data-cms-gallery-settings]');
        if (!settings || event.target.hasAttribute('data-cms-gallery-measurement-input')) return;
        var editor = galleryPeer(settings, '[data-wb-gallery-field-prefix]', settings.dataset.cmsGallerySettings);
        renderGalleryPreview(settings, editor);
    }

    document.addEventListener('change', onGallerySettingsChange);
    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-cms-gallery-weight] input')) onGallerySettingsChange(event);
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
