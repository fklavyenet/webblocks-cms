(function () {
    'use strict';
    document.addEventListener('change', function (event) {
        var target = event.target;
        var names = target.matches('[data-wb-author-site]') ? ['page_id', 'block_id', 'template_id'] : (target.id === 'author-page' ? ['block_id', 'template_id'] : (target.id === 'author-block' ? ['template_id'] : []));
        if (!names.length || !target.form) return;
        var form = target.form;
        names.forEach(function (name) { var field = form.elements.namedItem(name); if (field) field.value = ''; });
        if (target.matches('[data-wb-author-site]')) form.requestSubmit();
    });
    function initialize(root) {
        (root || document).querySelectorAll('[data-wb-binding-picker]').forEach(function (picker) {
            if (picker.dataset.initialized) return;
            picker.dataset.initialized = '1';
            var original = picker.querySelector('[data-wb-binding-value]');
            var controls = picker.querySelector('[data-wb-binding-controls]');
            var parts = {};
            controls.querySelectorAll('[data-wb-binding-part]').forEach(function (part) { parts[part.dataset.wbBindingPart] = part; });
            var choices = Array.from(original.options).filter(function (option) { return option.value && !option.dataset.unavailable; }).map(function (option) {
                var path = option.value.split('|');
                return { value: option.value, source: path[0], record: path[1], field: path[2], sourceLabel: option.dataset.source, recordLabel: option.dataset.record, fieldLabel: option.dataset.field, mode: path[1] === '@item' ? 'current' : 'fixed' };
            });
            // An unavailable existing selection stays visible and preserved until explicitly replaced.
            if (original.selectedOptions[0] && original.selectedOptions[0].dataset.unavailable) return;
            function fill(select, rows, key, label, selected, blank) {
                select.replaceChildren();
                if (blank) select.add(new Option(select.dataset.emptyLabel, ''));
                var seen = new Set();
                rows.forEach(function (row) { if (!seen.has(row[key])) { select.add(new Option(row[label], row[key])); seen.add(row[key]); } });
                select.value = selected || '';
                select.disabled = select.options.length === 0;
            }
            parts.mode.add(new Option(parts.mode.dataset.literal, 'literal'));
            if (choices.some(function (c) { return c.mode === 'current'; })) parts.mode.add(new Option(parts.mode.dataset.current, 'current'));
            if (choices.some(function (c) { return c.mode === 'fixed'; })) parts.mode.add(new Option(parts.mode.dataset.fixed, 'fixed'));
            var selected = choices.find(function (c) { return c.value === original.value; });
            parts.mode.value = selected ? selected.mode : 'literal';
            function redraw(from) {
                var mode = parts.mode.value;
                var rows = choices.filter(function (c) { return c.mode === mode; });
                var source = parts.source.value, record = parts.record.value, field = parts.field.value;
                if (from === 'mode') source = record = field = '';
                if (from === 'source') record = field = '';
                if (from === 'record') field = '';
                fill(parts.source, rows, 'source', 'sourceLabel', source, true);
                if (!source && rows.length && mode === 'current') { source = rows[0].source; parts.source.value = source; }
                rows = rows.filter(function (c) { return c.source === source; });
                fill(parts.record, rows, 'record', 'recordLabel', mode === 'current' ? '@item' : record, mode !== 'current');
                record = parts.record.value;
                rows = rows.filter(function (c) { return c.record === record; });
                fill(parts.field, rows, 'field', 'fieldLabel', field, true);
                ['source', 'record', 'field'].forEach(function (key) { parts[key].parentElement.hidden = mode === 'literal' || (mode === 'current' && key === 'record'); });
                var match = rows.find(function (c) { return c.field === parts.field.value; });
                // Never silently turn an incomplete dynamic selection into a literal binding.
                var nextValue = mode === 'literal' ? '' : (match ? match.value : 'incomplete|selection|field');
                if (mode !== 'literal' && !match && !Array.from(original.options).some(function (o) { return o.value === nextValue; })) {
                    original.add(new Option('', 'incomplete|selection|field'));
                }
                original.value = nextValue;
                var summary = picker.querySelector('[data-wb-binding-summary]');
                if (!summary.dataset.fallback) summary.dataset.fallback = summary.textContent;
                summary.textContent = match ? match.sourceLabel + ' / ' + match.recordLabel + ' / ' + match.fieldLabel : summary.dataset.fallback;
                parts.field.required = mode !== 'literal';
                parts.source.required = mode !== 'literal';
                parts.record.required = mode === 'fixed';
            }
            if (selected) { parts.source.add(new Option(selected.sourceLabel, selected.source)); parts.record.add(new Option(selected.recordLabel, selected.record)); parts.field.add(new Option(selected.fieldLabel, selected.field)); }
            var search = picker.querySelector('[data-wb-binding-search]');
            function filterOptions() {
                var query = search ? search.value.trim().toLocaleLowerCase() : '';
                ['record', 'field'].forEach(function (part) {
                    Array.from(parts[part].options).forEach(function (option) { option.hidden = Boolean(query && option.value && !option.selected && !option.text.toLocaleLowerCase().includes(query)); });
                });
            }
            if (search) search.addEventListener('input', filterOptions);
            ['mode', 'source', 'record', 'field'].forEach(function (part) { parts[part].addEventListener('change', function () { redraw(part); filterOptions(); }); });
            redraw('initial');
            original.hidden = true;
            controls.hidden = false;
        });
    }
    document.addEventListener('DOMContentLoaded', function () { initialize(document); });
    new MutationObserver(function (mutations) { mutations.forEach(function (mutation) { mutation.addedNodes.forEach(function (node) { if (node.nodeType === 1) initialize(node.parentElement || node); }); }); }).observe(document.documentElement, {childList: true, subtree: true});
    window.WebBlocksCmsInitializeBindingPickers = initialize;
    initialize(document);
})();
