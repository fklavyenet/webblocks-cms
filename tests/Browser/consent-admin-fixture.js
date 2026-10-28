document.addEventListener('DOMContentLoaded', function () {
    requestAnimationFrame(function () {
        var status = document.getElementById('test-status');
        var form = document.getElementById('consent-service-form');
        var modal = document.getElementById('consentServiceModal');
        var count = 0;
        function check(value, label) { if (!value) throw new Error(label); count += 1; }
        try {
            var filter = document.querySelector('[data-consent-filter-card] form');
            var list = document.querySelector('[data-consent-list-card]');
            check(!!filter && !!list, 'A filter card precedes the list card');
            check(!!(filter.compareDocumentPosition(list) & Node.DOCUMENT_POSITION_FOLLOWING), 'Filters appear above the list');
            check(!!filter.querySelector('select[name="site"]'), 'Site is inside the common filter form');
            list.querySelectorAll('td.wb-table-actions a').forEach(function (link) {
                check(!link.textContent.trim() && !!link.querySelector('.wb-icon') && !!link.getAttribute('aria-label'), 'Row actions have accessible icons');
            });
            if (list.dataset.consentSection !== 'services') {
                var genericModal = document.getElementById('consentEditorModal');
                check(!list.querySelector('textarea, input'), 'The list does not contain editing controls');
                if (list.dataset.consentSection === 'texts') {
                    check(!!filter.querySelector('select[name="locale"]'), 'Site and language share one filter form');
                    check(list.querySelectorAll('tbody tr').length === 6, 'Notice, preference window and each service occupy one row');
                    check(filter.querySelector('select[name="locale"]').value === 'de', 'The requested language is selected');
                    check(list.textContent.includes('Synthetische Datenschutzauswahl'), 'The list shows the effective translated value');
                }
                if (genericModal) {
                    check(genericModal.classList.contains('is-open'), 'The selected field opens with the native modal lifecycle');
                    var input = genericModal.querySelector('textarea, input:not([type="hidden"])');
                    if (list.dataset.consentSection === 'texts') {
                        check(genericModal.querySelectorAll('textarea').length === 2, 'The grouped editor contains both related text fields');
                        check(!!genericModal.querySelector('textarea[name="texts[service.youtube.name]"]') && !!genericModal.querySelector('textarea[name="texts[service.youtube.description]"]'), 'The service name and description share one modal');
                    }
                    var unchanged = new CustomEvent('wb:overlay:close-request', { bubbles: true, cancelable: true });
                    genericModal.dispatchEvent(unchanged);
                    check(!unchanged.defaultPrevented, 'Opening an editor alone does not mark it dirty');
                    input.value = input.type === 'number' ? '91' : 'Synthetic unsaved text';
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    var changed = new CustomEvent('wb:overlay:close-request', { bubbles: true, cancelable: true });
                    genericModal.dispatchEvent(changed);
                    check(changed.defaultPrevented, 'Unsaved field edits require confirmation before closing');
                    check(!genericModal.querySelector('fieldset, legend'), 'The field editor uses supported groups');
                }
                status.textContent = 'PASS: ' + count + ' admin consent checks';
                return;
            }
            check(!!form && modal.classList.contains('is-open'), 'Service editor opens with the real admin modal lifecycle');
            check(!form.querySelector('fieldset, legend'), 'Editor uses supported form groups');
            var tabs = form.querySelectorAll('[data-wb-tab]');
            check(tabs.length === 3, 'Information, executable sources and cookie declarations have separate tabs');
            tabs[2].click();
            check(form.querySelector('[data-consent-service-tab]').value === 'consent-service-cookies', 'The selected panel is retained');
            var untouched = new CustomEvent('wb:overlay:close-request', { bubbles: true, cancelable: true });
            modal.dispatchEvent(untouched);
            check(!untouched.defaultPrevented, 'Changing tabs alone is not a content edit');
            form.querySelector('[data-consent-cookie-add]').click();
            var row = form.querySelector('[data-consent-cookie-row]');
            check(!!row && !row.querySelector('input').name.includes('__index__'), 'Cookie inputs have real unique names');
            row.querySelector('[data-consent-cookie-remove]').click();
            check(!form.querySelector('[data-consent-cookie-row]'), 'Cookie rows can be removed');
            tabs[0].click();
            form.querySelector('[data-consent-handle]').value = 'synthetic-service';
            form.elements.namedItem('name').value = 'Synthetic service';
            var submitted = false;
            document.addEventListener('submit', function (event) { if (event.target === form) { event.preventDefault(); submitted = true; } });
            form.requestSubmit();
            check(submitted, 'A valid service form can submit');
            check(form.action.endsWith('/services/synthetic-service'), 'The custom identifier selects the shared service endpoint');
            check(form.elements.namedItem('_clear_cookies').value === '1', 'Removing every cookie explicitly clears stored declarations');
            status.textContent = 'PASS: ' + count + ' admin consent checks';
        } catch (error) { status.textContent = 'FAIL: ' + error.message; throw error; }
    });
});
