/*!
 * WebBlocks UI v2.27.0 (https://webblocksui.com/)
 * Copyright 2026 WebBlocks UI
 * Licensed under MIT
 */

/* ============================================================
   WebBlocks UI — Storage Utilities (utils/storage.js)

   Safe localStorage wrapper used by theme.js and any other
   module that needs to persist state across page loads.
   Exposed on window.WBStorage — internal API for WB modules.
   ============================================================ */

(function () {
  'use strict';

  // ── Safe localStorage access ──────────────────────────────
  // Falls back silently in private browsing or when storage
  // is blocked by browser policy.

  function get(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }

  function set(key, value) {
    try { localStorage.setItem(key, value); } catch (e) {}
  }

  function remove(key) {
    try { localStorage.removeItem(key); } catch (e) {}
  }

  // ── Public API ────────────────────────────────────────────

  window.WBStorage = {
    get:    get,
    set:    set,
    remove: remove
  };

})();
/* ============================================================
   WebBlocks UI — DOM Utilities (utils/dom.js)

   Shared helpers used across all WB modules.
   Exposed on window.WBDom — not intended for direct use by
   page authors; internal API for WB modules only.
   ============================================================ */

(function () {
  'use strict';

  // ── Focusable elements selector ───────────────────────────
  // Used by modal.js, drawer.js (focus trap)

  var FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(', ');

  // ── Focus trap ────────────────────────────────────────────
  // Keeps Tab focus cycling within a container element.
  // Call inside a keydown handler.

  function trapFocus(e, container) {
    if (e.key !== 'Tab') return;
    var focusable = Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR));
    if (!focusable.length) return;

    var first = focusable[0];
    var last  = focusable[focusable.length - 1];

    if (e.shiftKey) {
      if (document.activeElement === first) {
        e.preventDefault();
        last.focus();
      }
    } else {
      if (document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  // ── Focus first focusable element in container ────────────

  function focusFirst(container) {
    requestAnimationFrame(function () {
      var first = container.querySelector(FOCUSABLE_SELECTOR);
      if (first) first.focus();
    });
  }

  // ── Emit a custom event ───────────────────────────────────

  function emit(element, eventName, detail) {
    element.dispatchEvent(new CustomEvent(eventName, {
      bubbles: true,
      detail: detail || {}
    }));
  }

  // ── Debounce ──────────────────────────────────────────────
  // Returns a debounced version of fn.

  function debounce(fn, delay) {
    var timer;
    return function () {
      var args = arguments;
      var ctx  = this;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
    };
  }

  // ── Resolve a target element from a selector string ───────

  function resolveTarget(selector) {
    if (!selector) return null;
    if (selector === 'body') return document.body;
    return document.querySelector(selector);
  }

  // ── Public API ────────────────────────────────────────────

  window.WBDom = {
    FOCUSABLE:     FOCUSABLE_SELECTOR,
    trapFocus:     trapFocus,
    focusFirst:    focusFirst,
    emit:          emit,
    debounce:      debounce,
    resolveTarget: resolveTarget
  };

})();
/* ============================================================
   WebBlocks UI — Overlay Utilities (utils/overlay.js)

   Internal shared overlay runtime used by anchored overlays,
   viewport overlays, and root-managed programmatic layers.
   Exposed on window.WBDom.overlay for WB modules only.
   ============================================================ */

(function () {
  'use strict';

  var html = document.documentElement;
  var ROOT_ID = 'wb-overlay-root';
  var activeStack = [];
  var CLOSE_REQUEST_REASONS = {
    escape: true,
    outside: true,
    'close-control': true,
    'dismiss-control': true
  };
  var uid = 0;
  var root = null;
  var layers = {};
  var visibilityStates = new WeakMap();

  if (html) html.classList.add('wb-js');

  function nextId(prefix) {
    uid += 1;
    return 'wb-' + prefix + '-' + uid;
  }

  function ensureId(el, prefix) {
    if (!el.id) el.id = nextId(prefix || 'overlay');
    return el.id;
  }

  function ensureRoot() {
    if (root && document.body && document.body.contains(root)) return root;
    root = document.getElementById(ROOT_ID);
    if (!root) {
      root = document.createElement('div');
      root.id = ROOT_ID;
      root.className = 'wb-overlay-root';
      document.body.appendChild(root);
    }
    return root;
  }

  function ensureLayer(name) {
    if (layers[name] && ensureRoot().contains(layers[name])) return layers[name];

    var layer = ensureRoot().querySelector('.wb-overlay-layer--' + name);
    if (!layer) {
      layer = document.createElement('div');
      layer.className = 'wb-overlay-layer wb-overlay-layer--' + name;
      ensureRoot().appendChild(layer);
    }

    layers[name] = layer;
    return layer;
  }

  function isVisibilityAncestor(node) {
    return !!(node && node.nodeType === 1 && (
      node.id === ROOT_ID ||
      (node.classList && node.classList.contains('wb-overlay-layer'))
    ));
  }

  function getVisibilityAncestors(element) {
    var ancestors = [];
    var node = element ? element.parentElement : null;

    while (node) {
      if (isVisibilityAncestor(node)) {
        ancestors.push(node);
      }

      if (node.id === ROOT_ID) break;
      node = node.parentElement;
    }

    return ancestors;
  }

  function hasActiveDescendant(ancestor) {
    return activeStack.some(function (instance) {
      return !!(instance.active && instance.element && ancestor.contains(instance.element));
    });
  }

  function trackVisibility(instance) {
    var ancestors;

    if (!instance || !instance.element) return;

    instance.restoreElementHidden = instance.element.hasAttribute('hidden');
    if (instance.restoreElementHidden) {
      instance.element.hidden = false;
    }

    ancestors = getVisibilityAncestors(instance.element);
    instance.visibilityAncestors = ancestors;

    ancestors.forEach(function (ancestor) {
      var state = visibilityStates.get(ancestor);

      if (!state) {
        state = {
          count: 0,
          originalHidden: ancestor.hasAttribute('hidden')
        };
        visibilityStates.set(ancestor, state);
      }

      state.count += 1;

      if (state.originalHidden) {
        ancestor.hidden = false;
      }
    });
  }

  function untrackVisibility(instance) {
    var ancestors = instance && instance.visibilityAncestors ? instance.visibilityAncestors : [];

    if (instance && instance.restoreElementHidden) {
      instance.element.hidden = true;
      instance.restoreElementHidden = false;
    }

    ancestors.forEach(function (ancestor) {
      var state = visibilityStates.get(ancestor);

      if (!state) return;

      state.count = Math.max(0, state.count - 1);

      if (state.count === 0) {
        if (state.originalHidden && !hasActiveDescendant(ancestor)) {
          ancestor.hidden = true;
        }
        visibilityStates.delete(ancestor);
        return;
      }

      if (state.originalHidden) {
        ancestor.hidden = false;
      }
    });

    if (instance) {
      instance.visibilityAncestors = [];
    }
  }

  function getPlacement(value) {
    return typeof value === 'function' ? value() : (value || 'bottom-start');
  }

  function getSide(placement) {
    return String(placement || 'bottom-start').split('-')[0] || 'bottom';
  }

  function getAlign(placement) {
    return String(placement || 'bottom-start').split('-')[1] || 'start';
  }

  function computeCoords(rect, width, height, placement, offset) {
    var side = getSide(placement);
    var align = getAlign(placement);
    var top = rect.bottom + offset;
    var left = rect.left;

    if (side === 'top') top = rect.top - height - offset;
    if (side === 'right') left = rect.right + offset;
    if (side === 'left') left = rect.left - width - offset;

    if (side === 'bottom' || side === 'top') {
      if (align === 'end') left = rect.right - width;
      if (align === 'center') left = rect.left + ((rect.width - width) / 2);
    }

    if (side === 'right' || side === 'left') {
      top = rect.top;
      if (align === 'end') top = rect.bottom - height;
      if (align === 'center') top = rect.top + ((rect.height - height) / 2);
    }

    return { top: top, left: left };
  }

  function fitsWithinViewport(coords, width, height, padding) {
    return coords.top >= padding &&
      coords.left >= padding &&
      coords.top + height <= window.innerHeight - padding &&
      coords.left + width <= window.innerWidth - padding;
  }

  function pickPlacement(rect, width, height, preferred, offset, padding) {
    var side = getSide(preferred);
    var align = getAlign(preferred);
    var opposite = {
      bottom: 'top',
      top: 'bottom',
      left: 'right',
      right: 'left'
    }[side] || 'top';
    var placements = [preferred, opposite + '-' + align];

    for (var i = 0; i < placements.length; i += 1) {
      var coords = computeCoords(rect, width, height, placements[i], offset);
      if (fitsWithinViewport(coords, width, height, padding)) {
        return { placement: placements[i], coords: coords };
      }
    }

    return {
      placement: placements[0],
      coords: computeCoords(rect, width, height, placements[0], offset)
    };
  }

  function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
  }

  function syncBodyLock() {
    var lock = activeStack.some(function (instance) {
      return instance.active && instance.lockScroll;
    });

    document.body.classList.toggle('wb-overlay-lock', lock);
  }

  function isDialogInstance(instance) {
    return !!(instance && instance.layer === 'dialog');
  }

  function canReceiveFocus(element) {
    return !!(
      element &&
      typeof element.focus === 'function' &&
      document.contains(element) &&
      !element.hasAttribute('disabled') &&
      element.getAttribute('aria-hidden') !== 'true'
    );
  }

  function getParentOverlay(instance) {
    var i;

    if (!instance || !instance.trigger) return null;

    for (i = activeStack.length - 1; i >= 0; i -= 1) {
      var candidate = activeStack[i];
      if (!candidate.active || candidate === instance) continue;
      if (candidate.element && candidate.element.contains(instance.trigger)) return candidate;
      if (candidate.panel && candidate.panel.contains(instance.trigger)) return candidate;
    }

    return null;
  }

  function shouldElevateAnchored(instance) {
    return !!(instance && instance.layer === 'anchored' && getParentOverlay(instance));
  }

  function getBackdropElement(instance) {
    var el;

    if (!instance || !instance.backdrop) return null;
    if (instance.backdropElement && ensureLayer('dialog').contains(instance.backdropElement)) {
      return instance.backdropElement;
    }

    el = instance.backdropElement || document.createElement('div');
    el.className = 'wb-overlay-backdrop';
    el.hidden = true;
    el.setAttribute('data-wb-overlay-backdrop', 'true');
    el.setAttribute('data-wb-overlay-owner', instance.id);
    ensureLayer('dialog').appendChild(el);
    instance.backdropElement = el;
    return el;
  }

  function syncBackdropElement(instance) {
    var el = getBackdropElement(instance);

    if (!el) return;

    el.className = 'wb-overlay-backdrop' + (instance.backdropClass ? ' ' + instance.backdropClass : '');
    el.hidden = !instance.active;
    el.setAttribute('data-wb-overlay-owner', instance.id);
  }

  function updateLayering() {
    var dialogOffset = 0;
    var anchoredOffset = 0;

    activeStack.forEach(function (instance) {
      var element;
      var backdropElement;
      var zIndex;

      if (!instance.active || !instance.element) return;

      element = instance.element;
      backdropElement = instance.backdrop ? getBackdropElement(instance) : null;

      if (isDialogInstance(instance) || shouldElevateAnchored(instance)) {
        zIndex = 'calc(var(--wb-z-modal) + ' + (dialogOffset + 1) + ')';
        if (backdropElement) {
          backdropElement.style.zIndex = 'calc(var(--wb-z-modal) + ' + dialogOffset + ')';
          syncBackdropElement(instance);
        }
        dialogOffset += 2;
      } else if (instance.layer === 'anchored') {
        zIndex = 'calc(var(--wb-z-dropdown) + ' + anchoredOffset + ')';
        anchoredOffset += 1;
      } else {
        zIndex = '';
      }

      element.style.zIndex = zIndex;
    });

    activeStack.forEach(function (instance) {
      if (!instance.active || !instance.backdropElement) return;
      if (!instance.backdrop) return;
      syncBackdropElement(instance);
    });
  }

  function syncInteractions() {
    var topDialog = getTopmost(function (instance) {
      return instance.active && isDialogInstance(instance);
    });
    var topInteractive = getTopmost(function (instance) {
      return instance.active && instance.interactive !== false;
    });

    activeStack.forEach(function (instance) {
      if (!instance.element) return;

      if (!instance.active) {
        instance.element.removeAttribute('data-wb-overlay-interactive');
        return;
      }

      if (isDialogInstance(instance)) {
        instance.element.setAttribute('data-wb-overlay-interactive', instance === topInteractive && instance === topDialog ? 'true' : 'false');
        return;
      }

      instance.element.setAttribute('data-wb-overlay-interactive', instance === topInteractive ? 'true' : 'false');
    });
  }

  function syncStackState() {
    syncBodyLock();
    updateLayering();
    syncInteractions();
  }

  function normalizeCloseReason(reason) {
    if (reason === 'backdrop-click' || reason === 'outside-click') return 'outside';
    return reason || '';
  }

  function dispatchCloseRequest(instance, reason, originalEvent) {
    var event;

    if (!instance || !instance.element || !CLOSE_REQUEST_REASONS[reason]) return true;

    event = new CustomEvent('wb:overlay:close-request', {
      bubbles: true,
      cancelable: true,
      detail: {
        overlay: instance.element,
        type: instance.kind || 'overlay',
        reason: reason,
        opener: instance.trigger || null,
        trigger: instance.trigger || null,
        originalEvent: originalEvent || null
      }
    });

    return instance.element.dispatchEvent(event);
  }

  function registerActive(instance) {
    unregisterActive(instance);
    activeStack.push(instance);
  }

  function unregisterActive(instance) {
    activeStack = activeStack.filter(function (item) { return item !== instance; });
  }

  function portalToLayer(instance) {
    if (!instance.portal || !instance.element) return;

    if (!instance.placeholder && instance.element.parentNode) {
      instance.originalParent = instance.element.parentNode;
      instance.originalNextSibling = instance.element.nextSibling;
      instance.placeholder = document.createComment('wb-overlay:' + instance.id);
      instance.originalParent.insertBefore(instance.placeholder, instance.element);
    }

    ensureLayer(instance.layer).appendChild(instance.element);
    instance.element.setAttribute('data-wb-overlay-portaled', 'true');
    instance.element.setAttribute('data-wb-overlay-runtime', 'true');
    instance.element.setAttribute('data-wb-overlay-kind', instance.kind || 'overlay');
  }

  function mount(instance) {
    if (!instance || !instance.element) return;
    portalToLayer(instance);
    instance.element.setAttribute('data-wb-overlay-runtime', 'true');
    instance.element.setAttribute('data-wb-overlay-kind', instance.kind || 'overlay');
    instance.element.setAttribute('data-wb-overlay-layer', instance.layer || 'anchored');
    if (instance.backdrop) getBackdropElement(instance);
  }

  function restoreFromLayer(instance) {
    if (!instance.portal || !instance.element || !instance.placeholder || !instance.originalParent) return;

    instance.originalParent.insertBefore(instance.element, instance.placeholder);
    instance.originalParent.removeChild(instance.placeholder);
    instance.placeholder = null;
    instance.originalParent = null;
    instance.originalNextSibling = null;
    instance.element.removeAttribute('data-wb-overlay-portaled');
    instance.element.removeAttribute('data-wb-overlay-runtime');
    instance.element.removeAttribute('data-wb-overlay-kind');
    instance.element.removeAttribute('data-wb-overlay-layer');
  }

  function syncTriggerAria(instance, expanded) {
    if (!instance.trigger) return;

    if (instance.manageExpanded !== false) {
      instance.trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    if (instance.panel && instance.manageControls !== false && instance.kind !== 'tooltip') {
      instance.trigger.setAttribute('aria-controls', ensureId(instance.panel, instance.kind + '-panel'));
    }
  }

  function restoreFocus(instance) {
    var fallback;

    if (!instance.returnFocus) return;

    if (canReceiveFocus(instance.previouslyFocused)) {
      instance.previouslyFocused.focus();
      instance.previouslyFocused = null;
      return;
    }

    fallback = getTopmost(function (activeInstance) {
      return activeInstance.active && activeInstance.trapFocus;
    });

    if (fallback) {
      WBDom.focusFirst(fallback.panel || fallback.element);
    }

    instance.previouslyFocused = null;
  }

  function measureAnchored(instance) {
    var panel = instance.panel || instance.element;
    if (!panel || !instance.trigger) return;

    panel.style.visibility = 'hidden';
    panel.style.pointerEvents = 'none';
    panel.style.top = '0px';
    panel.style.left = '0px';

    var panelRect = panel.getBoundingClientRect();
    var triggerRect = instance.trigger.getBoundingClientRect();
    var preferred = getPlacement(instance.placement);
    var offset = instance.offset == null ? 8 : instance.offset;
    var padding = instance.viewportPadding == null ? 8 : instance.viewportPadding;
    var picked = pickPlacement(triggerRect, panelRect.width, panelRect.height, preferred, offset, padding);
    var coords = picked.coords;

    coords.left = clamp(coords.left, padding, Math.max(padding, window.innerWidth - panelRect.width - padding));
    coords.top = clamp(coords.top, padding, Math.max(padding, window.innerHeight - panelRect.height - padding));

    panel.style.top = Math.round(coords.top) + 'px';
    panel.style.left = Math.round(coords.left) + 'px';
    panel.style.visibility = '';
    panel.style.pointerEvents = '';
    panel.dataset.wbOverlayPlacement = picked.placement;
    panel.dataset.wbOverlaySide = getSide(picked.placement);

    if (instance.matchWidth) {
      panel.style.minWidth = Math.round(triggerRect.width) + 'px';
    }
  }

  function update(instance) {
    if (!instance || !instance.active) return;
    if (instance.position === 'anchored') measureAnchored(instance);
    if (typeof instance.onUpdate === 'function') instance.onUpdate(instance);
  }

  function updateAnchored() {
    activeStack.forEach(function (instance) {
      if (instance.active && instance.position === 'anchored') update(instance);
    });
  }

  function closeByGroup(group, except) {
    activeStack.slice().forEach(function (instance) {
      if (!instance.active || instance === except) return;
      if (instance.group === group) requestClose(instance, 'group-switch');
    });
  }

  function requestClose(instance, reason, options) {
    var normalizedReason;

    if (!instance || !instance.active) return;

    options = options || {};
    normalizedReason = normalizeCloseReason(reason);

    if (options.userInitiated !== false && CLOSE_REQUEST_REASONS[normalizedReason]) {
      if (!dispatchCloseRequest(instance, normalizedReason, options.originalEvent)) return;
    }

    if (typeof instance.onRequestClose === 'function') {
      instance.onRequestClose(normalizedReason, instance, options.originalEvent || null);
      return;
    }

    close(instance, normalizedReason);
  }

  function open(instance) {
    if (!instance || !instance.element) return;

    if (instance.group && instance.exclusive !== false) {
      closeByGroup(instance.group, instance);
    }

    instance.previouslyFocused = document.activeElement;
    instance.active = true;

    portalToLayer(instance);
    trackVisibility(instance);

    instance.element.classList.add('is-open');
    if (typeof instance.onToggle === 'function') instance.onToggle(true, instance);
    syncTriggerAria(instance, true);
    registerActive(instance);
    syncStackState();
    update(instance);

    if (instance.autoFocus === true) {
      WBDom.focusFirst(instance.panel || instance.element);
    }

    if (typeof instance.onAfterOpen === 'function') instance.onAfterOpen(instance);
  }

  function close(instance, reason) {
    if (!instance || !instance.element || !instance.active) return;

    instance.active = false;
    instance.element.classList.remove('is-open');
    if (typeof instance.onToggle === 'function') instance.onToggle(false, instance);
    syncTriggerAria(instance, false);
    unregisterActive(instance);
    untrackVisibility(instance);
    if (instance.backdropElement) {
      instance.backdropElement.hidden = true;
    }
    if (instance.restoreOnClose !== false) {
      restoreFromLayer(instance);
    }
    syncStackState();

    if (typeof instance.onAfterClose === 'function') instance.onAfterClose(instance, reason);
    restoreFocus(instance);
  }

  function closeAll(filter) {
    activeStack.slice().forEach(function (instance) {
      if (!instance.active) return;
      if (typeof filter === 'function' && !filter(instance)) return;
      requestClose(instance, 'close-all');
    });
  }

  function getTopmost(predicate) {
    for (var i = activeStack.length - 1; i >= 0; i -= 1) {
      var instance = activeStack[i];
      if (!instance.active) continue;
      if (!predicate || predicate(instance)) return instance;
    }
    return null;
  }

  function create(options) {
    options = options || {};

    return {
      id: options.id || nextId(options.kind || 'overlay'),
      kind: options.kind || 'overlay',
      group: options.group || null,
      layer: options.layer || 'anchored',
      element: options.element || null,
      panel: options.panel || options.element || null,
      trigger: options.trigger || null,
      portal: options.portal !== false,
      exclusive: options.exclusive !== false,
      position: options.position || null,
      placement: options.placement || 'bottom-start',
      offset: options.offset,
      viewportPadding: options.viewportPadding,
      matchWidth: !!options.matchWidth,
      backdrop: !!options.backdrop,
      backdropClass: options.backdropClass || '',
      lockScroll: !!options.lockScroll,
      trapFocus: !!options.trapFocus,
      autoFocus: options.autoFocus !== false,
      returnFocus: options.returnFocus !== false,
      restoreOnClose: options.restoreOnClose !== false,
      manageExpanded: options.manageExpanded !== false,
      manageControls: options.manageControls !== false,
      outsideClose: options.outsideClose !== false,
      escapeClose: options.escapeClose !== false,
      interactive: options.interactive !== false,
      onToggle: options.onToggle,
      onUpdate: options.onUpdate,
      onRequestClose: options.onRequestClose,
      onAfterOpen: options.onAfterOpen,
      onAfterClose: options.onAfterClose,
      backdropElement: null,
      placeholder: null,
      originalParent: null,
      originalNextSibling: null,
      previouslyFocused: null,
      active: false,
      visibilityAncestors: [],
      restoreElementHidden: false
    };
  }

  document.addEventListener('click', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('wb-overlay-backdrop')) {
      var ownerId = e.target.dataset.wbOverlayOwner;
      var owner = getTopmost(function (instance) {
        return instance.active && instance.backdrop && (!ownerId || instance.id === ownerId);
      });
      if (owner) requestClose(owner, 'outside', { originalEvent: e });
      return;
    }

    var top = getTopmost(function (instance) {
      return instance.active && instance.outsideClose !== false;
    });

    if (!top) return;
    if (top.element && top.element.contains(e.target)) return;
    if (top.trigger && top.trigger.contains(e.target)) return;
    requestClose(top, 'outside', { originalEvent: e });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var esc = getTopmost(function (instance) {
        return instance.active && instance.escapeClose !== false;
      });
      if (esc) {
        e.preventDefault();
        requestClose(esc, 'escape', { originalEvent: e });
        return;
      }
    }

    if (e.key === 'Tab') {
      var trap = getTopmost(function (instance) {
        return instance.active && instance.trapFocus;
      });
      if (trap) WBDom.trapFocus(e, trap.panel || trap.element);
    }
  });

  window.addEventListener('resize', updateAnchored);
  window.addEventListener('scroll', updateAnchored, true);

  WBDom.overlay = {
    ensureRoot: ensureRoot,
    ensureLayer: ensureLayer,
    ensureId: ensureId,
    create: create,
    mount: mount,
    open: open,
    close: close,
    requestClose: requestClose,
    closeAll: closeAll,
    update: update,
    updateAnchored: updateAnchored,
    getTopmost: getTopmost
  };

})();
/* ============================================================
   WebBlocks UI — Theme Engine (theme.js)

   Axes:
     data-mode     light | dark | auto         (on <html>, CSS-native)
     data-preset   modern | minimal | ...      (on <html>, JS bundle)
      data-accent   ocean | forest | sunset | royal | mint | amber | rose | slate-fire
     data-radius   sharp | default | soft      (on <html>, CSS override)
     data-density  compact | default | ...     (on <html>, CSS override)
     data-shadow   flat | default | soft       (on <html>, CSS override)
     data-font     system | modern | editorial (on <html>, CSS override)

   Storage keys:  wb-mode, wb-preset, wb-accent, wb-radius,
                  wb-density, wb-shadow, wb-font

   Button hooks:  data-wb-mode-cycle
                  data-wb-mode-label-light|dark|auto  (optional host-localized labels)
                  data-wb-mode-set="light|dark|auto"
                  data-wb-preset-set="modern|minimal|..."
                  data-wb-accent-set="ocean|..."
                  data-wb-radius-set="sharp|default|soft"
                  data-wb-density-set="compact|default|comfortable"
                  data-wb-shadow-set="flat|default|soft"
                  data-wb-font-set="system|modern|editorial"

   Public API:    WBTheme.setMode(v)   WBTheme.getMode()
                  WBTheme.setPreset(v) WBTheme.getPreset()
                  WBTheme.setAccent(v) WBTheme.getAccent()
                  WBTheme.setRadius(v) WBTheme.getRadius()
                  WBTheme.setDensity(v) WBTheme.getDensity()
                  WBTheme.setShadow(v) WBTheme.getShadow()
                  WBTheme.setFont(v)   WBTheme.getFont()
   ============================================================ */

(function () {
  'use strict';

  // ── Storage keys ──────────────────────────────────────────
  var MODE_KEY    = 'wb-mode';
  var PRESET_KEY  = 'wb-preset';
  var ACCENT_KEY  = 'wb-accent';
  var RADIUS_KEY  = 'wb-radius';
  var DENSITY_KEY = 'wb-density';
  var SHADOW_KEY  = 'wb-shadow';
  var FONT_KEY    = 'wb-font';

  // ── Valid values ──────────────────────────────────────────
  var VALID_MODES     = ['light', 'dark', 'auto'];
  var VALID_PRESETS   = ['modern', 'minimal', 'editorial', 'playful', 'corporate'];
  var VALID_ACCENTS   = ['ocean', 'forest', 'sunset', 'royal', 'mint', 'amber', 'rose', 'slate-fire'];
  var VALID_RADII     = ['sharp', 'default', 'soft'];
  var VALID_DENSITIES = ['compact', 'default', 'comfortable'];
  var VALID_SHADOWS   = ['flat', 'default', 'soft'];
  var VALID_FONTS     = ['system', 'modern', 'editorial'];

  // ── Defaults ──────────────────────────────────────────────
  var DEFAULT_MODE    = 'auto';
  var DEFAULT_PRESET  = null;   // no preset by default
  var DEFAULT_ACCENT  = 'ocean';
  var DEFAULT_RADIUS  = 'default';
  var DEFAULT_DENSITY = 'default';
  var DEFAULT_SHADOW  = 'default';
  var DEFAULT_FONT    = 'modern';

  // ── Named presets ─────────────────────────────────────────
  // Each preset is a full bundle of axis values. When applied,
  // all axes are set simultaneously. Individual axes can still
  // be overridden after applying a preset.
  var PRESETS = {
    modern: {
      accent: 'ocean', radius: 'default', density: 'default',
      shadow: 'default', font: 'modern'
    },
    minimal: {
      accent: 'ocean', radius: 'sharp', density: 'compact',
      shadow: 'flat', font: 'system'
    },
    editorial: {
      accent: 'sunset', radius: 'soft', density: 'comfortable',
      shadow: 'soft', font: 'editorial'
    },
    playful: {
      accent: 'rose', radius: 'soft', density: 'comfortable',
      shadow: 'soft', font: 'modern'
    },
    corporate: {
      accent: 'royal', radius: 'sharp', density: 'default',
      shadow: 'flat', font: 'system'
    }
  };

  // ── localStorage helper ───────────────────────────────────
  // Delegates to WBStorage (utils/storage.js)

  function ls(key, val) {
    if (val === undefined) return WBStorage.get(key);
    if (val === null) { WBStorage.remove(key); return; }
    WBStorage.set(key, val);
  }

  // ── OS dark preference ────────────────────────────────────
  function prefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
  }

  function getHtmlAttr(attrName) {
    return document.documentElement.getAttribute(attrName);
  }

  function getCurrentValue(attrName, validValues, fallback) {
    var value = getHtmlAttr(attrName);
    return validValues.includes(value) ? value : fallback;
  }

  function getCurrentMode() {
    return getCurrentValue('data-mode', VALID_MODES, DEFAULT_MODE);
  }

  function getCurrentPreset() {
    var value = getHtmlAttr('data-preset');
    return VALID_PRESETS.includes(value) ? value : null;
  }

  function getCurrentAccent() {
    return getCurrentValue('data-accent', VALID_ACCENTS, DEFAULT_ACCENT);
  }

  function getCurrentRadius() {
    return getCurrentValue('data-radius', VALID_RADII, DEFAULT_RADIUS);
  }

  function getCurrentDensity() {
    return getCurrentValue('data-density', VALID_DENSITIES, DEFAULT_DENSITY);
  }

  function getCurrentShadow() {
    return getCurrentValue('data-shadow', VALID_SHADOWS, DEFAULT_SHADOW);
  }

  function getCurrentFont() {
    return getCurrentValue('data-font', VALID_FONTS, DEFAULT_FONT);
  }

  // ── Apply individual axes ─────────────────────────────────

  function applyMode(mode) {
    if (!VALID_MODES.includes(mode)) mode = DEFAULT_MODE;
    document.documentElement.setAttribute('data-mode', mode);
    syncButtons('[data-wb-mode-set]', mode);
  }

  function applyAccent(accent) {
    if (!VALID_ACCENTS.includes(accent)) accent = DEFAULT_ACCENT;
    document.documentElement.setAttribute('data-accent', accent);
    syncButtons('[data-wb-accent-set]', accent);
  }

  function applyRadius(radius) {
    if (!VALID_RADII.includes(radius)) radius = DEFAULT_RADIUS;
    var html = document.documentElement;
    if (radius === 'default') { html.removeAttribute('data-radius'); }
    else { html.setAttribute('data-radius', radius); }
    syncButtons('[data-wb-radius-set]', radius);
  }

  function applyDensity(density) {
    if (!VALID_DENSITIES.includes(density)) density = DEFAULT_DENSITY;
    var html = document.documentElement;
    if (density === 'default') { html.removeAttribute('data-density'); }
    else { html.setAttribute('data-density', density); }
    syncButtons('[data-wb-density-set]', density);
  }

  function applyShadow(shadow) {
    if (!VALID_SHADOWS.includes(shadow)) shadow = DEFAULT_SHADOW;
    var html = document.documentElement;
    if (shadow === 'default') { html.removeAttribute('data-shadow'); }
    else { html.setAttribute('data-shadow', shadow); }
    syncButtons('[data-wb-shadow-set]', shadow);
  }

  function applyFont(font) {
    if (!VALID_FONTS.includes(font)) font = DEFAULT_FONT;
    document.documentElement.setAttribute('data-font', font);
    syncButtons('[data-wb-font-set]', font);
  }

  // ── Preset apply (bundles all axes) ───────────────────────

  function applyPreset(preset) {
    if (!VALID_PRESETS.includes(preset)) return;
    var def = PRESETS[preset];
    document.documentElement.setAttribute('data-preset', preset);
    // Apply each axis (also saves to localStorage via setters)
    setAccent(def.accent);
    setRadius(def.radius);
    setDensity(def.density);
    setShadow(def.shadow);
    setFont(def.font);
    syncButtons('[data-wb-preset-set]', preset);
  }

  // ── Persist + apply (public setters) ─────────────────────

  function setMode(mode) {
    if (!VALID_MODES.includes(mode)) mode = DEFAULT_MODE;
    ls(MODE_KEY, mode);
    applyMode(mode);
  }

  function cycleMode() {
    var current = ls(MODE_KEY) || DEFAULT_MODE;
    var next = current === 'light' ? 'dark' : current === 'dark' ? 'auto' : 'light';
    setMode(next);
    syncCycleButtons();
  }

  // Update icon on data-wb-mode-cycle buttons to reflect current mode
  function syncCycleButtons() {
    var mode = ls(MODE_KEY) || DEFAULT_MODE;
    var iconMap = { light: 'sun', dark: 'moon', auto: 'sun-moon' };
    var icon = iconMap[mode] || 'sun-moon';
    document.querySelectorAll('[data-wb-mode-cycle]').forEach(function (btn) {
      // Update <i> class if present
      var i = btn.querySelector('i.wb-icon');
      if (i) {
        i.className = i.className.replace(/wb-icon-sun(-moon)?|wb-icon-moon/g, '').trim();
        i.classList.add('wb-icon-' + icon);
      }
      // Update title/aria-label. Host-provided localized labels
      // (data-wb-mode-label-light|dark|auto) override the English
      // defaults, mirroring the data-wb-password-label-* pattern.
      var defaultLabels = { light: 'Light mode', dark: 'Dark mode', auto: 'Auto mode' };
      var label = btn.getAttribute('data-wb-mode-label-' + mode) || defaultLabels[mode];
      btn.setAttribute('title', label);
      btn.setAttribute('aria-label', label);
    });
  }

  function setPreset(preset) {
    if (!VALID_PRESETS.includes(preset)) return;
    ls(PRESET_KEY, preset);
    applyPreset(preset);
  }

  function setAccent(accent) {
    if (!VALID_ACCENTS.includes(accent)) accent = DEFAULT_ACCENT;
    ls(ACCENT_KEY, accent);
    applyAccent(accent);
  }

  function setRadius(radius) {
    if (!VALID_RADII.includes(radius)) radius = DEFAULT_RADIUS;
    ls(RADIUS_KEY, radius);
    applyRadius(radius);
  }

  function setDensity(density) {
    if (!VALID_DENSITIES.includes(density)) density = DEFAULT_DENSITY;
    ls(DENSITY_KEY, density);
    applyDensity(density);
  }

  function setShadow(shadow) {
    if (!VALID_SHADOWS.includes(shadow)) shadow = DEFAULT_SHADOW;
    ls(SHADOW_KEY, shadow);
    applyShadow(shadow);
  }

  function setFont(font) {
    if (!VALID_FONTS.includes(font)) font = DEFAULT_FONT;
    ls(FONT_KEY, font);
    applyFont(font);
  }

  // ── Sync UI buttons ───────────────────────────────────────

  function syncButtons(selector, value) {
    document.querySelectorAll(selector).forEach(function (el) {
      var attrName = selector.replace('[', '').replace(']', '');
      el.classList.toggle('is-active', el.getAttribute(attrName) === value);
    });
  }

  function syncAllButtons() {
    syncButtons('[data-wb-mode-set]', getCurrentMode());
    syncButtons('[data-wb-preset-set]', getCurrentPreset() || '');
    syncButtons('[data-wb-accent-set]', getCurrentAccent());
    syncButtons('[data-wb-radius-set]', getCurrentRadius());
    syncButtons('[data-wb-density-set]', getCurrentDensity());
    syncButtons('[data-wb-shadow-set]', getCurrentShadow());
    syncButtons('[data-wb-font-set]', getCurrentFont());
  }

  // ── Event delegation ──────────────────────────────────────

  function attachListeners() {
    document.addEventListener('click', function (e) {
      var el;

      el = e.target.closest('[data-wb-mode-cycle]');
      if (el) { e.preventDefault(); cycleMode(); return; }

      el = e.target.closest('[data-wb-mode-set]');
      if (el) { e.preventDefault(); setMode(el.getAttribute('data-wb-mode-set')); return; }

      el = e.target.closest('[data-wb-preset-set]');
      if (el) { e.preventDefault(); setPreset(el.getAttribute('data-wb-preset-set')); return; }

      el = e.target.closest('[data-wb-accent-set]');
      if (el) { e.preventDefault(); setAccent(el.getAttribute('data-wb-accent-set')); return; }

      el = e.target.closest('[data-wb-radius-set]');
      if (el) { e.preventDefault(); setRadius(el.getAttribute('data-wb-radius-set')); return; }

      el = e.target.closest('[data-wb-density-set]');
      if (el) { e.preventDefault(); setDensity(el.getAttribute('data-wb-density-set')); return; }

      el = e.target.closest('[data-wb-shadow-set]');
      if (el) { e.preventDefault(); setShadow(el.getAttribute('data-wb-shadow-set')); return; }

      el = e.target.closest('[data-wb-font-set]');
      if (el) { e.preventDefault(); setFont(el.getAttribute('data-wb-font-set')); return; }
    });

    // Re-sync buttons after any Livewire/SPA navigation
    document.addEventListener('DOMContentLoaded', syncAllButtons);
  }

  // ── OS preference watcher ─────────────────────────────────

  function watchSystem() {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
      // If user is in "auto" mode, re-apply so syncButtons reflects current visual state
      var stored = ls(MODE_KEY);
      if (!stored || stored === 'auto') syncButtons('[data-wb-mode-set]', 'auto');
    });
  }

  // ── Init ──────────────────────────────────────────────────

  function init() {
    // Mode (must run first — affects surface colors)
    var storedMode = ls(MODE_KEY);
    applyMode(VALID_MODES.includes(storedMode) ? storedMode : getCurrentMode());

    // Preset stays source-of-truth on the document when no stored override exists.
    var storedPreset = ls(PRESET_KEY);
    if (storedPreset && VALID_PRESETS.includes(storedPreset)) {
      document.documentElement.setAttribute('data-preset', storedPreset);
    } else if (getCurrentPreset()) {
      document.documentElement.setAttribute('data-preset', getCurrentPreset());
    } else {
      document.documentElement.removeAttribute('data-preset');
    }

    // Individual axes — restored from localStorage independently.
    // These may have been set by a previous preset OR by manual overrides.
    var storedAccent = ls(ACCENT_KEY);
    var storedRadius = ls(RADIUS_KEY);
    var storedDensity = ls(DENSITY_KEY);
    var storedShadow = ls(SHADOW_KEY);
    var storedFont = ls(FONT_KEY);

    applyAccent(VALID_ACCENTS.includes(storedAccent) ? storedAccent : getCurrentAccent());
    applyRadius(VALID_RADII.includes(storedRadius) ? storedRadius : getCurrentRadius());
    applyDensity(VALID_DENSITIES.includes(storedDensity) ? storedDensity : getCurrentDensity());
    applyShadow(VALID_SHADOWS.includes(storedShadow) ? storedShadow : getCurrentShadow());
    applyFont(VALID_FONTS.includes(storedFont) ? storedFont : getCurrentFont());

    attachListeners();
    watchSystem();
    // Sync cycle buttons after DOM is ready (icons need to reflect stored mode)
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', syncCycleButtons);
    } else {
      syncCycleButtons();
    }
  }

  // Run immediately — before paint — to prevent flash
  init();

  // ── Public API ────────────────────────────────────────────

  window.WBTheme = {
    setMode:     setMode,
    getMode:     getCurrentMode,

    setPreset:   setPreset,
    getPreset:   getCurrentPreset,

    setAccent:   setAccent,
    getAccent:   getCurrentAccent,

    setRadius:   setRadius,
    getRadius:   getCurrentRadius,

    setDensity:  setDensity,
    getDensity:  getCurrentDensity,

    setShadow:   setShadow,
    getShadow:   getCurrentShadow,

    setFont:     setFont,
    getFont:     getCurrentFont,

    // Convenience: get all current axis values
    getAll: function () {
      return {
        mode:    window.WBTheme.getMode(),
        preset:  window.WBTheme.getPreset(),
        accent:  window.WBTheme.getAccent(),
        radius:  window.WBTheme.getRadius(),
        density: window.WBTheme.getDensity(),
        shadow:  window.WBTheme.getShadow(),
        font:    window.WBTheme.getFont(),
      };
    },

    // Expose preset definitions for tooling / UI builders
    presets: PRESETS,

    // Re-sync cycle button icons after UI updates
    sync: syncCycleButtons,
  };

})();
/* ============================================================
   WebBlocks UI — Dropdown (dropdown.js)

   Usage:
     <div class="wb-dropdown">
       <button data-wb-toggle="dropdown" data-wb-target="#myMenu">
         Open
       </button>
       <ul class="wb-dropdown-menu" id="myMenu">
         <li><a class="wb-dropdown-item" href="#">Item</a></li>
       </ul>
     </div>

   Or self-contained (toggle + menu share the same .wb-dropdown parent):
     <div class="wb-dropdown">
       <button data-wb-toggle="dropdown">Open</button>
       <ul class="wb-dropdown-menu"> ... </ul>
     </div>
   ============================================================ */

(function () {
  'use strict';

  var instances = new WeakMap();

  function getMenu(trigger) {
    var targetId = trigger.getAttribute('data-wb-target') || trigger.getAttribute('aria-controls');
    var menu = targetId ? document.querySelector(targetId.charAt(0) === '#' ? targetId : ('#' + targetId)) : null;

    if (!menu) {
      // Fallback: first .wb-dropdown-menu in the same .wb-dropdown parent
      var parent = trigger.closest('.wb-dropdown');
      menu = parent ? parent.querySelector('.wb-dropdown-menu') : null;
    }

    if (!menu) return null;

    WBDom.overlay.ensureId(menu, 'dropdown-menu');
    trigger.setAttribute('aria-controls', menu.id);
    if (!trigger.getAttribute('data-wb-target')) {
      trigger.setAttribute('data-wb-target', '#' + menu.id);
    }

    return menu;
  }

  function getTrigger(menu) {
    var id = WBDom.overlay.ensureId(menu, 'dropdown-menu');
    return document.querySelector('[data-wb-toggle="dropdown"][data-wb-target="#' + id + '"]') ||
      document.querySelector('[data-wb-toggle="dropdown"][aria-controls="' + id + '"]') ||
      (menu.closest('.wb-dropdown') && menu.closest('.wb-dropdown').querySelector('[data-wb-toggle="dropdown"]'));
  }

  function getPlacement(trigger, menu) {
    var parent = trigger ? trigger.closest('.wb-dropdown') : menu.closest('.wb-dropdown');
    if (menu.hasAttribute('data-wb-placement')) return menu.getAttribute('data-wb-placement');
    if (trigger && trigger.hasAttribute('data-wb-placement')) return trigger.getAttribute('data-wb-placement');
    if (parent && parent.hasAttribute('data-wb-placement')) return parent.getAttribute('data-wb-placement');
    return (parent && parent.classList.contains('wb-dropdown-end')) || menu.classList.contains('wb-dropdown-menu-end')
      ? 'bottom-end'
      : 'bottom-start';
  }

  function ensureInstance(menu, trigger) {
    var instance = instances.get(menu);
    if (instance) {
      instance.trigger = trigger || instance.trigger;
      instance.placement = getPlacement(instance.trigger, menu);
      WBDom.overlay.mount(instance);
      return instance;
    }

    instance = WBDom.overlay.create({
      kind: 'dropdown',
      group: 'anchored-toggle',
      layer: 'anchored',
      element: menu,
      panel: menu,
      trigger: trigger,
      position: 'anchored',
      placement: getPlacement(trigger, menu),
      offset: 4,
      viewportPadding: 8,
      autoFocus: false,
      returnFocus: true,
      restoreOnClose: false,
      matchWidth: menu.hasAttribute('data-wb-match-width'),
      onAfterClose: function () {
        menu.classList.remove('is-leaving');
      }
    });

    instances.set(menu, instance);
    WBDom.overlay.mount(instance);
    return instance;
  }

  function init() {
    document.querySelectorAll('[data-wb-toggle="dropdown"]').forEach(function (trigger) {
      var menu = getMenu(trigger);
      if (!menu) return;
      ensureInstance(menu, trigger);
    });
  }

  function open(trigger, menu) {
    if (!menu || !trigger) return;
    var instance = ensureInstance(menu, trigger);
    instance.trigger = trigger;
    instance.placement = getPlacement(trigger, menu);
    WBDom.overlay.open(instance);
  }

  function close(menu) {
    if (!menu) return;
    var instance = ensureInstance(menu, getTrigger(menu));
    WBDom.overlay.close(instance, 'api');
  }

  function requestUserClose(menu, reason, originalEvent) {
    if (!menu) return;
    WBDom.overlay.requestClose(ensureInstance(menu, getTrigger(menu)), reason, {
      originalEvent: originalEvent || null
    });
  }

  function toggle(trigger) {
    var menu = getMenu(trigger);
    if (!menu) return;
    var instance = ensureInstance(menu, trigger);
    if (instance.active) {
      close(menu);
    } else {
      open(trigger, menu);
    }
  }

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-wb-toggle="dropdown"]');
    if (trigger) {
      toggle(trigger);
      return;
    }

    // Dismiss on data-wb-dismiss
    var dismiss = e.target.closest('[data-wb-dismiss="dropdown"]');
    if (dismiss) {
      var menu = dismiss.closest('.wb-dropdown-menu');
      if (menu) requestUserClose(menu, 'dismiss-control', e);
      return;
    }

    // Close dropdown when a menu item is clicked (but allow other handlers to run first)
    var menuItem = e.target.closest('.wb-dropdown-item');
    if (menuItem) {
      var menu = menuItem.closest('.wb-dropdown-menu');
      var instance = menu && instances.get(menu);
      if (instance && instance.active) {
        // Delay close slightly to allow other click handlers (like theme toggle) to run
        setTimeout(function () {
          var active = instances.get(menu);
          if (active && active.active) close(menu);
        }, 10);
        return;
      }
    }
  });

  // ── Public API ─────────────────────────────────────────────

  window.WBDropdown = {
    open: function (menuEl) {
      var trigger = getTrigger(menuEl);
      open(trigger, menuEl);
    },
    close: close,
    closeAll: function () {
      WBDom.overlay.closeAll(function (instance) {
        return instance.kind === 'dropdown';
      });
    }
  };

  init();

})();
/* ============================================================
   WebBlocks UI — Modal (modal.js)

   Usage:
     <!-- Trigger -->
     <button data-wb-toggle="modal" data-wb-target="#myModal">Open</button>

     <!-- Modal -->
     <div class="wb-modal" id="myModal" role="dialog" aria-modal="true" aria-labelledby="myModalTitle">
       <div class="wb-modal-dialog">
         <div class="wb-modal-header">
           <h5 class="wb-modal-title" id="myModalTitle">Title</h5>
           <button class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close">&times;</button>
         </div>
         <div class="wb-modal-body">Content</div>
         <div class="wb-modal-footer">
           <button class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">Cancel</button>
           <button class="wb-btn wb-btn-primary">Save</button>
         </div>
       </div>
     </div>
   Content-first viewer usage stays on the same public modal API.
   ============================================================ */

(function () {
  'use strict';

  var instances = new WeakMap();
  function staysInOverlayRoot(modal) {
    return !!(modal && modal.classList && modal.classList.contains('wb-cookie-consent-modal'));
  }

  function getActiveModal() {
    var top = WBDom.overlay.getTopmost(function (instance) {
      return instance.active && instance.kind === 'modal';
    });

    return top ? top.element : null;
  }

  function ensureInstance(modal, trigger) {
    var instance = instances.get(modal);
    if (instance) {
      instance.trigger = trigger || instance.trigger;
      return instance;
    }

    instance = WBDom.overlay.create({
      kind: 'modal',
      group: 'dialog',
      layer: 'dialog',
      element: modal,
      panel: modal.querySelector('.wb-modal-dialog') || modal,
      trigger: trigger,
      exclusive: false,
      backdrop: true,
      lockScroll: true,
      trapFocus: true,
      autoFocus: true,
      returnFocus: true,
      restoreOnClose: !staysInOverlayRoot(modal),
      onAfterOpen: function () {
        if (staysInOverlayRoot(modal)) {
          modal.hidden = false;
          modal.removeAttribute('aria-hidden');
        }
        WBDom.emit(modal, 'wb:modal:open');
      },
      onAfterClose: function () {
        if (staysInOverlayRoot(modal)) {
          modal.hidden = true;
          modal.setAttribute('aria-hidden', 'true');
        }
        WBDom.emit(modal, 'wb:modal:close');
      }
    });

    instances.set(modal, instance);
    return instance;
  }

  // ── Open ──────────────────────────────────────────────────

  function open(modal, trigger) {
    if (!modal) return;
    var instance = ensureInstance(modal, trigger);
    instance.trigger = trigger || instance.trigger;
    WBDom.overlay.open(instance);
  }

  // ── Close ─────────────────────────────────────────────────

  function close(modal) {
    if (!modal) modal = getActiveModal();
    if (!modal) return;
    WBDom.overlay.close(ensureInstance(modal), 'api');
  }

  function requestUserClose(modal, reason, originalEvent) {
    if (!modal) modal = getActiveModal();
    if (!modal) return;
    WBDom.overlay.requestClose(ensureInstance(modal), reason, {
      originalEvent: originalEvent || null
    });
  }

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('click', function (e) {
    // Trigger
    var trigger = e.target.closest('[data-wb-toggle="modal"]');
    if (trigger) {
      e.preventDefault();
      var target = trigger.getAttribute('data-wb-target');
      var modal  = target ? document.querySelector(target) : null;
      if (modal) open(modal, trigger);
      return;
    }

    // Dismiss button
    var dismiss = e.target.closest('[data-wb-dismiss="modal"]');
    if (dismiss) {
      requestUserClose(
        dismiss.closest('.wb-modal') || getActiveModal(),
        dismiss.classList.contains('wb-modal-close') ? 'close-control' : 'dismiss-control',
        e
      );
      return;
    }

    // Backdrop click — close if click is directly on .wb-modal (backdrop layer)
    var activeModal = getActiveModal();
    if (activeModal && e.target === activeModal) {
      requestUserClose(activeModal, 'outside', e);
    }
  });

  // ── Public API ─────────────────────────────────────────────

  window.WBModal = {
    open:      open,
    close:     close,
    getActive: getActiveModal
  };

})();
/* ============================================================
   WebBlocks UI — Cookie Consent (cookie-consent.js)

   Reusable HTML-first cookie consent behavior for banner, card,
   and preference-center modal compositions.
   ============================================================ */

(function () {
  'use strict';

  var CONSENT_KEY = 'wb-cookie-consent';
  var PREFERENCES_KEY = 'wb-cookie-consent-preferences';
  var OPTIONAL_CATEGORIES = ['preferences', 'analytics', 'marketing'];
  var dismissedForPage = false;
  var suppressModalReopen = false;
  var INERT_ROOT_SELECTOR = '[data-wb-cookie-consent-preview], [data-wb-demo-static], pre, code, template';

  function toArray(list) {
    return Array.prototype.slice.call(list || []);
  }

  function trim(value) {
    return value == null ? '' : String(value).trim();
  }

  function parseJson(value) {
    try {
      return JSON.parse(value);
    } catch (e) {
      return null;
    }
  }

  function normalizePreferences(preferences) {
    var source = preferences && typeof preferences === 'object' ? preferences : {};

    return {
      necessary: true,
      preferences: !!source.preferences,
      analytics: !!source.analytics,
      marketing: !!source.marketing
    };
  }

  function getStoredStatus() {
    var status = trim(WBStorage.get(CONSENT_KEY));
    return status === 'accepted' || status === 'rejected' || status === 'custom' ? status : '';
  }

  function readStoredPreferences() {
    var parsed = parseJson(WBStorage.get(PREFERENCES_KEY));
    return normalizePreferences(parsed);
  }

  function getStatusForPreferences(preferences) {
    var allEnabled = OPTIONAL_CATEGORIES.every(function (category) {
      return preferences[category] === true;
    });
    var allDisabled = OPTIONAL_CATEGORIES.every(function (category) {
      return preferences[category] === false;
    });

    if (allEnabled) return 'accepted';
    if (allDisabled) return 'rejected';
    return 'custom';
  }

  function getState() {
    return {
      status: getStoredStatus(),
      preferences: readStoredPreferences()
    };
  }

  function hasConsent() {
    return !!getStoredStatus();
  }

  function withModalReopenSuppressed(callback) {
    suppressModalReopen = true;

    try {
      return callback();
    } finally {
      suppressModalReopen = false;
    }
  }

  function getRoots() {
    return toArray(document.querySelectorAll('[data-wb-cookie-consent]')).filter(function (root) {
      return !isInertElement(root);
    });
  }

  function isInertElement(element) {
    return !!(element && element.closest && element.closest(INERT_ROOT_SELECTOR));
  }

  function isDisabledTrigger(element) {
    return !!(element && (element.disabled || element.getAttribute('aria-disabled') === 'true'));
  }

  function isModalRoot(root) {
    return !!(root && (root.classList.contains('wb-modal') || root.classList.contains('wb-cookie-consent-modal')));
  }

  function resolveRoot(target) {
    if (!target) return null;
    if (typeof target === 'string') target = document.querySelector(target);
    if (!target || target.nodeType !== 1 || isInertElement(target)) return null;
    return target;
  }

  function getFirstRoot() {
    var roots = getRoots();
    return roots.length ? roots[0] : null;
  }

  function getFirstModalRoot() {
    var roots = getRoots();
    var i;

    for (i = 0; i < roots.length; i += 1) {
      if (isModalRoot(roots[i])) return roots[i];
    }

    return null;
  }

  function getOpenRoot() {
    var activeModal = window.WBModal && WBModal.getActive ? WBModal.getActive() : null;
    if (activeModal && activeModal.hasAttribute('data-wb-cookie-consent')) return activeModal;

    return getRoots().find(function (root) {
      return !isModalRoot(root) && root.hidden === false;
    }) || null;
  }

  function canDismiss(root) {
    if (!root) return hasConsent();
    return hasConsent() || root.getAttribute('data-wb-cookie-consent-allow-dismiss') === 'true';
  }

  function hideRoot(root) {
    if (!root) return false;

    if (isModalRoot(root)) {
      if (window.WBModal) WBModal.close(root);
      root.hidden = true;
      root.setAttribute('aria-hidden', 'true');
      return true;
    }

    root.hidden = true;
    root.setAttribute('aria-hidden', 'true');
    return true;
  }

  function hideNonModalRootsExcept(exceptRoot) {
    getRoots().forEach(function (root) {
      if (root === exceptRoot || isModalRoot(root)) return;
      root.hidden = true;
      root.setAttribute('aria-hidden', 'true');
    });
  }

  function showRoot(root, trigger) {
    if (!root) return false;

    syncRoot(root, getState().preferences);
    hideNonModalRootsExcept(root);

    if (isModalRoot(root)) {
      root.hidden = false;
      root.removeAttribute('aria-hidden');
      if (window.WBModal) WBModal.open(root, trigger || null);
      return true;
    }

    root.hidden = false;
    root.removeAttribute('aria-hidden');
    return true;
  }

  function closeAll() {
    return withModalReopenSuppressed(function () {
      getRoots().forEach(function (root) {
        hideRoot(root);
      });
      return true;
    });
  }

  function syncRoot(root, preferences) {
    toArray(root.querySelectorAll('[data-wb-cookie-category]')).forEach(function (control) {
      var category = trim(control.getAttribute('data-wb-cookie-category')).toLowerCase();
      var required = control.getAttribute('data-wb-cookie-required') === 'true' || category === 'necessary';

      if (!category || typeof control.checked !== 'boolean') return;

      control.checked = required ? true : !!preferences[category];
      control.disabled = required;

      if (required) control.setAttribute('aria-disabled', 'true');
      else control.removeAttribute('aria-disabled');
    });
  }

  function syncAllRoots() {
    var preferences = getState().preferences;
    getRoots().forEach(function (root) {
      syncRoot(root, preferences);
    });
  }

  function readPreferencesFromScope(scope) {
    var preferences = readStoredPreferences();

    toArray((scope || document).querySelectorAll('[data-wb-cookie-category]')).forEach(function (control) {
      var category = trim(control.getAttribute('data-wb-cookie-category')).toLowerCase();
      var required = control.getAttribute('data-wb-cookie-required') === 'true' || category === 'necessary';

      if (!category || typeof control.checked !== 'boolean') return;
      preferences[category] = required ? true : !!control.checked;
    });

    return normalizePreferences(preferences);
  }

  function emitChange(status, preferences) {
    WBDom.emit(document.documentElement, 'wb:cookie-consent:change', {
      status: status,
      preferences: normalizePreferences(preferences),
      hasConsent: true,
      storageKeys: {
        consent: CONSENT_KEY,
        preferences: PREFERENCES_KEY
      }
    });
  }

  function save(preferences, status) {
    var normalized = normalizePreferences(preferences);
    var nextStatus = status || getStatusForPreferences(normalized);

    dismissedForPage = true;
    WBStorage.set(CONSENT_KEY, nextStatus);
    WBStorage.set(PREFERENCES_KEY, JSON.stringify(normalized));
    syncAllRoots();
    closeAll();
    emitChange(nextStatus, normalized);

    return {
      status: nextStatus,
      preferences: normalized
    };
  }

  function acceptAll() {
    return save({
      necessary: true,
      preferences: true,
      analytics: true,
      marketing: true
    }, 'accepted');
  }

  function rejectAll() {
    return save({
      necessary: true,
      preferences: false,
      analytics: false,
      marketing: false
    }, 'rejected');
  }

  function savePreferences(scope) {
    return save(readPreferencesFromScope(scope), 'custom');
  }

  function getPreferredOpenRoot(trigger) {
    var explicitTarget = trigger ? resolveRoot(trigger.getAttribute('data-wb-target')) : null;
    if (explicitTarget) return explicitTarget;
    return getFirstModalRoot() || getFirstRoot();
  }

  function showInitialRoot() {
    if (hasConsent()) {
      closeAll();
      syncAllRoots();
      return false;
    }

    if (dismissedForPage) {
      closeAll();
      return false;
    }

    return showRoot(getFirstRoot());
  }

  function open(target, trigger) {
    dismissedForPage = false;
    syncAllRoots();
    return showRoot(resolveRoot(target) || getFirstModalRoot() || getFirstRoot(), trigger || null);
  }

  function close(target) {
    var root = resolveRoot(target) || getOpenRoot();
    if (!root) return false;

    if (!hasConsent()) dismissedForPage = true;
    return closeAll();
  }

  function clear() {
    dismissedForPage = false;
    WBStorage.remove(CONSENT_KEY);
    WBStorage.remove(PREFERENCES_KEY);
    syncAllRoots();
    closeAll();
    showInitialRoot();
    return get();
  }

  function get() {
    return getState();
  }

  document.addEventListener('click', function (e) {
    var accept = e.target.closest('[data-wb-cookie-consent-accept]');
    var reject = e.target.closest('[data-wb-cookie-consent-reject]');
    var saveButton = e.target.closest('[data-wb-cookie-consent-save]');
    var openButton = e.target.closest('[data-wb-cookie-consent-open]');
    var closeButton = e.target.closest('[data-wb-cookie-consent-close]');
    var root;

    if (isDisabledTrigger(accept) || isDisabledTrigger(reject) || isDisabledTrigger(saveButton) || isDisabledTrigger(openButton) || isDisabledTrigger(closeButton)) {
      return;
    }

    if (isInertElement(e.target)) return;

    if (accept) {
      e.preventDefault();
      e.stopPropagation();
      acceptAll();
      return;
    }

    if (reject) {
      e.preventDefault();
      e.stopPropagation();
      rejectAll();
      return;
    }

    if (saveButton) {
      e.preventDefault();
      e.stopPropagation();
      root = saveButton.closest('[data-wb-cookie-consent]') || getFirstModalRoot() || getFirstRoot();
      savePreferences(root || document);
      return;
    }

    if (openButton) {
      e.preventDefault();
      e.stopPropagation();
      open(getPreferredOpenRoot(openButton), openButton);
      return;
    }

    if (closeButton) {
      root = closeButton.closest('[data-wb-cookie-consent]');

      if (!root) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }

      e.preventDefault();
      e.stopPropagation();
      hideRoot(root);
    }
  }, true);

  document.addEventListener('wb:modal:close', function (e) {
    var modal = e.target;

    if (!modal || !modal.hasAttribute('data-wb-cookie-consent')) return;
    if (suppressModalReopen || dismissedForPage) return;
    if (canDismiss(modal) || hasConsent()) return;

    requestAnimationFrame(function () {
      if (!hasConsent()) showInitialRoot();
    });
  });

  syncAllRoots();
  showInitialRoot();

  window.WBCookieConsent = {
    open: open,
    close: close,
    get: get,
    set: save,
    clear: clear,
    hasConsent: hasConsent
  };

})();
/* ============================================================
   WebBlocks UI — Gallery Pattern (gallery.js)

   Inline gallery pattern that populates a shared wb-modal viewer.
   The viewer remains a content-first wb-modal usage mode.
   ============================================================ */

(function () {
  'use strict';

  var activeViewer = null;

  function toArray(list) {
    return Array.prototype.slice.call(list || []);
  }

  function trim(value) {
    return value == null ? '' : String(value).trim();
  }

  function getModalSelector(trigger) {
    return trigger ? (trigger.getAttribute('data-wb-gallery-target') || trigger.getAttribute('data-wb-target')) : '';
  }

  function getViewerParts(modal) {
    return {
      image: modal.querySelector('.wb-gallery-viewer-image'),
      caption: modal.querySelector('.wb-gallery-viewer-caption'),
      meta: modal.querySelector('.wb-gallery-viewer-meta'),
      counter: modal.querySelector('.wb-gallery-viewer-counter'),
      prev: modal.querySelector('.wb-gallery-viewer-prev'),
      next: modal.querySelector('.wb-gallery-viewer-next')
    };
  }

  function getGallery(trigger) {
    return trigger ? trigger.closest('.wb-gallery') : null;
  }

  function getItems(gallery, modalSelector) {
    if (!gallery || !modalSelector) return [];

    return toArray(gallery.querySelectorAll('.wb-gallery-trigger')).filter(function (item) {
      return getModalSelector(item) === modalSelector;
    });
  }

  function readItem(trigger) {
    var item = trigger.closest('.wb-gallery-item') || trigger.parentElement;
    var image = trigger.querySelector('.wb-gallery-media') || trigger.querySelector('img');
    var caption = item ? item.querySelector('.wb-gallery-caption') : null;
    var meta = item ? item.querySelector('.wb-gallery-meta') : null;

    return {
      full: trim(trigger.getAttribute('data-wb-gallery-full')) || trim(trigger.getAttribute('href')) || (image ? trim(image.getAttribute('src')) : ''),
      alt: trim(trigger.getAttribute('data-wb-gallery-alt')) || (image ? trim(image.getAttribute('alt')) : ''),
      caption: trim(trigger.getAttribute('data-wb-gallery-caption')) || (caption ? trim(caption.textContent) : ''),
      meta: trim(trigger.getAttribute('data-wb-gallery-meta')) || (meta ? trim(meta.textContent) : ''),
      width: trim(trigger.getAttribute('data-wb-gallery-width')) || (image ? trim(image.getAttribute('width')) : ''),
      height: trim(trigger.getAttribute('data-wb-gallery-height')) || (image ? trim(image.getAttribute('height')) : '')
    };
  }

  function setText(element, value) {
    if (!element) return;
    var hasValue = !!trim(value);
    element.textContent = hasValue ? value : '';
    element.hidden = !hasValue;
  }

  function setDisabled(button, disabled) {
    if (!button) return;
    button.disabled = disabled;
    button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  }

  function syncModalLabel(state, item) {
    if (!state || !state.modal) return;

    var captionId = state.parts.caption && state.parts.caption.id ? state.parts.caption.id : '';
    var label = trim(item.caption) || trim(item.alt) || 'Gallery viewer';

    if (captionId && trim(item.caption)) {
      state.modal.setAttribute('aria-labelledby', captionId);
      state.modal.removeAttribute('aria-label');
      return;
    }

    state.modal.removeAttribute('aria-labelledby');
    state.modal.setAttribute('aria-label', label);
  }

  function emitChange(state, item) {
    if (!state || !state.modal) return;

    WBDom.emit(state.modal, 'wb:gallery:change', {
      index: state.index,
      count: state.items.length,
      item: item,
      trigger: state.items[state.index]
    });
  }

  function render(state) {
    if (!state || !state.items.length || state.index < 0 || state.index >= state.items.length) return;

    var item = readItem(state.items[state.index]);
    var parts = state.parts;

    if (parts.image) {
      parts.image.src = item.full;
      parts.image.alt = item.alt;

      if (item.width) parts.image.setAttribute('width', item.width);
      else parts.image.removeAttribute('width');

      if (item.height) parts.image.setAttribute('height', item.height);
      else parts.image.removeAttribute('height');
    }

    setText(parts.caption, item.caption);
    setText(parts.meta, item.meta);
    syncModalLabel(state, item);

    if (parts.counter) {
      parts.counter.textContent = (state.index + 1) + ' / ' + state.items.length;
    }

    setDisabled(parts.prev, state.index <= 0);
    setDisabled(parts.next, state.index >= state.items.length - 1);
    emitChange(state, item);
  }

  function buildState(trigger) {
    var modalSelector = getModalSelector(trigger);
    var modal = modalSelector ? document.querySelector(modalSelector) : null;
    var gallery = getGallery(trigger);
    var items = getItems(gallery, modalSelector);
    var index = items.indexOf(trigger);

    if (!modal || !gallery || index === -1) return null;

    return {
      modal: modal,
      gallery: gallery,
      items: items,
      index: index,
      parts: getViewerParts(modal)
    };
  }

  function openTrigger(trigger) {
    if (!trigger) return false;

    var state = buildState(trigger);
    if (!state) return false;

    activeViewer = state;
    render(state);
    WBModal.open(state.modal, trigger);
    return true;
  }

  function step(direction) {
    if (!activeViewer || WBModal.getActive() !== activeViewer.modal) return false;

    var nextIndex = activeViewer.index + direction;
    if (nextIndex < 0 || nextIndex >= activeViewer.items.length) return false;

    activeViewer.index = nextIndex;
    render(activeViewer);
    return true;
  }

  function onTriggerClick(e) {
    var trigger = e.target.closest('.wb-gallery .wb-gallery-trigger');
    if (!trigger || !getModalSelector(trigger)) return;

    e.preventDefault();
    openTrigger(trigger);
  }

  function onViewerControlClick(e) {
    var prev = e.target.closest('.wb-gallery-viewer-prev');
    var next = e.target.closest('.wb-gallery-viewer-next');

    if (prev && activeViewer && activeViewer.modal.contains(prev)) {
      e.preventDefault();
      step(-1);
      return;
    }

    if (next && activeViewer && activeViewer.modal.contains(next)) {
      e.preventDefault();
      step(1);
    }
  }

  function onKeydown(e) {
    if (!activeViewer || WBModal.getActive() !== activeViewer.modal) return;
    if (e.altKey || e.ctrlKey || e.metaKey) return;

    var active = document.activeElement;
    if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable)) {
      return;
    }

    if (e.key === 'ArrowLeft') {
      if (step(-1)) e.preventDefault();
      return;
    }

    if (e.key === 'ArrowRight') {
      if (step(1)) e.preventDefault();
    }
  }

  document.addEventListener('click', onTriggerClick);
  document.addEventListener('click', onViewerControlClick);
  document.addEventListener('keydown', onKeydown);

  document.addEventListener('wb:modal:close', function (e) {
    if (activeViewer && e.target === activeViewer.modal) {
      activeViewer = null;
    }
  });

  window.WBGallery = {
    open: function (trigger) {
      if (typeof trigger === 'string') trigger = document.querySelector(trigger);
      return openTrigger(trigger);
    },
    prev: function () {
      return step(-1);
    },
    next: function () {
      return step(1);
    },
    getActive: function () {
      return activeViewer;
    }
  };

})();
/* ============================================================
   WebBlocks UI — Slider Pattern (slider.js)

   Track-based slider runtime for wb-slider markup.
   ============================================================ */

(function () {
  'use strict';

  var states = new WeakMap();

  function toArray(list) {
    return Array.prototype.slice.call(list || []);
  }

  function boolAttr(element, name, fallback) {
    if (!element || !element.hasAttribute(name)) return fallback;
    var value = String(element.getAttribute(name) || '').toLowerCase();
    return value === 'true' || value === '1' || value === 'yes' || value === '';
  }

  function intAttr(element, name, fallback) {
    var value = parseInt(element ? element.getAttribute(name) : '', 10);
    return Number.isFinite(value) ? value : fallback;
  }

  function getParts(root) {
    return {
      viewport: root.querySelector('.wb-slider-viewport'),
      track: root.querySelector('.wb-slider-track'),
      prev: root.querySelector('[data-wb-slider-prev], .wb-slider-prev'),
      next: root.querySelector('[data-wb-slider-next], .wb-slider-next'),
      dots: root.querySelector('[data-wb-slider-dots], .wb-slider-dots')
    };
  }

  function getSlides(parts) {
    return parts.track ? toArray(parts.track.querySelectorAll(':scope > .wb-slide')) : [];
  }

  function readOptions(root) {
    return {
      autoplay: boolAttr(root, 'data-wb-slider-autoplay', false),
      interval: Math.max(1000, intAttr(root, 'data-wb-slider-interval', 6000)),
      loop: boolAttr(root, 'data-wb-slider-loop', true),
      pauseOnHover: boolAttr(root, 'data-wb-slider-pause-on-hover', true),
      keyboard: boolAttr(root, 'data-wb-slider-keyboard', true),
      swipe: boolAttr(root, 'data-wb-slider-swipe', true)
    };
  }

  function ensureDots(state) {
    if (!state.parts.dots || state.dots.length || state.slides.length < 2) return;

    state.slides.forEach(function (_, index) {
      var dot = document.createElement('button');
      dot.className = 'wb-slider-dot';
      dot.type = 'button';
      dot.setAttribute('data-wb-slider-dot', String(index));
      dot.setAttribute('aria-label', 'Show slide ' + (index + 1));
      state.parts.dots.appendChild(dot);
    });

    state.dots = toArray(state.parts.dots.querySelectorAll('.wb-slider-dot, [data-wb-slider-dot]'));
  }

  function setDisabled(button, disabled) {
    if (!button) return;
    button.disabled = disabled;
    button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  }

  function normalizeIndex(state, index) {
    if (!state.slides.length) return 0;
    if (state.options.loop) return (index + state.slides.length) % state.slides.length;
    return Math.max(0, Math.min(index, state.slides.length - 1));
  }

  function emit(root, name, detail) {
    if (window.WBDom && typeof window.WBDom.emit === 'function') {
      window.WBDom.emit(root, name, detail);
      return;
    }

    root.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail }));
  }

  function render(state) {
    var root = state.root;
    var count = state.slides.length;

    root.setAttribute('data-wb-slider-single', count <= 1 ? 'true' : 'false');

    if (state.parts.track) {
      state.parts.track.style.transform = 'translateX(' + (-state.index * 100) + '%)';
    }

    state.slides.forEach(function (slide, index) {
      var active = index === state.index;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', active ? 'false' : 'true');
    });

    state.dots.forEach(function (dot, index) {
      var active = index === state.index;
      dot.classList.toggle('is-active', active);
      dot.setAttribute('aria-current', active ? 'true' : 'false');
    });

    setDisabled(state.parts.prev, !state.options.loop && state.index <= 0);
    setDisabled(state.parts.next, !state.options.loop && state.index >= count - 1);

    emit(root, 'wb:slider:change', {
      index: state.index,
      count: count,
      slide: state.slides[state.index] || null
    });
  }

  function goTo(root, index) {
    var state = states.get(root);
    if (!state || !state.slides.length) return false;

    var nextIndex = normalizeIndex(state, index);
    if (nextIndex === state.index) return false;

    state.index = nextIndex;
    render(state);
    restartAutoplay(state);
    return true;
  }

  function step(root, direction) {
    var state = states.get(root);
    return state ? goTo(root, state.index + direction) : false;
  }

  function stopAutoplay(state) {
    if (!state || !state.timer) return;
    window.clearInterval(state.timer);
    state.timer = null;
  }

  function startAutoplay(state) {
    if (!state || !state.options.autoplay || state.slides.length < 2) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    stopAutoplay(state);
    state.timer = window.setInterval(function () {
      step(state.root, 1);
    }, state.options.interval);
  }

  function restartAutoplay(state) {
    if (!state || !state.options.autoplay) return;
    startAutoplay(state);
  }

  function onClick(e) {
    var prev = e.target.closest('[data-wb-slider-prev], .wb-slider-prev');
    var next = e.target.closest('[data-wb-slider-next], .wb-slider-next');
    var dot = e.target.closest('[data-wb-slider-dot], .wb-slider-dot');
    var control = prev || next || dot;
    var root = control ? control.closest('[data-wb-slider], .wb-slider') : null;
    var state = root ? states.get(root) : null;

    if (!state || !control) return;

    e.preventDefault();

    if (prev) {
      step(root, -1);
      return;
    }

    if (next) {
      step(root, 1);
      return;
    }

    var index = state.dots.indexOf(dot);
    if (index >= 0) goTo(root, index);
  }

  function onKeydown(e) {
    var root = e.target.closest ? e.target.closest('[data-wb-slider], .wb-slider') : null;
    var state = root ? states.get(root) : null;

    if (!state || !state.options.keyboard) return;
    if (e.altKey || e.ctrlKey || e.metaKey) return;

    if (e.key === 'ArrowLeft') {
      if (step(root, -1)) e.preventDefault();
      return;
    }

    if (e.key === 'ArrowRight') {
      if (step(root, 1)) e.preventDefault();
    }
  }

  function bindSwipe(state) {
    if (!state.options.swipe || !state.parts.viewport || state.slides.length < 2) return;

    var startX = 0;
    var startY = 0;
    var active = false;

    state.parts.viewport.addEventListener('pointerdown', function (e) {
      if (e.button != null && e.button !== 0) return;
      active = true;
      startX = e.clientX;
      startY = e.clientY;
    });

    state.parts.viewport.addEventListener('pointerup', function (e) {
      if (!active) return;
      active = false;

      var dx = e.clientX - startX;
      var dy = e.clientY - startY;

      if (Math.abs(dx) < 42 || Math.abs(dx) < Math.abs(dy)) return;
      step(state.root, dx < 0 ? 1 : -1);
    });

    state.parts.viewport.addEventListener('pointercancel', function () {
      active = false;
    });
  }

  function init(root) {
    if (typeof root === 'string') root = document.querySelector(root);
    if (!root || states.has(root)) return states.get(root) || null;

    var parts = getParts(root);
    var slides = getSlides(parts);

    if (!parts.track || !slides.length) return null;

    var activeIndex = Math.max(0, slides.findIndex(function (slide) {
      return slide.classList.contains('is-active');
    }));

    var state = {
      root: root,
      parts: parts,
      slides: slides,
      dots: parts.dots ? toArray(parts.dots.querySelectorAll('.wb-slider-dot, [data-wb-slider-dot]')) : [],
      options: readOptions(root),
      index: activeIndex,
      timer: null
    };

    states.set(root, state);
    ensureDots(state);
    render(state);
    bindSwipe(state);

    if (state.options.pauseOnHover) {
      root.addEventListener('mouseenter', function () {
        stopAutoplay(state);
      });
      root.addEventListener('mouseleave', function () {
        startAutoplay(state);
      });
    }

    startAutoplay(state);
    return state;
  }

  function initAll() {
    toArray(document.querySelectorAll('[data-wb-slider], .wb-slider[data-wb-slider-auto]')).forEach(init);
  }

  document.addEventListener('click', onClick);
  document.addEventListener('keydown', onKeydown);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  window.WBSlider = {
    init: init,
    initAll: initAll,
    goTo: goTo,
    next: function (root) {
      if (typeof root === 'string') root = document.querySelector(root);
      return step(root, 1);
    },
    prev: function (root) {
      if (typeof root === 'string') root = document.querySelector(root);
      return step(root, -1);
    },
    get: function (root) {
      if (typeof root === 'string') root = document.querySelector(root);
      return root ? states.get(root) || null : null;
    }
  };

})();
/* ============================================================
   WebBlocks UI — Tabs (tabs.js)

   Usage (two equivalent patterns):

   Pattern A — attribute-based:
     <div class="wb-tabs" data-wb-tabs>
       <div class="wb-tabs-nav" role="tablist">
         <button class="wb-tabs-btn is-active" data-wb-tab="panel1">Tab 1</button>
         <button class="wb-tabs-btn" data-wb-tab="panel2">Tab 2</button>
       </div>
       <div class="wb-tabs-panels">
         <div class="wb-tabs-panel is-active" id="panel1">Content 1</div>
         <div class="wb-tabs-panel" id="panel2">Content 2</div>
       </div>
     </div>

   Pattern B — class-based (simpler):
     <div class="wb-tabs">
       <div class="wb-tab-list">
         <button class="wb-tab-item is-active" data-wb-tab="panel1">Tab 1</button>
         <button class="wb-tab-item" data-wb-tab="panel2">Tab 2</button>
       </div>
       <div class="wb-tab-panels">
         <div class="wb-tab-panel is-active" id="panel1">Content 1</div>
         <div class="wb-tab-panel" id="panel2">Content 2</div>
       </div>
     </div>

   Optional — sync the active tab into a form field (data-wb-tabs-field):

     A host page whose tabs sit inside a <form> often needs to know, on the
     server, which tab was active when the form was submitted — for example
     to redisplay the same tab after a validation error, instead of silently
     reopening on the first one. Point the container at a field with
     data-wb-tabs-field (a selector resolved with container.querySelector);
     WBTabs writes the active tab id into it on every change, so the host
     never has to listen for wb:tabs:change itself just to keep a hidden
     input in sync.

     <div class="wb-tabs" data-wb-tabs data-wb-tabs-field="[data-tab-field]">
       <div class="wb-tabs-nav" role="tablist">
         <button class="wb-tabs-btn is-active" data-wb-tab="panel1">Tab 1</button>
         <button class="wb-tabs-btn" data-wb-tab="panel2">Tab 2</button>
       </div>
       <div class="wb-tabs-panels">
         <div class="wb-tabs-panel is-active" id="panel1">Content 1</div>
         <div class="wb-tabs-panel" id="panel2">Content 2</div>
       </div>
       <input type="hidden" name="active_tab" value="panel1" data-tab-field>
     </div>

     The field's initial value is the host's responsibility (it should match
     whichever panel is rendered .is-active on load); WBTabs only keeps it in
     sync from that point on, the same way the rest of the widget never
     initializes state that the server already rendered correctly.
   ============================================================ */

(function () {
  'use strict';

  // Accept both wb-tabs-btn and wb-tab-item; both wb-tabs-panel and wb-tab-panel
  var BTN_SEL   = '.wb-tabs-btn[data-wb-tab], .wb-tab-item[data-wb-tab]';
  var PANEL_SEL = '.wb-tabs-panel, .wb-tab-panel';

  function setPanelState(panel, isActive) {
    panel.classList.toggle('is-active', isActive);
    panel.hidden = !isActive;
    panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
  }

  function activate(container, targetId) {
    // Deactivate all tabs + panels in this container
    container.querySelectorAll(BTN_SEL).forEach(function (btn) {
      btn.classList.remove('is-active');
      btn.setAttribute('aria-selected', 'false');
      btn.setAttribute('tabindex', '-1');
    });
    container.querySelectorAll(PANEL_SEL).forEach(function (panel) {
      setPanelState(panel, false);
    });

    // Activate target
    var targetBtn = container.querySelector('[data-wb-tab="' + targetId + '"]');
    var targetPanel = container.querySelector('#' + targetId);

    if (targetBtn) {
      targetBtn.classList.add('is-active');
      targetBtn.setAttribute('aria-selected', 'true');
      targetBtn.removeAttribute('tabindex');
    }
    if (targetPanel) {
      setPanelState(targetPanel, true);
    }

    // Mirror into a host-declared form field, if any (data-wb-tabs-field).
    var fieldSelector = container.getAttribute('data-wb-tabs-field');
    if (fieldSelector) {
      var field = container.querySelector(fieldSelector);
      if (field) field.value = targetId;
    }

    // Emit event
    WBDom.emit(container, 'wb:tabs:change', { tabId: targetId });
  }

  // ── Keyboard navigation ────────────────────────────────────

  function handleKeydown(e, container) {
    var btns = Array.from(container.querySelectorAll(BTN_SEL));
    var idx  = btns.indexOf(document.activeElement);
    if (idx === -1) return;

    var next = null;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
      next = btns[(idx + 1) % btns.length];
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
      next = btns[(idx - 1 + btns.length) % btns.length];
    } else if (e.key === 'Home') {
      next = btns[0];
    } else if (e.key === 'End') {
      next = btns[btns.length - 1];
    }

    if (next) {
      e.preventDefault();
      next.focus();
      activate(container, next.getAttribute('data-wb-tab'));
    }
  }

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.wb-tabs-btn[data-wb-tab], .wb-tab-item[data-wb-tab]');
    if (!btn) return;
    var container = btn.closest('.wb-tabs');
    if (!container) return;
    e.preventDefault();
    activate(container, btn.getAttribute('data-wb-tab'));
  });

  document.addEventListener('keydown', function (e) {
    var btn = e.target.closest('.wb-tabs-btn, .wb-tab-item');
    if (!btn) return;
    var container = btn.closest('.wb-tabs');
    if (!container) return;
    handleKeydown(e, container);
  });

  // ── Public API ─────────────────────────────────────────────

  window.WBTabs = {
    activate: function (containerEl, tabId) {
      activate(containerEl, tabId);
    },
    activateById: function (tabId) {
      var btn = document.querySelector('[data-wb-tab="' + tabId + '"]');
      if (!btn) return;
      var container = btn.closest('[data-wb-tabs]');
      if (container) activate(container, tabId);
    }
  };

})();
/* WebBlocks UI — Chart. Dependency-free SVG enhancement of a semantic table.
 * Source values are numbers or missing cells; missing values never become zero.
 * Hosts own data, labels, formatting, aggregation and authorization.
 */
(function () {
  'use strict';

  var NS = 'http://www.w3.org/2000/svg';
  var instances = new WeakMap();
  var serial = 0;
  var MAX_ROWS = 500;
  var MAX_SERIES = 8;

  function node(tag, attributes, text, svg) {
    var el = svg ? document.createElementNS(NS, tag) : document.createElement(tag);
    Object.keys(attributes || {}).forEach(function (key) { el.setAttribute(key, attributes[key]); });
    if (text !== undefined) el.textContent = text;
    return el;
  }

  function emit(root, name, detail) {
    root.dispatchEvent(new CustomEvent('wb:chart:' + name, { bubbles: true, detail: detail }));
  }

  function read(root) {
    var sourceId = root.getAttribute('data-wb-chart-table');
    var table = sourceId ? document.getElementById(sourceId) : root.querySelector('table');
    if (!table || table.tagName !== 'TABLE' || !table.tHead || !table.tHead.rows.length) throw new Error('missing-table');
    var headers = Array.from(table.tHead.rows[0].cells);
    var count = headers.length - 1;
    var rows = Array.from(table.tBodies).flatMap(function (body) { return Array.from(body.rows); });
    var type = root.getAttribute('data-wb-chart') || 'line';
    var label = root.getAttribute('aria-label') || (table.caption && table.caption.textContent.trim());
    if (!label || count < 1 || count > MAX_SERIES || rows.length > MAX_ROWS || !['line', 'bar'].includes(type)) throw new Error('invalid-config');
    var series = headers.slice(1).map(function (header) {
      var name = header.textContent.trim();
      if (!name) throw new Error('invalid-config');
      return { label: name, values: [], display: [] };
    });
    var displayLabels = [];
    var labels = rows.map(function (row) {
      if (row.cells.length !== headers.length) throw new Error('invalid-row');
      series.forEach(function (item, index) {
        var cell = row.cells[index + 1];
        var raw = cell.hasAttribute('data-wb-chart-value') ? cell.getAttribute('data-wb-chart-value') : cell.textContent;
        raw = raw.trim();
        var value = raw === '' ? null : Number(raw);
        // Bound exponents to keep scale arithmetic and SVG coordinates finite.
        if (value !== null && (!/^[+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?$/i.test(raw) || !Number.isFinite(value) || Math.abs(value) > 1e100 || (value !== 0 && Math.abs(value) < 1e-100))) throw new Error('invalid-value');
        item.values.push(value);
        item.display.push(cell.textContent.trim() || (value === null ? '' : String(value)));
      });
      var labelCell = row.cells[0];
      var fullLabel = labelCell.textContent.trim();
      displayLabels.push((labelCell.getAttribute('data-wb-chart-label') || fullLabel).trim());
      return fullLabel;
    });
    return { table: table, label: label, type: type, labels: labels, displayLabels: displayLabels, series: series };
  }

  function scale(series) {
    var values = series.flatMap(function (item) { return item.values.filter(function (value) { return value !== null; }); });
    if (!values.length) return null;
    var min = Math.min(0, ...values);
    var max = Math.max(0, ...values);
    if (min === max) max = 1;
    var rough = (max - min) / 4;
    var magnitude = Math.pow(10, Math.floor(Math.log10(rough)));
    var fraction = rough / magnitude;
    var step = (fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10) * magnitude;
    min = Math.floor(min / step) * step;
    max = Math.ceil(max / step) * step;
    var ticks = [];
    var steps = Math.round((max - min) / step);
    for (var i = 0; i <= steps; i++) ticks.push(Number((min + step * i).toPrecision(12)) || 0);
    return { min: min, max: max, ticks: ticks };
  }

  function formatter(root) {
    var lang = root.getAttribute('lang') || (root.closest('[lang]') || document.documentElement).getAttribute('lang') || 'en';
    try { new Intl.NumberFormat(lang); } catch (_) { lang = 'en'; }
    return function (value) {
      return new Intl.NumberFormat(lang, {
        maximumSignificantDigits: 3,
        notation: value !== 0 && (Math.abs(value) >= 1e9 || Math.abs(value) < .001) ? 'scientific' : 'standard'
      }).format(value);
    };
  }

  function announce(state, selection, notify) {
    state.selection = selection;
    var activeMark = selection ? state.svg.querySelector('.wb-chart-mark[data-index="' + selection.index + '"][data-series="' + selection.series + '"]') : null;
    if (state.activeMark !== activeMark) {
      if (state.activeMark) state.activeMark.classList.remove('is-active');
      if (activeMark) activeMark.classList.add('is-active');
      state.activeMark = activeMark;
    }
    var text = state.root.getAttribute('data-wb-chart-help') || '';
    if (selection) {
      var item = state.data.series[selection.series];
      text = (state.data.series.length > 1 ? item.label + ' — ' : '') + state.data.displayLabels[selection.index] + ': ' + item.display[selection.index];
    }
    if (state.readout.textContent !== text) state.readout.textContent = text;
    if (notify && selection) emit(state.root, 'select', {
      index: selection.index, series: selection.series,
      label: state.data.labels[selection.index], value: state.data.series[selection.series].values[selection.index]
    });
  }

  function selectFromPointer(state, event) {
    if (!state.geometry || !state.data.labels.length) return;
    var rect = state.svg.getBoundingClientRect();
    if (!rect.width || !rect.height) return;
    var mark = event.target.closest && event.target.closest('.wb-chart-mark');
    if (mark && state.svg.contains(mark)) {
      announce(state, { index: Number(mark.getAttribute('data-index')), series: Number(mark.getAttribute('data-series')) }, false);
      return;
    }
    var g = state.geometry;
    var x = (event.clientX - rect.left) * g.width / rect.width;
    var y = (event.clientY - rect.top) * g.height / rect.height;
    if (x < g.left || x > g.right || y < g.top || y > g.bottom) return;
    var index = Math.max(0, Math.min(state.data.labels.length - 1, Math.round((x - g.left) / g.slot - .5)));
    var candidates = state.data.series.map(function (item, s) { return { series: s, value: item.values[index] }; })
      .filter(function (item) { return item.value !== null; });
    if (!candidates.length) { announce(state, null, false); return; }
    candidates.sort(function (a, b) { return Math.abs(g.y(a.value) - y) - Math.abs(g.y(b.value) - y); });
    announce(state, { index: index, series: candidates[0].series }, false);
  }

  function keyboard(state, event) {
    if (!state.geometry || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'Escape'].includes(event.key)) return;
    event.preventDefault();
    if (event.key === 'Escape') { announce(state, null, false); return; }
    var current = state.selection;
    var series = current ? current.series : state.data.series.findIndex(function (item) { return item.values.some(function (value) { return value !== null; }); });
    var index = current ? current.index : -1;
    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
      var direction = event.key === 'ArrowUp' ? -1 : 1;
      for (var s = 1; s <= state.data.series.length; s++) {
        var next = (series + direction * s + state.data.series.length) % state.data.series.length;
        var at = Math.max(0, index);
        if (state.data.series[next].values[at] !== null) { announce(state, { series: next, index: at }, true); return; }
      }
      return;
    }
    var reverse = event.key === 'ArrowLeft' || event.key === 'End';
    var step = reverse ? -1 : 1;
    var start = event.key === 'Home' ? 0 : event.key === 'End' ? state.data.labels.length - 1 : index < 0 ? (reverse ? state.data.labels.length - 1 : 0) : index + step;
    for (var i = start; i >= 0 && i < state.data.labels.length; i += step) {
      if (state.data.series[series].values[i] !== null) { announce(state, { series: series, index: i }, true); return; }
    }
  }

  function render(state) {
    var root = state.root;
    state.svg.replaceChildren();
    state.legend.replaceChildren();
    state.marks = [];
    state.activeMark = null;
    state.geometry = null;
    var domain = scale(state.data.series);
    state.viewport.hidden = !domain;
    state.legend.hidden = !domain || state.data.series.length === 1;
    root.classList.toggle('is-empty', !domain);
    if (!domain) {
      state.readout.textContent = root.getAttribute('data-wb-chart-empty') || '';
      state.selection = null;
      return;
    }
    var width = Math.max(320, state.viewport.clientWidth || 640);
    var height = Math.max(200, state.viewport.clientHeight || 288);
    var format = formatter(root);
    var left = Math.min(140, Math.max(48, ...domain.ticks.map(function (tick) { return format(tick).length * 7 + 16; })));
    var right = width - 16;
    var top = 16;
    var bottom = height - 44;
    var slot = (right - left) / Math.max(1, state.data.labels.length);
    var x = function (i) { return left + (i + .5) * slot; };
    var y = function (v) { return bottom - (v - domain.min) / (domain.max - domain.min) * (bottom - top); };
    state.geometry = { width: width, height: height, left: left, right: right, top: top, bottom: bottom, slot: slot, y: y };
    state.svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
    domain.ticks.forEach(function (tick) {
      state.svg.append(node('line', { x1: left, y1: y(tick), x2: right, y2: y(tick), class: tick === 0 ? 'wb-chart-axis' : 'wb-chart-grid' }, undefined, true));
      state.svg.append(node('text', { x: left - 8, y: y(tick) + 4, 'text-anchor': 'end', class: 'wb-chart-label' }, format(tick), true));
    });
    var longest = Math.max(1, ...state.data.displayLabels.map(function (label) { return Math.min(18, label.length); }));
    var interval = Math.max(1, Math.ceil(longest * 7 / slot));
    state.data.displayLabels.forEach(function (label, i) {
      if (i % interval !== 0) return;
      var halfLabel = Math.min(18, label.length) * 3.5;
      var labelX = Math.max(halfLabel + 4, Math.min(width - halfLabel - 4, x(i)));
      var text = node('text', { x: labelX, y: bottom + 24, 'text-anchor': 'middle', class: 'wb-chart-label' }, label.length > 18 ? label.slice(0, 17) + '…' : label, true);
      text.append(node('title', {}, label, true));
      state.svg.append(text);
    });
    state.data.series.forEach(function (item, s) {
      var group = node('g', { class: 'wb-chart-series wb-chart-series-' + (s + 1) }, undefined, true);
      var path = '';
      var connected = false;
      item.values.forEach(function (value, i) {
        if (value === null) { connected = false; return; }
        if (state.data.type === 'line') {
          path += (connected ? ' L ' : ' M ') + x(i) + ' ' + y(value);
          connected = true;
        }
      });
      if (state.data.type === 'line') group.append(node('path', { d: path, class: 'wb-chart-line' }, undefined, true));
      item.values.forEach(function (value, i) {
        if (value === null) return;
        var attributes = { class: 'wb-chart-mark', 'data-index': i, 'data-series': s };
        var mark;
        if (state.data.type === 'bar') {
          var barWidth = slot * .72 / state.data.series.length;
          mark = node('rect', Object.assign(attributes, {
            x: x(i) - slot * .36 + s * barWidth, y: Math.min(y(0), y(value)),
            width: Math.max(.1, barWidth - Math.min(2, barWidth * .1)), height: Math.abs(y(value) - y(0))
          }), undefined, true);
        } else {
          mark = node('circle', Object.assign(attributes, { cx: x(i), cy: y(value), r: 3 }), undefined, true);
        }
        mark.append(node('title', {}, (state.data.series.length > 1 ? item.label + ' — ' : '') + state.data.displayLabels[i] + ': ' + item.display[i], true));
        group.append(mark);
        state.marks.push(mark);
      });
      state.svg.append(group);
      var legendItem = node('li', { class: 'wb-chart-series wb-chart-series-' + (s + 1) });
      var swatch = node('svg', { class: 'wb-chart-swatch', viewBox: '0 0 24 8', 'aria-hidden': 'true', focusable: 'false' }, undefined, true);
      swatch.append(state.data.type === 'line'
        ? node('path', { d: 'M 0 4 L 24 4', class: 'wb-chart-line' }, undefined, true)
        : node('rect', { x: 0, y: 1, width: 24, height: 6, fill: 'currentColor' }, undefined, true));
      legendItem.append(swatch);
      legendItem.append(document.createTextNode(item.label));
      state.legend.append(legendItem);
    });
    announce(state, state.selection, false);
  }

  function update(root) {
    var state = instances.get(root);
    if (!state) return initOne(root);
    try {
      state.data = read(root);
      state.selection = null;
      state.viewport.setAttribute('aria-label', state.data.label);
      root.classList.remove('is-invalid');
      render(state);
      root.classList.add('is-ready');
      emit(root, 'ready', { type: state.data.type, rows: state.data.labels.length });
      return true;
    } catch (error) {
      root.classList.remove('is-ready', 'is-empty');
      root.classList.add('is-invalid');
      state.viewport.hidden = true;
      state.legend.hidden = true;
      state.geometry = null;
      state.readout.textContent = root.getAttribute('data-wb-chart-error') || '';
      emit(root, 'error', { code: error.message });
      return false;
    }
  }

  function initOne(root) {
    if (instances.has(root)) return update(root);
    var id = 'wb-chart-' + (++serial);
    var content = node('div', { class: 'wb-chart-content' });
    var viewport = node('div', { class: 'wb-chart-viewport', tabindex: '0', role: 'group', 'aria-describedby': id + '-help ' + id + '-value' });
    var svg = node('svg', { class: 'wb-chart-svg', 'aria-hidden': 'true', focusable: 'false' }, undefined, true);
    var help = node('p', { id: id + '-help', class: 'wb-sr-only' }, root.getAttribute('data-wb-chart-help') || '');
    var readout = node('p', { id: id + '-value', class: 'wb-chart-readout', role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true' });
    var legend = node('ul', { class: 'wb-chart-legend' });
    viewport.append(svg);
    content.append(viewport, help, legend, readout);
    root.prepend(content);
    var state = { root: root, content: content, viewport: viewport, svg: svg, legend: legend, readout: readout, marks: [], selection: null };
    instances.set(root, state);
    viewport.addEventListener('keydown', function (event) { keyboard(state, event); });
    viewport.addEventListener('pointermove', function (event) { if (event.pointerType !== 'touch') selectFromPointer(state, event); });
    viewport.addEventListener('pointerdown', function (event) { selectFromPointer(state, event); });
    var ready = update(root);
    if (window.ResizeObserver) {
      var lastWidth = 0;
      var lastHeight = 0;
      state.observer = new ResizeObserver(function () {
        var width = viewport.clientWidth;
        var height = viewport.clientHeight;
        if (width > 0 && height > 0 && (width !== lastWidth || height !== lastHeight)) {
          lastWidth = width;
          lastHeight = height;
          if (root.classList.contains('is-ready')) render(state);
        }
      });
      state.observer.observe(viewport);
    }
    return ready;
  }

  function init(scope) {
    scope = scope || document;
    if (scope.matches && scope.matches('[data-wb-chart]')) initOne(scope);
    scope.querySelectorAll('[data-wb-chart]').forEach(initOne);
  }

  function destroy(root) {
    var state = instances.get(root);
    if (!state) return;
    if (state.observer) state.observer.disconnect();
    state.content.remove();
    root.classList.remove('is-ready', 'is-empty', 'is-invalid');
    instances.delete(root);
    emit(root, 'destroy', {});
  }

  window.WBChart = { init: init, update: update, destroy: destroy };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(); });
  else init();
})();
/* ============================================================
   WebBlocks UI — Accordion (accordion.js)

   Usage (two equivalent patterns):

   Pattern A — attribute-based (full):
     <div class="wb-accordion" data-wb-accordion>
       <div class="wb-accordion-item">
         <button class="wb-accordion-trigger" data-wb-accordion-trigger
                 aria-expanded="false" aria-controls="acc1">
           Section Title
           <span class="wb-accordion-icon"></span>
         </button>
         <div class="wb-accordion-content" id="acc1">
           <div class="wb-accordion-body">Content here</div>
         </div>
       </div>
     </div>

   Pattern B — class-based (simpler):
     <div class="wb-accordion">
       <div class="wb-accordion-item is-open">
         <button class="wb-accordion-trigger">Section Title</button>
         <div class="wb-accordion-body">Content here</div>
       </div>
     </div>

   Options (on [data-wb-accordion]):
     data-wb-accordion-single="true"  — only one panel open at a time
   ============================================================ */

(function () {
  'use strict';

  // Trigger selector: data attribute (Pattern A) OR class-only (Pattern B)
  var TRIGGER_SEL = '[data-wb-accordion-trigger], .wb-accordion-trigger:not([data-wb-accordion-trigger])';

  function getItem(trigger) {
    return trigger.closest('.wb-accordion-item');
  }

  function getContent(trigger) {
    // Pattern A: aria-controls points to a wrapper element
    var id = trigger.getAttribute('aria-controls');
    if (id) return document.getElementById(id);
    var item = getItem(trigger);
    if (!item) return null;
    // Pattern A: .wb-accordion-content wrapper
    var content = item.querySelector('.wb-accordion-content');
    if (content) return content;
    // Pattern B: .wb-accordion-body used directly
    return item.querySelector('.wb-accordion-body');
  }

  function isOpen(trigger) {
    // Pattern A uses aria-expanded; Pattern B uses is-open on the item
    if (trigger.hasAttribute('aria-expanded')) {
      return trigger.getAttribute('aria-expanded') === 'true';
    }
    var item = getItem(trigger);
    return item ? item.classList.contains('is-open') : false;
  }

  // ── Open a single trigger ─────────────────────────────────

  function open(trigger) {
    var item = getItem(trigger);
    var content = getContent(trigger);
    if (trigger.hasAttribute('aria-expanded')) trigger.setAttribute('aria-expanded', 'true');
    trigger.classList.add('is-open');
    if (item) item.classList.add('is-open');
    if (content) {
      content.classList.add('is-open');
      // Animate height (only meaningful for wb-accordion-content with max-height transition)
      content.style.maxHeight = content.scrollHeight + 'px';
    }
  }

  // ── Close a single trigger ────────────────────────────────

  function close(trigger) {
    var item = getItem(trigger);
    var content = getContent(trigger);
    if (trigger.hasAttribute('aria-expanded')) trigger.setAttribute('aria-expanded', 'false');
    trigger.classList.remove('is-open');
    if (item) item.classList.remove('is-open');
    if (content) {
      content.classList.remove('is-open');
      content.style.maxHeight = '0';
    }
  }

  // ── Toggle ────────────────────────────────────────────────

  function toggle(trigger) {
    var accordion = trigger.closest('[data-wb-accordion]') || trigger.closest('.wb-accordion');
    var single = accordion && accordion.getAttribute('data-wb-accordion-single') === 'true';

    if (isOpen(trigger)) {
      close(trigger);
    } else {
      // In single mode, close all siblings first
      if (single && accordion) {
        accordion.querySelectorAll(TRIGGER_SEL).forEach(function (t) {
          if (t !== trigger && isOpen(t)) close(t);
        });
      }
      open(trigger);
    }

    // Emit event
    WBDom.emit(trigger, 'wb:accordion:toggle', { open: isOpen(trigger) });
  }

  // ── Recalculate open heights (e.g. after resize) ──────────

  function recalc() {
    document.querySelectorAll(TRIGGER_SEL).forEach(function (trigger) {
      if (!isOpen(trigger)) return;
      var content = getContent(trigger);
      if (content) content.style.maxHeight = content.scrollHeight + 'px';
    });
  }

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest(TRIGGER_SEL);
    if (trigger) {
      e.preventDefault();
      toggle(trigger);
    }
  });

  window.addEventListener('resize', WBDom.debounce(recalc, 150));

  // ── Public API ─────────────────────────────────────────────

  window.WBAccordion = {
    open:   open,
    close:  close,
    toggle: toggle,
    recalc: recalc
  };

})();
/* ============================================================
   WebBlocks UI — Sidebar (sidebar.js)

   Usage (toggle button — typically in .wb-navbar):
     <button data-wb-toggle="sidebar" data-wb-target="#appSidebar"
             aria-expanded="false" aria-controls="appSidebar">
       ☰
     </button>

     <div class="wb-sidebar-backdrop" id="appBackdrop" data-wb-sidebar-backdrop></div>
     <aside class="wb-sidebar" id="appSidebar"> ... </aside>

   The sidebar is controlled purely via the "is-open" class.
   On desktop (>768px) the sidebar is always visible via CSS —
   JS open/close only affects mobile.
   ============================================================ */

(function () {
  'use strict';

  function getSidebar(trigger) {
    var id = trigger.getAttribute('data-wb-target');
    return id ? document.querySelector(id) : document.querySelector('.wb-sidebar');
  }

  function getBackdrop(sidebar) {
    // Look for a sibling/nearby backdrop element
    var parent = sidebar.parentElement;
    return parent ? parent.querySelector('[data-wb-sidebar-backdrop]') : null;
  }

  function isOpen(sidebar) {
    return sidebar.classList.contains('is-open');
  }

  function open(sidebar) {
    sidebar.classList.add('is-open');
    var backdrop = getBackdrop(sidebar);
    if (backdrop) backdrop.classList.add('is-open');
    document.body.style.overflow = 'hidden'; // prevent scroll behind overlay

    // Sync trigger button aria
    syncTriggers(sidebar, true);
    WBDom.emit(sidebar, 'wb:sidebar:open');
  }

  function close(sidebar) {
    sidebar.classList.remove('is-open');
    var backdrop = getBackdrop(sidebar);
    if (backdrop) backdrop.classList.remove('is-open');
    document.body.style.overflow = '';

    syncTriggers(sidebar, false);
    WBDom.emit(sidebar, 'wb:sidebar:close');
  }

  function toggle(sidebar) {
    if (isOpen(sidebar)) {
      close(sidebar);
    } else {
      open(sidebar);
    }
  }

  function syncTriggers(sidebar, expanded) {
    var id = sidebar.id;
    if (!id) return;
    document.querySelectorAll('[data-wb-target="#' + id + '"]').forEach(function (btn) {
      btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      btn.classList.toggle('is-open', expanded);
    });
  }

  // ── Close sidebar on desktop resize ───────────────────────

  window.addEventListener('resize', WBDom.debounce(function () {
    if (window.innerWidth > 768) {
      document.querySelectorAll('.wb-sidebar.is-open').forEach(function (sidebar) {
        sidebar.classList.remove('is-open');
        var backdrop = getBackdrop(sidebar);
        if (backdrop) backdrop.classList.remove('is-open');
        document.body.style.overflow = '';
        syncTriggers(sidebar, false);
      });
    }
  }, 100));

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('click', function (e) {
    // Toggle button
    var trigger = e.target.closest('[data-wb-toggle="sidebar"]');
    if (trigger) {
      var sidebar = getSidebar(trigger);
      if (sidebar) toggle(sidebar);
      return;
    }

    // Backdrop click — close
    var backdrop = e.target.closest('[data-wb-sidebar-backdrop]');
    if (backdrop) {
      var openSidebar = document.querySelector('.wb-sidebar.is-open');
      if (openSidebar) close(openSidebar);
      return;
    }

    // Nav link click on mobile — close sidebar
    if (window.innerWidth <= 768) {
      var link = e.target.closest('.wb-sidebar-link[href]');
      if (link) {
        var openSb = document.querySelector('.wb-sidebar.is-open');
        if (openSb) close(openSb);
      }
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var openSidebar = document.querySelector('.wb-sidebar.is-open');
      if (openSidebar && window.innerWidth <= 768) close(openSidebar);
    }
  });

  // ── Public API ─────────────────────────────────────────────

  window.WBSidebar = {
    open:   open,
    close:  close,
    toggle: toggle,
    isOpen: isOpen
  };

})();
/* ============================================================
   WebBlocks UI — Section Nav (WBSectionNav)
   Runtime active-state sync for in-page wb-section-nav anchors.

   Usage:
     <nav class="wb-section-nav" aria-label="On this page">
       <ul class="wb-section-nav-list">
         <li class="wb-section-nav-item">
           <a class="wb-section-nav-link" href="#overview">Overview</a>
         </li>
       </ul>
     </nav>

   Behavior:
     - activates the matching link when the page hash targets a valid section
     - keeps the active link in sync while scrolling long-form content
     - ignores section-nav blocks that do not point at real in-page targets

   Public API:
     WBSectionNav.sync()
   ============================================================ */

(function () {
  'use strict';

  var instances = [];
  var isBound = false;
  var isScheduled = false;
  var scrollContainers = [];

  function normalizeHash(hash) {
    if (!hash || hash === '#') return '';

    try {
      return decodeURIComponent(hash.replace(/^#/, ''));
    } catch (error) {
      return hash.replace(/^#/, '');
    }
  }

  function getLinkHash(link) {
    var href = link.getAttribute('href') || '';
    if (href.charAt(0) !== '#') return '';
    return normalizeHash(href);
  }

  function isScrollable(element) {
    if (!element || element === document.body || element === document.documentElement) return false;

    var style = window.getComputedStyle(element);
    var overflowY = style.overflowY;
    return (overflowY === 'auto' || overflowY === 'scroll') && element.scrollHeight > element.clientHeight;
  }

  function getScrollContainer(element) {
    var current = element.parentElement;

    while (current) {
      if (isScrollable(current)) return current;
      current = current.parentElement;
    }

    return window;
  }

  function getScrollTop(container) {
    return container === window ? window.scrollY : container.scrollTop;
  }

  function getViewportHeight(container) {
    return container === window ? window.innerHeight : container.clientHeight;
  }

  function getReadingLine(container) {
    return getScrollTop(container) + Math.min(getViewportHeight(container) * 0.25, 160);
  }

  function getTargetTop(target, container) {
    var rect = target.getBoundingClientRect();

    if (container === window) {
      return rect.top + window.scrollY;
    }

    var containerRect = container.getBoundingClientRect();
    return rect.top - containerRect.top + container.scrollTop;
  }

  function setActive(instance, activeId) {
    if (instance.activeId === activeId) return;

    instance.activeId = activeId || null;

    instance.items.forEach(function (item) {
      var isActive = item.id === activeId;
      item.link.classList.toggle('is-active', isActive);

      if (isActive) {
        item.link.setAttribute('aria-current', 'location');
      } else if (item.link.getAttribute('aria-current') === 'location') {
        item.link.removeAttribute('aria-current');
      }
    });
  }

  function findByHash(instance, hash) {
    if (!hash) return null;

    for (var i = 0; i < instance.items.length; i += 1) {
      if (instance.items[i].id === hash) return instance.items[i];
    }

    return null;
  }

  function findByScroll(instance) {
    var active = instance.items[0] || null;
    var readingLine = getReadingLine(instance.scrollContainer);

    instance.items.forEach(function (item) {
      var top = getTargetTop(item.target, instance.scrollContainer);
      if (top - readingLine <= 0) active = item;
    });

    return active;
  }

  function isHashMatchCurrent(instance, match) {
    if (!match) return false;

    var readingLine = getReadingLine(instance.scrollContainer);
    var currentByScroll = findByScroll(instance);
    var matchTop = getTargetTop(match.target, instance.scrollContainer);
    var nextItem = null;
    var i;

    for (i = 0; i < instance.items.length; i += 1) {
      if (instance.items[i].id === match.id) {
        nextItem = instance.items[i + 1] || null;
        break;
      }
    }

    if (currentByScroll && currentByScroll.id === match.id) return true;
    if (readingLine < matchTop) return false;
    if (!nextItem) return true;

    return readingLine < getTargetTop(nextItem.target, instance.scrollContainer);
  }

  function syncInstance(instance) {
    if (!instance.items.length) return;

    var hashMatch = findByHash(instance, normalizeHash(window.location.hash));
    if (isHashMatchCurrent(instance, hashMatch)) {
      setActive(instance, hashMatch.id);
      return;
    }

    var scrollMatch = findByScroll(instance);
    setActive(instance, scrollMatch ? scrollMatch.id : null);
  }

  function syncAll() {
    instances.forEach(syncInstance);
  }

  function scheduleSync() {
    if (isScheduled) return;

    isScheduled = true;
    window.requestAnimationFrame(function () {
      isScheduled = false;
      syncAll();
    });
  }

  function collectInstance(nav) {
    var links = Array.from(nav.querySelectorAll('.wb-section-nav-link[href^="#"]'));
    var items = [];

    links.forEach(function (link) {
      var id = getLinkHash(link);
      var target = id ? document.getElementById(id) : null;

      if (!target) return;

      items.push({
        id: id,
        link: link,
        target: target
      });
    });

    if (!items.length) return null;

    return {
      nav: nav,
      items: items,
      scrollContainer: getScrollContainer(nav),
      activeId: null
    };
  }

  function bindEvents() {
    if (isBound) return;
    isBound = true;

    document.addEventListener('click', function (event) {
      var link = event.target.closest('.wb-section-nav-link[href^="#"]');
      if (!link) return;

      instances.forEach(function (instance) {
        if (!instance.nav.contains(link)) return;

        var id = getLinkHash(link);
        var match = findByHash(instance, id);
        if (match) setActive(instance, match.id);
      });
    });

    window.addEventListener('hashchange', scheduleSync);
    window.addEventListener('resize', scheduleSync, { passive: true });
    window.addEventListener('load', scheduleSync);

    scrollContainers.forEach(function (container) {
      container.addEventListener('scroll', scheduleSync, { passive: true });
    });
  }

  function init() {
    instances = Array.from(document.querySelectorAll('.wb-section-nav'))
      .map(collectInstance)
      .filter(Boolean);

    if (!instances.length) return;

    scrollContainers = instances
      .map(function (instance) { return instance.scrollContainer; })
      .filter(function (container, index, list) {
        return list.indexOf(container) === index;
      });

    bindEvents();
    syncAll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.WBSectionNav = {
    sync: syncAll
  };
})();
/* ============================================================
   WebBlocks UI — Nav Group (nav-group.js)
   Collapsible navigation groups for sidebars and menus.

   Usage:
     <div class="wb-nav-group" data-wb-nav-group>
       <button class="wb-nav-group-toggle">
         <span class="wb-nav-group-icon">...</span>
         <span class="wb-nav-group-label">Settings</span>
         <span class="wb-nav-group-arrow"></span>
       </button>
       <div class="wb-nav-group-items">
         <a class="wb-nav-group-item" href="#">Profile</a>
         <a class="wb-nav-group-item" href="#">Security</a>
       </div>
     </div>

   Options (data attributes on .wb-nav-group):
     data-wb-nav-group-open  — open by default
     data-wb-nav-group-accordion — close siblings when opening
   ============================================================ */

(function () {
  'use strict';

  // ── Toggle a single group ─────────────────────────────────

  function toggle(group) {
    var isOpen = group.classList.contains('is-open');

    // Accordion: close siblings in the same parent
    if (group.dataset.wbNavGroupAccordion !== undefined || group.closest('[data-wb-accordion]')) {
      var parent = group.parentElement;
      if (parent) {
        var siblings = Array.from(parent.querySelectorAll(':scope > [data-wb-nav-group]'));
        siblings.forEach(function (sibling) {
          if (sibling !== group) closeGroup(sibling);
        });
      }
    }

    if (isOpen) {
      closeGroup(group);
    } else {
      openGroup(group);
    }
  }

  function normalizeToggle(group, isOpen) {
    var toggle = group.querySelector('.wb-nav-group-toggle');
    if (!toggle) return;

    if (!toggle.hasAttribute('type') && toggle.tagName === 'BUTTON') toggle.setAttribute('type', 'button');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  function openGroup(group) {
    group.classList.add('is-open');
    normalizeToggle(group, true);
    WBDom.emit(group, 'wb:navgroup:open');
  }

  function closeGroup(group) {
    group.classList.remove('is-open');
    normalizeToggle(group, false);
    WBDom.emit(group, 'wb:navgroup:close');
  }

  // ── Auto-open groups with active children ─────────────────

  function autoOpenActive() {
    var groups = document.querySelectorAll('[data-wb-nav-group]');
    groups.forEach(function (group) {
      // Open if explicitly set
      if (group.dataset.wbNavGroupOpen !== undefined) {
        openGroup(group);
        return;
      }
      // Open if a child is active
      var items = group.querySelector('.wb-nav-group-items');
      if (items && items.querySelector('.is-active')) {
        openGroup(group);
        return;
      }

      // Keep the accessibility state in sync for initially closed groups too.
      normalizeToggle(group, false);
    });
  }

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('click', function (e) {
    var toggleBtn = e.target.closest('.wb-nav-group-toggle');
    if (!toggleBtn) return;

    var group = toggleBtn.closest('[data-wb-nav-group]');
    if (!group) return;

    e.preventDefault();
    toggle(group);
  });

  // ── Init on DOM ready ─────────────────────────────────────

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoOpenActive);
  } else {
    autoOpenActive();
  }

  // ── Public API ─────────────────────────────────────────────

  window.WBNavGroup = {
    open:   openGroup,
    close:  closeGroup,
    toggle: toggle,
    init:   autoOpenActive
  };

})();
/* ============================================================
   WebBlocks UI — Drawer (drawer.js)

   Usage:
     <!-- Trigger -->
     <button data-wb-toggle="drawer" data-wb-target="#myDrawer">Open</button>

     <!-- Drawer -->
     <div class="wb-drawer" id="myDrawer" role="dialog" aria-modal="true" aria-labelledby="myDrawerTitle">
       <div class="wb-drawer-header">
         <h5 class="wb-drawer-title" id="myDrawerTitle">Title</h5>
         <button class="wb-drawer-close" data-wb-dismiss="drawer" aria-label="Close">&times;</button>
       </div>
       <div class="wb-drawer-body">Content</div>
       <div class="wb-drawer-footer">
         <button class="wb-btn wb-btn-secondary" data-wb-dismiss="drawer">Cancel</button>
         <button class="wb-btn wb-btn-primary">Save</button>
       </div>
     </div>

     Shared overlay backdrop is managed by the runtime.
     Clicking outside the drawer panel closes it automatically.
   ============================================================ */

(function () {
  'use strict';

  var instances = new WeakMap();
  function getActiveDrawer() {
    var top = WBDom.overlay.getTopmost(function (instance) {
      return instance.active && instance.kind === 'drawer';
    });

    return top ? top.element : null;
  }

  function ensureInstance(drawer, trigger) {
    var instance = instances.get(drawer);
    if (instance) {
      instance.trigger = trigger || instance.trigger;
      return instance;
    }

    instance = WBDom.overlay.create({
      kind: 'drawer',
      group: 'dialog',
      layer: 'dialog',
      element: drawer,
      panel: drawer,
      trigger: trigger,
      exclusive: false,
      backdrop: true,
      lockScroll: true,
      trapFocus: true,
      autoFocus: true,
      returnFocus: true,
      onAfterOpen: function () {
        WBDom.emit(drawer, 'wb:drawer:open');
      },
      onAfterClose: function () {
        WBDom.emit(drawer, 'wb:drawer:close');
      }
    });

    instances.set(drawer, instance);
    return instance;
  }

  // ── Open ──────────────────────────────────────────────────

  function open(drawer, trigger) {
    if (!drawer) return;
    var instance = ensureInstance(drawer, trigger);
    instance.trigger = trigger || instance.trigger;
    WBDom.overlay.open(instance);
  }

  // ── Close ─────────────────────────────────────────────────

  function close(drawer) {
    if (!drawer) drawer = getActiveDrawer();
    if (!drawer) return;
    WBDom.overlay.close(ensureInstance(drawer), 'api');
  }

  function requestUserClose(drawer, reason, originalEvent) {
    if (!drawer) drawer = getActiveDrawer();
    if (!drawer) return;
    WBDom.overlay.requestClose(ensureInstance(drawer), reason, {
      originalEvent: originalEvent || null
    });
  }

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('click', function (e) {
    // Trigger
    var trigger = e.target.closest('[data-wb-toggle="drawer"]');
    if (trigger) {
      e.preventDefault();
      var target = trigger.getAttribute('data-wb-target');
      var drawer = target ? document.querySelector(target) : null;
      if (drawer) open(drawer, trigger);
      return;
    }

    // Dismiss button (data-wb-dismiss="drawer")
    var dismiss = e.target.closest('[data-wb-dismiss="drawer"]');
    if (dismiss) {
      requestUserClose(
        dismiss.closest('.wb-drawer') || getActiveDrawer(),
        dismiss.classList.contains('wb-drawer-close') ? 'close-control' : 'dismiss-control',
        e
      );
      return;
    }
  });

  // ── Public API ─────────────────────────────────────────────

  window.WBDrawer = {
    open:      open,
    close:     close,
    getActive: getActiveDrawer
  };

})();
/* ============================================================
   WebBlocks UI — Command Palette (command-palette.js)

   Usage:
     <!-- Trigger (keyboard shortcut handled automatically) -->
     <button data-wb-toggle="cmd" data-wb-target="#myCmdPalette">
       Search
     </button>

     <!-- Palette markup -->
     <div class="wb-cmd-backdrop" id="myCmdPalette" role="dialog"
          aria-modal="true" aria-label="Command palette">
       <div class="wb-cmd-dialog">
         <div class="wb-cmd-search">
           <span class="wb-cmd-search-icon">⌕</span>
           <input class="wb-cmd-input" type="text"
                  placeholder="Search commands..." autocomplete="off" />
         </div>
         <div class="wb-cmd-results">
           <!-- Groups populated by search or static HTML -->
         </div>
         <div class="wb-cmd-footer">
           <span class="wb-cmd-footer-hint">
             <kbd class="wb-cmd-kbd">↑↓</kbd> navigate
           </span>
           <span class="wb-cmd-footer-hint">
             <kbd class="wb-cmd-kbd">↵</kbd> select
           </span>
           <span class="wb-cmd-footer-hint">
             <kbd class="wb-cmd-kbd">Esc</kbd> close
           </span>
         </div>
       </div>
     </div>

   Default keyboard shortcut: Cmd/Ctrl + K
   Note: custom shortcut override is not wired in the shipped implementation.

   Items can be static HTML .wb-cmd-item elements, or provided via a
   custom search function assigned to WBCommandPalette.onSearch(query, callback).
   ============================================================ */

(function () {
  'use strict';

  var instances = new WeakMap();
  var selectedIndex = -1;
  var items = [];

  // ── External search handler ────────────────────────────── 
  // Assign: WBCommandPalette.onSearch = function(query, callback) { ... }
  var searchHandler = null;

  // ── Helpers ───────────────────────────────────────────────

  function getItems(palette) {
    return Array.from(palette.querySelectorAll('.wb-cmd-item'));
  }

  function clearSelection() {
    items.forEach(function (item) { item.classList.remove('is-selected'); });
    selectedIndex = -1;
  }

  function selectItem(index) {
    if (!items.length) return;
    if (index < 0) index = items.length - 1;
    if (index >= items.length) index = 0;

    clearSelection();
    selectedIndex = index;
    items[selectedIndex].classList.add('is-selected');
    items[selectedIndex].scrollIntoView({ block: 'nearest' });
  }

  function activateSelected() {
    if (selectedIndex < 0 || !items[selectedIndex]) return;
    items[selectedIndex].click();
  }

  function getActivePalette() {
    var top = WBDom.overlay.getTopmost(function (instance) {
      return instance.active && instance.kind === 'command-palette';
    });

    return top ? top.element : null;
  }

  function ensureInstance(palette, trigger) {
    var instance = instances.get(palette);
    if (instance) {
      instance.trigger = trigger || instance.trigger;
      return instance;
    }

    instance = WBDom.overlay.create({
      kind: 'command-palette',
      group: 'dialog',
      layer: 'dialog',
      element: palette,
      panel: palette.querySelector('.wb-cmd-dialog') || palette,
      trigger: trigger,
      exclusive: false,
      backdrop: true,
      backdropClass: 'wb-overlay-backdrop-dark',
      lockScroll: true,
      trapFocus: true,
      autoFocus: false,
      returnFocus: true,
      onAfterOpen: function () {
        palette.dispatchEvent(new CustomEvent('wb:cmd:open', { bubbles: true }));
      },
      onAfterClose: function () {
        palette.dispatchEvent(new CustomEvent('wb:cmd:close', { bubbles: true }));
      }
    });

    instances.set(palette, instance);
    return instance;
  }

  // ── Open ──────────────────────────────────────────────────

  function open(palette, trigger) {
    if (!palette) return;
    var instance = ensureInstance(palette, trigger);
    instance.trigger = trigger || instance.trigger;
    WBDom.overlay.open(instance);

    // Focus the input
    requestAnimationFrame(function () {
      var input = palette.querySelector('.wb-cmd-input');
      if (input) {
        input.value = '';
        input.focus();
        handleInput(input);
      }
      items = getItems(palette);
      selectedIndex = -1;
    });
  }

  // ── Close ─────────────────────────────────────────────────

  function close(palette) {
    if (!palette) palette = getActivePalette();
    if (!palette) return;
    WBDom.overlay.close(ensureInstance(palette), 'api');
  }

  function requestUserClose(palette, reason, originalEvent) {
    if (!palette) palette = getActivePalette();
    if (!palette) return;
    WBDom.overlay.requestClose(ensureInstance(palette), reason, {
      originalEvent: originalEvent || null
    });
  }

  // ── Input / search ─────────────────────────────────────── 

  function handleInput(input) {
    var palette = input.closest('.wb-cmd-backdrop');
    if (!palette) return;

    var query = input.value.trim().toLowerCase();

    // If custom search handler is set, delegate
    if (typeof searchHandler === 'function') {
      searchHandler(query, function (html) {
        var results = palette.querySelector('.wb-cmd-results');
        if (results) {
          results.innerHTML = html;
          items = getItems(palette);
          selectedIndex = -1;
        }
      });
      return;
    }

    // Built-in: filter visible items by text match
    items = getItems(palette);
    var anyVisible = false;

    items.forEach(function (item) {
      var text = (item.textContent || '').toLowerCase();
      var match = !query || text.includes(query);
      item.style.display = match ? '' : 'none';
      if (match) anyVisible = true;
    });

    // Show/hide empty state
    var emptyEl = palette.querySelector('.wb-cmd-empty');
    if (emptyEl) emptyEl.style.display = anyVisible ? 'none' : '';

    // Hide groups with no visible items
    var groups = palette.querySelectorAll('.wb-cmd-group');
    groups.forEach(function (group) {
      var visibleItems = Array.from(group.querySelectorAll('.wb-cmd-item'))
        .filter(function (i) { return i.style.display !== 'none'; });
      group.style.display = visibleItems.length ? '' : 'none';
    });

    // Reset selection
    items = items.filter(function (i) { return i.style.display !== 'none'; });
    selectedIndex = -1;

    palette.dispatchEvent(new CustomEvent('wb:cmd:search', {
      bubbles: true,
      detail: { query: query }
    }));
  }

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('click', function (e) {
    // Toggle trigger
    var trigger = e.target.closest('[data-wb-toggle="cmd"]');
    if (trigger) {
      e.preventDefault();
      var target = trigger.getAttribute('data-wb-target');
      var palette = target ? document.querySelector(target) : null;
      if (palette) open(palette, trigger);
      return;
    }

    // Backdrop click to close
    var backdrop = e.target.closest('.wb-cmd-backdrop');
    if (backdrop && e.target === backdrop) {
      requestUserClose(backdrop, 'outside', e);
      return;
    }

    // Item click — close after selection
    var item = e.target.closest('.wb-cmd-item');
    if (item && getActivePalette()) {
      // Allow item click to propagate first, then close
      setTimeout(function () { close(getActivePalette()); }, 80);
    }
  });

  document.addEventListener('keydown', function (e) {
    // Global shortcut: Cmd/Ctrl + K (or custom)
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      e.preventDefault();
      var activePalette = getActivePalette();
      if (activePalette) {
        close(activePalette);
      } else {
        // Find first palette with data-wb-cmd-default
        var palette = document.querySelector('[data-wb-cmd-default]') ||
                      document.querySelector('.wb-cmd-backdrop');
        if (palette) open(palette);
      }
      return;
    }

    var activePalette = getActivePalette();
    if (!activePalette) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        selectItem(selectedIndex + 1);
        break;
      case 'ArrowUp':
        e.preventDefault();
        selectItem(selectedIndex - 1);
        break;
      case 'Enter':
        e.preventDefault();
        activateSelected();
        break;
    }
  });

  document.addEventListener('input', function (e) {
    var input = e.target;
    if (!input.classList.contains('wb-cmd-input')) return;
    handleInput(input);
  });

  // ── Public API ─────────────────────────────────────────────

  window.WBCommandPalette = {
    open:      open,
    close:     close,
    getActive: getActivePalette,
    onSearch:  function (fn) { searchHandler = fn; }
  };

})();
/* ============================================================
   WebBlocks UI — Toast (toast.js)

   Usage (programmatic):
     WBToast.show('Saved successfully', { type: 'success' });
     WBToast.show('Something went wrong', { type: 'danger', duration: 6000 });
     WBToast.show('Upload complete', {
       type: 'success',
       title: 'Done',
       position: 'top-right',
       duration: 4000
     });
     WBToast.show('Manual', { duration: 0 }); // no auto-dismiss

   Usage (HTML declarative — dismiss button):
      <div class="wb-toast wb-toast-success" id="myToast" data-wb-toast-timeout="6000">
        <div class="wb-toast-body">Message</div>
        <button class="wb-toast-close" data-wb-dismiss="toast">&times;</button>
      </div>

   Options:
     message   {string}  Toast text (required)
     title     {string}  Optional bold title above message
     type      {string}  success | warning | danger | info (default: none)
      position  {string}  top-right (default) | top-center | top-left |
                          bottom-right | bottom-center | bottom-left
      duration  {number}  ms before auto-dismiss (default: 6000 for success/info,
                          0 for warning/danger, 0 = no auto-dismiss)
      closable  {boolean} show close button (default: true)
    ============================================================ */

(function () {
  'use strict';

  // ── Container cache ───────────────────────────────────────
  var containers = {};

  var POSITIONS = [
    'top-right', 'top-center', 'top-left',
    'bottom-right', 'bottom-center', 'bottom-left'
  ];

  var TRANSIENT_TYPES = { success: true, info: true };
  var PERSISTENT_TYPES = { warning: true, danger: true, error: true };
  var DEFAULT_TRANSIENT_DURATION = 6000;

  function getContainer(position) {
    position = position || 'top-right';
    if (containers[position]) return containers[position];

    var el = document.createElement('div');
    el.className = 'wb-toast-container';
    el.setAttribute('aria-live', 'polite');
    el.setAttribute('aria-atomic', 'true');
    if (position !== 'top-right') {
      el.classList.add('wb-toast-container-' + position);
    }
    WBDom.overlay.ensureLayer('toast').appendChild(el);
    containers[position] = el;
    return el;
  }

  // ── Dismiss a toast element ───────────────────────────────

  function dismiss(toast) {
    if (!toast || toast.classList.contains('is-leaving')) return;
    if (toast.__wbToastTimer) {
      clearTimeout(toast.__wbToastTimer);
      toast.__wbToastTimer = null;
    }
    toast.classList.add('is-leaving');

    var done = false;
    function remove() {
      if (done) return;
      done = true;
      if (toast.parentNode) toast.parentNode.removeChild(toast);
      WBDom.emit(toast, 'wb:toast:close');
    }

    toast.addEventListener('animationend', remove, { once: true });
    setTimeout(remove, 300); // fallback
  }

  // ── Icon SVG per type ─────────────────────────────────────

  var ICONS = {
    success: '<svg class="wb-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--wb-success)"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    warning: '<svg class="wb-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--wb-warning)"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    danger:  '<svg class="wb-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--wb-danger)"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    info:    '<svg class="wb-toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--wb-info)"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
  };

  // ── Show ──────────────────────────────────────────────────

  function show(message, opts) {
    opts = opts || {};

    var type      = opts.type     || null;
    var title     = opts.title    || null;
    var position  = POSITIONS.indexOf(opts.position) !== -1 ? opts.position : 'top-right';
    var duration  = opts.duration !== undefined ? opts.duration : defaultDuration(type);
    var closable  = opts.closable !== false;

    var toast = document.createElement('div');
    toast.className = 'wb-toast' + (type ? ' wb-toast-' + type : '');
    toast.setAttribute('role', type === 'warning' || type === 'danger' || type === 'error' ? 'alert' : 'status');

    var html = '';

    // Icon
    if (type && ICONS[type]) {
      html += ICONS[type];
    }

    // Body
    html += '<div class="wb-toast-body">';
    if (title) {
      html += '<span class="wb-toast-title">' + _escape(title) + '</span>';
    }
    html += _escape(message);
    html += '</div>';

    // Close button
    if (closable) {
      html += '<button type="button" class="wb-toast-close" data-wb-dismiss="toast" aria-label="Close">&times;</button>';
    }

    toast.innerHTML = html;
    if (duration !== undefined) {
      toast.setAttribute('data-wb-toast-timeout', String(duration));
    }

    var container = getContainer(position);
    container.appendChild(toast);
    initToast(toast);

    return toast;
  }

  function inferType(toast) {
    if (toast.classList.contains('wb-toast-success')) return 'success';
    if (toast.classList.contains('wb-toast-info')) return 'info';
    if (toast.classList.contains('wb-toast-warning')) return 'warning';
    if (toast.classList.contains('wb-toast-danger')) return 'danger';
    if (toast.classList.contains('wb-toast-error')) return 'error';
    return null;
  }

  function defaultDuration(type) {
    if (TRANSIENT_TYPES[type]) return DEFAULT_TRANSIENT_DURATION;
    if (PERSISTENT_TYPES[type]) return 0;
    return DEFAULT_TRANSIENT_DURATION;
  }

  function parseDuration(value) {
    if (value === null || value === undefined || value === '') return null;
    var duration = parseInt(value, 10);
    return isNaN(duration) ? null : Math.max(0, duration);
  }

  function resolveDuration(toast) {
    var autoDismiss = toast.getAttribute('data-wb-auto-dismiss');
    if (autoDismiss === 'false' || autoDismiss === '0') return 0;
    var explicit = parseDuration(toast.getAttribute('data-wb-toast-timeout'));
    if (explicit !== null) return explicit;
    return defaultDuration(inferType(toast));
  }

  function initToast(toast) {
    if (!toast || toast.__wbToastInitialized) return toast;
    toast.__wbToastInitialized = true;

    var duration = resolveDuration(toast);
    WBDom.emit(toast, 'wb:toast:open', { duration: duration });

    if (duration > 0) {
      toast.__wbToastTimer = setTimeout(function () { dismiss(toast); }, duration);
    }

    return toast;
  }

  function initAll(root) {
    root = root || document;
    if (root.matches && root.matches('.wb-toast')) initToast(root);
    Array.prototype.forEach.call(root.querySelectorAll ? root.querySelectorAll('.wb-toast') : [], initToast);
  }

  function observeToasts() {
    if (!window.MutationObserver || !document.body) return;

    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        Array.prototype.forEach.call(mutation.addedNodes, function (node) {
          if (node.nodeType === 1) initAll(node);
        });
      });
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }

  // ── HTML escape helper ────────────────────────────────────

  function _escape(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Event delegation — dismiss button ────────────────────

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-wb-dismiss="toast"], .wb-toast-close');
    if (!btn) return;
    var toast = btn.closest('.wb-toast');
    if (toast) dismiss(toast);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll(document);
      observeToasts();
    });
  } else {
    initAll(document);
    observeToasts();
  }

  // ── Public API ────────────────────────────────────────────

  window.WBToast = {
    show:    show,
    init:    initToast,
    initAll: initAll,
    dismiss: dismiss
  };

})();
/* ============================================================
   WebBlocks UI — Popover (popover.js)

   Usage (HTML):
     <div class="wb-popover" data-wb-popover>
       <button class="wb-btn wb-btn-secondary" data-wb-toggle="popover">
         Info
       </button>
       <div class="wb-popover-panel">
         <div class="wb-popover-body">Content here</div>
       </div>
     </div>

   Placement modifiers (on .wb-popover wrapper):
     wb-popover-top | wb-popover-right | wb-popover-left | wb-popover-end

   Dismiss:
     <button data-wb-dismiss="popover">Close</button>

   JS API:
     WBPopover.open(wrapperEl)
     WBPopover.close(wrapperEl)
     WBPopover.closeAll()
   ============================================================ */

(function () {
  'use strict';

  var instances = new WeakMap();
  var wrapperPanels = new WeakMap();

  function getWrapperFromTrigger(trigger) {
    return trigger.closest('[data-wb-popover]') || trigger.closest('.wb-popover');
  }

  function getPanel(wrapper, trigger) {
    if (!wrapper) return null;

    var targetId = trigger && (trigger.getAttribute('data-wb-target') || trigger.getAttribute('aria-controls'));
    var panel = targetId ? document.querySelector(targetId.charAt(0) === '#' ? targetId : ('#' + targetId)) : null;
    if (!panel) panel = wrapperPanels.get(wrapper) || null;
    if (!panel) panel = wrapper.querySelector('.wb-popover-panel');
    if (!panel) return null;

    WBDom.overlay.ensureId(panel, 'popover-panel');
    if (trigger) {
      trigger.setAttribute('aria-controls', panel.id);
      if (!trigger.getAttribute('data-wb-target')) trigger.setAttribute('data-wb-target', '#' + panel.id);
    }
    wrapperPanels.set(wrapper, panel);
    return panel;
  }

  function getWrapper(target) {
    if (!target) return null;
    if (target.classList && target.classList.contains('wb-popover')) return target;
    if (target.classList && target.classList.contains('wb-popover-panel')) {
      var instance = instances.get(target);
      return instance ? instance.wrapper : null;
    }
    return target.closest('[data-wb-popover]') || target.closest('.wb-popover');
  }

  function getTrigger(panel) {
    var id = WBDom.overlay.ensureId(panel, 'popover-panel');
    return document.querySelector('[data-wb-toggle="popover"][data-wb-target="#' + id + '"]') ||
      document.querySelector('[data-wb-toggle="popover"][aria-controls="' + id + '"]');
  }

  function getPlacement(wrapper, trigger, panel) {
    if (panel.hasAttribute('data-wb-placement')) return panel.getAttribute('data-wb-placement');
    if (trigger && trigger.hasAttribute('data-wb-placement')) return trigger.getAttribute('data-wb-placement');
    if (wrapper && wrapper.hasAttribute('data-wb-placement')) return wrapper.getAttribute('data-wb-placement');

    var align = wrapper && wrapper.classList.contains('wb-popover-end') ? 'end' : 'start';
    if (wrapper && wrapper.classList.contains('wb-popover-top')) return 'top-' + align;
    if (wrapper && wrapper.classList.contains('wb-popover-right')) return 'right-start';
    if (wrapper && wrapper.classList.contains('wb-popover-left')) return 'left-start';
    return 'bottom-' + align;
  }

  function syncWrapperState(wrapper, panel, isOpen) {
    if (wrapper) wrapper.classList.toggle('is-open', isOpen);
    panel.classList.toggle('wb-popover-panel-end', !!(wrapper && wrapper.classList.contains('wb-popover-end')));
  }

  function ensureInstance(wrapper, trigger, panel) {
    var instance = instances.get(panel);
    if (instance) {
      instance.trigger = trigger || instance.trigger;
      instance.placement = getPlacement(wrapper, instance.trigger, panel);
      syncWrapperState(wrapper, panel, instance.active);
      WBDom.overlay.mount(instance);
      return instance;
    }

    instance = WBDom.overlay.create({
      kind: 'popover',
      group: 'anchored-toggle',
      layer: 'anchored',
      element: panel,
      panel: panel,
      trigger: trigger,
      position: 'anchored',
      placement: getPlacement(wrapper, trigger, panel),
      offset: 8,
      viewportPadding: 8,
      autoFocus: false,
      returnFocus: true,
      restoreOnClose: false,
      onToggle: function (isOpen) {
        syncWrapperState(wrapper, panel, isOpen);
      },
      onAfterOpen: function () {
        if (wrapper) WBDom.emit(wrapper, 'wb:popover:open');
      },
      onAfterClose: function () {
        if (wrapper) WBDom.emit(wrapper, 'wb:popover:close');
      }
    });

    instance.wrapper = wrapper;
    wrapperPanels.set(wrapper, panel);
    instances.set(panel, instance);
    WBDom.overlay.mount(instance);
    return instance;
  }

  function init() {
    document.querySelectorAll('[data-wb-popover], .wb-popover').forEach(function (wrapper) {
      var trigger = wrapper.querySelector('[data-wb-toggle="popover"]');
      if (!trigger) return;
      var panel = getPanel(wrapper, trigger);
      if (!panel) return;
      ensureInstance(wrapper, trigger, panel);
    });
  }

  // ── Open ──────────────────────────────────────────────────

  function open(wrapper) {
    if (!wrapper) return;
    var trigger = wrapper.querySelector('[data-wb-toggle="popover"]');
    var panel = getPanel(wrapper, trigger);
    if (!panel || !trigger) return;

    var instance = ensureInstance(wrapper, trigger, panel);
    instance.wrapper = wrapper;
    instance.trigger = trigger;
    instance.placement = getPlacement(wrapper, trigger, panel);
    WBDom.overlay.open(instance);
  }

  // ── Close ─────────────────────────────────────────────────

  function close(wrapper) {
    wrapper = getWrapper(wrapper);
    if (!wrapper) return;

    var panel = getPanel(wrapper);
    var trigger = wrapper.querySelector('[data-wb-toggle="popover"]') || (panel ? getTrigger(panel) : null);
    panel = getPanel(wrapper, trigger);
    if (!panel) return;

    var instance = ensureInstance(wrapper, trigger, panel);
    WBDom.overlay.close(instance, 'api');
  }

  function requestUserClose(wrapper, reason, originalEvent) {
    wrapper = getWrapper(wrapper);
    if (!wrapper) return;

    var panel = getPanel(wrapper);
    var trigger = wrapper.querySelector('[data-wb-toggle="popover"]') || (panel ? getTrigger(panel) : null);
    panel = getPanel(wrapper, trigger);
    if (!panel) return;

    WBDom.overlay.requestClose(ensureInstance(wrapper, trigger, panel), reason, {
      originalEvent: originalEvent || null
    });
  }

  function closeAll() {
    WBDom.overlay.closeAll(function (instance) {
      return instance.kind === 'popover';
    });
  }

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('click', function (e) {
    // Toggle trigger
    var trigger = e.target.closest('[data-wb-toggle="popover"]');
    if (trigger) {
      var wrapper = getWrapperFromTrigger(trigger);
      if (!wrapper) return;
      var panel = getPanel(wrapper, trigger);
      var instance = panel && ensureInstance(wrapper, trigger, panel);
      if (instance && instance.active) {
        close(wrapper);
      } else {
        open(wrapper);
      }
      return;
    }

    // Dismiss button inside popover
    var dismiss = e.target.closest('[data-wb-dismiss="popover"]');
    if (dismiss) {
      var wrapper = getWrapper(dismiss.closest('.wb-popover-panel') || dismiss);
      if (wrapper) requestUserClose(wrapper, 'dismiss-control', e);
      return;
    }
  });

  // ── Public API ────────────────────────────────────────────

  window.WBPopover = {
    open:     open,
    close:    close,
    closeAll: closeAll
  };

  init();

})();
/* ============================================================
   WebBlocks UI — Tooltip (tooltip.js)

   Tooltips are anchored and positioned by the shared overlay
   runtime using the [data-wb-tooltip] attribute. This module adds:

   1. Programmatic show/hide API.
   2. Support for tooltips on disabled elements (which don't
      receive :hover). Wrap the disabled element in a
      <span data-wb-tooltip="..."> to work around this.
   3. data-wb-tooltip-delay — show delay in ms (default: 0).

   Usage (HTML):
     <button data-wb-tooltip="Save changes">Save</button>

   Usage (programmatic):
     WBTooltip.show(el);     // force show
     WBTooltip.hide(el);     // force hide
     WBTooltip.hideAll();    // hide all forced tooltips

   Hover, focus, and programmatic calls all feed the same
   runtime-managed tooltip layer.
   ============================================================ */

(function () {
  'use strict';

  var timers = new WeakMap ? new WeakMap() : null;
  var activeTarget = null;
  var tooltip = document.createElement('div');
  var tooltipBody = document.createElement('div');
  var tooltipArrow = document.createElement('div');
  var instance;

  tooltip.className = 'wb-tooltip';
  tooltip.setAttribute('role', 'tooltip');
  tooltip.hidden = true;

  tooltipBody.className = 'wb-tooltip-body';
  tooltipArrow.className = 'wb-tooltip-arrow';

  tooltip.appendChild(tooltipBody);
  tooltip.appendChild(tooltipArrow);

  instance = WBDom.overlay.create({
    kind: 'tooltip',
    group: 'tooltip',
    layer: 'anchored',
    element: tooltip,
    panel: tooltip,
    portal: false,
    position: 'anchored',
    placement: 'top-center',
    offset: 8,
    viewportPadding: 8,
    autoFocus: false,
    returnFocus: false,
    manageExpanded: false,
    manageControls: false,
    outsideClose: false,
    escapeClose: false,
    interactive: false,
    onToggle: function (isOpen) {
      tooltip.hidden = !isOpen;
    }
  });

  WBDom.overlay.ensureLayer('anchored').appendChild(tooltip);

  function getDelay(el) {
    var d = parseInt(el.getAttribute('data-wb-tooltip-delay'), 10);
    return isNaN(d) ? 0 : d;
  }

  function getPlacement(el) {
    var placement = el.getAttribute('data-wb-tooltip-placement') || 'top';
    if (placement === 'top' || placement === 'bottom') return placement + '-center';
    if (placement === 'left' || placement === 'right') return placement + '-center';
    return placement;
  }

  function clearTimer(el) {
    if (!el || !timers) return;
    clearTimeout(timers.get(el));
    timers.delete(el);
  }

  function updateTooltipContent(el) {
    tooltipBody.textContent = el.getAttribute('data-wb-tooltip') || '';
    tooltip.classList.toggle('wb-tooltip-wrap', el.hasAttribute('data-wb-tooltip-wrap'));
  }

  // ── Programmatic show/hide ────────────────────────────────

  function show(el) {
    if (!el) return;
    clearTimer(el);
    activeTarget = el;
    updateTooltipContent(el);
    instance.trigger = el;
    instance.placement = getPlacement(el);
    WBDom.overlay.open(instance);
    WBDom.emit(el, 'wb:tooltip:show');
  }

  function hide(el) {
    if (el) clearTimer(el);
    if (el && activeTarget && el !== activeTarget) return;
    if (!activeTarget) return;
    var target = activeTarget;
    activeTarget = null;
    WBDom.overlay.close(instance, 'tooltip-hide');
    WBDom.emit(target, 'wb:tooltip:hide');
  }

  function hideAll() {
    hide(activeTarget);
  }

  function scheduleShow(el) {
    if (!el) return;

    var delay = getDelay(el);
    clearTimer(el);

    if (delay <= 0) {
      show(el);
      return;
    }

    if (!timers) return;
    timers.set(el, setTimeout(function () {
      show(el);
    }, delay));
  }

  // ── Delayed hover (optional enhancement) ─────────────────
  // Only activates on elements with data-wb-tooltip-delay set.

  document.addEventListener('mouseover', function (e) {
    var el = e.target.closest('[data-wb-tooltip]');
    if (!el || (e.relatedTarget && el.contains(e.relatedTarget))) return;
    scheduleShow(el);
  });

  document.addEventListener('mouseout', function (e) {
    var el = e.target.closest('[data-wb-tooltip]');
    if (!el || (e.relatedTarget && el.contains(e.relatedTarget))) return;
    hide(el);
  });

  document.addEventListener('focusin', function (e) {
    var el = e.target.closest('[data-wb-tooltip]');
    if (!el) return;
    scheduleShow(el);
  });

  document.addEventListener('focusout', function (e) {
    var el = e.target.closest('[data-wb-tooltip]');
    if (!el || (e.relatedTarget && el.contains(e.relatedTarget))) return;
    hide(el);
  });

  // ── Public API ────────────────────────────────────────────

  window.WBTooltip = {
    show:    show,
    hide:    hide,
    hideAll: hideAll
  };

})();
/* ============================================================
   WebBlocks UI — Dismiss (dismiss.js)

   Centralised handler for dismissible primitives that use
   data-wb-dismiss. Currently handles:

     alert     — removes .wb-alert from DOM (with animation)
     toast     — handled by toast.js; included here for completeness
     banner    — generic dismissible banner (.wb-banner)

   Usage:
     <div class="wb-alert wb-alert-info wb-alert-dismiss" id="myAlert">
       <span class="wb-alert-title">Heads up!</span>
       This is a dismissible alert.
       <button class="wb-alert-close" data-wb-dismiss="alert" aria-label="Close">&times;</button>
     </div>

   JS API:
     WBDismiss.dismiss(element)   // dismiss a .wb-alert / .wb-banner by element or selector
   ============================================================ */

(function () {
  'use strict';

  // ── Dismiss a single element ──────────────────────────────

  function dismiss(el) {
    if (!el || el.classList.contains('is-leaving')) return;

    el.classList.add('is-leaving');
    WBDom.emit(el, 'wb:dismiss');

    var done = false;
    function remove() {
      if (done) return;
      done = true;
      if (el.parentNode) el.parentNode.removeChild(el);
      WBDom.emit(document, 'wb:dismissed', { element: el });
    }

    el.addEventListener('transitionend', remove, { once: true });
    setTimeout(remove, 400); // fallback
  }

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-wb-dismiss="alert"]');
    if (btn) {
      var alert = btn.closest('.wb-alert');
      if (alert) dismiss(alert);
      return;
    }

    var bannerBtn = e.target.closest('[data-wb-dismiss="banner"]');
    if (bannerBtn) {
      var banner = bannerBtn.closest('.wb-banner');
      if (banner) dismiss(banner);
    }
  });

  // ── Public API ────────────────────────────────────────────

  window.WBDismiss = {
    dismiss: dismiss
  };

})();
/* ============================================================
   WebBlocks UI — AJAX Toggle (ajax-toggle.js)

   Sends a POST request when a checkbox is toggled.
   On failure, reverts the checkbox to its previous state.

   Usage:
     <input type="checkbox"
            class="wb-switch-input"
            data-wb-ajax-toggle
            data-wb-url="/admin/posts/toggle"
            data-wb-field="publish"
            data-wb-id="42"
            checked>

   POST body (JSON):
     { "id": "42", "name": "publish", "checked": "true" }

   Headers sent:
     Content-Type: application/json
     X-CSRF-TOKEN: <meta name="csrf-token"> value (if present)
     X-Requested-With: XMLHttpRequest

   Success detection (either condition triggers success):
     - HTTP status 200–299
     - Response body contains { "success": true } (optional extra check)

   Feedback (data-wb-feedback):
     "toast"   — WBToast.show() on success and error (default)
     "none"    — silent; listen to custom events instead

   Custom events (all bubble, dispatched on the checkbox element):
     wb:ajax-toggle:success  — detail: { id, field, checked, response }
     wb:ajax-toggle:error    — detail: { id, field, checked, status, error }

   Options (data attributes):
     data-wb-ajax-toggle         — marker attribute (required)
     data-wb-url                 — POST endpoint (required)
     data-wb-field               — field/column name sent as "name" (required)
     data-wb-id                  — record identifier sent as "id" (required)
     data-wb-feedback            — "toast" | "none" (default: "toast")
     data-wb-success-msg         — custom success toast message
     data-wb-error-msg           — custom error toast message
   ============================================================ */

(function () {
  'use strict';

  // ── Read CSRF token from meta tag ─────────────────────────

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  // ── Handle a toggle ───────────────────────────────────────

  function handleToggle(checkbox) {
    var url      = checkbox.getAttribute('data-wb-url');
    var field    = checkbox.getAttribute('data-wb-field');
    var id       = checkbox.getAttribute('data-wb-id');
    var feedback = checkbox.getAttribute('data-wb-feedback') || 'toast';
    var successMsg = checkbox.getAttribute('data-wb-success-msg') || null;
    var errorMsg   = checkbox.getAttribute('data-wb-error-msg')   || null;

    if (!url || !field || !id) {
      console.warn('[WBAjaxToggle] Missing required attribute(s): data-wb-url, data-wb-field, data-wb-id');
      return;
    }

    var newChecked  = checkbox.checked;
    var prevChecked = !newChecked; // before this click it was the opposite

    // Disable during request to prevent double-clicks
    checkbox.disabled = true;

    var headers = {
      'Content-Type':     'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    };

    var csrf = getCsrfToken();
    if (csrf) headers['X-CSRF-TOKEN'] = csrf;

    var body = JSON.stringify({
      id:      id,
      name:    field,
      checked: String(newChecked)
    });

    fetch(url, {
      method:      'POST',
      headers:     headers,
      body:        body,
      credentials: 'same-origin'
    })
    .then(function (response) {
      var ok = response.ok; // status 200-299

      // Try to parse JSON body for optional { success: true } check
      return response.text().then(function (text) {
        var json = null;
        try { json = JSON.parse(text); } catch (e) { /* not JSON */ }

        // If HTTP is OK but body explicitly says success: false, treat as error
        if (ok && json && json.success === false) {
          ok = false;
        }

        return { ok: ok, status: response.status, json: json };
      });
    })
    .then(function (result) {
      checkbox.disabled = false;

      if (result.ok) {
        // ── Success ──────────────────────────────────────────
        if (feedback === 'toast' && window.WBToast) {
          WBToast.show(
            successMsg || (newChecked ? 'Enabled' : 'Disabled'),
            { type: 'success', duration: 2500 }
          );
        }

        WBDom.emit(checkbox, 'wb:ajax-toggle:success', {
          id:       id,
          field:    field,
          checked:  newChecked,
          response: result.json
        });

      } else {
        // ── Error — revert checkbox ───────────────────────────
        checkbox.checked = prevChecked;

        if (feedback === 'toast' && window.WBToast) {
          WBToast.show(
            errorMsg || 'An error occurred. Please try again.',
            { type: 'danger', duration: 4000 }
          );
        }

        WBDom.emit(checkbox, 'wb:ajax-toggle:error', {
          id:      id,
          field:   field,
          checked: newChecked,
          status:  result.status,
          error:   result.json
        });
      }
    })
    .catch(function (err) {
      // Network error / fetch failed
      checkbox.disabled = false;
      checkbox.checked  = prevChecked;

      if (feedback === 'toast' && window.WBToast) {
        WBToast.show(
          errorMsg || 'Network error. Please check your connection.',
          { type: 'danger', duration: 4000 }
        );
      }

      WBDom.emit(checkbox, 'wb:ajax-toggle:error', {
        id:      id,
        field:   field,
        checked: newChecked,
        status:  0,
        error:   err
      });
    });
  }

  // ── Event delegation ──────────────────────────────────────

  document.addEventListener('change', function (e) {
    var checkbox = e.target;
    if (
      checkbox.tagName === 'INPUT' &&
      checkbox.type    === 'checkbox' &&
      checkbox.hasAttribute('data-wb-ajax-toggle')
    ) {
      handleToggle(checkbox);
    }
  });

  // ── Public API ────────────────────────────────────────────

  window.WBAjaxToggle = {
    handle: handleToggle
  };

})();
/* ============================================================
   WebBlocks UI — Password Toggle (WBPasswordToggle)

   Canonical usage:
     <div class="wb-input-group">
       <input class="wb-input" id="login-password" type="password">
       <button class="wb-btn wb-btn-secondary wb-input-addon-btn wb-btn-icon"
               type="button"
               data-wb-password-toggle
               data-wb-target="#login-password"
               aria-label="Show password"
               aria-pressed="false">
         <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
       </button>
     </div>

   State contract:
     hidden  -> input[type=password], aria-pressed=false, wb-icon-eye
     visible -> input[type=text],     aria-pressed=true,  wb-icon-eye-off

   Optional label overrides:
     data-wb-password-label-show="Show password"
     data-wb-password-label-hide="Hide password"

   Public API:
     WBPasswordToggle.toggle(buttonEl)
     WBPasswordToggle.sync(rootOrButton)
   ============================================================ */

(function () {
  'use strict';

  var BUTTON_SELECTOR = '[data-wb-password-toggle]';
  var INPUT_MARKER = 'data-wb-password-input';
  var LABEL_SHOW = 'Show password';
  var LABEL_HIDE = 'Hide password';

  function resolveExplicitTarget(button) {
    var selector = button.getAttribute('data-wb-target');
    var target = null;

    if (selector) {
      target = WBDom.resolveTarget(selector);
      if (!target && selector.charAt(0) !== '#') {
        target = document.getElementById(selector);
      }
    }

    if (!target) {
      var controls = button.getAttribute('aria-controls');
      if (controls) target = document.getElementById(controls);
    }

    return target;
  }

  function resolveImplicitTarget(button) {
    var group = button.closest('.wb-input-group');
    if (group) {
      return group.querySelector('input[' + INPUT_MARKER + '], input[type="password"]');
    }

    var prev = button.previousElementSibling;
    if (prev && prev.tagName === 'INPUT' && prev.type === 'password') return prev;

    return null;
  }

  function resolveTarget(button) {
    var target = resolveExplicitTarget(button) || resolveImplicitTarget(button);

    if (!target || target.tagName !== 'INPUT') return null;
    if (target.type !== 'password' && target.type !== 'text' && !target.hasAttribute(INPUT_MARKER)) return null;

    target.setAttribute(INPUT_MARKER, '');
    return target;
  }

  function isVisible(input) {
    return input.type === 'text';
  }

  function setIcon(button, visible) {
    var icon = button.querySelector('.wb-icon');
    if (!icon) return;

    icon.classList.remove('wb-icon-eye', 'wb-icon-eye-off');
    icon.classList.add(visible ? 'wb-icon-eye-off' : 'wb-icon-eye');
  }

  function setLabel(button, visible) {
    var showLabel = button.getAttribute('data-wb-password-label-show') || LABEL_SHOW;
    var hideLabel = button.getAttribute('data-wb-password-label-hide') || LABEL_HIDE;
    var label = visible ? hideLabel : showLabel;

    button.setAttribute('aria-label', label);

    if (button.hasAttribute('title')) {
      button.setAttribute('title', label);
    }
  }

  function syncButton(button) {
    var input = resolveTarget(button);
    if (!input) return;

    if (button.tagName === 'BUTTON' && !button.getAttribute('type')) {
      button.setAttribute('type', 'button');
    }

    if (input.id) {
      button.setAttribute('aria-controls', input.id);
    }

    var visible = isVisible(input);
    button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    setLabel(button, visible);
    setIcon(button, visible);
  }

  function sync(rootOrButton) {
    var root = rootOrButton || document;

    if (root.matches && root.matches(BUTTON_SELECTOR)) {
      syncButton(root);
      return;
    }

    root.querySelectorAll(BUTTON_SELECTOR).forEach(function (button) {
      syncButton(button);
    });
  }

  function toggle(button) {
    var input = resolveTarget(button);
    if (!input || button.disabled || input.disabled) return;

    var focused = document.activeElement === input;
    var selectionStart = null;
    var selectionEnd = null;

    if (focused) {
      try {
        selectionStart = input.selectionStart;
        selectionEnd = input.selectionEnd;
      } catch (err) {
        selectionStart = null;
        selectionEnd = null;
      }
    }

    input.type = isVisible(input) ? 'password' : 'text';
    syncButton(button);

    if (focused) {
      try {
        input.focus({ preventScroll: true });
      } catch (err) {
        input.focus();
      }

      if (selectionStart !== null && selectionEnd !== null) {
        try {
          input.setSelectionRange(selectionStart, selectionEnd);
        } catch (err) {
          /* Ignore unsupported selection restoration. */
        }
      }
    }
  }

  document.addEventListener('mousedown', function (e) {
    var button = e.target.closest(BUTTON_SELECTOR);
    if (!button) return;

    e.preventDefault();
  });

  document.addEventListener('click', function (e) {
    var button = e.target.closest(BUTTON_SELECTOR);
    if (!button) return;

    e.preventDefault();
    toggle(button);
  });

  function init() {
    sync(document);
  }

  window.WBPasswordToggle = {
    toggle: function (buttonOrSelector) {
      var button = typeof buttonOrSelector === 'string'
        ? document.querySelector(buttonOrSelector)
        : buttonOrSelector;

      if (button) toggle(button);
    },
    sync: sync
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }

})();
/* ============================================================
   WebBlocks UI — Collapse (WBCollapse)
   Toggles .is-open on .wb-collapse elements.

   Triggers:
     data-wb-collapse="target-id"   — toggles a standalone collapse
     .wb-collapse-panel > .wb-collapse-trigger — toggles panel

   Public API:
     WBCollapse.open(id)
     WBCollapse.close(id)
     WBCollapse.toggle(id)
   ============================================================ */
(function () {
  'use strict';

  /* ── Panel trigger (button inside .wb-collapse-panel) ──── */
  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('.wb-collapse-trigger');
    if (trigger) {
      var panel = trigger.closest('.wb-collapse-panel');
      if (panel) {
        panel.classList.toggle('is-open');
        var expanded = panel.classList.contains('is-open');
        trigger.setAttribute('aria-expanded', expanded);
      }
      return;
    }

    /* ── Standalone toggle: data-wb-collapse="id" ───────── */
    var btn = e.target.closest('[data-wb-collapse]');
    if (btn) {
      var targetId = btn.getAttribute('data-wb-collapse');
      var el = document.getElementById(targetId);
      if (el) {
        el.classList.toggle('is-open');
        var isOpen = el.classList.contains('is-open');
        btn.setAttribute('aria-expanded', isOpen);
      }
    }
  });

  /* ── Public API ─────────────────────────────────────────── */
  window.WBCollapse = {
    open: function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.classList.add('is-open');
        var btn = document.querySelector('[data-wb-collapse="' + id + '"]');
        if (btn) btn.setAttribute('aria-expanded', 'true');
      }
    },
    close: function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.classList.remove('is-open');
        var btn = document.querySelector('[data-wb-collapse="' + id + '"]');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }
    },
    toggle: function (id) {
      var el = document.getElementById(id);
      if (el) {
        if (el.classList.contains('is-open')) {
          window.WBCollapse.close(id);
        } else {
          window.WBCollapse.open(id);
        }
      }
    }
  };
})();
/* ============================================================
   WebBlocks UI — Update Indicator (WBUpdateIndicator)
   A navbar "update available" badge that reveals itself from a
   JSON status endpoint. The element starts `hidden`; the badge
   is shown only when the endpoint reports an available update.

   Markup (a shipped icon action; the dot uses the wb-btn-dot primitive):
     <a class="wb-btn wb-btn-ghost wb-btn-icon"
        data-wb-update-indicator
        data-wb-update-indicator-url="/path/to/indicator.json"
        data-wb-update-indicator-state="unknown"
        hidden>
       <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
       <span class="wb-btn-dot" aria-hidden="true"></span>
       <span class="wb-sr-only" data-wb-update-indicator-label>Update available</span>
     </a>

   Endpoint JSON (fetched once on load, same-origin, credentialed):
     { "visible": true, "state": "update_available",
       "label": "Update 1.2.3 available", "url": "/admin/updates" }
   - visible : boolean — badge is revealed only when strictly === true
   - state   : string  — mirrored onto data-wb-update-indicator-state
   - label   : string  — sets aria-label, title, and the
                         [data-wb-update-indicator-label] text
   - url     : string  — optional; sets the element href

   Fails safe, not silent: a bad status, an unparseable body or a network
   error leaves the badge hidden — the page never breaks over an update
   check — but the element is marked `data-wb-update-indicator-state="error"`
   and the reason is reported with console.warn. Silence here is expensive:
   a badge that never appears looks identical whether the endpoint 404s,
   redirects to a login page, or correctly reports "no update".

   Public API:
     WBUpdateIndicator.refresh()          — re-fetch every indicator
     WBUpdateIndicator.refresh(element)    — re-fetch one indicator
   ============================================================ */
(function () {
  'use strict';

  var SELECTOR = '[data-wb-update-indicator]';

  function apply(el, data) {
    if (!data || data.visible !== true) {
      el.hidden = true;

      return;
    }

    el.hidden = false;

    if (typeof data.state === 'string') {
      el.setAttribute('data-wb-update-indicator-state', data.state);
    }

    if (typeof data.label === 'string') {
      el.setAttribute('aria-label', data.label);
      el.setAttribute('title', data.label);

      var label = el.querySelector('[data-wb-update-indicator-label]');
      if (label) {
        label.textContent = data.label;
      }
    }

    if (typeof data.url === 'string' && data.url) {
      el.setAttribute('href', data.url);
    }
  }

  function fail(el, url, reason) {
    el.hidden = true;
    el.setAttribute('data-wb-update-indicator-state', 'error');

    if (window.console && typeof window.console.warn === 'function') {
      window.console.warn('[wb-update-indicator] ' + reason + ' — ' + url + '. The update badge stays hidden.');
    }
  }

  function refreshOne(el) {
    var url = el.getAttribute('data-wb-update-indicator-url');
    if (!url) {
      return;
    }

    window.fetch(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    }).then(function (response) {
      if (!response.ok) {
        fail(el, url, 'the status endpoint answered HTTP ' + response.status);

        return null;
      }

      // An auth redirect lands here as 200 HTML and throws on parse — worth
      // saying out loud, since it looks exactly like "no update available".
      return response.json().then(null, function () {
        fail(el, url, 'the status endpoint did not return JSON');

        return null;
      });
    }).then(function (data) {
      if (data !== null) {
        apply(el, data);
      }
    }).catch(function (error) {
      fail(el, url, 'the status request failed (' + (error && error.message ? error.message : 'network error') + ')');
    });
  }

  function refresh(target) {
    if (target && target.nodeType === 1) {
      refreshOne(target);

      return;
    }

    var nodes = document.querySelectorAll(SELECTOR);
    for (var i = 0; i < nodes.length; i++) {
      refreshOne(nodes[i]);
    }
  }

  function init() {
    refresh();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* ── Public API ─────────────────────────────────────────── */
  window.WBUpdateIndicator = {
    refresh: refresh
  };
})();
/* ============================================================
   WebBlocks UI — Busy Submit (WBBusySubmit)
   Locks a submit button and shows the wb-btn busy state while its
   form is in flight.

   Markup:
     <button class="wb-btn wb-btn-primary" type="submit"
             data-wb-busy
             data-wb-busy-label="Saving…">
       <span data-wb-busy-text>Save</span>
     </button>

   Behavior on the owning form's submit event:
   - adds `.is-busy` and `aria-busy="true"` to the button
   - disables the button (submission has already fired, so this
     only blocks a second click)
   - when `data-wb-busy-label` is set, swaps the `[data-wb-busy-text]`
     node's text (or the aria-label as a fallback) to that label

   Public API:
     WBBusySubmit.bind()          — bind any unbound [data-wb-busy]
                                    buttons (call after injecting DOM)
   ============================================================ */
(function () {
  'use strict';

  function bindOne(button) {
    var form = button.closest('form');

    if (!form || button.getAttribute('data-wb-busy-bound') === 'true') {
      return;
    }

    button.setAttribute('data-wb-busy-bound', 'true');

    form.addEventListener('submit', function () {
      var busyLabel = button.getAttribute('data-wb-busy-label');
      var textNode = button.querySelector('[data-wb-busy-text]');

      button.classList.add('is-busy');
      button.setAttribute('aria-busy', 'true');
      button.disabled = true;

      if (busyLabel) {
        if (textNode) {
          textNode.textContent = busyLabel;
        }

        button.setAttribute('aria-label', busyLabel);
      }
    });
  }

  function bind() {
    document.querySelectorAll('[data-wb-busy]').forEach(bindOne);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }

  window.WBBusySubmit = { bind: bind };
}());
