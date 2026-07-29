(function () {
    var SCOPE_SELECTOR = '[data-wb-capability-scope]';

    function checkboxesIn(element) {
        return Array.prototype.slice.call(element.querySelectorAll('input[type="checkbox"][name="capabilities[]"]'));
    }

    function selectedCount(checkboxes) {
        return checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
    }

    function refreshGroup(group) {
        var badge = group.querySelector('[data-wb-capability-group-count]');

        if (!badge) {
            return;
        }

        var checkboxes = checkboxesIn(group);
        var selected = selectedCount(checkboxes);

        badge.textContent = selected + '/' + checkboxes.length;
        badge.classList.toggle('wb-status-active', selected > 0);
        badge.classList.toggle('wb-status-info', selected === 0);
    }

    function refreshTotal(scope) {
        var badge = scope.querySelector('[data-wb-capability-total]');

        if (!badge) {
            return;
        }

        var template = badge.getAttribute('data-wb-capability-total-template') || '__SELECTED__/__TOTAL__';
        var checkboxes = checkboxesIn(scope);

        badge.textContent = template
            .replace('__SELECTED__', String(selectedCount(checkboxes)))
            .replace('__TOTAL__', String(checkboxes.length));
    }

    function refreshScope(scope) {
        Array.prototype.slice.call(scope.querySelectorAll('[data-wb-capability-group]')).forEach(refreshGroup);
        refreshTotal(scope);
    }

    function refreshAll() {
        Array.prototype.slice.call(document.querySelectorAll(SCOPE_SELECTOR)).forEach(refreshScope);
    }

    // Delegated so the Create Token card and every Edit API Token modal are covered
    // by one listener, including modals rendered into the overlay root.
    document.addEventListener('change', function (event) {
        var target = event.target;

        if (!target || target.type !== 'checkbox' || target.name !== 'capabilities[]') {
            return;
        }

        var scope = target.closest(SCOPE_SELECTOR);

        if (!scope) {
            return;
        }

        var group = target.closest('[data-wb-capability-group]');

        if (group) {
            refreshGroup(group);
        }

        refreshTotal(scope);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refreshAll);
    } else {
        refreshAll();
    }
})();
