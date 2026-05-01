(function () {
  var MODE_ICON_CLASSES = ['wb-icon-sun', 'wb-icon-moon', 'wb-icon-sun-moon'];

  function nextMode(current) {
    return current === 'light' ? 'dark' : current === 'dark' ? 'auto' : 'light';
  }

  function iconClassForMode(mode) {
    if (mode === 'light') return 'wb-icon-sun';
    if (mode === 'dark') return 'wb-icon-moon';
    return 'wb-icon-sun-moon';
  }

  function labelForMode(mode) {
    if (mode === 'light') return 'Switch to dark mode';
    if (mode === 'dark') return 'Switch to auto mode';
    return 'Switch to light mode';
  }

  function syncModeButtons() {
    if (!window.WBTheme || typeof window.WBTheme.getMode !== 'function') return;

    var mode = window.WBTheme.getMode();

    document.querySelectorAll('[data-wb-header-actions-mode-toggle]').forEach(function (button) {
      var icon = button.querySelector('.wb-icon');

      if (icon) {
        MODE_ICON_CLASSES.forEach(function (className) {
          icon.classList.remove(className);
        });

        icon.classList.add(iconClassForMode(mode));
      }

      button.setAttribute('aria-label', labelForMode(mode));
      button.setAttribute('aria-pressed', mode === 'dark' ? 'true' : 'false');
    });
  }

  function syncAccentMenus() {
    if (!window.WBTheme || typeof window.WBTheme.getAccent !== 'function') return;

    var accent = window.WBTheme.getAccent();

    document.querySelectorAll('[data-wb-header-actions-accent-option]').forEach(function (option) {
      var isActive = option.getAttribute('data-wb-accent-set') === accent;
      option.classList.toggle('is-active', isActive);
      option.setAttribute('aria-checked', isActive ? 'true' : 'false');
    });

    document.querySelectorAll('[data-wb-header-actions-accent-toggle]').forEach(function (button) {
      button.setAttribute('aria-label', 'Change accent color');
    });
  }

  function syncAccentExpandedState() {
    document.querySelectorAll('[data-wb-header-actions-accent]').forEach(function (wrapper) {
      var button = wrapper.querySelector('[data-wb-header-actions-accent-toggle]');
      var menu = wrapper.querySelector('.wb-dropdown-menu');

      if (!button || !menu) return;

      button.setAttribute('aria-expanded', menu.classList.contains('is-open') ? 'true' : 'false');
    });
  }

  function syncAll() {
    syncModeButtons();
    syncAccentMenus();
    syncAccentExpandedState();
  }

  document.addEventListener('click', function (event) {
    var modeButton = event.target.closest('[data-wb-header-actions-mode-toggle]');
    if (modeButton) {
      event.preventDefault();
      if (window.WBTheme && typeof window.WBTheme.getMode === 'function' && typeof window.WBTheme.setMode === 'function') {
        window.WBTheme.setMode(nextMode(window.WBTheme.getMode()));
        syncAll();
      }
      return;
    }

    var accentOption = event.target.closest('[data-wb-header-actions-accent-option]');
    if (accentOption) {
      if (window.WBTheme && typeof window.WBTheme.setAccent === 'function') {
        window.WBTheme.setAccent(accentOption.getAttribute('data-wb-accent-set'));
        syncAll();
      }
      return;
    }

    setTimeout(syncAccentExpandedState, 0);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      setTimeout(syncAccentExpandedState, 0);
    }
  });

  document.addEventListener('DOMContentLoaded', syncAll);

  if (document.readyState !== 'loading') {
    syncAll();
  }
})();
