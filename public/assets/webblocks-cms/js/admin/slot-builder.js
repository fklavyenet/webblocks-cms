(function () {
    if (!document.querySelector('[data-wb-slot-builder]')) {
        return;
    }

    var admin = window.WebBlocksCmsAdmin || {};
    var escapeHtml = admin.escapeHtml || function (value) {
        return String(value || '');
    };

    function addSlot(builder, payload) {
        var list = builder.querySelector('[data-wb-slot-list]');
        var tableWrap = builder.querySelector('[data-wb-slot-table-wrap]');
        var emptyState = builder.querySelector('[data-wb-slot-empty]');

        if (!list || !payload.id || !payload.slug) {
            return;
        }

        var existingSlotTypeIds = Array.prototype.slice.call(list.querySelectorAll('[data-wb-slot-type-id]')).map(function (input) {
            return input.value;
        });

        if (existingSlotTypeIds.indexOf(String(payload.id)) !== -1) {
            return;
        }

        if (emptyState) {
            emptyState.remove();
        }

        if (tableWrap) {
            tableWrap.hidden = false;
        }

        var wrapper = document.createElement('tbody');
        wrapper.innerHTML = buildSlotItemHtml(payload);
        list.appendChild(wrapper.firstElementChild);
        syncSlotBuilder(builder);
    }

    function buildSlotItemHtml(payload) {
        return '' +
            '<tr data-wb-slot-item>' +
                '<td>' +
                    '<strong>' + escapeHtml(payload.name || 'Slot') + '</strong>' +
                    '<input type="hidden" name="" value="" data-wb-slot-id>' +
                    '<input type="hidden" name="" value="' + escapeHtml(String(payload.id)) + '" data-wb-slot-type-id>' +
                    '<input type="hidden" name="" value="0" data-wb-slot-sort>' +
                    '<input type="hidden" name="" value="0" data-wb-slot-delete>' +
                    '<input type="hidden" value="' + escapeHtml(payload.slug) + '" data-wb-slot-slug>' +
                    '<input type="hidden" value="' + escapeHtml(payload.name || 'Slot') + '" data-wb-slot-name>' +
                '</td>' +
                '<td>' +
                    '<div class="wb-action-group">' +
                        '<span class="wb-action-btn" aria-disabled="true" title="Save the page before editing slot blocks"><i class="wb-icon wb-icon-layers" aria-hidden="true"></i></span>' +
                        '<button type="button" class="wb-action-btn" data-wb-slot-move="up" title="Move slot up" aria-label="Move slot up"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>' +
                        '<button type="button" class="wb-action-btn" data-wb-slot-move="down" title="Move slot down" aria-label="Move slot down"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>' +
                        '<button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-slot-remove title="Delete slot" aria-label="Delete slot"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
    }

    function syncSlotBuilder(builder) {
        if (!builder) {
            return;
        }

        var list = builder.querySelector('[data-wb-slot-list]');
        var tableWrap = builder.querySelector('[data-wb-slot-table-wrap]');
        var menu = builder.querySelector('#page-slot-menu');
        var addButton = builder.querySelector('[data-wb-target="#page-slot-menu"]');

        if (!list) {
            return;
        }

        var items = Array.prototype.slice.call(list.querySelectorAll('[data-wb-slot-item]'));

        items.forEach(function (item, index) {
            var inputs = item.querySelectorAll('input');
            var sortInput = item.querySelector('[data-wb-slot-sort]');
            var up = item.querySelector('[data-wb-slot-move="up"]');
            var down = item.querySelector('[data-wb-slot-move="down"]');

            inputs.forEach(function (input) {
                if (input.hasAttribute('data-wb-slot-id')) {
                    input.name = 'slots[' + index + '][id]';
                } else if (input.hasAttribute('data-wb-slot-type-id')) {
                    input.name = 'slots[' + index + '][slot_type_id]';
                } else if (input.hasAttribute('data-wb-slot-sort')) {
                    input.name = 'slots[' + index + '][sort_order]';
                } else if (input.hasAttribute('data-wb-slot-delete')) {
                    input.name = 'slots[' + index + '][_delete]';
                }
            });

            if (sortInput) {
                sortInput.value = index;
            }

            if (up) {
                up.disabled = index === 0;
            }

            if (down) {
                down.disabled = index === items.length - 1;
            }
        });

        if (tableWrap) {
            tableWrap.hidden = items.length === 0;
        }

        if (items.length === 0 && !builder.querySelector('[data-wb-slot-empty]')) {
            var empty = document.createElement('div');
            empty.className = 'wb-empty';
            empty.setAttribute('data-wb-slot-empty', '');
            empty.innerHTML = '<div class="wb-empty-title">No slots yet</div><div class="wb-empty-text">Add Header, Main, Sidebar, or Footer to start defining the page structure.</div>';
            if (tableWrap) {
                tableWrap.parentNode.insertBefore(empty, tableWrap);
            } else {
                list.appendChild(empty);
            }
        }

        if (items.length > 0) {
            var existingEmpty = builder.querySelector('[data-wb-slot-empty]');

            if (existingEmpty) {
                existingEmpty.remove();
            }
        }

        if (menu) {
            var optionButtons = Array.prototype.slice.call(menu.querySelectorAll('[data-wb-slot-add]'));
            var selectedIds = items.map(function (item) {
                var input = item.querySelector('[data-wb-slot-type-id]');

                return input ? String(input.value) : null;
            }).filter(Boolean);

            optionButtons.forEach(function (button) {
                var payload = {};

                try {
                    payload = JSON.parse(button.getAttribute('data-slot-type') || '{}');
                } catch (error) {
                    payload = {};
                }

                button.hidden = selectedIds.indexOf(String(payload.id || '')) !== -1;
            });

            var hasVisibleOptions = optionButtons.some(function (button) {
                return !button.hidden;
            });
            var emptyLabel = menu.querySelector('[data-wb-slot-menu-empty]');

            if (!hasVisibleOptions) {
                if (!emptyLabel) {
                    emptyLabel = document.createElement('div');
                    emptyLabel.className = 'wb-dropdown-label';
                    emptyLabel.setAttribute('data-wb-slot-menu-empty', '');
                    emptyLabel.textContent = 'All slot types already added';
                    menu.appendChild(emptyLabel);
                }
            } else if (emptyLabel) {
                emptyLabel.remove();
            }

            if (addButton) {
                addButton.disabled = !hasVisibleOptions;
            }
        }
    }

    document.querySelectorAll('[data-wb-slot-builder]').forEach(function (builder) {
        syncSlotBuilder(builder);
    });

    document.addEventListener('click', function (event) {
        var addButton = event.target.closest('[data-wb-slot-add]');
        var moveButton = event.target.closest('[data-wb-slot-move]');
        var removeButton = event.target.closest('[data-wb-slot-remove]');

        if (addButton) {
            var slotBuilder = addButton.closest('[data-wb-slot-builder]');

            if (!slotBuilder) {
                slotBuilder = document.querySelector('[data-wb-slot-builder]');
            }

            if (!slotBuilder) {
                return;
            }

            var payload = {};

            try {
                payload = JSON.parse(addButton.getAttribute('data-slot-type') || '{}');
            } catch (error) {
                payload = {};
            }

            addSlot(slotBuilder, payload);
            return;
        }

        if (moveButton) {
            var slotItem = moveButton.closest('[data-wb-slot-item]');

            if (!slotItem) {
                return;
            }

            var direction = moveButton.getAttribute('data-wb-slot-move');
            var sibling = direction === 'up' ? slotItem.previousElementSibling : slotItem.nextElementSibling;

            while (sibling && !sibling.matches('[data-wb-slot-item]')) {
                sibling = direction === 'up' ? sibling.previousElementSibling : sibling.nextElementSibling;
            }

            if (!sibling) {
                return;
            }

            if (direction === 'up') {
                slotItem.parentNode.insertBefore(slotItem, sibling);
            } else {
                slotItem.parentNode.insertBefore(sibling, slotItem);
            }

            syncSlotBuilder(slotItem.closest('[data-wb-slot-builder]'));
            return;
        }

        if (!removeButton) {
            return;
        }

        var removableSlot = removeButton.closest('[data-wb-slot-item]');

        if (!removableSlot) {
            return;
        }

        removableSlot.remove();
        syncSlotBuilder(removeButton.closest('[data-wb-slot-builder]'));
    });
}());
