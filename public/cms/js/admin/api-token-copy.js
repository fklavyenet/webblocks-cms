(function () {
    function fallbackCopy(value) {
        var helper = document.createElement('textarea');
        helper.value = value;
        helper.setAttribute('readonly', '');
        helper.style.position = 'fixed';
        helper.style.opacity = '0';
        document.body.appendChild(helper);
        helper.select();

        try {
            var copied = document.execCommand('copy');
            document.body.removeChild(helper);
            return copied ? Promise.resolve() : Promise.reject();
        } catch (error) {
            document.body.removeChild(helper);
            return Promise.reject(error);
        }
    }

    function copyText(value) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(value);
        }

        return fallbackCopy(value);
    }

    Array.prototype.slice.call(document.querySelectorAll('[data-wb-copy-target]')).forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-wb-copy-target'));
            var feedback = button.parentElement.querySelector('[data-wb-api-token-copy-feedback]')
                || document.querySelector('[data-wb-api-token-copy-feedback]');

            if (!target || !feedback) {
                return;
            }

            copyText(target.value || target.textContent || '').then(function () {
                feedback.textContent = feedback.getAttribute('data-copy-success');
                window.clearTimeout(feedback.wbCopyFeedbackTimer);
                feedback.wbCopyFeedbackTimer = window.setTimeout(function () { feedback.textContent = ''; }, 1600);
            }).catch(function () {
                feedback.textContent = feedback.getAttribute('data-copy-failed');
            });
        });
    });
})();
