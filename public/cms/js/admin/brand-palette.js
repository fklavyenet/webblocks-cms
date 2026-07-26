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

  // Font pickers: the select lists the families the site actually ships, and
  // the text field only appears for a hand-written stack.
  function bindFontChoice(select) {
    var stackField = document.getElementById(select.getAttribute('data-wb-font-choice'));

    if (!stackField) {
      return;
    }

    select.addEventListener('change', function () {
      if (select.value === '__custom') {
        stackField.classList.remove('wb-hidden');
        stackField.focus();

        return;
      }

      stackField.classList.add('wb-hidden');
      stackField.value = select.value;
    });
  }

  // Theme preview: public.css themes [data-wb-public-theme-preview], so the
  // preview only has to follow the select to show the real preset colours.
  function bindThemePreview(select) {
    var preview = document.querySelector('[data-wb-public-theme-preview]');

    if (!preview) {
      return;
    }

    select.addEventListener('change', function () {
      var preset = select.value;
      var label = select.options[select.selectedIndex]
        ? select.options[select.selectedIndex].text
        : preset;

      preview.setAttribute('data-wb-public-theme-preview', preset);

      var badge = document.querySelector('[data-wb-theme-preview-label]');
      if (badge) {
        badge.textContent = label;
      }

      var hook = document.querySelector('[data-wb-theme-preview-hook]');
      if (hook) {
        hook.textContent = 'data-wb-public-theme="' + preset + '"';
      }
    });
  }

  function init() {
    var pickers = document.querySelectorAll('[data-wb-brand-picker]');

    for (var index = 0; index < pickers.length; index++) {
      bind(pickers[index]);
    }

    var fontChoices = document.querySelectorAll('[data-wb-font-choice]');

    for (var fontIndex = 0; fontIndex < fontChoices.length; fontIndex++) {
      bindFontChoice(fontChoices[fontIndex]);
    }

    var presetSelect = document.getElementById('site_public_theme_preset');

    if (presetSelect) {
      bindThemePreview(presetSelect);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
