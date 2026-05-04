(function () {
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

        var tag = node.tagName.toLowerCase();

        if (/^(script|style|iframe|img|figure|table|thead|tbody|tfoot|tr|td|th|button|h[1-6])$/.test(tag)) {
            return '';
        }

        if (tag === 'br') {
            return '<br>';
        }

        if (tag === 'strong' || tag === 'em' || tag === 'code') {
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
        var content = sanitizeInlineChildren(node, doc);

        if (!hasMeaningfulText(content)) {
            return '';
        }

        return '<li>' + content + '</li>';
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

            if (child.nodeType === Node.ELEMENT_NODE) {
                var fallback = sanitizeInlineNode(child, doc);

                if (hasMeaningfulText(fallback)) {
                    items += '<li>' + fallback + '</li>';
                }
            }
        });

        return items ? '<' + tagName + '>' + items + '</' + tagName + '>' : '';
    }

    function sanitizeHtmlFragment(html, doc) {
        var template = doc.createElement('template');
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

        function consumeRootNode(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                inlineBuffer += escapeHtml(node.textContent || '');
                return;
            }

            if (node.nodeType !== Node.ELEMENT_NODE) {
                return;
            }

            var tag = node.tagName.toLowerCase();

            if (/^(script|style|iframe|img|figure|table|thead|tbody|tfoot|tr|td|th|button|h[1-6])$/.test(tag)) {
                return;
            }

            if (tag === 'p') {
                flushInlineBuffer();

                var paragraph = sanitizeInlineChildren(node, doc);

                if (hasMeaningfulText(paragraph)) {
                    blocks.push('<p>' + paragraph + '</p>');
                }

                return;
            }

            if (tag === 'ul' || tag === 'ol') {
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

            inlineBuffer += sanitizeInlineNode(node, doc);
        }

        template.innerHTML = html || '';

        Array.prototype.slice.call(template.content.childNodes).forEach(consumeRootNode);
        flushInlineBuffer();

        return blocks.join('');
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

    function placeCaretInside(node, atEnd) {
        var selection = window.getSelection();
        var range = document.createRange();

        range.selectNodeContents(node);
        range.collapse(atEnd !== false);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    function focusSurface(surface) {
        if (document.activeElement !== surface) {
            surface.focus();
        }
    }

    function normalizeSurface(surface) {
        var sanitized = sanitizeHtmlFragment(surface.innerHTML, document);

        surface.innerHTML = sanitized || '<p><br></p>';
    }

    function syncInput(editor) {
        var sanitized = sanitizeHtmlFragment(editor.surface.innerHTML, document);

        editor.surface.innerHTML = sanitized || '<p><br></p>';

        if (editor.input.value !== sanitized) {
            editor.input.value = sanitized;
            dispatchEditorEvents(editor.input);
        }
    }

    function execAndSync(editor, command, value) {
        focusSurface(editor.surface);
        document.execCommand(command, false, value || null);
        normalizeSurface(editor.surface);
        syncInput(editor);
    }

    function applyCode(editor) {
        focusSurface(editor.surface);

        var range = getSelectionRange(editor.surface);

        if (!range) {
            return;
        }

        if (range.collapsed) {
            var code = document.createElement('code');

            code.textContent = 'code';
            range.insertNode(code);
            placeCaretInside(code, false);
            normalizeSurface(editor.surface);
            syncInput(editor);
            return;
        }

        var existingCode = closestElement(range.commonAncestorContainer, 'CODE', editor.surface);

        if (existingCode) {
            unwrap(existingCode);
            normalizeSurface(editor.surface);
            syncInput(editor);
            return;
        }

        var fragment = range.extractContents();
        var wrapper = document.createElement('code');

        wrapper.appendChild(fragment);
        range.insertNode(wrapper);
        placeCaretInside(wrapper, true);
        normalizeSurface(editor.surface);
        syncInput(editor);
    }

    function applyLink(editor) {
        focusSurface(editor.surface);

        var range = getSelectionRange(editor.surface);

        if (!range) {
            return;
        }

        var existingLink = closestElement(range.commonAncestorContainer, 'A', editor.surface);
        var currentHref = existingLink ? existingLink.getAttribute('href') || '' : '';
        var href = window.prompt('Enter link URL', currentHref || 'https://');

        if (href === null) {
            return;
        }

        href = href.trim();

        if (existingLink) {
            if (href === '') {
                unwrap(existingLink);
            } else if (isSafeHref(href)) {
                existingLink.setAttribute('href', href);
            }

            normalizeSurface(editor.surface);
            syncInput(editor);
            return;
        }

        if (range.collapsed || !isSafeHref(href)) {
            return;
        }

        document.execCommand('createLink', false, href);
        normalizeSurface(editor.surface);
        syncInput(editor);
    }

    function handlePaste(editor, event) {
        event.preventDefault();

        var clipboard = event.clipboardData || window.clipboardData;
        var html = clipboard && clipboard.getData ? clipboard.getData('text/html') : '';
        var text = clipboard && clipboard.getData ? clipboard.getData('text/plain') : '';
        var safeHtml = html ? sanitizeHtmlFragment(html, document) : convertTextToHtml(text);

        focusSurface(editor.surface);
        document.execCommand('insertHTML', false, safeHtml || escapeHtml(text));
        normalizeSurface(editor.surface);
        syncInput(editor);
    }

    function handleAction(editor, action) {
        if (action === 'bold') {
            execAndSync(editor, 'bold');
            return;
        }

        if (action === 'italic') {
            execAndSync(editor, 'italic');
            return;
        }

        if (action === 'code') {
            applyCode(editor);
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

        if (action === 'clear') {
            focusSurface(editor.surface);
            document.execCommand('removeFormat', false, null);
            document.execCommand('unlink', false, null);
            normalizeSurface(editor.surface);
            syncInput(editor);
        }
    }

    function bindEditor(root) {
        if (!root || root.dataset.wbRichTextBound === 'true') {
            return;
        }

        var surface = root.querySelector('[data-wb-rich-text-surface]');
        var input = root.querySelector('[data-wb-rich-text-input]');

        if (!surface || !input) {
            return;
        }

        root.dataset.wbRichTextBound = 'true';

        var editor = {
            root: root,
            surface: surface,
            input: input,
        };

        surface.innerHTML = sanitizeHtmlFragment(input.value, document) || '<p><br></p>';

        root.querySelectorAll('[data-wb-rich-text-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                handleAction(editor, button.dataset.wbRichTextAction);
            });
        });

        surface.addEventListener('input', function () {
            syncInput(editor);
        });

        surface.addEventListener('blur', function () {
            normalizeSurface(surface);
            syncInput(editor);
        });

        surface.addEventListener('paste', function (event) {
            handlePaste(editor, event);
        });

        if (input.form) {
            input.form.addEventListener('submit', function () {
                syncInput(editor);
            });
        }
    }

    document.querySelectorAll('[data-wb-rich-text-editor]').forEach(bindEditor);
}());
