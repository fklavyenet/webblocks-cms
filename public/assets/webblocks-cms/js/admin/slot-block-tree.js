(function () {
    var treeSelector = '[data-wb-cms-slot-block-tree][data-page-id][data-slot-type-id]';
    var storagePrefix = 'webblocks.cms.slotBlocks.expanded';

    function treeRoots() {
        return Array.prototype.slice.call(document.querySelectorAll(treeSelector));
    }

    function rootRows(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-slot-block-row][data-block-id], [data-wb-slot-block-row][data-wb-slot-block-id]'));
    }

    function rootDetailRows(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-slot-block-details-row][data-block-id], [data-wb-slot-block-details-row][data-wb-slot-block-id]'));
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

    function setExpandedState(root, expandedIds) {
        rootToggles(root).forEach(function (button) {
            setToggleExpanded(button, expandedIds.indexOf(toggleId(button)) !== -1);
        });
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
        var controlsChildren = (button.getAttribute('aria-controls') || '').indexOf('slot-block-row-') !== -1;
        var expandLabel = controlsChildren ? 'Expand block details and child blocks' : 'Expand block details';
        var collapseLabel = controlsChildren ? 'Collapse block details and child blocks' : 'Collapse block details';

        button.setAttribute('aria-label', expanded ? collapseLabel : expandLabel);
        button.setAttribute('title', expanded ? collapseLabel : expandLabel);
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
            var visible = rowVisible(root, row);
            var container = row.closest('[data-admin-sortable-item]');

            row.hidden = !visible;

            if (container) {
                container.hidden = !visible;
            }
        });

        rootDetailRows(root).forEach(function (row) {
            var visible = rowVisible(root, row);
            var toggle = toggleButtonFor(root, rowBlockId(row));
            var expanded = toggle && toggle.getAttribute('aria-expanded') === 'true';

            row.hidden = !(visible && expanded);
        });
    }

    function syncSlotBlockExpandedState(root) {
        var expanded = currentExpandedSlotBlocks(root);

        syncSlotBlockRows(root);

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
        root.addEventListener('click', function (event) {
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
