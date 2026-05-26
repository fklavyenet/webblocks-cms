(function () {
  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
      return;
    }

    callback();
  }

  function dismissAlert(alert) {
    if (!alert || alert.hidden) {
      return;
    }

    if (alert.contains(document.activeElement)) {
      return;
    }

    alert.hidden = true;
  }

  ready(function () {
    document.querySelectorAll('[data-wb-contact-success-dismiss]').forEach(function (alert) {
      var delay = parseInt(alert.getAttribute('data-wb-contact-success-dismiss-delay') || '7000', 10);
      var close = alert.querySelector('[data-wb-contact-success-close]');

      if (close) {
        close.addEventListener('click', function () {
          dismissAlert(alert);
        });
      }

      window.setTimeout(function () {
        dismissAlert(alert);
      }, Number.isFinite(delay) && delay > 0 ? delay : 7000);
    });
  });
}());
