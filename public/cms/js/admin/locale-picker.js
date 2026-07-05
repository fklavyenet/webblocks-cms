(function () {
  function run() {
    document.querySelectorAll('[data-wb-locale-picker]').forEach(function (picker) {
      var select = picker.querySelector('[data-wb-locale-options]');
      var form = picker.closest('form');
      var mode = form ? form.querySelector('[data-wb-locale-mode]') : null;
      var custom = form ? form.querySelector('[data-wb-locale-custom]') : null;

      if (!select) {
        return;
      }

      select.addEventListener('change', function () {
        if (mode) {
          mode.value = 'standard';
        }

        if (custom && select.value) {
          custom.open = false;
        }
      });

      if (custom) {
        custom.addEventListener('toggle', function () {
          if (mode && custom.open) {
            mode.value = 'custom';
          }
        });

        custom.querySelectorAll('[data-wb-locale-custom-input]').forEach(function (input) {
          input.addEventListener('focus', function () {
            if (mode) {
              mode.value = 'custom';
            }
          });
        });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
})();
