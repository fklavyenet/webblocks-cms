(function () {
    if (!document.querySelector('[data-wb-slot-block-toggle], [data-wb-slot-block-link], [data-wb-slot-block-expanded-input]')) {
        return;
    }

    function slotBlockRows() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-wb-slot-block-row][data-wb-slot-block-id]'));
    }

    function currentExpandedSlotBlocks() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-wb-slot-block-toggle][aria-expanded="true"]'))
            .map(function (button) {
                return button.getAttribute('data-wb-slot-toggle') || '';
            })
            .filter(function (value) {
                return value !== '';
            });
    }

    function toggleButtonFor(blockId) {
        return document.querySelector('[data-wb-slot-block-toggle][data-wb-slot-toggle="' + blockId + '"]');
    }

    function rowVisible(row) {
        var parentId = row.getAttribute('data-wb-slot-parent-id');

        if (!parentId) {
            return true;
        }

        var parentToggle = toggleButtonFor(parentId);

        if (!parentToggle || parentToggle.getAttribute('aria-expanded') !== 'true') {
            return false;
        }

        var parentRow = document.querySelector('[data-wb-slot-block-row][data-wb-slot-block-id="' + parentId + '"]');

        return parentRow ? rowVisible(parentRow) : true;
    }

    function syncSlotBlockRows() {
        slotBlockRows().forEach(function (row) {
            row.hidden = !rowVisible(row);
        });
    }

    function syncSlotBlockExpandedState() {
        var expanded = currentExpandedSlotBlocks().join(',');

        syncSlotBlockRows();

        document.querySelectorAll('[data-wb-slot-block-expanded-input]').forEach(function (input) {
            var forcedParentRow = input.closest('[data-wb-slot-block-row][data-wb-slot-parent-id]');
            var forced = forcedParentRow ? forcedParentRow.getAttribute('data-wb-slot-parent-id') : null;
            var values = expanded === '' ? [] : expanded.split(',').filter(Boolean);

            if (forced && values.indexOf(forced) === -1) {
                values.push(forced);
            }

            input.value = values.join(',');
        });

        document.querySelectorAll('[data-wb-slot-block-link]').forEach(function (link) {
            var baseUrl = link.getAttribute('data-base-url');

            if (!baseUrl) {
                return;
            }

            var url = new URL(baseUrl, window.location.origin);

            if (expanded !== '') {
                url.searchParams.set('expanded', expanded);
            }

            link.href = url.toString();
        });

        var currentUrl = new URL(window.location.href);

        if (expanded !== '') {
            currentUrl.searchParams.set('expanded', expanded);
        } else {
            currentUrl.searchParams.delete('expanded');
        }

        window.history.replaceState({}, '', currentUrl.toString());
    }

    document.addEventListener('click', function (event) {
        var slotBlockToggle = event.target.closest('[data-wb-slot-block-toggle]');

        if (!slotBlockToggle) {
            return;
        }

        var expanded = slotBlockToggle.getAttribute('aria-expanded') === 'true';

        slotBlockToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        slotBlockToggle.setAttribute('aria-label', expanded ? 'Expand child blocks' : 'Collapse child blocks');
        slotBlockToggle.setAttribute('title', expanded ? 'Expand child blocks' : 'Collapse child blocks');
        syncSlotBlockExpandedState();
    });

    syncSlotBlockExpandedState();
}());
