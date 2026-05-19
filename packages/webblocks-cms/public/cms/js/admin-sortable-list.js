(function () {
    var rootSelector = '[data-admin-sortable-list]';
    var itemSelector = '[data-admin-sortable-item]';
    var handleSelector = '[data-admin-sortable-handle]';
    var orderSelector = '[data-admin-sortable-order]';
    var draggingClass = 'is-dragging';
    var invalidTargetClass = 'is-sortable-invalid-target';
    var activeHandle = null;
    var activeItem = null;
    var activeRoot = null;
    var originalOrder = [];
    var movedDuringDrag = false;
    var persistedOnDrop = false;

    function sortableItems(root) {
        if (!root) {
            return [];
        }

        return Array.prototype.slice.call(root.querySelectorAll(itemSelector)).filter(function (item) {
            return item.parentElement === root && !item.hidden;
        });
    }

    function rootMode(root) {
        return root ? String(root.getAttribute('data-admin-sortable-mode') || '').trim() : '';
    }

    function itemGroup(item) {
        return [
            String(item.getAttribute('data-parent-id') || ''),
            String(item.getAttribute('data-slot-type-id') || ''),
        ].join('|');
    }

    function groupItems(root, item) {
        var groupKey = itemGroup(item);

        return sortableItems(root).filter(function (candidate) {
            return itemGroup(candidate) === groupKey;
        });
    }

    function segmentFor(item) {
        var segment = [];
        var current = item;
        var baseDepth = Number(item.getAttribute('data-depth') || '0');

        while (current && current.matches(itemSelector)) {
            var currentDepth = Number(current.getAttribute('data-depth') || '0');

            if (segment.length > 0 && currentDepth <= baseDepth) {
                break;
            }

            segment.push(current);
            current = current.nextElementSibling;
        }

        return segment;
    }

    function updateOrderInputs(root) {
        if (rootMode(root) === 'slot-blocks') {
            sortableItems(root).forEach(function (item) {
                var group = groupItems(root, item);
                var index = group.indexOf(item);
                var input = item.querySelector(orderSelector);

                if (input && index >= 0) {
                    input.value = String(index);
                }
            });

            return;
        }

        sortableItems(root).forEach(function (item, index) {
            var input = item.querySelector(orderSelector);

            if (input) {
                input.value = String(index);
            }
        });
    }

    function dispatchReordered(root, detail) {
        if (!root) {
            return;
        }

        root.dispatchEvent(new CustomEvent('admin-sortable-list:reordered', {
            bubbles: true,
            detail: detail || {
                itemCount: sortableItems(root).length,
            },
        }));
    }

    function restoreOriginalOrder(root) {
        if (!root || originalOrder.length === 0) {
            return;
        }

        originalOrder.forEach(function (item) {
            if (item && item.parentElement === root) {
                root.appendChild(item);
            }
        });

        updateOrderInputs(root);
    }

    function clearInvalidTargets(root) {
        sortableItems(root).forEach(function (item) {
            item.classList.remove(invalidTargetClass);
        });
    }

    function clearDraggingState(root) {
        if (activeItem) {
            activeItem.classList.remove(draggingClass);
        }

        if (root) {
            clearInvalidTargets(root);
        }

        activeItem = null;
        activeRoot = null;
        activeHandle = null;
        originalOrder = [];
        movedDuringDrag = false;
        persistedOnDrop = false;
    }

    function canReorderTogether(root, targetItem) {
        if (!activeItem || !targetItem || targetItem === activeItem) {
            return false;
        }

        if (rootMode(root) !== 'slot-blocks') {
            return true;
        }

        return itemGroup(activeItem) === itemGroup(targetItem);
    }

    function moveSegment(root, targetItem, clientY) {
        var rect = targetItem.getBoundingClientRect();
        var insertAfter = clientY > rect.top + (rect.height / 2);
        var activeSegment = segmentFor(activeItem);
        var targetSegment = segmentFor(targetItem);
        var referenceNode = insertAfter ? targetSegment[targetSegment.length - 1].nextElementSibling : targetItem;

        if (referenceNode === activeItem || targetSegment.indexOf(activeItem) !== -1) {
            return false;
        }

        activeSegment.forEach(function (segmentItem) {
            root.insertBefore(segmentItem, referenceNode);
        });

        updateOrderInputs(root);

        return true;
    }

    function csrfToken() {
        var token = document.querySelector('meta[name="csrf-token"]');

        return token ? token.getAttribute('content') : '';
    }

    function persistSlotBlockReorder(root, movedItem) {
        var reorderUrl = root.getAttribute('data-admin-sortable-reorder-url');
        var item = movedItem || activeItem;

        if (!reorderUrl || !item) {
            return Promise.resolve();
        }

        var ids = groupItems(root, item).map(function (groupItem) {
            return Number(groupItem.getAttribute('data-block-id') || '0');
        }).filter(function (id) {
            return id > 0;
        });

        return window.fetch(reorderUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ blocks: ids }),
            credentials: 'same-origin',
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Slot block reorder request failed with status ' + response.status + '.');
            }

            return response.json();
        }).then(function () {
            dispatchReordered(root, {
                itemCount: ids.length,
                blockIds: ids,
                mode: 'slot-blocks',
            });
        }).catch(function (error) {
            console.warn('[WebBlocks CMS] Slot block reorder failed.', error);
            restoreOriginalOrder(root);
            window.location.reload();
        });
    }

    function bindRoot(root) {
        if (!root || root.getAttribute('data-admin-sortable-ready') === 'true') {
            if (root) {
                updateOrderInputs(root);
            }

            return;
        }

        root.setAttribute('data-admin-sortable-ready', 'true');
        updateOrderInputs(root);

        root.addEventListener('dragstart', function (event) {
            var item = event.target.closest(itemSelector);

            if (!item || !root.contains(item) || item.parentElement !== root || !activeHandle || !item.contains(activeHandle)) {
                event.preventDefault();

                return;
            }

            activeItem = item;
            activeRoot = root;
            originalOrder = sortableItems(root).slice();
            movedDuringDrag = false;
            persistedOnDrop = false;
            item.classList.add(draggingClass);

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.getAttribute('data-block-id') || item.getAttribute('data-wb-builder-item-row') || 'sortable-item');
            }
        });

        root.addEventListener('dragover', function (event) {
            var targetItem = event.target.closest(itemSelector);

            if (!activeItem || !targetItem || !root.contains(targetItem) || targetItem.parentElement !== root) {
                return;
            }

            clearInvalidTargets(root);

            if (!canReorderTogether(root, targetItem)) {
                targetItem.classList.add(invalidTargetClass);

                return;
            }

            event.preventDefault();
            if (moveSegment(root, targetItem, event.clientY)) {
                movedDuringDrag = true;
            }
        });

        root.addEventListener('drop', function (event) {
            if (!activeItem) {
                return;
            }

            event.preventDefault();
            updateOrderInputs(root);

            if (rootMode(root) === 'slot-blocks') {
                persistedOnDrop = true;
                persistSlotBlockReorder(root, activeItem);

                return;
            }

            dispatchReordered(root, {
                itemCount: sortableItems(root).length,
            });
        });

        root.addEventListener('dragend', function () {
            if (!activeItem) {
                clearDraggingState(root);

                return;
            }

            if (rootMode(root) === 'slot-blocks' && movedDuringDrag && !persistedOnDrop) {
                persistSlotBlockReorder(root, activeItem);
            }

            updateOrderInputs(root);
            clearDraggingState(root);
        });
    }

    function init(scope) {
        var root = scope && scope.querySelectorAll ? scope : document;

        Array.prototype.slice.call(root.querySelectorAll(rootSelector)).forEach(bindRoot);
    }

    document.addEventListener('mousedown', function (event) {
        activeHandle = event.target.closest(handleSelector);
    });

    document.addEventListener('mouseup', function () {
        activeHandle = null;
    });

    document.addEventListener('dragend', function () {
        clearDraggingState(activeRoot || (activeItem ? activeItem.parentElement : null));
    });

    window.WebBlocksAdminSortableList = window.WebBlocksAdminSortableList || {};
    window.WebBlocksAdminSortableList.init = init;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        }, { once: true });
    } else {
        init(document);
    }
}());
