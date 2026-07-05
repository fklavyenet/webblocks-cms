(function () {
  function run() {
    document.querySelectorAll('[data-wb-locale-picker]').forEach(function (picker) {
      var filter = picker.querySelector('[data-wb-locale-filter]');
      var select = picker.querySelector('[data-wb-locale-options]');
      var form = picker.closest('form');
      var mode = form ? form.querySelector('[data-wb-locale-mode]') : null;
      var custom = form ? form.querySelector('[data-wb-locale-custom]') : null;

      if (!filter || !select) {
        return;
      }

      function applyFilter() {
        var query = String(filter.value || '').toLowerCase().trim();

        Array.prototype.forEach.call(select.options, function (option) {
          if (!option.value) {
            option.hidden = false;
            return;
          }

          var haystack = String(option.getAttribute('data-search') || option.textContent || '').toLowerCase();
          option.hidden = query !== '' && haystack.indexOf(query) === -1;
        });
      }

      filter.addEventListener('input', applyFilter);

      select.addEventListener('change', function () {
        if (mode) {
          mode.value = 'standard';
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
