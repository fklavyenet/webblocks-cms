(function () {
    function selectedCheckboxes(listing) {
        return Array.prototype.slice.call(listing.querySelectorAll('[data-wb-admin-row-select]:checked'));
    }

    function allCheckboxes(listing) {
        return Array.prototype.slice.call(listing.querySelectorAll('[data-wb-admin-row-select]'));
    }

    function modalForListing(listing) {
        var trigger = listing.querySelector('[data-wb-admin-bulk-delete-trigger]');
        var target = trigger ? trigger.getAttribute('data-wb-target') : '';

        return target ? document.querySelector(target) : null;
    }

    function syncHiddenInputs(modal, selected) {
        var target = modal ? modal.querySelector('[data-wb-admin-bulk-inputs]') : null;

        if (!target) {
            return;
        }

        target.innerHTML = '';

        selected.forEach(function (checkbox) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'backup_ids[]';
            input.value = checkbox.value;
            target.appendChild(input);
        });
    }

    function syncListing(listing) {
        var selected = selectedCheckboxes(listing);
        var rows = allCheckboxes(listing);
        var count = selected.length;
        var selectAll = listing.querySelector('[data-wb-admin-select-all-visible]');
        var actionBar = listing.querySelector('[data-wb-admin-bulk-actions]');
        var countTarget = listing.querySelector('[data-wb-admin-bulk-count]');
        var trigger = listing.querySelector('[data-wb-admin-bulk-delete-trigger]');
        var modal = modalForListing(listing);
        var modalCount = modal ? modal.querySelector('[data-wb-admin-bulk-modal-count]') : null;
        var modalSubmit = modal ? modal.querySelector('[data-wb-admin-bulk-delete-submit]') : null;

        if (countTarget) {
            countTarget.textContent = String(count);
        }

        if (actionBar) {
            actionBar.hidden = count === 0;
        }

        if (trigger) {
            trigger.disabled = count === 0;
        }

        if (modalCount) {
            modalCount.textContent = String(count);
        }

        if (modalSubmit) {
            modalSubmit.disabled = count === 0;
        }

        if (selectAll) {
            selectAll.checked = rows.length > 0 && count === rows.length;
            selectAll.indeterminate = count > 0 && count < rows.length;
        }

        if (modal) {
            syncHiddenInputs(modal, selected);
        }
    }

    function initializeListing(listing) {
        if (listing.getAttribute('data-wb-admin-bulk-listing-ready') === 'true') {
            syncListing(listing);

            return;
        }

        listing.setAttribute('data-wb-admin-bulk-listing-ready', 'true');
        listing.addEventListener('change', function (event) {
            var selectAll = event.target.closest('[data-wb-admin-select-all-visible]');

            if (selectAll) {
                allCheckboxes(listing).forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
            }

            if (selectAll || event.target.closest('[data-wb-admin-row-select]')) {
                syncListing(listing);
            }
        });

        syncListing(listing);
    }

    function run() {
        Array.prototype.slice.call(document.querySelectorAll('[data-wb-admin-bulk-listing]')).forEach(initializeListing);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }

    window.addEventListener('pageshow', run);
}());
