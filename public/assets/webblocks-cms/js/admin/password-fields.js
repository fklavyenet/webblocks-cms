(function () {
    if (!document.querySelector('[data-password-toggle]')) {
        return;
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-password-toggle]');

        if (!button) {
            return;
        }

        var wrapper = button.closest('[data-password-field]');
        var input = wrapper ? wrapper.querySelector('[data-password-input]') : null;

        if (!input) {
            return;
        }

        var isHidden = input.type === 'password';
        var label = button.querySelector('[data-password-toggle-label]');
        var icon = button.querySelector('[data-password-toggle-icon]');

        input.type = isHidden ? 'text' : 'password';
        button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');

        if (label) {
            label.textContent = isHidden ? 'Hide password' : 'Show password';
        }

        if (icon) {
            icon.classList.remove('wb-icon-eye', 'wb-icon-eye-off');
            icon.classList.add(isHidden ? 'wb-icon-eye-off' : 'wb-icon-eye');
        }
    });
}());
