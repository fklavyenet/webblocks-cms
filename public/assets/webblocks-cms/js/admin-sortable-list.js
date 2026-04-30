(function () {
    var rootSelector = '[data-admin-sortable-list]';
    var itemSelector = '[data-admin-sortable-item]';
    var handleSelector = '[data-admin-sortable-handle]';
    var orderSelector = '[data-admin-sortable-order]';
    var draggingClass = 'is-dragging';
    var activeHandle = null;
    var activeItem = null;

    function sortableItems(root) {
        if (!root) {
            return [];
        }

        return Array.prototype.slice.call(root.querySelectorAll(itemSelector)).filter(function (item) {
            return item.parentElement === root && !item.hidden;
        });
    }

    function updateOrderInputs(root) {
        sortableItems(root).forEach(function (item, index) {
            var input = item.querySelector(orderSelector);

            if (input) {
                input.value = String(index);
            }
        });
    }

    function dispatchReordered(root) {
        if (!root) {
            return;
        }

        root.dispatchEvent(new CustomEvent('admin-sortable-list:reordered', {
            bubbles: true,
            detail: {
                itemCount: sortableItems(root).length,
            },
        }));
    }

    function clearDraggingState() {
        if (activeItem) {
            activeItem.classList.remove(draggingClass);
        }

        activeItem = null;
        activeHandle = null;
    }

    function reorderOnPointer(root, targetItem, clientY) {
        if (!root || !targetItem || !activeItem || targetItem === activeItem) {
            return;
        }

        var rect = targetItem.getBoundingClientRect();
        var insertAfter = clientY > rect.top + (rect.height / 2);
        var nextSibling = insertAfter ? targetItem.nextElementSibling : targetItem;

        if (nextSibling === activeItem || targetItem === activeItem.previousElementSibling && !insertAfter) {
            return;
        }

        root.insertBefore(activeItem, nextSibling);
        updateOrderInputs(root);
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
            item.classList.add(draggingClass);

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.getAttribute('data-wb-builder-item-row') || 'sortable-item');
            }
        });

        root.addEventListener('dragover', function (event) {
            var targetItem = event.target.closest(itemSelector);

            if (!activeItem || !targetItem || !root.contains(targetItem) || targetItem.parentElement !== root) {
                return;
            }

            event.preventDefault();
            reorderOnPointer(root, targetItem, event.clientY);
        });

        root.addEventListener('drop', function (event) {
            if (!activeItem) {
                return;
            }

            event.preventDefault();
            updateOrderInputs(root);
            dispatchReordered(root);
        });

        root.addEventListener('dragend', function () {
            if (!activeItem) {
                clearDraggingState();

                return;
            }

            updateOrderInputs(root);
            clearDraggingState();
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
        clearDraggingState();
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
