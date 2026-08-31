document.querySelectorAll('[data-wb-restore-form]').forEach(function (form) {
    var acknowledgement = form.querySelector('[data-wb-restore-ack]');
    var submitButton = form.querySelector('[data-wb-restore-submit]');

    if (!acknowledgement || !submitButton) { return; }

    function sync() { submitButton.disabled = !acknowledgement.checked; }

    sync();
    acknowledgement.addEventListener('change', sync);
});
