document.addEventListener('DOMContentLoaded', function () {
    requestAnimationFrame(function () {
        var status = document.getElementById('test-status');
        var form = document.getElementById('consent-service-form');
        var modal = document.getElementById('consentServiceModal');
        var count = 0;
        function check(value, label) { if (!value) throw new Error(label); count += 1; }
        try {
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
