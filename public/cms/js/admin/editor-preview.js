(function () {
    'use strict';
    var active = new Set();
    function encode(value) {
        var bytes = new TextEncoder().encode(value), result = '';
        for (var i = 0; i < bytes.length; i += 8192) result += String.fromCharCode.apply(null, bytes.subarray(i, i + 8192));
        return btoa(result);
    }
    function initialize(root) {
        if (root.dataset.previewBound) return;
        var form = root.closest('form');
        if (!form) return;
        root.dataset.previewBound = 'true';
        var panel = root.querySelector('[data-preview-panel]'), frame = panel.querySelector('[data-preview-frame]');
        var toggle = root.querySelector('[data-preview-toggle]'), refresh = panel.querySelector('[data-preview-refresh]');
        var locale = panel.querySelector('[data-preview-locale]'), width = panel.querySelector('[data-preview-width]');
        var status = panel.querySelector('[data-preview-status]'), time = panel.querySelector('[data-preview-time]');
        var roots = new Set(root.dataset.roots.split(' ')), modal = form.closest('.wb-modal');
        var sequence = 0, controller = null, last = null, open = false, stopped = false, stale = false, editor = null, pagination = {};
        var narrow = window.matchMedia('(max-width: 1099px)');
        function message(key, detail) {
            status.textContent = root.getAttribute('data-message-' + key.replace(/_/g, '-')) + (detail ? ' ' + detail : '');
            panel.dataset.previewState = key;
        }
        function entries() {
            var result = [], present = new Set(), data = new FormData(form);
            data.forEach(function (value, name) {
                if (typeof value !== 'string' || !roots.has(name.split('[')[0]) || /password|secret|token|credential|authorization|api[_-]?key|private[_-]?key/i.test(name)) return;
                result.push({ name: name, present: true, value: encode(value) }); present.add(name);
            });
            Array.from(form.elements).forEach(function (field) {
                if (!field.name || field.disabled || !roots.has(field.name.split('[')[0]) || present.has(field.name)) return;
                if (field.type === 'checkbox' || field.type === 'radio' || field.multiple) {
                    result.push({ name: field.name, present: false, value: null }); present.add(field.name);
                }
            });
            return result;
        }
        function snapshot() { return JSON.stringify([locale.value, entries()]); }
        function close() {
            open = false; sequence += 1;
            if (controller) controller.abort();
            panel.hidden = true; toggle.setAttribute('aria-expanded', 'false');
            form.classList.remove('wb-editor-preview-open');
            if (modal) modal.classList.remove('wb-editor-preview-modal');
        }
        function prepare(html) {
            // An opaque sandbox and nonce-only script policy isolate authored HTML from the panel.
            var doc = new DOMParser().parseFromString(html, 'text/html');
            doc.querySelectorAll('script, base, meta[http-equiv], iframe, object, embed').forEach(function (node) { node.remove(); });
            var nonce = crypto.randomUUID().replace(/-/g, '');
            var policy = doc.createElement('meta'); policy.httpEquiv = 'Content-Security-Policy';
            policy.content = "default-src 'none'; script-src 'nonce-" + nonce + "'; style-src https: http: 'unsafe-inline'; img-src https: http: data: blob:; font-src https: http: data:; media-src https: http: blob:; connect-src 'none'; form-action 'none'; frame-src 'none'; object-src 'none'; base-uri " + window.location.origin;
            doc.head.prepend(policy);
            var base = doc.createElement('base'); base.href = root.dataset.base; policy.after(base);
            var bridge = doc.createElement('script'); bridge.src = root.dataset.bridge; bridge.setAttribute('nonce', nonce);
            doc.body.append(bridge);
            return '<!doctype html>' + doc.documentElement.outerHTML;
        }
        async function render() {
            if (!open || stopped) return;
            var unsupported = Array.from(form.elements).some(function (field) { return (field.type === 'file' && field.files.length) || (field.type === 'password' && field.value); });
            if (unsupported) { sequence += 1; if (controller) controller.abort(); refresh.disabled = false; stale = true; message('unsupported'); return; }
            var sent = ++sequence, values = entries(), sentSnapshot = JSON.stringify([locale.value, values]);
            if (controller) controller.abort();
            var currentController = new AbortController(); controller = currentController;
            var timeout = setTimeout(function () { currentController.abort(); }, 30000);
            refresh.disabled = true; message('loading');
            try {
                var response = await fetch(root.dataset.endpoint, {
                    method: 'POST', credentials: 'same-origin', signal: currentController.signal,
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify({ locale: locale.value, form_locale: root.dataset.formLocale, block_id: root.dataset.blockId || null, entries: values, pagination: pagination })
                });
                if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) {
                    var error = new Error('Preview failed'); error.status = response.status;
                    if (response.status === 422) {
                        var invalid = await response.json(); error.detail = Object.values(invalid.errors || {}).flat().join(' ');
                    }
                    throw error;
                }
                var data = await response.json();
                if (sent !== sequence || stopped || !open) return;
                frame.srcdoc = prepare(data.html);
                if (data.issues.length) { stale = true; message('source_failed'); return; }
                stale = false;
                last = sentSnapshot;
                time.textContent = root.dataset.lastRender + ' ' + new Date(data.rendered_at).toLocaleString();
                message(snapshot() === last ? 'current' : 'pending');
            } catch (error) {
                if (sent !== sequence || stopped || !open) return;
                stale = true;
                message([401, 403, 419].includes(error.status) ? 'auth_failed' : 'failed', error.detail);
            } finally {
                clearTimeout(timeout);
                if (sent === sequence) { refresh.disabled = false; controller = null; }
            }
        }
        toggle.addEventListener('click', function () {
            if (open) { close(); return; }
            if (narrow.matches) return;
            if (!editor) {
                editor = document.createElement('div'); editor.className = 'wb-editor-preview-editor';
                Array.from(form.childNodes).forEach(function (node) { editor.append(node); });
                form.append(editor, panel);
            }
            open = true; panel.hidden = false; form.classList.add('wb-editor-preview-open');
            if (modal) modal.classList.add('wb-editor-preview-modal');
            toggle.setAttribute('aria-expanded', 'true'); refresh.disabled = false;
            // Opening the panel never fetches providers: rendering requires an explicit refresh.
            if (!stale) message(last && snapshot() === last ? 'current' : 'ready');
        });
        panel.querySelector('[data-preview-close]').addEventListener('click', function () { close(); toggle.focus(); });
        refresh.addEventListener('click', function () { render(); });
        function changed(event) {
            if (panel.contains(event.target)) return;
            if (open) message(!stale && snapshot() === last ? 'current' : 'pending');
        }
        form.addEventListener('input', changed); form.addEventListener('change', changed);
        locale.addEventListener('change', function () { pagination = {}; message('pending'); });
        width.addEventListener('change', function () { panel.querySelector('[data-preview-viewport]').dataset.width = width.value; });
        function resized() { if (narrow.matches && open) close(); }
        narrow.addEventListener('change', resized);
        function bridge(event) {
            if (!open || event.source !== frame.contentWindow || event.data?.type !== 'wb-editor-preview-page') return;
            var next = {}, valid = true;
            Object.entries(event.data.pagination || {}).forEach(function (item) {
                if (!/^wb_collection_[1-9][0-9]*_page$/.test(item[0]) || !Number.isInteger(item[1]) || item[1] < 1 || item[1] > 10000) valid = false;
                else next[item[0]] = item[1];
            });
            if (!valid || Object.keys(next).length > 32 || Object.keys(next).length === 0) return;
            pagination = next; render();
        }
        window.addEventListener('message', bridge);
        var item = { root: root, pause: close, stop: function () {
            stopped = true; close(); delete root.dataset.previewBound;
            window.removeEventListener('message', bridge); narrow.removeEventListener('change', resized);
            active.delete(item);
        } };
        active.add(item);
    }
    function scan() {
        active.forEach(function (item) { if (!item.root.isConnected) item.stop(); });
        document.querySelectorAll('[data-wb-editor-preview]').forEach(initialize);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
    new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
    window.addEventListener('pagehide', function () { active.forEach(function (item) { item.pause(); }); });
    window.addEventListener('pageshow', function (event) { if (event.persisted) scan(); });
}());
