document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-wb-admin-dirty-form]');
    var overlay = form.closest('.wb-modal');
    var status = document.getElementById('test-status');
    var checks = 0;

    function check(expected, label) {
        var event = new CustomEvent('wb:overlay:close-request', { bubbles: true, cancelable: true });
        overlay.dispatchEvent(event);
        if (event.defaultPrevented !== expected) throw new Error(label);
        var confirmation = document.getElementById('wb-admin-dirty-close-confirm-modal');
        if (expected && (!confirmation || !confirmation.classList.contains('is-open'))) throw new Error('Missing confirmation: ' + label);
        if (confirmation && confirmation.classList.contains('is-open')) window.WBModal.close(confirmation);
        checks += 1;
    }

    function setMetadata(name, value) {
        var field = form.elements.namedItem(name);
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden'; field.name = name;
            form.appendChild(field);
        }
        field.value = value;
    }

    try {
        check(false, 'Untouched form');
        setMetadata('_editor_recovery_session', 'synthetic-session');
        check(false, 'Recovery session assigned after initialization');
        setMetadata('_editor_recovery_base', 'synthetic-fingerprint');
        setMetadata('_slot_block_tab', 'settings');
        setMetadata('_consent_tab', 'consent-service-cookies');
        check(false, 'Recovery metadata and tab selection');

        var parent = form.elements.namedItem('parent_id');
        var original = parent.value;
        parent.value = original ? '' : parent.options[1].value;
        check(true, 'Real parent change with recovery metadata');
        // Reinitializing a fragment must not erase an existing edit baseline.
        window.WebBlocksCmsAdmin.initializeDynamicContent(form);
        check(true, 'Edit survives fragment reinitialization');
        parent.value = original;
        check(false, 'Reverting real edits');

        var type = form.elements.namedItem('block_type_id');
        original = type.value;
        type.value = '999999';
        check(true, 'Hidden content fields remain protected');
        type.value = original;
        check(false, 'Original content restored');
        status.textContent = 'PASS: ' + checks + ' dirty-overlay checks';
    } catch (error) {
        status.textContent = 'FAIL: ' + error.message;
        throw error;
    }
});
