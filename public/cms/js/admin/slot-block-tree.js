(function () {
    function i18n(key) { return document.body.getAttribute('data-wb-i18n-' + key) || ''; }
    var treeSelector = '[data-wb-cms-slot-block-tree][data-page-id][data-slot-type-id]';
    var storagePrefix = 'webblocks.cms.slotBlocks.expanded';

    function treeRoots() {
        return Array.prototype.slice.call(document.querySelectorAll(treeSelector));
    }

    function rootRows(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-slot-block-row][data-block-id], [data-wb-slot-block-row][data-wb-slot-block-id]'));
    }

    function rootToggles(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-slot-block-toggle][data-slot-toggle], [data-wb-slot-block-toggle][data-wb-slot-toggle]'));
    }

    function storageKey(root) {
        var pageId = root.getAttribute('data-page-id') || '';
        var slotTypeId = root.getAttribute('data-slot-type-id') || '';

        if (pageId === '' || slotTypeId === '') {
            return null;
        }

        return storagePrefix + '.page.' + pageId + '.slot.' + slotTypeId;
    }

    function readStoredExpanded(root) {
        var key = storageKey(root);

        if (!key) {
            return null;
        }

        try {
            var raw = window.localStorage.getItem(key);

            if (!raw) {
                return null;
            }

            var parsed = JSON.parse(raw);

            if (Array.isArray(parsed)) {
                return parsed.map(function (value) {
                    return String(value || '');
                }).filter(Boolean);
            }

            if (typeof parsed === 'string' && parsed !== '') {
                return parsed.split(',').filter(Boolean);
            }
        } catch (error) {
            return null;
        }

        return null;
    }

    function writeStoredExpanded(root, expandedIds) {
        var key = storageKey(root);

        if (!key) {
            return;
        }

        try {
            window.localStorage.setItem(key, JSON.stringify(expandedIds));
        } catch (error) {
            // Ignore storage write failures.
        }
    }

    function toggleButtonFor(root, blockId) {
        return root.querySelector('[data-slot-block-toggle][data-slot-toggle="' + blockId + '"], [data-wb-slot-block-toggle][data-wb-slot-toggle="' + blockId + '"]');
    }

    function toggleId(button) {
        return button.getAttribute('data-slot-toggle') || button.getAttribute('data-wb-slot-toggle') || '';
    }

    function rowBlockId(row) {
        return row.getAttribute('data-block-id') || row.getAttribute('data-wb-slot-block-id') || '';
    }

    function rowParentId(row) {
        return row.getAttribute('data-slot-parent-id') || row.getAttribute('data-wb-slot-parent-id') || '';
    }

    function setRowVisible(row, visible) {
        var container = row.closest('[data-admin-sortable-item]');

        row.hidden = !visible;

        if (container) {
            container.hidden = !visible;
        }
    }

    function normalizedSearchValue(value) {
        return String(value || '')
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase()
            .trim();
    }

    function setExpandedState(root, expandedIds) {
        rootToggles(root).forEach(function (button) {
            setToggleExpanded(button, expandedIds.indexOf(toggleId(button)) !== -1);
        });
    }

    function syncExpandAllButton(root) {
        var button = root.querySelector('[data-wb-slot-block-expand-all]');
        var toggles = rootToggles(root);

        if (!button || toggles.length === 0) {
            return;
        }

        var allExpanded = toggles.every(function (toggle) {
            return toggle.getAttribute('aria-expanded') === 'true';
        });
        var label = button.getAttribute(allExpanded ? 'data-collapse-label' : 'data-expand-label') || '';
        var labelNode = button.querySelector('[data-wb-slot-block-expand-all-label]');
        var icon = button.querySelector('.wb-icon');

        button.setAttribute('aria-pressed', allExpanded ? 'true' : 'false');
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);

        if (labelNode) {
            labelNode.textContent = label;
        }

        if (icon) {
            icon.classList.toggle('wb-icon-maximize2', !allExpanded);
            icon.classList.toggle('wb-icon-minimize2', allExpanded);
        }
    }

    function uniqueIds(values) {
        return values.filter(function (value, index) {
            return value !== '' && values.indexOf(value) === index;
        });
    }

    function readLegacyExpanded() {
        var url = new URL(window.location.href);

        if (!url.searchParams.has('expanded')) {
            return null;
        }

        return uniqueIds((url.searchParams.get('expanded') || '').split(/[,-]/).map(function (value) {
            return String(Number(String(value || '').trim()) || '');
        }));
    }

    function clearLegacyExpandedFromUrl() {
        var url = new URL(window.location.href);

        if (!url.searchParams.has('expanded')) {
            return;
        }

        url.searchParams.delete('expanded');
        window.history.replaceState({}, '', url.toString());
    }

    function currentExpandedSlotBlocks(root) {
        return rootToggles(root)
            .filter(function (button) {
                return button.getAttribute('aria-expanded') === 'true';
            })
            .map(function (button) {
                return toggleId(button);
            })
            .filter(Boolean);
    }

    function setToggleExpanded(button, expanded) {
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        button.setAttribute('aria-label', expanded ? i18n('collapse-children') : i18n('expand-children'));
        button.setAttribute('title', expanded ? i18n('collapse-children') : i18n('expand-children'));
    }

    function rowVisible(root, row) {
        var parentId = rowParentId(row);

        if (!parentId) {
            return true;
        }

        var parentToggle = toggleButtonFor(root, parentId);

        if (!parentToggle || parentToggle.getAttribute('aria-expanded') !== 'true') {
            return false;
        }

        var parentRow = rootRows(root).find(function (candidate) {
            return rowBlockId(candidate) === parentId;
        });

        return parentRow ? rowVisible(root, parentRow) : true;
    }

    function syncSlotBlockRows(root) {
        rootRows(root).forEach(function (row) {
            setRowVisible(row, rowVisible(root, row));
        });
    }

    function searchSlotBlocks(root) {
        var input = root.querySelector('[data-wb-slot-block-search]');

        if (!input) {
            return;
        }

        var query = normalizedSearchValue(input.value);
        var rows = rootRows(root);
        var clearButton = root.querySelector('[data-wb-slot-block-search-clear]');
        var emptyMessage = root.querySelector('[data-wb-slot-block-search-empty]');

        if (clearButton) {
            clearButton.hidden = query === '';
        }

        if (query === '') {
            if (Array.isArray(root._wbSlotBlockSearchExpanded)) {
                setExpandedState(root, root._wbSlotBlockSearchExpanded);
                delete root._wbSlotBlockSearchExpanded;
            }

            if (emptyMessage) {
                emptyMessage.hidden = true;
            }

            syncSlotBlockExpandedState(root);

            return;
        }

        if (!Array.isArray(root._wbSlotBlockSearchExpanded)) {
            root._wbSlotBlockSearchExpanded = currentExpandedSlotBlocks(root);
        }

        var rowsById = {};
        var visibleIds = [];
        var matchingRows = rows.filter(function (row) {
            rowsById[rowBlockId(row)] = row;

            return normalizedSearchValue(row.getAttribute('data-wb-slot-block-search-text')).indexOf(query) !== -1;
        });

        matchingRows.forEach(function (row) {
            var current = row;

            while (current) {
                visibleIds.push(rowBlockId(current));
                current = rowsById[rowParentId(current)] || null;
            }
        });

        visibleIds = uniqueIds(visibleIds);
        setExpandedState(root, uniqueIds(root._wbSlotBlockSearchExpanded.concat(visibleIds)));
        rows.forEach(function (row) {
            setRowVisible(row, visibleIds.indexOf(rowBlockId(row)) !== -1);
        });
        syncExpandAllButton(root);

        if (emptyMessage) {
            emptyMessage.hidden = matchingRows.length !== 0;
        }
    }

    function clearSlotBlockSearch(root, focus) {
        var input = root.querySelector('[data-wb-slot-block-search]');

        if (!input) {
            return;
        }

        input.value = '';
        searchSlotBlocks(root);

        if (focus) {
            input.focus();
        }
    }

    function syncSlotBlockExpandedState(root) {
        var expanded = currentExpandedSlotBlocks(root);

        syncSlotBlockRows(root);
        syncExpandAllButton(root);

        writeStoredExpanded(root, expanded);
    }

    function hydrateExpandedState(root) {
        var defaultExpanded = currentExpandedSlotBlocks(root);
        var storedExpanded = readStoredExpanded(root);
        var legacyExpanded = readLegacyExpanded();

        if (legacyExpanded !== null) {
            setExpandedState(root, uniqueIds(defaultExpanded.concat(legacyExpanded)));
            clearLegacyExpandedFromUrl();
        } else if (storedExpanded !== null) {
            setExpandedState(root, uniqueIds(defaultExpanded.concat(storedExpanded)));
        }

        syncSlotBlockExpandedState(root);
    }

    function initializeTree(root) {
        if (root.getAttribute('data-wb-cms-slot-block-tree-ready') === 'true') {
            hydrateExpandedState(root);

            return;
        }

        root.setAttribute('data-wb-cms-slot-block-tree-ready', 'true');
        root.addEventListener('input', function (event) {
            if (event.target.matches('[data-wb-slot-block-search]')) {
                searchSlotBlocks(root);
            }
        });
        root.addEventListener('click', function (event) {
            var clearSearchButton = event.target.closest('[data-wb-slot-block-search-clear]');

            if (clearSearchButton && root.contains(clearSearchButton)) {
                clearSlotBlockSearch(root, true);

                return;
            }

            var expandAllButton = event.target.closest('[data-wb-slot-block-expand-all]');

            if (expandAllButton && root.contains(expandAllButton)) {
                clearSlotBlockSearch(root, false);
                var expand = expandAllButton.getAttribute('aria-pressed') !== 'true';

                rootToggles(root).forEach(function (button) {
                    setToggleExpanded(button, expand);
                });
                syncSlotBlockExpandedState(root);

                return;
            }

            var slotBlockToggle = event.target.closest('[data-slot-block-toggle], [data-wb-slot-block-toggle]');

            if (!slotBlockToggle || !root.contains(slotBlockToggle)) {
                return;
            }

            setToggleExpanded(slotBlockToggle, slotBlockToggle.getAttribute('aria-expanded') !== 'true');
            syncSlotBlockExpandedState(root);
        });

        hydrateExpandedState(root);
    }

    function run() {
        var roots = treeRoots();

        if (roots.length === 0) {
            return;
        }

        roots.forEach(initializeTree);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }

    window.addEventListener('pageshow', run);
}());
