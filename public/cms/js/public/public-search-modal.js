(function () {
  var ACTIVE_CLASS = 'is-open';
  var BODY_LOCK_CLASS = 'wb-overlay-lock';
  var debounceTimer = null;
  var activeRequest = 0;
  var copy = {
    helper: 'Enter a search term to find published content for this site and locale.',
    unavailable: 'Search is temporarily unavailable. You can still use the search page.',
    count: '__count__ result for __query__.',
    countPlural: '__count__ results for __query__.',
    untitled: 'Untitled'
  };

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback, { once: true });
      return;
    }

    callback();
  }

  function createResultItem(result) {
    var link = document.createElement('a');
    link.className = 'wb-link-list-item';
    link.href = String(result.url || '');

    var main = document.createElement('div');
    main.className = 'wb-link-list-main';

    var title = document.createElement('span');
    title.className = 'wb-link-list-title';
    title.textContent = String(result.title || copy.untitled);

    var meta = document.createElement('span');
    meta.className = 'wb-link-list-meta';
    meta.textContent = String(result.url || '');

    main.appendChild(title);
    main.appendChild(meta);
    link.appendChild(main);

    if (result.excerpt) {
      var excerpt = document.createElement('div');
      excerpt.className = 'wb-link-list-desc';
      excerpt.textContent = String(result.excerpt);
      link.appendChild(excerpt);
    }

    return link;
  }

  function setBodyLocked(locked) {
    if (!document.body) {
      return;
    }

    document.body.classList.toggle(BODY_LOCK_CLASS, locked);
    document.body.style.overflow = locked ? 'hidden' : '';
  }

  ready(function () {
    var overlay = document.querySelector('[data-wb-public-search-overlay]');
    var modal = document.querySelector('[data-wb-public-search-modal]');
    var form = document.querySelector('[data-wb-public-search-form]');
    var input = document.querySelector('[data-wb-public-search-input]');
    var loading = document.querySelector('[data-wb-public-search-loading]');
    var count = document.querySelector('[data-wb-public-search-count]');
    var message = document.querySelector('[data-wb-public-search-message]');
    var results = document.querySelector('[data-wb-public-search-results]');
    var closeButtons = document.querySelectorAll('[data-wb-public-search-close]');
    var triggers = document.querySelectorAll('[data-wb-public-search-open]');

    if (!overlay || !modal || !form || !input || !loading || !count || !message || !results || triggers.length === 0) {
      return;
    }

    function parseCopy() {
      try {
        return JSON.parse(form.getAttribute('data-search-copy') || '{}') || {};
      } catch (error) {
        return {};
      }
    }

    copy = Object.assign(copy, parseCopy());
    var helperText = String(copy.helper);

    function formatTemplate(template, values) {
      return String(template || '').replace(/__([a-z_]+)__/g, function (match, key) {
        return Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : match;
      });
    }

    function setMessage(text, visible) {
      message.hidden = !visible;

      while (message.firstChild) {
        message.removeChild(message.firstChild);
      }

      if (visible) {
        var wrapper = document.createElement('div');
        wrapper.textContent = text;
        message.appendChild(wrapper);
      }
    }

    function setCount(text, visible) {
      count.hidden = !visible;
      count.textContent = visible ? text : '';
    }

    function setLoading(visible) {
      loading.hidden = !visible;
    }

    function clearResults() {
      results.hidden = true;
      results.className = 'wb-public-search-results';

      while (results.firstChild) {
        results.removeChild(results.firstChild);
      }
    }

    function renderPayload(payload) {
      var payloadResults = Array.isArray(payload.results) ? payload.results : [];
      var query = String(payload.query || '').trim();
      var total = Number(payload.count || 0);

      clearResults();
      setLoading(false);

      if (payload.minimum_query_length) {
        setCount('', false);
        setMessage(String(payload.minimum_query_length), true);
        return;
      }

      if (!query) {
        setCount('', false);
        setMessage(helperText, true);
        return;
      }

      if (payload.no_results) {
        setCount('', false);
        setMessage(String(payload.no_results), true);
        return;
      }

      setMessage('', false);
      setCount(formatTemplate(total === 1 ? copy.count : copy.countPlural, {
        count: total,
        query: query
      }), true);
      results.className = 'wb-link-list';

      payloadResults.forEach(function (result) {
        results.appendChild(createResultItem(result));
      });

      results.hidden = payloadResults.length === 0;
    }

    function searchNow(query) {
      var jsonPath = form.getAttribute('data-search-json-path');

      if (!jsonPath || !window.fetch) {
        return;
      }

      var requestId = activeRequest + 1;
      activeRequest = requestId;

      setLoading(true);

      window.fetch(jsonPath + '?q=' + encodeURIComponent(query), {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      }).then(function (response) {
        if (!response.ok) {
          throw new Error('Search request failed.');
        }

        return response.json();
      }).then(function (payload) {
        if (requestId !== activeRequest) {
          return;
        }

        renderPayload(payload || {});
      }).catch(function () {
        if (requestId !== activeRequest) {
          return;
        }

        clearResults();
        setLoading(false);
        setCount('', false);
        setMessage(String(copy.unavailable), true);
      });
    }

    function scheduleSearch(query) {
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(function () {
        searchNow(query);
      }, 180);
    }

    function syncFormAction(path) {
      if (!path) {
        return;
      }

      form.setAttribute('action', path);
    }

    function setBackdropVisible(visible) {
      var backdrop = overlay.querySelector('.wb-overlay-backdrop');

      if (!backdrop) {
        return;
      }

      backdrop.hidden = !visible;
    }

    function openModal(initialQuery, path) {
      syncFormAction(path);
      setBackdropVisible(true);
      modal.hidden = false;
      modal.classList.add(ACTIVE_CLASS);
      setBodyLocked(true);

      input.value = String(initialQuery || '');
      window.setTimeout(function () {
        input.focus();
        input.select();
      }, 0);

      if (input.value.trim()) {
        searchNow(input.value.trim());
        return;
      }

      clearResults();
      setLoading(false);
      setCount('', false);
      setMessage(helperText, true);
    }

    function closeModal() {
      setBackdropVisible(false);
      modal.hidden = true;
      modal.classList.remove(ACTIVE_CLASS);
      setBodyLocked(false);
      setLoading(false);
    }

    triggers.forEach(function (trigger) {
      trigger.addEventListener('click', function (event) {
        event.preventDefault();

        var currentQuery = new URLSearchParams(window.location.search).get('q') || '';
        var href = trigger.getAttribute('href') || form.getAttribute('action') || '/search';
        openModal(currentQuery, href);
      });
    });

    closeButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        closeModal();
      });
    });

    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        closeModal();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !modal.hidden) {
        closeModal();
      }
    });

    input.addEventListener('input', function () {
      scheduleSearch(input.value.trim());
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      searchNow(input.value.trim());
    });
  });
}());
