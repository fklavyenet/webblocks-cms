(function () {
    if (!document.querySelector('[data-wb-slot-block-toggle], [data-wb-slot-block-link], [data-wb-slot-block-expanded-input]')) {
        return;
    }

    function currentExpandedSlotBlocks() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-wb-slot-block-toggle][aria-expanded="true"]'))
            .map(function (button) {
                var controls = button.getAttribute('aria-controls') || '';
                return controls.replace('slot-block-children-', '');
            })
            .filter(function (value) {
                return value !== '';
            });
    }

    function syncSlotBlockExpandedState() {
        var expanded = currentExpandedSlotBlocks().join(',');

        document.querySelectorAll('[data-wb-slot-block-expanded-input]').forEach(function (input) {
            var forcedParent = input.closest('tbody[id^="slot-block-children-"]');
            var forced = forcedParent ? forcedParent.id.replace('slot-block-children-', '') : null;
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

        var target = slotBlockToggle.getAttribute('data-wb-target');
        var group = target ? document.getElementById(target) : null;
        var expanded = slotBlockToggle.getAttribute('aria-expanded') === 'true';

        if (!group) {
            return;
        }

        slotBlockToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        slotBlockToggle.setAttribute('aria-label', expanded ? 'Expand child blocks' : 'Collapse child blocks');
        slotBlockToggle.setAttribute('title', expanded ? 'Expand child blocks' : 'Collapse child blocks');
        group.hidden = expanded;
        syncSlotBlockExpandedState();
    });

    syncSlotBlockExpandedState();
}());
