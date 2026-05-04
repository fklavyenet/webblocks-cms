(function () {
    function dispatchEditorEvents(textarea) {
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function replaceSelection(textarea, replacement, selectionStart, selectionEnd) {
        var value = textarea.value;

        textarea.value = value.slice(0, selectionStart) + replacement + value.slice(selectionEnd);
        textarea.focus();
        textarea.setSelectionRange(selectionStart, selectionStart + replacement.length);
        dispatchEditorEvents(textarea);
    }

    function isWrappedSelection(value, start, end, before, after) {
        if (
            start < before.length
            || end + after.length > value.length
            || value.slice(start - before.length, start) !== before
            || value.slice(end, end + after.length) !== after
        ) {
            return false;
        }

        return hasStandaloneWrapBoundary(value, start - before.length, end + after.length, before, after);
    }

    function hasStandaloneWrapBoundary(value, wrappedStart, wrappedEnd, before, after) {
        var beforeChar = before.charAt(0);
        var afterChar = after.charAt(after.length - 1);
        var previousChar = wrappedStart > 0 ? value.charAt(wrappedStart - 1) : '';
        var nextChar = wrappedEnd < value.length ? value.charAt(wrappedEnd) : '';

        if (before.length === 1 && before === after) {
            return previousChar !== beforeChar && nextChar !== afterChar;
        }

        return true;
    }

    function expandSelectionToExistingWrap(value, start, end, before, after) {
        if (!value || start > end) {
            return null;
        }

        var wrappedStart = start - before.length;
        var wrappedEnd = end + after.length;

        if (
            wrappedStart >= 0
            && wrappedEnd <= value.length
            && value.slice(wrappedStart, start) === before
            && value.slice(end, wrappedEnd) === after
            && hasStandaloneWrapBoundary(value, wrappedStart, wrappedEnd, before, after)
        ) {
            return {
                start: wrappedStart,
                end: wrappedEnd,
                innerStart: start,
                innerEnd: end,
            };
        }

        return null;
    }

    function toggleWrap(textarea, before, after, placeholder) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.slice(start, end);
        var expanded = expandSelectionToExistingWrap(value, start, end, before, after);

        if (selected && isWrappedSelection(value, start, end, before, after)) {
            replaceSelection(textarea, selected, start - before.length, end + after.length);
            textarea.setSelectionRange(start - before.length, end - before.length);
            return;
        }

        if (selected && expanded) {
            replaceSelection(textarea, selected, expanded.start, expanded.end);
            textarea.setSelectionRange(expanded.start, expanded.start + selected.length);
            return;
        }

        if (!selected && isWrappedSelection(value, start, end, before, after)) {
            replaceSelection(textarea, '', start - before.length, end + after.length);
            textarea.setSelectionRange(start - before.length, start - before.length);
            return;
        }

        if (!selected && expanded) {
            replaceSelection(textarea, '', expanded.start, expanded.end);
            textarea.setSelectionRange(expanded.start, expanded.start);
            return;
        }

        var replacement = selected || placeholder;
        var replacementStart = start + before.length;

        replaceSelection(textarea, before + replacement + after, start, end);
        textarea.setSelectionRange(replacementStart, replacementStart + replacement.length);
    }

    function getSelectedLinesRange(value, start, end) {
        var lineStart = value.lastIndexOf('\n', Math.max(0, start - 1));
        var lineEnd = value.indexOf('\n', end);

        return {
            start: lineStart === -1 ? 0 : lineStart + 1,
            end: lineEnd === -1 ? value.length : lineEnd,
        };
    }

    function toggleLinePrefix(textarea, applyPrefixFn, detectPrefixRegex, stripPrefixRegex, fallback) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;

        if (start === end) {
            replaceSelection(textarea, fallback, start, end);
            textarea.setSelectionRange(start, start + fallback.length);
            return;
        }

        var range = getSelectedLinesRange(value, start, end);
        var selectedBlock = value.slice(range.start, range.end);
        var lines = selectedBlock.split('\n');
        var nonEmptyLines = lines.filter(function (line) {
            return line.trim() !== '';
        });

        if (nonEmptyLines.length === 0) {
            replaceSelection(textarea, selectedBlock, range.start, range.end);
            textarea.setSelectionRange(range.start, range.end);
            return;
        }

        var shouldStrip = nonEmptyLines.every(function (line) {
            return detectPrefixRegex.test(line);
        });
        var index = 0;
        var replacement = lines.map(function (line) {
            if (line.trim() === '') {
                return line;
            }

            if (shouldStrip) {
                return line.replace(stripPrefixRegex, '');
            }

            index += 1;

            return applyPrefixFn(line, index);
        }).join('\n');

        replaceSelection(textarea, replacement, range.start, range.end);
        textarea.setSelectionRange(range.start, range.start + replacement.length);
    }

    function getMarkdownLinkRange(value, start, end) {
        var linkPattern = /\[[^\]]+\]\([^\s)]+\)/g;
        var match;

        while ((match = linkPattern.exec(value)) !== null) {
            var matchStart = match.index;
            var matchEnd = matchStart + match[0].length;

            if ((start === matchStart && end === matchEnd) || (start >= matchStart && end <= matchEnd)) {
                return {
                    start: matchStart,
                    end: matchEnd,
                    value: match[0],
                };
            }
        }

        return null;
    }

    function toggleLink(textarea) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.slice(start, end);
        var existingLink = getMarkdownLinkRange(value, start, end);
        var placeholder = 'link text';
        var urlPlaceholder = 'https://example.com';

        if (existingLink) {
            textarea.focus();
            textarea.setSelectionRange(existingLink.start, existingLink.end);
            return;
        }

        var replacement = '[' + (selected || placeholder) + '](' + urlPlaceholder + ')';
        var labelStart = start + 1;

        replaceSelection(textarea, replacement, start, end);
        textarea.setSelectionRange(labelStart, labelStart + (selected || placeholder).length);
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
                    toggleWrap(textarea, '**', '**', 'bold');
                    return;
                }

                if (action === 'italic') {
                    toggleWrap(textarea, '*', '*', 'italic');
                    return;
                }

                if (action === 'code') {
                    toggleWrap(textarea, '`', '`', 'code');
                    return;
                }

                if (action === 'link') {
                    toggleLink(textarea);
                    return;
                }

                if (action === 'bullet-list') {
                    toggleLinePrefix(
                        textarea,
                        function (line) {
                            return '- ' + line;
                        },
                        /^- /,
                        /^- /,
                        '- list item'
                    );
                    return;
                }

                if (action === 'numbered-list') {
                    toggleLinePrefix(
                        textarea,
                        function (line, index) {
                            return String(index) + '. ' + line;
                        },
                        /^\d+\. /,
                        /^\d+\. /,
                        '1. list item'
                    );
                }
            });
        });
    }

    document.querySelectorAll('[data-wb-rich-text-editor]').forEach(bindEditor);
}());
