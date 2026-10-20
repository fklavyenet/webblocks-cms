(function () {
    'use strict';
    var queue = [], requests = [], output;
    // Controlled transport: deliberately ignores AbortSignal to exercise late-response guards.
    window.fetch = function (url, options) {
        return new Promise(function (resolve, reject) {
            var payload = JSON.parse(options.body);
            requests.push(payload);
            if (Object.keys(payload.pagination).length) {
                resolve(response({ html: '<p>Pagination rendered</p>', issues: [], rendered_at: new Date().toISOString() }));
                output.textContent += ' Pagination: PASS';
            } else queue.push({ resolve: resolve, reject: reject });
        });
    };
    function response(data, status) {
        return { ok: !status || status === 200, status: status || 200, headers: { get: function () { return 'application/json'; } }, json: function () { return Promise.resolve(data); } };
    }
    function tick() { return new Promise(function (resolve) { setTimeout(resolve, 0); }); }
    function assert(value, message) { if (!value) throw new Error(message); }
    document.addEventListener('DOMContentLoaded', function () {
        output = document.querySelector('#preview-test-results');
        document.querySelector('#run-preview-tests').addEventListener('click', async function (event) {
            event.target.disabled = true;
            var root = document.querySelector('[data-wb-editor-preview]');
            var toggle = root.querySelector('[data-preview-toggle]');
            var panel = root.querySelector('[data-preview-panel]');
            var refresh = panel.querySelector('[data-preview-refresh]');
            var frame = panel.querySelector('[data-preview-frame]');
            var title = document.querySelector('[name="title"]');
            function html(text) { return { html: '<p>' + text + '</p><a href="/?wb_collection_1_page=2">Next collection page</a>', issues: [], rendered_at: new Date().toISOString() }; }
            function status() { return panel.querySelector('[data-preview-status]').textContent; }
            try {
                toggle.click(); assert(requests.length === 0, 'Opening must not fetch.');
                title.value = 'Unsaved fixture title'; title.dispatchEvent(new Event('input', { bubbles: true }));
                assert(requests.length === 0, 'Typing must not fetch.');
                refresh.click(); var old = queue.shift();
                toggle.click(); toggle.click(); refresh.click(); var current = queue.shift();
                current.resolve(response(html('Newer render'))); await tick();
                old.resolve(response(html('Older render'))); await tick();
                assert(frame.srcdoc.includes('Newer render') && !frame.srcdoc.includes('Older render'), 'Older responses must not overwrite newer renders.');
                output.textContent = 'No implicit fetch / latest response: PASS.';
                var lastTime = panel.querySelector('[data-preview-time]').textContent;
                refresh.click(); queue.shift().resolve(response({}, 403)); await tick();
                assert(status().includes('Access expired or denied'), 'Authorization failures need localized copy.');
                assert(title.value === 'Unsaved fixture title', 'Authorization failure must preserve the form.');
                toggle.click(); toggle.click();
                assert(status().includes('Access expired or denied'), 'Reopening must retain the failure state.');
                refresh.click(); queue.shift().resolve(response({ errors: { title: ['Fixture validation error.'] } }, 422)); await tick();
                assert(status().includes('Fixture validation error.'), 'Validation errors must be visible.');
                refresh.click(); queue.shift().reject(new TypeError('Offline fixture')); await tick();
                assert(status().includes('Preview failed') && title.value === 'Unsaved fixture title', 'Network failure must preserve the form.');
                refresh.click(); queue.shift().resolve(response(Object.assign(html('Incomplete source'), { issues: ['denied'] }))); await tick();
                assert(status().includes('content source failed or denied'), 'Source failure needs localized copy.');
                assert(panel.querySelector('[data-preview-time]').textContent === lastTime, 'Failed renders must retain the last successful timestamp.');
                refresh.click(); queue.shift().resolve(response(html('Recovered preview'))); await tick();
                assert(status().includes('Preview is current.'), 'Explicit refresh must recover.');
                assert(frame.getAttribute('sandbox') === 'allow-scripts', 'Preview must retain an opaque sandbox.');
                output.textContent += ' Auth / validation / network / source failures / recovery: PASS. Click Next collection page in the preview to test pagination.';
            } catch (error) { output.textContent = 'FAIL: ' + error.message; throw error; }
        });
    });
}());
