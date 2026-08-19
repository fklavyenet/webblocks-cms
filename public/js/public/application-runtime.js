(function () {
  'use strict';

  function parse(element, attribute) {
    try {
      return JSON.parse(element.getAttribute(attribute) || '{}');
    } catch (error) {
      return {};
    }
  }

  function mount() {
    document.querySelectorAll('[data-wb-application][data-wb-application-instance]').forEach(function (host) {
      if (host.dataset.wbApplicationMounted === 'true') {
        return;
      }

      host.dataset.wbApplicationMounted = 'true';

      var minHeight = Number.parseInt(host.getAttribute('data-wb-application-min-height') || '0', 10);
      if (Number.isFinite(minHeight) && minHeight > 0) {
        host.style.setProperty('--wb-application-min-height', Math.min(minHeight, 2000) + 'px');
      }

      document.dispatchEvent(new CustomEvent('webblocks:application:mount', {
        detail: {
          handle: host.getAttribute('data-wb-application'),
          instance: host.getAttribute('data-wb-application-instance'),
          element: host.querySelector('[data-wb-application-mount]') || host,
          settings: parse(host, 'data-wb-application-settings'),
          context: parse(host, 'data-wb-application-context')
        }
      }));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount, { once: true });
  } else {
    mount();
  }
}());
