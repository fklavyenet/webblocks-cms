(function () {
    // The vocabulary here is the browser-side half of a pair: SafeRichTextRenderer
    // sanitizes the same content again on render, so a tag allowed in one and not
    // the other is silently dropped on the way to the page. Change both together.
    var INLINE_TAGS = ['strong', 'em', 'code', 's'];
    var INLINE_TAG_ALIASES = { b: 'strong', i: 'em', strike: 's', del: 's' };
    var DROPPED_TAG_PATTERN = /^(script|style|iframe|img|embed|object|template|svg|math|input|select|textarea|button)$/;
    var LIST_TAGS = ['ul', 'ol'];

    var KEYBOARD_SHORTCUTS = { b: 'bold', i: 'italic', k: 'link' };

    // Typed at the start of a block and completed with a space, the way the
    // markers read in plain text.
    var MARKDOWN_SHORTCUTS = [
        { pattern: /^[-*]$/, command: 'insertUnorderedList' },
        { pattern: /^1\.$/, command: 'insertOrderedList' },
        { pattern: /^>$/, action: 'quote' },
    ];

    var activeEditor = null;
    var linkContext = null;
    var focusContext = null;

    function dispatchEditorEvents(input) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value);
    }

    function hasMeaningfulText(html) {
        var text = String(html || '')
            .replace(/<br\s*\/?>/gi, '')
            .replace(/<[^>]+>/g, '')
            .replace(/&nbsp;/gi, ' ')
            .trim();

        return text !== '';
    }

    function isSafeHref(href) {
        var value = String(href || '').trim();

        if (!value || /\s/.test(value)) {
            return false;
        }

        if (value.charAt(0) === '/') {
            return value.slice(0, 2) !== '//';
        }

        if (value.charAt(0) === '#') {
            return value.length > 1;
        }

        if (!/^[A-Za-z][A-Za-z0-9+.-]*:/.test(value)) {
            return false;
        }

        var scheme = value.split(':', 1)[0].toLowerCase();

        if (scheme === 'http' || scheme === 'https') {
            try {
                var parsed = new URL(value);

                return parsed.protocol === 'http:' || parsed.protocol === 'https:';
            } catch (error) {
                return false;
            }
        }

        if (scheme === 'mailto' || scheme === 'tel') {
            return value.slice(scheme.length + 1).trim() !== '';
        }

        return false;
    }

    function unwrap(node) {
        var parent = node.parentNode;

        if (!parent) {
            return;
        }

        while (node.firstChild) {
            parent.insertBefore(node.firstChild, node);
        }

        parent.removeChild(node);
    }

    function sanitizeInlineNode(node, doc) {
        if (node.nodeType === Node.TEXT_NODE) {
            return escapeHtml(node.textContent || '');
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return '';
        }

        var tag = INLINE_TAG_ALIASES[node.tagName.toLowerCase()] || node.tagName.toLowerCase();

        if (DROPPED_TAG_PATTERN.test(tag)) {
            return '';
        }

        if (tag === 'br') {
            return '<br>';
        }

        if (INLINE_TAGS.indexOf(tag) !== -1) {
            var wrapped = sanitizeInlineChildren(node, doc);

            return hasMeaningfulText(wrapped) ? '<' + tag + '>' + wrapped + '</' + tag + '>' : '';
        }

        if (tag === 'a') {
            var href = String(node.getAttribute('href') || '').trim();
            var linked = sanitizeInlineChildren(node, doc);

            if (!hasMeaningfulText(linked)) {
                return '';
            }

            if (!isSafeHref(href)) {
                return linked;
            }

            return '<a href="' + escapeAttribute(href) + '">' + linked + '</a>';
        }

        return sanitizeInlineChildren(node, doc);
    }

    function sanitizeInlineChildren(node, doc) {
        var html = '';

        Array.prototype.slice.call(node.childNodes).forEach(function (child) {
            html += sanitizeInlineNode(child, doc);
        });

        return html;
    }

    function sanitizeListItem(node, doc) {
        var inline = '';
        var nested = '';

        Array.prototype.slice.call(node.childNodes).forEach(function (child) {
            if (child.nodeType === Node.ELEMENT_NODE && LIST_TAGS.indexOf(child.tagName.toLowerCase()) !== -1) {
                nested += sanitizeList(child, child.tagName.toLowerCase(), doc);

                return;
            }

            inline += sanitizeInlineNode(child, doc);
        });

        if (!hasMeaningfulText(inline) && nested === '') {
            return '';
        }

        return '<li>' + inline + nested + '</li>';
    }

    function sanitizeList(node, tagName, doc) {
        var items = '';

        Array.prototype.slice.call(node.childNodes).forEach(function (child) {
            if (child.nodeType === Node.ELEMENT_NODE && child.tagName.toLowerCase() === 'li') {
                items += sanitizeListItem(child, doc);

                return;
            }

            if (child.nodeType === Node.TEXT_NODE) {
                var text = escapeHtml(child.textContent || '');

                if (hasMeaningfulText(text)) {
                    items += '<li>' + text + '</li>';
                }

                return;
            }

            if (child.nodeType !== Node.ELEMENT_NODE) {
                return;
            }

            // A list nested directly under its parent list rather than inside an
            // item: browsers accept it, the HTML spec does not. Adopt it into the
            // previous item when there is one, so the level survives.
            if (LIST_TAGS.indexOf(child.tagName.toLowerCase()) !== -1) {
                var orphan = sanitizeList(child, child.tagName.toLowerCase(), doc);

                if (orphan === '') {
                    return;
                }

                if (items.slice(-5) === '</li>') {
                    items = items.slice(0, -5) + orphan + '</li>';

                    return;
                }

                items += '<li>' + orphan + '</li>';

                return;
            }

            var fallback = sanitizeInlineNode(child, doc);

            if (hasMeaningfulText(fallback)) {
                items += '<li>' + fallback + '</li>';
            }
        });

        return items ? '<' + tagName + '>' + items + '</' + tagName + '>' : '';
    }

    function sanitizeBlocks(nodes, doc, allowQuote) {
        var blocks = [];
        var inlineBuffer = '';

        function flushInlineBuffer() {
            if (!hasMeaningfulText(inlineBuffer)) {
                inlineBuffer = '';

                return;
            }

            blocks.push('<p>' + inlineBuffer + '</p>');
            inlineBuffer = '';
        }

        function consumeNode(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                inlineBuffer += escapeHtml(node.textContent || '');

                return;
            }

            if (node.nodeType !== Node.ELEMENT_NODE) {
                return;
            }

            var tag = node.tagName.toLowerCase();

            if (DROPPED_TAG_PATTERN.test(tag)) {
                return;
            }

            if (/^(p|h[1-6]|pre|td|th|caption)$/.test(tag)) {
                flushInlineBuffer();

                var paragraph = sanitizeInlineChildren(node, doc);

                if (tag === 'pre') {
                    paragraph = paragraph.replace(/\r\n?|\n/g, '<br>');
                }

                if (hasMeaningfulText(paragraph)) {
                    blocks.push('<p>' + paragraph + '</p>');
                }

                return;
            }

            if (LIST_TAGS.indexOf(tag) !== -1) {
                flushInlineBuffer();

                var list = sanitizeList(node, tag, doc);

                if (list) {
                    blocks.push(list);
                }

                return;
            }

            if (tag === 'li') {
                flushInlineBuffer();

                var item = sanitizeListItem(node, doc);

                if (item) {
                    blocks.push('<ul>' + item + '</ul>');
                }

                return;
            }

            if (tag === 'blockquote') {
                flushInlineBuffer();

                // A quote inside a quote flattens to one level: the editor offers no
                // way to build it and the rendered result is indistinguishable.
                var quoted = sanitizeBlocks(Array.prototype.slice.call(node.childNodes), doc, false);

                if (quoted !== '') {
                    blocks.push(allowQuote ? '<blockquote>' + quoted + '</blockquote>' : quoted);
                }

                return;
            }

            if (INLINE_TAGS.indexOf(INLINE_TAG_ALIASES[tag] || tag) !== -1 || tag === 'a' || tag === 'br') {
                inlineBuffer += sanitizeInlineNode(node, doc);
                return;
            }

            // Pasted containers may hold multiple paragraphs, lists, headings,
            // or table cells. Preserve their copy and block boundaries.
            Array.prototype.slice.call(node.childNodes).forEach(consumeNode);
        }

        nodes.forEach(consumeNode);
        flushInlineBuffer();

        return blocks.join('');
    }

    function sanitizeHtmlFragment(html, doc) {
        var template = doc.createElement('template');

        template.innerHTML = html || '';

        return sanitizeBlocks(Array.prototype.slice.call(template.content.childNodes), doc, true);
    }

    function convertTextToHtml(text) {
        var lines = String(text || '').replace(/\r\n?/g, '\n').split('\n');
        var blocks = [];
        var paragraph = [];

        function flushParagraph() {
            if (!paragraph.length) {
                return;
            }

            blocks.push('<p>' + paragraph.map(escapeHtml).join('<br>') + '</p>');
            paragraph = [];
        }

        lines.forEach(function (line) {
            if (line.trim() === '') {
                flushParagraph();
                return;
            }

            paragraph.push(line);
        });

        flushParagraph();

        return blocks.join('');
    }

    function getSelectionRange(surface) {
        var selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) {
            return null;
        }

        var range = selection.getRangeAt(0);

        if (!surface.contains(range.commonAncestorContainer)) {
            return null;
        }

        return range;
    }

    function closestElement(node, tagName, boundary) {
        var current = node;
        var expected = String(tagName || '').toUpperCase();

        while (current && current !== boundary) {
            if (current.nodeType === Node.ELEMENT_NODE && current.tagName === expected) {
                return current;
            }

            current = current.parentNode;
        }

        return null;
    }

    function closestEditorRoot(node) {
        var element = null;

        if (!node) {
            return null;
        }

        if (node.nodeType === Node.ELEMENT_NODE) {
            element = node;
        } else if (node.parentElement) {
            element = node.parentElement;
        }

        return element ? element.closest('[data-wb-rich-text-editor]') : null;
    }

    // range.collapse(true) collapses to the *start*, so `atEnd` has to invert it.
    // It did not, which is why the caret jumped to the top of the field after a
    // paste instead of landing after what was pasted.
    function placeCaretInside(node, atEnd) {
        var selection = window.getSelection();
        var range = document.createRange();

        range.selectNodeContents(node);
        range.collapse(atEnd === false);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function placeCaretAfter(node) {
        var selection = window.getSelection();
        var range = document.createRange();

        range.setStartAfter(node);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function focusSurface(surface) {
        if (document.activeElement !== surface) {
            surface.focus();
        }
    }

    function getEditor(root) {
        return root && root._wbRichTextEditor ? root._wbRichTextEditor : null;
    }

    function syncInput(editor, sanitized) {
        if (typeof sanitized === 'undefined') {
            sanitized = sanitizeHtmlFragment(editor.surface.innerHTML, document);
        }

        if (editor.input.value !== sanitized) {
            editor.input.value = sanitized;
            dispatchEditorEvents(editor.input);
        }

        return sanitized;
    }

    // Count text plus element boundaries, rather than child-node paths: HTML
    // reparsing merges adjacent text nodes and drops empty text nodes created by
    // Range operations. Boundary units retain empty paragraphs and line breaks.
    function nodeUnits(node) {
        if (node.nodeType === Node.TEXT_NODE) return node.textContent.length;
        return 2 + Array.prototype.reduce.call(node.childNodes, function (sum, child) { return sum + nodeUnits(child); }, 0);
    }

    function selectionBookmark(editor) {
        var range = getSelectionRange(editor.surface) || editor.savedRange;
        function point(target, offset) {
            var cursor = 0;
            var result = null;
            function walk(node) {
                if (node === target) {
                    result = cursor + (node.nodeType === Node.TEXT_NODE ? offset : (node === editor.surface ? 0 : 1) + Array.prototype.slice.call(node.childNodes, 0, offset).reduce(function (sum, child) { return sum + nodeUnits(child); }, 0));
                    return;
                }
                if (node.nodeType === Node.TEXT_NODE) { cursor += node.textContent.length; return; }
                if (node !== editor.surface) cursor++;
                Array.prototype.forEach.call(node.childNodes, function (child) { if (result === null) walk(child); });
                if (node !== editor.surface) cursor++;
            }
            walk(editor.surface);
            return result;
        }
        if (!range) return null;
        var start = point(range.startContainer, range.startOffset);
        var end = point(range.endContainer, range.endOffset);
        return start !== null && end !== null ? { start: start, end: end } : null;
    }

    function restoreBookmark(editor, bookmark) {
        if (!bookmark) return;
        function point(saved) {
            var cursor = 0;
            var result = null;
            function walk(node) {
                if (node.nodeType === Node.TEXT_NODE) {
                    if (saved >= cursor && saved <= cursor + node.textContent.length) result = { node: node, offset: saved - cursor };
                    cursor += node.textContent.length;
                    return;
                }
                if (node !== editor.surface) cursor++;
                for (var i = 0; i <= node.childNodes.length && !result; i++) {
                    if (saved === cursor) { result = { node: node, offset: i }; break; }
                    if (i < node.childNodes.length) walk(node.childNodes[i]);
                }
                if (node !== editor.surface) cursor++;
            }
            walk(editor.surface);
            return result;
        }
        try {
            var range = document.createRange();
            var start = point(bookmark.start);
            var end = point(bookmark.end);
            range.setStart(start.node, start.offset);
            range.setEnd(end.node, end.offset);
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            editor.savedRange = range.cloneRange();
        } catch (error) {
            placeCaretInside(editor.surface, true);
            saveSelection(editor);
        }
    }

    function snapshot(editor) {
        return { html: editor.surface.innerHTML, selection: selectionBookmark(editor) };
    }

    function rememberSelection(editor) {
        var current = editor.history[editor.historyIndex];
        var selection = selectionBookmark(editor);
        if (JSON.stringify(current.selection) !== JSON.stringify(selection)) editor.historyKind = null;
        if (current.html === editor.surface.innerHTML) current.selection = selection;
    }

    function recordHistory(editor, kind) {
        if (editor.mutating || editor.composing) return;
        var state = snapshot(editor);
        var current = editor.history[editor.historyIndex];
        var now = Date.now();
        if (current.html === state.html) {
            current.selection = state.selection;
            refreshToolbarState(editor);
            return;
        }
        var merge = kind && kind === editor.historyKind && now - editor.historyTime < 750;
        editor.history = editor.history.slice(0, editor.historyIndex + 1);
        if (merge && editor.historyIndex > 0) {
            editor.history[editor.historyIndex] = state;
        } else {
            editor.history.push(state);
            editor.historyIndex++;
        }
        // Bound both entry count and HTML storage; always retain one undo step.
        var size = editor.history.reduce(function (sum, item) { return sum + item.html.length * 2; }, 0);
        while (editor.history.length > 2 && (editor.history.length > 100 || size > 8 * 1024 * 1024)) {
            size -= editor.history.shift().html.length * 2;
            editor.historyIndex--;
        }
        editor.historyKind = kind || null;
        editor.historyTime = now;
        refreshToolbarState(editor);
    }

    function mutate(editor, callback) {
        rememberSelection(editor);
        editor.historyKind = null;
        editor.mutating = true;
        try { callback(); } finally {
            editor.mutating = false;
            recordHistory(editor, null);
        }
    }

    function moveHistory(editor, direction) {
        if (editor.composing) return;
        var index = editor.historyIndex + direction;
        if (index < 0 || index >= editor.history.length) return;
        editor.historyIndex = index;
        editor.historyKind = null;
        editor.savedRange = null;
        editor.surface.innerHTML = editor.history[index].html;
        focusSurface(editor.surface);
        restoreBookmark(editor, editor.history[index].selection);
        syncEditor(editor);
    }

    function updateWordCount(editor) {
        var counter = editor.root.querySelector('[data-wb-rich-text-word-count]');
        if (!counter) return;
        var text = editor.input.value.replace(/<[^>]*>/g, ' ');
        var plain = document.createElement('div');
        plain.innerHTML = text;
        text = (plain.textContent || '').trim();
        var count = text ? text.split(/\s+/u).length : 0;
        var label = count === 1 ? editor.root.dataset.wordCountSingular : editor.root.dataset.wordCountPlural;
        counter.textContent = (label || ':count').replace(':count', String(count));
    }

    function toggleFocus(editor) {
        var dialog = document.querySelector('[data-wb-rich-text-focus-modal]');
        var runtime = modalRuntime();
        if (!dialog || !runtime) return;
        if (focusContext) {
            runtime.close(dialog);
            return;
        }
        saveSelection(editor);
        var placeholder = document.createElement('div');
        editor.root.before(placeholder);
        // Keep the successful control in its original form, even while WBModal
        // portals the visual editor outside that form. Dirty tracking stays put.
        placeholder.appendChild(editor.input);
        focusContext = { editor: editor, placeholder: placeholder };
        dialog.querySelector('[data-wb-rich-text-focus-body]').appendChild(editor.root);
        editor.root.classList.add('is-focused');
        editor.root.querySelector('[data-wb-rich-text-action="focus"]').setAttribute('aria-pressed', 'true');
        runtime.open(dialog, null);
        restoreSelection(editor);
    }

    function leaveFocus() {
        if (!focusContext) return;
        var editor = focusContext.editor;
        var placeholder = focusContext.placeholder;
        placeholder.before(editor.root);
        editor.root.appendChild(editor.input);
        placeholder.remove();
        editor.root.classList.remove('is-focused');
        editor.root.querySelector('[data-wb-rich-text-action="focus"]').setAttribute('aria-pressed', 'false');
        focusContext = null;
        restoreSelection(editor);
    }

    function saveSelection(editor) {
        var range = getSelectionRange(editor.surface);

        if (!range) {
            return false;
        }

        editor.savedRange = range.cloneRange();
        activeEditor = editor;

        return true;
    }

    function restoreSelection(editor) {
        var selection = window.getSelection();

        if (!editor || !editor.savedRange || !selection) {
            return false;
        }

        var saved = editor.savedRange.cloneRange();
        focusSurface(editor.surface);

        try {
            selection.removeAllRanges();
            selection.addRange(saved);
            editor.savedRange = saved.cloneRange();
            activeEditor = editor;

            return true;
        } catch (error) {
            editor.savedRange = null;

            return false;
        }
    }

    function ensureSelection(editor) {
        if (restoreSelection(editor)) {
            return getSelectionRange(editor.surface);
        }

        focusSurface(editor.surface);

        var range = getSelectionRange(editor.surface);

        if (range) {
            editor.savedRange = range.cloneRange();

            return range;
        }

        placeCaretInside(editor.surface, true);
        saveSelection(editor);

        return getSelectionRange(editor.surface);
    }

    // Store clean markup without rebuilding the editing DOM on blur or submit.
    // History owns DOM restoration; sanitation must not move the writer's caret.
    function normalizeEditor(editor, options) {
        options = options || {};

        var sanitized = sanitizeHtmlFragment(editor.surface.innerHTML, document);

        syncInput(editor, sanitized);

        if (options.keepFocus !== false) {
            focusSurface(editor.surface);

            if (!editor.savedRange) {
                placeCaretInside(editor.surface, true);
            }

            saveSelection(editor);
        }

        refreshToolbarState(editor);
        updateWordCount(editor);
    }

    function syncEditor(editor) {
        syncInput(editor);
        saveSelection(editor);
        refreshToolbarState(editor);
        updateWordCount(editor);
    }

    function queryCommandState(command) {
        try {
            return document.queryCommandState(command);
        } catch (error) {
            return false;
        }
    }

    function refreshToolbarState(editor) {
        if (!editor || !editor.buttons.length) {
            return;
        }

        var range = getSelectionRange(editor.surface);
        var node = range ? range.commonAncestorContainer : null;
        var states = {
            bold: !!range && queryCommandState('bold'),
            italic: !!range && queryCommandState('italic'),
            strikethrough: !!range && queryCommandState('strikeThrough'),
            'bullet-list': !!range && queryCommandState('insertUnorderedList'),
            'numbered-list': !!range && queryCommandState('insertOrderedList'),
            code: !!range && !!closestElement(node, 'CODE', editor.surface),
            link: !!range && !!closestElement(node, 'A', editor.surface),
            quote: !!range && !!closestElement(node, 'BLOCKQUOTE', editor.surface),
        };

        editor.buttons.forEach(function (button) {
            var action = button.getAttribute('data-wb-rich-text-action');

            if (action === 'undo' || action === 'redo') {
                button.disabled = action === 'undo' ? editor.historyIndex === 0 : editor.historyIndex === editor.history.length - 1;
                return;
            }

            if (!Object.prototype.hasOwnProperty.call(states, action)) {
                return;
            }

            button.setAttribute('aria-pressed', states[action] ? 'true' : 'false');
        });
    }

    function execAndSync(editor, command, value) {
        if (!ensureSelection(editor)) {
            return;
        }

        focusSurface(editor.surface);
        document.execCommand(command, false, value || null);
        syncEditor(editor);
    }

    function applyCode(editor) {
        var range = ensureSelection(editor);

        if (!range) {
            return;
        }

        var existingCode = closestElement(range.commonAncestorContainer, 'CODE', editor.surface);

        if (existingCode) {
            var released = existingCode.firstChild;

            unwrap(existingCode);

            if (released) {
                placeCaretInside(released, true);
            }

            syncEditor(editor);

            return;
        }

        var wrapper = document.createElement('code');

        if (range.collapsed) {
            wrapper.textContent = 'code';
            range.insertNode(wrapper);
            placeCaretInside(wrapper, true);
            syncEditor(editor);

            return;
        }

        wrapper.appendChild(range.extractContents());
        range.insertNode(wrapper);
        placeCaretInside(wrapper, true);
        syncEditor(editor);
    }

    function applyQuote(editor) {
        var range = ensureSelection(editor);

        if (!range) {
            return;
        }

        var existingQuote = closestElement(range.commonAncestorContainer, 'BLOCKQUOTE', editor.surface);

        if (existingQuote) {
            var released = existingQuote.firstChild;

            unwrap(existingQuote);

            if (released) {
                placeCaretInside(released, true);
            }

            syncEditor(editor);

            return;
        }

        focusSurface(editor.surface);
        document.execCommand('formatBlock', false, 'blockquote');
        syncEditor(editor);
    }

    function isInsideList(editor) {
        var range = getSelectionRange(editor.surface);

        return !!range && !!closestElement(range.commonAncestorContainer, 'LI', editor.surface);
    }

    function isAtBlockStart(node, surface) {
        var current = node;

        while (current && current.parentNode && current.parentNode !== surface) {
            if (current.previousSibling) {
                return false;
            }

            current = current.parentNode;
        }

        return !!current && current.parentNode === surface;
    }

    function applyMarkdownShortcut(editor, event) {
        var range = getSelectionRange(editor.surface);

        if (!range || !range.collapsed) {
            return;
        }

        var node = range.startContainer;

        if (node.nodeType !== Node.TEXT_NODE || !isAtBlockStart(node, editor.surface)) {
            return;
        }

        var marker = String(node.textContent || '').slice(0, range.startOffset);
        var shortcut = null;

        MARKDOWN_SHORTCUTS.forEach(function (candidate) {
            if (candidate.pattern.test(marker)) {
                shortcut = candidate;
            }
        });

        if (!shortcut) {
            return;
        }

        event.preventDefault();

        node.textContent = String(node.textContent || '').slice(range.startOffset);

        var selection = window.getSelection();
        var collapsed = document.createRange();

        collapsed.setStart(node, 0);
        collapsed.collapse(true);
        selection.removeAllRanges();
        selection.addRange(collapsed);
        saveSelection(editor);

        if (shortcut.action) {
            handleAction(editor, shortcut.action);

            return;
        }

        execAndSync(editor, shortcut.command);
    }

    function handleKeydown(editor, event) {
        if (event.isComposing || editor.composing) return;
        if ((event.metaKey || event.ctrlKey) && !event.altKey) {
            var key = String(event.key || '').toLowerCase();
            if (key === 'z' || key === 'y') {
                event.preventDefault();
                event.stopPropagation();
                moveHistory(editor, key === 'y' || event.shiftKey ? 1 : -1);
                return;
            }
            var shortcutAction = KEYBOARD_SHORTCUTS[String(event.key || '').toLowerCase()];

            if (shortcutAction) {
                event.preventDefault();
                handleAction(editor, shortcutAction);

                return;
            }
        }

        // Tab is a list control only inside a list; everywhere else it stays the
        // browser's focus key, so the field never becomes a keyboard trap.
        if (event.key === 'Tab' && !event.metaKey && !event.ctrlKey && isInsideList(editor)) {
            event.preventDefault();
            mutate(editor, function () { execAndSync(editor, event.shiftKey ? 'outdent' : 'indent'); });

            return;
        }

        if (event.key === ' ') {
            mutate(editor, function () { applyMarkdownShortcut(editor, event); });
        }
    }

    function linkModal() {
        return document.querySelector('[data-wb-rich-text-link-modal]');
    }

    function modalRuntime() {
        return window.WBModal || null;
    }

    function setLinkError(dialog, message) {
        var error = dialog.querySelector('[data-wb-rich-text-link-error]');

        if (!error) {
            return;
        }

        error.textContent = message || '';
        error.hidden = !message;
    }

    function closeLinkModal(dialog) {
        var runtime = modalRuntime();

        if (runtime && typeof runtime.close === 'function') {
            runtime.close(dialog);

            return;
        }

        dialog.hidden = true;
        dialog.classList.remove('is-open');
    }

    function applyLink(editor) {
        var range = ensureSelection(editor);
        var dialog = linkModal();

        if (!range || !dialog) {
            return;
        }

        var existingLink = closestElement(range.commonAncestorContainer, 'A', editor.surface);
        var urlInput = dialog.querySelector('[data-wb-rich-text-link-url]');
        var textInput = dialog.querySelector('[data-wb-rich-text-link-text]');
        var removeButton = dialog.querySelector('[data-wb-rich-text-link-remove]');
        var runtime = modalRuntime();

        linkContext = { editor: editor, link: existingLink, selection: range.cloneRange() };

        if (urlInput) {
            urlInput.value = existingLink ? existingLink.getAttribute('href') || '' : '';
        }

        if (textInput) {
            textInput.value = existingLink ? existingLink.textContent || '' : range.toString();
        }

        if (removeButton) {
            removeButton.hidden = !existingLink;
        }

        setLinkError(dialog, '');

        if (runtime && typeof runtime.open === 'function') {
            runtime.open(dialog, null);
        } else {
            dialog.hidden = false;
            dialog.classList.add('is-open');
        }

        if (urlInput) {
            window.setTimeout(function () {
                urlInput.focus();
                urlInput.select();
            }, 0);
        }
    }

    function commitLink(dialog) {
        if (linkContext) mutate(linkContext.editor, function () { performCommitLink(dialog); });
    }

    function performCommitLink(dialog) {
        if (!linkContext) {
            return;
        }

        var editor = linkContext.editor;
        var existingLink = linkContext.link;
        var pendingSelection = linkContext.selection.cloneRange();
        var urlInput = dialog.querySelector('[data-wb-rich-text-link-url]');
        var textInput = dialog.querySelector('[data-wb-rich-text-link-text]');
        var href = urlInput ? urlInput.value.trim() : '';
        var label = textInput ? textInput.value.trim() : '';

        if (!isSafeHref(href)) {
            setLinkError(dialog, dialog.getAttribute('data-invalid-url-message'));

            if (urlInput) {
                urlInput.focus();
            }

            return;
        }

        closeLinkModal(dialog);
        linkContext = null;
        editor.savedRange = pendingSelection;

        var range = ensureSelection(editor);

        if (!range) {
            return;
        }

        if (existingLink) {
            existingLink.setAttribute('href', href);

            if (label !== '' && label !== existingLink.textContent) {
                existingLink.textContent = label;
            }

            placeCaretAfter(existingLink);
            syncEditor(editor);

            return;
        }

        // An unchanged label over a real selection keeps whatever markup the
        // selection carries -- bold inside a linked phrase survives. Any other
        // case is an explicit rewrite, so a plain anchor is what was asked for.
        if (!range.collapsed && (label === '' || label === range.toString())) {
            focusSurface(editor.surface);
            document.execCommand('unlink', false, null);
            document.execCommand('createLink', false, href);
            syncEditor(editor);

            return;
        }

        var link = document.createElement('a');

        link.setAttribute('href', href);
        link.textContent = label !== '' ? label : href;

        if (!range.collapsed) {
            range.deleteContents();
        }

        range.insertNode(link);
        placeCaretAfter(link);
        syncEditor(editor);
    }

    function removeLink(dialog) {
        if (linkContext) mutate(linkContext.editor, function () { performRemoveLink(dialog); });
    }

    function performRemoveLink(dialog) {
        if (!linkContext) {
            return;
        }

        var editor = linkContext.editor;
        var existingLink = linkContext.link;

        closeLinkModal(dialog);
        linkContext = null;

        if (!existingLink) {
            return;
        }

        var released = existingLink.firstChild;

        unwrap(existingLink);

        if (released) {
            placeCaretInside(released, true);
        }

        syncEditor(editor);
    }

    // A single pasted paragraph belongs inline at the caret; several paragraphs
    // retain their block boundaries. Both are one editor-history transaction.
    function inlinePasteContent(html) {
        var match = /^<p>([\s\S]*)<\/p>$/.exec(html || '');

        if (!match || /<(?:p|ul|ol|li|blockquote)\b/i.test(match[1])) {
            return null;
        }

        return match[1];
    }

    function handlePaste(editor, event) {
        mutate(editor, function () { pasteContent(editor, event); });
    }

    function pasteContent(editor, event) {
        event.preventDefault();

        var clipboard = event.clipboardData || event.dataTransfer || window.clipboardData;
        var html = clipboard && clipboard.getData ? clipboard.getData('text/html') : '';
        var text = clipboard && clipboard.getData ? clipboard.getData('text/plain') : '';
        var safeHtml = html ? sanitizeHtmlFragment(html, document) : convertTextToHtml(text);

        if (!safeHtml && !text) return;

        if (!ensureSelection(editor)) {
            return;
        }

        var inline = inlinePasteContent(safeHtml);

        document.execCommand('insertHTML', false, (inline === null ? safeHtml : inline) || escapeHtml(text));

        syncEditor(editor);
    }

    function handleAction(editor, action) {
        if (action === 'undo' || action === 'redo') {
            moveHistory(editor, action === 'undo' ? -1 : 1);
            return;
        }
        if (action === 'focus') {
            toggleFocus(editor);
            return;
        }
        // Commands may synchronously emit input events. Commit one complete
        // transaction, including direct DOM operations such as code and links.
        if (editor.mutating) {
            performAction(editor, action);
        } else {
            mutate(editor, function () { performAction(editor, action); });
        }
    }

    function performAction(editor, action) {
        if (!editor || !action) {
            return;
        }

        activeEditor = editor;

        if (action === 'bold') {
            execAndSync(editor, 'bold');

            return;
        }

        if (action === 'italic') {
            execAndSync(editor, 'italic');

            return;
        }

        if (action === 'strikethrough') {
            execAndSync(editor, 'strikeThrough');

            return;
        }

        if (action === 'code') {
            applyCode(editor);

            return;
        }

        if (action === 'quote') {
            applyQuote(editor);

            return;
        }

        if (action === 'link') {
            applyLink(editor);

            return;
        }

        if (action === 'bullet-list') {
            execAndSync(editor, 'insertUnorderedList');

            return;
        }

        if (action === 'numbered-list') {
            execAndSync(editor, 'insertOrderedList');

            return;
        }

        if (action === 'indent' || action === 'outdent') {
            execAndSync(editor, action);

            return;
        }

        if (action === 'clear') {
            if (!ensureSelection(editor)) {
                return;
            }

            document.execCommand('removeFormat', false, null);
            document.execCommand('unlink', false, null);
            syncEditor(editor);
        }
    }

    function bindEditor(root) {
        var existing = getEditor(root);

        if (existing) {
            return existing;
        }

        if (!root) {
            return null;
        }

        var surface = root.querySelector('[data-wb-rich-text-surface]');
        var input = root.querySelector('[data-wb-rich-text-input]');

        if (!surface || !input) {
            return null;
        }

        var editor = {
            root: root,
            surface: surface,
            input: input,
            savedRange: null,
            buttons: Array.prototype.slice.call(root.querySelectorAll('[data-wb-rich-text-action]')),
            history: [],
            historyIndex: 0,
            historyKind: null,
            historyTime: 0,
            mutating: false,
            composing: false,
        };

        var initialHtml = sanitizeHtmlFragment(input.value, document);

        root.dataset.wbRichTextBound = 'true';
        root._wbRichTextEditor = editor;
        surface.innerHTML = initialHtml || '<p><br></p>';

        if (input.value !== initialHtml) {
            input.value = initialHtml;
        }
        editor.history.push(snapshot(editor));
        refreshToolbarState(editor);
        updateWordCount(editor);

        surface.addEventListener('beforeinput', function (event) {
            if (editor.mutating || editor.composing) return;
            if (event.inputType === 'historyUndo' || event.inputType === 'historyRedo') {
                if (event.cancelable) {
                    event.preventDefault();
                    moveHistory(editor, event.inputType === 'historyUndo' ? -1 : 1);
                }
                return;
            }
            rememberSelection(editor);
        });

        surface.addEventListener('compositionstart', function () {
            rememberSelection(editor);
            editor.historyKind = null;
            editor.composing = true;
        });
        surface.addEventListener('compositionend', function () {
            editor.composing = false;
            syncEditor(editor);
            recordHistory(editor, null);
        });

        surface.addEventListener('focus', function () {
            activeEditor = editor;
            saveSelection(editor);
            refreshToolbarState(editor);
        });

        surface.addEventListener('input', function (event) {
            activeEditor = editor;
            if (!editor.mutating && (event.inputType === 'historyUndo' || event.inputType === 'historyRedo')) {
                moveHistory(editor, event.inputType === 'historyUndo' ? -1 : 1);
                // A non-cancelable native history event may have modified the
                // DOM even when there is no corresponding editor history step.
                editor.surface.innerHTML = editor.history[editor.historyIndex].html;
                restoreBookmark(editor, editor.history[editor.historyIndex].selection);
            }
            syncEditor(editor);
            recordHistory(editor, /^(insertText|deleteContentBackward|deleteContentForward)$/.test(event.inputType || '') ? event.inputType : null);
        });

        surface.addEventListener('keydown', function (event) {
            activeEditor = editor;
            handleKeydown(editor, event);
        });

        surface.addEventListener('keyup', function () {
            activeEditor = editor;
            saveSelection(editor);
            refreshToolbarState(editor);
        });

        surface.addEventListener('mouseup', function () {
            activeEditor = editor;
            saveSelection(editor);
            refreshToolbarState(editor);
        });

        surface.addEventListener('paste', function (event) {
            activeEditor = editor;
            handlePaste(editor, event);
        });

        surface.addEventListener('drop', function (event) {
            // Dropped HTML uses the same safe insertion path as clipboard HTML.
            event.preventDefault();
            var range = document.caretRangeFromPoint ? document.caretRangeFromPoint(event.clientX, event.clientY) : null;
            if (range && surface.contains(range.startContainer)) {
                var selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                saveSelection(editor);
            }
            handlePaste(editor, event);
        });

        surface.addEventListener('blur', function () {
            editor.historyKind = null;
            // The pending link dialog owns the field until it closes.
            if (linkContext && linkContext.editor === editor) {
                return;
            }

            normalizeEditor(editor, { keepFocus: false });
        });

        return editor;
    }

    function initializeEditors(context) {
        var roots = [];
        var scope = context || document;

        if (scope.matches && scope.matches('[data-wb-rich-text-editor]')) {
            roots.push(scope);
        }

        if (scope.querySelectorAll) {
            roots = roots.concat(Array.prototype.slice.call(scope.querySelectorAll('[data-wb-rich-text-editor]')));
        }

        roots.forEach(function (root) {
            bindEditor(root);
        });
    }

    document.addEventListener('selectionchange', function () {
        var selection = window.getSelection();
        var node = null;
        var root = null;
        var editor = null;

        if (!selection || selection.rangeCount === 0) {
            return;
        }

        node = selection.anchorNode || selection.focusNode || selection.getRangeAt(0).commonAncestorContainer;
        root = closestEditorRoot(node);

        if (!root) {
            return;
        }

        editor = bindEditor(root);

        if (!editor) {
            return;
        }

        activeEditor = editor;
        saveSelection(editor);
        refreshToolbarState(editor);
    });

    document.addEventListener('focusin', function (event) {
        var root = closestEditorRoot(event.target);
        var editor = null;

        if (!root) {
            return;
        }

        editor = bindEditor(root);

        if (!editor) {
            return;
        }

        if (event.target === editor.surface || editor.surface.contains(event.target)) {
            activeEditor = editor;
            saveSelection(editor);
        }
    });

    document.addEventListener('mousedown', function (event) {
        var button = event.target.closest('[data-wb-rich-text-action]');
        var root = null;
        var editor = null;

        if (button) {
            root = closestEditorRoot(button);
            editor = bindEditor(root);

            if (!editor) {
                return;
            }

            event.preventDefault();
            activeEditor = editor;
            restoreSelection(editor);
            return;
        }

        root = closestEditorRoot(event.target);

        if (!root) {
            return;
        }

        bindEditor(root);
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-wb-rich-text-action]');
        var root = null;
        var editor = null;

        if (!button) {
            return;
        }

        root = closestEditorRoot(button);
        editor = bindEditor(root);

        if (!editor) {
            return;
        }

        handleAction(editor, button.dataset.wbRichTextAction);
    });

    document.addEventListener('click', function (event) {
        var dialog = event.target.closest('[data-wb-rich-text-link-modal]');

        if (!dialog) {
            return;
        }

        if (event.target.closest('[data-wb-rich-text-link-apply]')) {
            event.preventDefault();
            commitLink(dialog);

            return;
        }

        if (event.target.closest('[data-wb-rich-text-link-remove]')) {
            event.preventDefault();
            removeLink(dialog);

            return;
        }

        if (event.target.closest('[data-wb-dismiss="modal"]')) {
            linkContext = null;
        }
    });

    document.addEventListener('keydown', function (event) {
        var dialog = event.target.closest ? event.target.closest('[data-wb-rich-text-link-modal]') : null;

        if (!dialog || event.key !== 'Enter') {
            return;
        }

        if (!event.target.matches('[data-wb-rich-text-link-url], [data-wb-rich-text-link-text]')) {
            return;
        }

        event.preventDefault();
        commitLink(dialog);
    });

    document.addEventListener('wb:modal:close', function (event) {
        if (event.target && event.target.matches('[data-wb-rich-text-focus-modal]')) leaveFocus();
        if (event.target && event.target.closest && event.target.closest('[data-wb-rich-text-link-modal]')) {
            linkContext = null;
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!form || !form.matches || !form.matches('form')) {
            return;
        }

        initializeEditors(form);

        Array.prototype.slice.call(form.querySelectorAll('[data-wb-rich-text-editor]')).forEach(function (root) {
            var editor = bindEditor(root);

            if (editor) {
                normalizeEditor(editor, { keepFocus: false });
            }
        });
    }, true);

    window.WebBlocksCmsAdminRichTextEditor = {
        init: initializeEditors,
        activeEditor: function () {
            return activeEditor;
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initializeEditors(document);
        });
    } else {
        initializeEditors(document);
    }
}());
