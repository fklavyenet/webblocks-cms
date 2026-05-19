(function () {
    function copyText(value) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(value);
        }

        return new Promise(function (resolve, reject) {
            try {
                var helper = document.createElement('input');
                helper.value = value;
                document.body.appendChild(helper);
                helper.select();
                document.execCommand('copy');
                document.body.removeChild(helper);
                resolve();
            } catch (error) {
                reject(error);
            }
        });
    }

    function initializeButton(button) {
        if (!button || button.getAttribute('data-wb-copy-ready') === 'true') {
            return;
        }

        button.setAttribute('data-wb-copy-ready', 'true');
        button.addEventListener('click', function () {
            var url = button.getAttribute('data-wb-copy-url');
            var feedback = document.querySelector('[data-wb-copy-feedback]');

            if (!url) {
                return;
            }

            copyText(url)
                .then(function () {
                    if (!feedback) {
                        return;
                    }

                    feedback.textContent = 'Public URL copied.';
                    window.clearTimeout(window.__wbMediaCopyTimer || 0);
                    window.__wbMediaCopyTimer = window.setTimeout(function () {
                        feedback.textContent = '';
                    }, 1600);
                })
                .catch(function () {
                    if (feedback) {
                        feedback.textContent = 'Copy failed.';
                    }
                });
        });
    }

    Array.prototype.slice.call(document.querySelectorAll('[data-wb-copy-url]')).forEach(initializeButton);
})();
