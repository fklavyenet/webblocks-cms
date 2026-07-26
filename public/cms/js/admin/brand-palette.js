/**
 * Brand palette colour inputs: keeps the native colour picker and the hex text
 * field in sync. The text field is the one that submits, so the picker is a
 * convenience layer and the form still works without JavaScript.
 */
(function () {
  'use strict';

  function normalizeHex(value) {
    var candidate = String(value || '').trim().toLowerCase();

    if (/^#[0-9a-f]{3}$/.test(candidate)) {
      return '#' + candidate[1] + candidate[1] + candidate[2] + candidate[2] + candidate[3] + candidate[3];
    }

    return /^#[0-9a-f]{6}$/.test(candidate) ? candidate : null;
  }

  function bind(picker) {
    var textField = document.getElementById(picker.getAttribute('data-wb-brand-picker'));

    if (!textField) {
      return;
    }

    picker.addEventListener('input', function () {
      textField.value = picker.value;
    });

    textField.addEventListener('input', function () {
      var hex = normalizeHex(textField.value);

      if (hex) {
        picker.value = hex;
      }
    });

    textField.addEventListener('blur', function () {
      var hex = normalizeHex(textField.value);

      if (hex) {
        textField.value = hex;
      }
    });
  }

  function init() {
    var pickers = document.querySelectorAll('[data-wb-brand-picker]');

    for (var index = 0; index < pickers.length; index++) {
      bind(pickers[index]);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
