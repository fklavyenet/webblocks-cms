(function () {
    var treeSelector = '[data-wb-cms-slot-block-tree][data-wb-slot-id]';
    var storagePrefix = 'webblocks.cms.slotBlocks.expanded.';

    function treeRoots() {
        return Array.prototype.slice.call(document.querySelectorAll(treeSelector));
    }

    function rootRows(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-wb-slot-block-row][data-wb-slot-block-id]'));
    }

    function rootToggles(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-wb-slot-block-toggle][data-wb-slot-toggle]'));
    }

    function storageKey(root) {
        var slotId = root.getAttribute('data-wb-slot-id') || '';

        return slotId === '' ? null : storagePrefix + slotId;
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
        return root.querySelector('[data-wb-slot-block-toggle][data-wb-slot-toggle="' + blockId + '"]');
    }

    function currentExpandedSlotBlocks(root) {
        return rootToggles(root)
            .filter(function (button) {
                return button.getAttribute('aria-expanded') === 'true';
            })
            .map(function (button) {
                return button.getAttribute('data-wb-slot-toggle') || '';
            })
            .filter(Boolean);
    }

    function setToggleExpanded(button, expanded) {
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        button.setAttribute('aria-label', expanded ? 'Collapse child blocks' : 'Expand child blocks');
        button.setAttribute('title', expanded ? 'Collapse child blocks' : 'Expand child blocks');
    }

    function rowVisible(root, row) {
        var parentId = row.getAttribute('data-wb-slot-parent-id');

        if (!parentId) {
            return true;
        }

        var parentToggle = toggleButtonFor(root, parentId);

        if (!parentToggle || parentToggle.getAttribute('aria-expanded') !== 'true') {
            return false;
        }

        var parentRow = root.querySelector('[data-wb-slot-block-row][data-wb-slot-block-id="' + parentId + '"]');

        return parentRow ? rowVisible(root, parentRow) : true;
    }

    function syncSlotBlockRows(root) {
        rootRows(root).forEach(function (row) {
            row.hidden = !rowVisible(root, row);
        });
    }

    function syncSlotBlockExpandedState(root) {
        var expanded = currentExpandedSlotBlocks(root);
        var expandedQuery = expanded.join(',');

        syncSlotBlockRows(root);

        root.querySelectorAll('[data-wb-slot-block-expanded-input]').forEach(function (input) {
            var forcedParentRow = input.closest('[data-wb-slot-block-row][data-wb-slot-parent-id]');
            var forced = forcedParentRow ? forcedParentRow.getAttribute('data-wb-slot-parent-id') : null;
            var values = expanded.slice();

            if (forced && values.indexOf(forced) === -1) {
                values.push(forced);
            }

            input.value = values.join(',');
        });

        root.querySelectorAll('[data-wb-slot-block-link]').forEach(function (link) {
            var baseUrl = link.getAttribute('data-base-url');

            if (!baseUrl) {
                return;
            }

            var url = new URL(baseUrl, window.location.origin);

            if (expandedQuery !== '') {
                url.searchParams.set('expanded', expandedQuery);
            } else {
                url.searchParams.delete('expanded');
            }

            link.href = url.toString();
        });

        writeStoredExpanded(root, expanded);

        var currentUrl = new URL(window.location.href);

        if (expandedQuery !== '') {
            currentUrl.searchParams.set('expanded', expandedQuery);
        } else {
            currentUrl.searchParams.delete('expanded');
        }

        window.history.replaceState({}, '', currentUrl.toString());
    }

    function hydrateExpandedState(root) {
        var storedExpanded = readStoredExpanded(root);

        if (storedExpanded !== null) {
            rootToggles(root).forEach(function (button) {
                setToggleExpanded(button, storedExpanded.indexOf(button.getAttribute('data-wb-slot-toggle') || '') !== -1);
            });
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
            var slotBlockToggle = event.target.closest('[data-wb-slot-block-toggle]');

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
