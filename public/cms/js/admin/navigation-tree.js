(function () {
    var editorSelector = '[data-navigation-tree-editor]';
    var treeSelector = '[data-navigation-tree]';
    var childListSelector = '[data-navigation-children]';
    var itemSelector = '[data-navigation-item]';
    var handleSelector = '[data-navigation-handle]';
    var chosenClass = 'wb-navigation-tree-chosen';
    var maximumDepth = 3;

    function directItems(list) {
        return Array.prototype.slice.call(list ? list.children : []).filter(function (child) {
            return child.matches(itemSelector);
        });
    }

    function directChildList(item) {
        return Array.prototype.slice.call(item.children).find(function (child) {
            return child.matches(childListSelector);
        }) || null;
    }

    function itemDepth(item) {
        return Number(item.getAttribute('data-depth')) || 1;
    }

    function subtreeHeight(item) {
        var descendants = Array.prototype.slice.call(item.querySelectorAll(itemSelector));

        return descendants.reduce(function (height, descendant) {
            return Math.max(height, itemDepth(descendant) - itemDepth(item) + 1);
        }, 1);
    }

    function updateDepth(item, depth) {
        item.setAttribute('data-depth', String(depth));

        var children = directChildList(item);

        directItems(children).forEach(function (child) {
            updateDepth(child, depth + 1);
        });
    }

    function ensureChildList(item) {
        var list = directChildList(item);

        if (list) {
            return list;
        }

        list = document.createElement('ul');
        list.className = 'wb-navigation-children';
        list.setAttribute('data-navigation-children', '');
        item.appendChild(list);

        return list;
    }

    function canBecomeChild(item, parent) {
        if (!parent || parent === item || parent.getAttribute('data-item-link-type') !== 'group') {
            return false;
        }

        if (item.contains(parent)) {
            return false;
        }

        return itemDepth(parent) + subtreeHeight(item) <= maximumDepth;
    }

    function moveBeforeOrAfter(item, target, after) {
        if (!target || target === item || item.contains(target)) {
            return false;
        }

        var list = target.parentElement;
        var parent = list.closest(itemSelector);
        var depth = parent ? itemDepth(parent) + 1 : 1;

        if ((parent && !canBecomeChild(item, parent)) || depth + subtreeHeight(item) - 1 > maximumDepth) {
            return false;
        }

        list.insertBefore(item, after ? target.nextElementSibling : target);
        updateDepth(item, depth);

        return true;
    }

    function indent(item) {
        var previous = item.previousElementSibling;

        if (!previous || !previous.matches(itemSelector) || !canBecomeChild(item, previous)) {
            return false;
        }

        ensureChildList(previous).appendChild(item);
        updateDepth(item, itemDepth(previous) + 1);

        return true;
    }

    function outdent(item) {
        var parent = item.parentElement.closest(itemSelector);

        if (!parent) {
            return false;
        }

        parent.after(item);
        updateDepth(item, itemDepth(parent));

        return true;
    }

    function initEditor(root) {
        if (!root || root.getAttribute('data-navigation-tree-ready') === 'true') {
            return;
        }

        root.setAttribute('data-navigation-tree-ready', 'true');

        var tree = root.querySelector(treeSelector);

        if (!tree) {
            return;
        }

        var status = root.querySelector('[data-navigation-save-status]');
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var confirmedSnapshot = tree.innerHTML;
        var saveInFlight = false;
        var savePending = false;
        var saveTimer = null;
        var active = null;

        function setStatus(text, tone) {
            if (!status) {
                return;
            }

            status.textContent = text || '';
            status.hidden = !text;
            status.className = 'wb-text-sm';
            status.classList.add(tone === 'error' ? 'wb-text-danger' : (tone === 'success' ? 'wb-text-success' : 'wb-text-muted'));
        }

        function buildPayload() {
            var siblingPositions = {};

            return {
                site_id: Number(root.getAttribute('data-site-id')),
                menu_key: root.getAttribute('data-menu-key'),
                items: Array.prototype.slice.call(root.querySelectorAll(itemSelector)).map(function (item) {
                    var parent = item.parentElement.closest(itemSelector);
                    var parentId = parent ? Number(parent.getAttribute('data-item-id')) : null;
                    var key = parentId === null ? 'root' : String(parentId);

                    siblingPositions[key] = (siblingPositions[key] || 0) + 1;

                    return {
                        id: Number(item.getAttribute('data-item-id')),
                        parent_id: parentId,
                        position: siblingPositions[key],
                    };
                }),
            };
        }

        function restoreConfirmedOrder() {
            tree.innerHTML = confirmedSnapshot;
        }

        function queueSave() {
            if (saveInFlight) {
                savePending = true;

                return;
            }

            saveInFlight = true;
            savePending = false;
            setStatus(root.getAttribute('data-saving-text'), 'muted');
            root.querySelectorAll('.wb-navigation-row').forEach(function (row) {
                row.classList.add('is-saving');
            });

            var requestedSnapshot = tree.innerHTML;
            var requestedPayload = buildPayload();

            window.fetch(root.getAttribute('data-reorder-url'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
                },
                body: JSON.stringify(requestedPayload),
                credentials: 'same-origin',
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error(root.getAttribute('data-save-failed-text'));
                }

                return response.json();
            }).then(function () {
                confirmedSnapshot = requestedSnapshot;
                saveInFlight = false;

                if (savePending) {
                    queueSave();

                    return;
                }

                root.querySelectorAll('.wb-navigation-row').forEach(function (row) {
                    row.classList.remove('is-saving');
                });
                setStatus(root.getAttribute('data-saved-text'), 'success');
                window.clearTimeout(saveTimer);
                saveTimer = window.setTimeout(function () {
                    setStatus('', 'muted');
                }, 1500);
            }).catch(function () {
                saveInFlight = false;
                savePending = false;
                setStatus(root.getAttribute('data-could-not-save-text'), 'error');
                restoreConfirmedOrder();
                window.setTimeout(function () {
                    window.location.reload();
                }, 900);
            });
        }

        function startPointer(event, handle) {
            var item = handle.closest(itemSelector);

            if (!item || event.button !== 0) {
                return;
            }

            active = {
                handle: handle,
                item: item,
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                moved: false,
                dragging: false,
            };
            handle.setPointerCapture(event.pointerId);
        }

        function movePointer(event) {
            if (!active || active.pointerId !== event.pointerId) {
                return;
            }

            if (!active.dragging && Math.hypot(event.clientX - active.startX, event.clientY - active.startY) < 6) {
                return;
            }

            active.dragging = true;
            active.item.classList.add(chosenClass);
            event.preventDefault();

            var target = document.elementFromPoint(event.clientX, event.clientY);
            var targetItem = target ? target.closest(itemSelector) : null;

            if (targetItem && root.contains(targetItem) && targetItem !== active.item && !active.item.contains(targetItem)) {
                var targetRect = targetItem.querySelector(':scope > .wb-navigation-row').getBoundingClientRect();

                if (moveBeforeOrAfter(active.item, targetItem, event.clientY > targetRect.top + (targetRect.height / 2))) {
                    active.moved = true;
                }
            }

            var desiredDepth = Math.min(maximumDepth, Math.max(1, Math.round(Math.max(0, event.clientX - tree.getBoundingClientRect().left - 24) / 32) + 1));

            while (desiredDepth > itemDepth(active.item) && indent(active.item)) {
                active.moved = true;
            }

            while (desiredDepth < itemDepth(active.item) && outdent(active.item)) {
                active.moved = true;
            }

            if (event.clientY < 48) {
                window.scrollBy(0, -12);
            } else if (event.clientY > window.innerHeight - 48) {
                window.scrollBy(0, 12);
            }
        }

        function endPointer(event) {
            if (!active || active.pointerId !== event.pointerId) {
                return;
            }

            active.item.classList.remove(chosenClass);

            if (active.handle.hasPointerCapture(event.pointerId)) {
                active.handle.releasePointerCapture(event.pointerId);
            }

            var moved = active.moved;
            active = null;

            if (moved) {
                queueSave();
            }
        }

        root.addEventListener('pointerdown', function (event) {
            var handle = event.target.closest(handleSelector);

            if (handle && root.contains(handle)) {
                startPointer(event, handle);
            }
        });
        root.addEventListener('pointermove', movePointer);
        root.addEventListener('pointerup', endPointer);
        root.addEventListener('pointercancel', endPointer);

        root.addEventListener('keydown', function (event) {
            var handle = event.target.closest(handleSelector);

            if (!handle || !root.contains(handle) || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
                return;
            }

            var item = handle.closest(itemSelector);
            var moved = false;

            if (event.key === 'ArrowUp') {
                var previous = item.previousElementSibling;
                moved = previous && previous.matches(itemSelector) ? moveBeforeOrAfter(item, previous, false) : false;
            } else if (event.key === 'ArrowDown') {
                var next = item.nextElementSibling;
                moved = next && next.matches(itemSelector) ? moveBeforeOrAfter(item, next, true) : false;
            } else if (event.key === 'ArrowRight') {
                moved = indent(item);
            } else if (event.key === 'ArrowLeft') {
                moved = outdent(item);
            }

            if (!moved) {
                return;
            }

            event.preventDefault();
            handle.focus();
            queueSave();
        });
    }

    function init(scope) {
        var container = scope && scope.querySelectorAll ? scope : document;

        Array.prototype.slice.call(container.querySelectorAll(editorSelector)).forEach(initEditor);
    }

    window.WebBlocksNavigationTree = window.WebBlocksNavigationTree || {};
    window.WebBlocksNavigationTree.init = init;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        }, { once: true });
    } else {
        init(document);
    }
}());
