(function () {
    'use strict';
    var active = new Set();
    var restoredUrl = new URL(window.location.href);
    if (restoredUrl.searchParams.has('_editor_recovery_ticket')) {
        ['_editor_recovery_restore', '_editor_recovery_ticket', '_editor_recovery_block'].forEach(function (key) { restoredUrl.searchParams.delete(key); });
        history.replaceState(null, '', restoredUrl.href);
    }

    function encode(value) {
        var bytes = new TextEncoder().encode(value), text = '';
        for (var i = 0; i < bytes.length; i += 8192) {
            text += String.fromCharCode.apply(null, bytes.subarray(i, i + 8192));
        }
        return btoa(text);
    }

    function initialize(root) {
        if (root.dataset.recoveryBound) return;
        root.dataset.recoveryBound = 'true';
        var form = root.closest('form');
        if (!form) return;
        var status = root.querySelector('[data-recovery-status]');
        var choice = root.querySelector('[data-recovery-choice]');
        var review = root.querySelector('[data-recovery-reviewed]');
        var restore = root.querySelector('[data-recovery-restore]');
        var sessionField = root.querySelector('[name="_editor_recovery_session"]');
        var baseField = root.querySelector('[name="_editor_recovery_base"]');
        var previousSession = sessionField.value;
        var roots = new Set(root.dataset.roots.split(' '));
        var storageKey = 'wb-editor-recovery:' + root.dataset.identity;
        var session, release, timer, interval, inFlight = null, initialized = false;
        var sequence = 0, paused = false, conflict = false, submitting = false, saving = false, loading = false;
        var lastProtected = null, protectedUntil = 0, initialSnapshot = null, stopped = false;
        var controller = null, saveController = null, saveError = false;
        var item = { root: root, stop: function () {
            stopped = true; delete root.dataset.recoveryBound; clearTimeout(timer); clearInterval(interval);
            if (controller) controller.abort();
            if (saveController) saveController.abort();
            if (release) release();
            active.delete(item);
        } };
        active.add(item);

        function message(key, time) {
            if (stopped) return;
            if (saveError && !submitting) return;
            status.textContent = root.dataset['message' + key.charAt(0).toUpperCase() + key.slice(1)] + (time ? ' ' + time : '');
            root.dataset.recoveryState = key;
        }

        function entries() {
            var data = new FormData(form), result = [], present = new Set();
            var excluded = new Set(Array.from(form.elements).filter(function (field) { return field.type === 'password' || field.type === 'file'; }).map(function (field) { return field.name; }));
            data.forEach(function (value, name) {
                if (typeof value !== 'string' || excluded.has(name) || !roots.has(name.split('[')[0]) || /password|secret|token|credential|authorization|api[_-]?key|private[_-]?key/i.test(name)) return;
                // The empty Gallery control precedes its rows; an absent array also survives recovery.
                result.push(name === 'gallery_items' && value === ''
                    ? { name: 'gallery_items[]', present: false, value: null }
                    : { name: name, present: true, value: encode(value) });
                present.add(name);
            });
            Array.from(form.elements).forEach(function (field) {
                if (!field.name || field.disabled || excluded.has(field.name) || !roots.has(field.name.split('[')[0]) || present.has(field.name) || /password|secret|token|credential|authorization|api[_-]?key|private[_-]?key/i.test(field.name)) return;
                if (field.type === 'checkbox' || field.type === 'radio' || field.multiple) {
                    result.push({ name: field.name, present: false, value: null });
                    present.add(field.name);
                }
            });
            return result;
        }

        function context() {
            var data = { locale: root.dataset.locale };
            if (root.dataset.blockId) data.block_id = Number(root.dataset.blockId);
            return data;
        }

        async function api(method, suffix, extra) {
            var url = new URL(root.dataset.endpoint.replace('SESSION', session) + (suffix || ''), window.location.href);
            var data = Object.assign(context(), extra || {});
            if (method === 'GET') Object.keys(data).forEach(function (key) { url.searchParams.set(key, data[key]); });
            var requestController = new AbortController();
            controller = requestController;
            var timeout = setTimeout(function () { requestController.abort(); }, 15000);
            try {
                var response = await fetch(url.href, {
                    method: method, credentials: 'same-origin', signal: requestController.signal,
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: method === 'GET' ? undefined : JSON.stringify(data)
                });
                if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) {
                    var error = new Error('Recovery request failed'); error.status = response.status; throw error;
                }
                var result = await response.json();
                if (method === 'GET' && typeof result.csrf_token === 'string') {
                    form.querySelectorAll('[name="_token"]').forEach(function (field) { field.value = result.csrf_token; });
                    var meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.content = result.csrf_token;
                }
                return result;
            } finally { clearTimeout(timeout); if (controller === requestController) controller = null; }
        }

        function offer(data) {
            conflict = !!data.conflict;
            paused = true;
            choice.hidden = false;
            root.querySelector('[data-recovery-conflict]').hidden = !conflict;
            root.querySelector('[data-recovery-review]').hidden = !conflict;
            review.checked = false;
            restore.disabled = conflict;
            message(conflict ? 'conflict' : 'available', data.saved_at);
        }

        function checkpoint(force) {
            if (!initialized || stopped || paused || saving) return Promise.resolve(false);
            if (inFlight) return inFlight.then(function (ok) { return ok ? checkpoint(force) : false; });
            var values = entries(), snapshot = JSON.stringify(values);
            if (lastProtected !== null && Date.now() >= protectedUntil) force = true;
            if (!force && (snapshot === lastProtected || (lastProtected === null && snapshot === initialSnapshot))) return Promise.resolve(true);
            message('pending');
            var sent = ++sequence;
            inFlight = api('PUT', '', { _checkpoint: '1', base_fingerprint: baseField.value, sequence: sent, entries: values })
                .then(function (data) {
                    sequence = Math.max(sequence, Number(data.sequence));
                    protectedUntil = Number(data.expires_at_ms || 0);
                    if (Number(data.sequence) !== sent) { var error = new Error('Checkpoint changed'); error.status = 409; throw error; }
                    lastProtected = snapshot;
                    message(JSON.stringify(entries()) === lastProtected ? 'protected' : 'pending', data.saved_at);
                    return true;
                }).catch(function (error) { failure(error, false); return false; })
                .finally(function () { inFlight = null; });
            return inFlight;
        }

        async function load() {
            if (loading || stopped) return;
            loading = true;
            try {
            sessionField.value = session;
            var data = await api('GET');
            sequence = Number(data.sequence || 0);
            protectedUntil = Number(data.expires_at_ms || 0);
            var currentSnapshot = JSON.stringify(entries());
            initialized = true;
            if (data.exists) {
                lastProtected = JSON.stringify(data.entries);
                if (previousSession === session && !data.conflict) {
                    message(currentSnapshot === lastProtected ? 'protected' : 'pending', data.saved_at);
                    if (currentSnapshot !== lastProtected) checkpoint(true);
                } else offer(data);
            } else if (previousSession === session || currentSnapshot !== initialSnapshot) checkpoint(true);
            else message('ready');
            } finally { loading = false; }
        }

        function acquire() {
            return navigator.locks.request('wb-editor-recovery:' + session, { ifAvailable: true }, function (lock) {
                if (!lock) {
                    session = crypto.randomUUID(); sessionStorage.setItem(storageKey, session);
                    return acquire();
                }
                return new Promise(function (resolve) {
                    release = resolve;
                    load().catch(function (error) {
                        if (stopped) return;
                        if (error.status === 410) {
                            release(); session = crypto.randomUUID(); sessionStorage.setItem(storageKey, session); acquire();
                        } else { sessionField.value = ''; message('failed'); }
                    });
                });
            });
        }

        review.addEventListener('change', function () { restore.disabled = conflict && !review.checked; });
        restore.addEventListener('click', async function () {
            if (stopped) return;
            if (conflict && !review.checked) return;
            restore.disabled = true;
            try {
                var data = await api('POST', '/restore', { reviewed_conflict: conflict && review.checked });
                window.location.assign(data.redirect);
            } catch (error) {
                if (error.status === 409) offer({ conflict: true });
                else message('failed');
                restore.disabled = conflict && !review.checked;
            }
        });
        root.querySelector('[data-recovery-discard]').addEventListener('click', async function () {
            if (stopped) return;
            try {
                if (inFlight) await inFlight;
                await api('DELETE');
                choice.hidden = true; paused = false; initialized = false; sequence = 0; lastProtected = null; initialSnapshot = JSON.stringify(entries());
                if (release) release();
                session = crypto.randomUUID(); sessionStorage.setItem(storageKey, session);
                await Promise.resolve(); acquire();
            } catch (error) { message('failed'); }
        });
        root.querySelector('[data-recovery-continue]').addEventListener('click', function () {
            if (stopped) return;
            paused = true; message('paused');
        });
        function changed() {
            if (!initialized || paused || stopped || saving) return;
            saveError = false;
            if (JSON.stringify(entries()) === lastProtected) { message('protected'); return; }
            message('pending'); clearTimeout(timer); timer = setTimeout(function () { checkpoint(); }, 1500);
        }
        form.addEventListener('input', changed);
        form.addEventListener('change', changed);
        function failure(error, attemptedSave) {
            if (stopped) return;
            saveError = false;
            var auth = [401, 403, 419].includes(error.status);
            var validation = attemptedSave && error.status === 422;
            message(auth ? 'auth_failed' : (validation ? 'validation_failed' : (attemptedSave ? 'save_failed' : 'failed')));
            root.querySelector('[data-recovery-login]').hidden = !auth;
            root.querySelector('[data-recovery-saved-link]').hidden = !attemptedSave && error.status !== 410;
            var errors = root.querySelector('[data-recovery-errors]');
            errors.replaceChildren();
            if (validation && error.errors && typeof error.errors === 'object') {
                Object.values(error.errors).flat().slice(0, 30).forEach(function (text) {
                    if (typeof text !== 'string') return;
                    var item = document.createElement('li'); item.textContent = text.slice(0, 1000); errors.appendChild(item);
                });
            }
            saveError = attemptedSave || submitting;
        }

        function lockForm() {
            var controls = Array.from(form.elements).map(function (field) { return [field, field.disabled]; });
            var editors = Array.from(document.querySelectorAll('[contenteditable="true"]')).filter(function (surface) {
                var editor = surface.closest('[data-wb-rich-text-editor]');
                return form.contains(surface) || (editor && editor.querySelector('[data-wb-rich-text-input]')?.form === form);
            });
            var richTextRoots = Array.from(new Set(editors.map(function (surface) { return surface.closest('[data-wb-rich-text-editor]'); }).filter(Boolean)))
                .map(function (editor) { return [editor, editor.inert]; });
            controls.forEach(function (entry) { entry[0].disabled = true; });
            editors.forEach(function (editor) { editor.contentEditable = 'false'; });
            richTextRoots.forEach(function (entry) { entry[0].inert = true; });
            return function () {
                controls.forEach(function (entry) { entry[0].disabled = entry[1]; });
                editors.forEach(function (editor) { editor.contentEditable = 'true'; });
                richTextRoots.forEach(function (entry) { entry[0].inert = entry[1]; });
            };
        }

        form.addEventListener('submit', async function (event) {
            if (stopped) return;
            event.preventDefault(); event.stopImmediatePropagation();
            if (submitting) return;
            submitting = true; saveError = false; clearTimeout(timer);
            root.querySelector('[data-recovery-errors]').replaceChildren();
            root.querySelector('[data-recovery-login]').hidden = true;
            root.querySelector('[data-recovery-saved-link]').hidden = true;
            var unlock = null, timeout = null, attemptedSave = false;
            try {
                if (!session || !release) throw new Error('Recovery unavailable');
                if (loading) throw new Error('Recovery still loading');
                if (!initialized) await load();
                if (paused) { message('paused'); return; }
                // GET refreshes the current session's CSRF token after sign-in in another tab.
                var current = await api('GET');
                if (current.conflict) { offer(current); return; }
                if (inFlight && !await inFlight) return;
                do {
                    if (!await checkpoint(true) || stopped) return;
                    // Edits made during the request must be confirmed before the save starts.
                } while (JSON.stringify(entries()) !== lastProtected);
                if (!form.reportValidity()) return;
                var body = new FormData(form);
                if (event.submitter?.name) body.append(event.submitter.name, event.submitter.value);
                var action = new URL(form.action, window.location.href);
                if (action.origin !== window.location.origin) throw new Error('Invalid save destination');
                saving = true; unlock = lockForm(); message('saving');
                form.setAttribute('aria-busy', 'true');
                saveController = new AbortController();
                timeout = setTimeout(function () { saveController?.abort(); }, 30000);
                attemptedSave = true;
                var response = await fetch(action.href, {
                    method: form.method.toUpperCase(), credentials: 'same-origin', redirect: 'error',
                    headers: { Accept: 'application/json', 'X-WebBlocks-Editor-Save': '1', 'X-CSRF-TOKEN': body.get('_token') || '' },
                    body: body, signal: saveController.signal
                });
                var data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
                if (!response.ok || data?.saved !== true || typeof data.redirect !== 'string') {
                    var error = new Error('Save not confirmed'); error.status = response.status; error.errors = data?.errors; throw error;
                }
                var destination = new URL(data.redirect, window.location.href);
                if (destination.origin !== window.location.origin) throw new Error('Invalid saved destination');
                paused = true; clearTimeout(timer); clearInterval(interval);
                window.location.assign(destination.href);
            } catch (error) { failure(error, attemptedSave); }
            finally {
                clearTimeout(timeout); saveController = null;
                if (unlock) unlock();
                form.removeAttribute('aria-busy');
                saving = false; submitting = false;
            }
        }, true);
        initialSnapshot = JSON.stringify(entries());
        interval = setInterval(function () {
            if (initialized) checkpoint();
            else if (session && release && !stopped) load().catch(function () { message('failed'); });
        }, 10000);
        try {
            if (!navigator.locks || !crypto.randomUUID || !window.TextEncoder) throw new Error('Recovery unavailable');
            session = sessionStorage.getItem(storageKey) || crypto.randomUUID();
            sessionStorage.setItem(storageKey, session);
            acquire().catch(function () { sessionField.value = ''; message('failed'); });
        } catch (error) { sessionField.value = ''; message('failed'); }
    }

    function scan() {
        active.forEach(function (item) { if (!item.root.isConnected) item.stop(); });
        document.querySelectorAll('[data-wb-editor-recovery]').forEach(initialize);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
    else scan();
    new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
    window.addEventListener('pageshow', function (event) { if (event.persisted) scan(); });
    window.addEventListener('pagehide', function () { active.forEach(function (item) { item.stop(); }); });
}());
