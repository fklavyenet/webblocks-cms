/* Read-only synthetic review: never submit the reconciliation form. */
document.addEventListener('DOMContentLoaded', function () {
    var output = document.getElementById('test-status');
    var button = document.querySelector('[data-wb-target^="#reconcile-"]');
    var modal = document.querySelector(button.getAttribute('data-wb-target'));
    var table = document.querySelector('.wb-table');
    var submitted = false;
    document.addEventListener('submit', function (event) { event.preventDefault(); submitted = true; });
    function assert(value, text) { if (!value) { throw new Error(text); } }
    try {
        assert(table.querySelectorAll('thead th').length === 5 && table.querySelector('tbody th[scope="row"]'), 'Compact semantic table');
        button.click();
        setTimeout(function () {
            try {
                assert(modal.classList.contains('is-open'), 'Native review modal opens');
                assert(modal.querySelector('textarea[required][maxlength="500"]'), 'Operator reason is required and bounded');
                assert(modal.textContent.includes('No files are restored and no update is started.'), 'Recovery action explains its safe pre-apply scope');
                assert(!submitted, 'Review never submits a reconciliation');
                output.textContent = 'PASS update-attention: compact table, native modal, required reason, retained recovery requirement, no mutation.';
            } catch (error) { output.textContent = 'FAIL update-attention: ' + error.message; }
        }, 100);
    } catch (error) { output.textContent = 'FAIL update-attention: ' + error.message; }
});
