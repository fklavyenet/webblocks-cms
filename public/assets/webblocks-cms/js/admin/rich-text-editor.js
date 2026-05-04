(function () {
    function dispatchEditorEvents(textarea) {
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function replaceSelection(textarea, before, after, fallback, selectFallback) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.slice(start, end);
        var replacement = selected || fallback;
        var nextValue = value.slice(0, start) + before + replacement + after + value.slice(end);
        var selectionStart = start + before.length;
        var selectionEnd = selectionStart + replacement.length;

        textarea.value = nextValue;
        textarea.focus();

        if (selected || selectFallback) {
            textarea.setSelectionRange(selectionStart, selectionEnd);
        } else {
            textarea.setSelectionRange(selectionEnd + after.length, selectionEnd + after.length);
        }

        dispatchEditorEvents(textarea);
    }

    function replaceLines(textarea, formatter, fallback, selectFallback) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.slice(start, end);
        var replacement = selected
            ? selected.split(/\r?\n/).map(function (line, index) {
                return formatter(line, index);
            }).join('\n')
            : fallback;
        var nextValue = value.slice(0, start) + replacement + value.slice(end);

        textarea.value = nextValue;
        textarea.focus();

        if (selected || selectFallback) {
            textarea.setSelectionRange(start, start + replacement.length);
        } else {
            textarea.setSelectionRange(start + replacement.length, start + replacement.length);
        }

        dispatchEditorEvents(textarea);
    }

    function bindEditor(textarea) {
        var toolbar = textarea.parentElement ? textarea.parentElement.querySelector('[role="toolbar"]') : null;

        if (!toolbar || textarea.dataset.wbRichTextBound === 'true') {
            return;
        }

        textarea.dataset.wbRichTextBound = 'true';

        toolbar.querySelectorAll('[data-wb-rich-text-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.dataset.wbRichTextAction;

                if (action === 'bold') {
                    replaceSelection(textarea, '**', '**', 'bold', true);
                    return;
                }

                if (action === 'italic') {
                    replaceSelection(textarea, '*', '*', 'italic', true);
                    return;
                }

                if (action === 'code') {
                    replaceSelection(textarea, '`', '`', 'code', true);
                    return;
                }

                if (action === 'link') {
                    replaceSelection(textarea, '[', '](https://example.com)', 'link text', true);
                    return;
                }

                if (action === 'bullet-list') {
                    replaceLines(textarea, function (line) {
                        return '- ' + line;
                    }, '- list item', false);
                    return;
                }

                if (action === 'numbered-list') {
                    replaceLines(textarea, function (line, index) {
                        return String(index + 1) + '. ' + line;
                    }, '1. list item', false);
                }
            });
        });
    }

    document.querySelectorAll('[data-wb-rich-text-editor]').forEach(bindEditor);
}());
