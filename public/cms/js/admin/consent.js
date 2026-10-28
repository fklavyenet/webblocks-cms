(function () {
    'use strict';
    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-consent-service-form]');
        if (!form) return;
        var handle = form.querySelector('[data-consent-handle]');
        if (handle) form.action = form.action.replace('__handle__', encodeURIComponent(handle.value));
        var rows = form.querySelectorAll('[data-consent-cookie-row]');
        if (!rows.length) {
            var empty = document.createElement('input');
            empty.type = 'hidden'; empty.name = '_clear_cookies'; empty.value = '1';
            form.appendChild(empty);
        }
    });
    document.addEventListener('click', function (event) {
        var add = event.target.closest('[data-consent-cookie-add]');
        if (add) {
            var form = add.closest('form');
            var template = form.querySelector('[data-consent-cookie-template]');
            var rows = form.querySelector('[data-consent-cookie-rows]');
            var index = Number(rows.dataset.nextIndex || Date.now());
            if (rows.children.length >= 20) return;
            var holder = document.createElement('template');
            holder.innerHTML = template.innerHTML.replaceAll('__index__', String(index));
            rows.appendChild(holder.content.cloneNode(true));
            rows.dataset.nextIndex = String(index + 1);
        }
        var remove = event.target.closest('[data-consent-cookie-remove]');
        if (remove) remove.closest('[data-consent-cookie-row]').remove();
    });
})();
