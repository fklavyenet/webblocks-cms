(function () {
  function nextMode(current) {
    return current === 'light' ? 'dark' : current === 'dark' ? 'auto' : 'light';
  }

  function nextAccent(current) {
    var accents = ['ocean', 'forest', 'sunset', 'royal', 'mint', 'amber', 'rose', 'slate-fire'];
    var index = accents.indexOf(current);
    return accents[(index + 1 + accents.length) % accents.length];
  }

  document.addEventListener('click', function (event) {
    var modeButton = event.target.closest('[data-wb-header-actions-mode-toggle]');
    if (modeButton) {
        event.preventDefault();
        if (window.WBTheme && typeof window.WBTheme.getMode === 'function' && typeof window.WBTheme.setMode === 'function') {
            window.WBTheme.setMode(nextMode(window.WBTheme.getMode()));
        }
        return;
    }

    var accentButton = event.target.closest('[data-wb-header-actions-accent-toggle]');
    if (accentButton) {
        event.preventDefault();
        if (window.WBTheme && typeof window.WBTheme.getAccent === 'function' && typeof window.WBTheme.setAccent === 'function') {
            window.WBTheme.setAccent(nextAccent(window.WBTheme.getAccent()));
        }
    }
  });
})();
