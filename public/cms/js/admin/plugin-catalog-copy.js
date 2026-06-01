(function () {
    function copyText(value) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(value);
        }

        return new Promise(function (resolve, reject) {
            var textarea = document.createElement('textarea');

            textarea.value = value;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                resolve();
            } catch (error) {
                reject(error);
            } finally {
                document.body.removeChild(textarea);
            }
        });
    }

    function feedback(message) {
        var target = document.querySelector('[data-wb-copy-feedback]');

        if (target) {
            target.textContent = message;
        }
    }

    function initializeButton(button) {
        if (!button || button.getAttribute('data-wb-copy-ready') === 'true') {
            return;
        }

        button.setAttribute('data-wb-copy-ready', 'true');
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-wb-copy-value');
            var label = button.getAttribute('data-wb-copy-label') || 'Value';

            if (!value) {
                return;
            }

            copyText(value)
                .then(function () {
                    feedback(label + ' copied.');
                })
                .catch(function () {
                    feedback('Copy failed. Select the value manually.');
                });
        });
    }

    Array.prototype.slice.call(document.querySelectorAll('[data-wb-copy-value]')).forEach(initializeButton);
})();
