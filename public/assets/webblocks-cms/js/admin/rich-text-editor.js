(function () {
    const EDITOR_SELECTOR = '[data-wb-rich-text-editor]';
    const ALLOWED_TAGS = new Set(['P', 'BR', 'STRONG', 'EM', 'CODE', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'A']);
    const BLOCK_TAGS = new Set(['P', 'UL', 'OL', 'LI', 'BLOCKQUOTE']);
    const INLINE_TAGS = new Set(['STRONG', 'EM', 'CODE', 'A', 'BR']);

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function isAllowedHref(value) {
        const href = String(value || '').trim();

        if (!href || /\s/.test(href)) {
            return false;
        }

        if (/^[A-Za-z][A-Za-z0-9+.-]*:/.test(href)) {
            const scheme = href.split(':', 1)[0].toLowerCase();

            if (scheme === 'http' || scheme === 'https') {
                try {
                    const url = new URL(href);
                    return url.protocol === 'http:' || url.protocol === 'https:';
                } catch (error) {
                    return false;
                }
            }

            if (scheme === 'mailto') {
                return href.slice(7).trim() !== '';
            }

            if (scheme === 'tel') {
                return href.slice(4).trim() !== '';
            }

            return false;
        }

        return href.startsWith('/') || href.startsWith('#') || href.startsWith('?');
    }

    function renameElement(documentRef, node, tagName) {
        const replacement = documentRef.createElement(tagName);

        while (node.firstChild) {
            replacement.appendChild(node.firstChild);
        }

        node.parentNode.replaceChild(replacement, node);

        return replacement;
    }

    function unwrapElement(node) {
        const parent = node.parentNode;

        if (!parent) {
            return;
        }

        while (node.firstChild) {
            parent.insertBefore(node.firstChild, node);
        }

        parent.removeChild(node);
    }

    function sanitizeNode(documentRef, node) {
        if (node.nodeType === Node.COMMENT_NODE) {
            node.remove();
            return;
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        let element = node;
        let tagName = element.tagName.toUpperCase();

        if (tagName === 'B') {
            element = renameElement(documentRef, element, 'strong');
            tagName = 'STRONG';
        }

        if (tagName === 'I') {
            element = renameElement(documentRef, element, 'em');
            tagName = 'EM';
        }

        if (tagName === 'DIV') {
            element = renameElement(documentRef, element, 'p');
            tagName = 'P';
        }

        if (['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'SVG', 'MATH', 'NOSCRIPT'].includes(tagName)) {
            element.remove();
            return;
        }

        Array.from(element.childNodes).forEach((child) => sanitizeNode(documentRef, child));

        if (!ALLOWED_TAGS.has(tagName)) {
            unwrapElement(element);
            return;
        }

        Array.from(element.attributes).forEach((attribute) => {
            const name = attribute.name.toLowerCase();

            if (tagName !== 'A' || !['href', 'target', 'rel'].includes(name)) {
                element.removeAttribute(attribute.name);
            }
        });

        if (tagName !== 'A') {
            return;
        }

        const href = (element.getAttribute('href') || '').trim();

        if (!isAllowedHref(href)) {
            element.removeAttribute('href');
            element.removeAttribute('target');
            element.removeAttribute('rel');
            return;
        }

        element.setAttribute('href', href);

        if (element.getAttribute('target') === '_blank') {
            element.setAttribute('target', '_blank');
            element.setAttribute('rel', 'noopener noreferrer');
            return;
        }

        element.removeAttribute('target');
        element.removeAttribute('rel');
    }

    function normalizeRoot(root) {
        let paragraph = null;
        let node = root.firstChild;

        while (node) {
            const next = node.nextSibling;

            if (node.nodeType === Node.ELEMENT_NODE && BLOCK_TAGS.has(node.tagName.toUpperCase())) {
                paragraph = null;
                node = next;
                continue;
            }

            if (node.nodeType === Node.ELEMENT_NODE && !INLINE_TAGS.has(node.tagName.toUpperCase())) {
                node.remove();
                node = next;
                continue;
            }

            if (!paragraph) {
                paragraph = root.ownerDocument.createElement('p');
                root.insertBefore(paragraph, node);
            }

            paragraph.appendChild(node);
            node = next;
        }
    }

    function sanitizeHtml(html) {
        const parser = new DOMParser();
        const documentRef = parser.parseFromString('<div>' + String(html || '') + '</div>', 'text/html');
        const root = documentRef.body.firstElementChild;

        if (!root) {
            return '';
        }

        Array.from(root.childNodes).forEach((child) => sanitizeNode(documentRef, child));
        normalizeRoot(root);

        return root.textContent.replace(/\u00a0/g, ' ').trim() === '' ? '' : root.innerHTML.trim();
    }

    function insertHtmlAtSelection(surface, html) {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) {
            surface.focus();
            return;
        }

        const range = selection.getRangeAt(0);
        range.deleteContents();

        const fragment = range.createContextualFragment(html);
        const lastNode = fragment.lastChild;
        range.insertNode(fragment);

        if (lastNode) {
            range.setStartAfter(lastNode);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        }
    }

    function wrapSelection(surface, html) {
        surface.focus();

        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
            return;
        }

        const text = selection.toString();

        if (!text.trim()) {
            return;
        }

        insertHtmlAtSelection(surface, html.replace('%s', escapeHtml(text)));
    }

    function sanitizedValue(editor) {
        const input = editor.querySelector('[data-wb-rich-text-input]');
        const surface = editor.querySelector('[data-wb-rich-text-surface]');

        if (!input || !surface) {
            return '';
        }

        return sanitizeHtml(surface.innerHTML);
    }

    function syncEditor(editor, normalizeSurface) {
        const input = editor.querySelector('[data-wb-rich-text-input]');
        const surface = editor.querySelector('[data-wb-rich-text-surface]');

        if (!input || !surface) {
            return;
        }

        const sanitized = sanitizedValue(editor);
        input.value = sanitized;

        if (normalizeSurface) {
            surface.innerHTML = sanitized;
        }
    }

    function runCommand(editor, command) {
        const surface = editor.querySelector('[data-wb-rich-text-surface]');

        if (!surface) {
            return;
        }

        surface.focus();

        if (command === 'bold') {
            document.execCommand('bold');
        } else if (command === 'italic') {
            document.execCommand('italic');
        } else if (command === 'unordered-list') {
            document.execCommand('insertUnorderedList');
        } else if (command === 'ordered-list') {
            document.execCommand('insertOrderedList');
        } else if (command === 'blockquote') {
            document.execCommand('formatBlock', false, 'blockquote');
        } else if (command === 'link') {
            const href = window.prompt('Enter a link URL', 'https://');

            if (!href) {
                return;
            }

            if (!isAllowedHref(href)) {
                window.alert('Use a safe link URL starting with http, https, mailto, tel, /, #, or ?.');
                return;
            }

            document.execCommand('createLink', false, href.trim());
        } else if (command === 'code') {
            wrapSelection(surface, '<code>%s</code>');
        } else if (command === 'clear') {
            document.execCommand('removeFormat');
            document.execCommand('unlink');
        }

        syncEditor(editor, true);
    }

    function initializeEditor(editor) {
        if (editor.dataset.wbRichTextEditorReady === 'true') {
            return;
        }

        const input = editor.querySelector('[data-wb-rich-text-input]');
        const surface = editor.querySelector('[data-wb-rich-text-surface]');

        if (!input || !surface) {
            return;
        }

        editor.dataset.wbRichTextEditorReady = 'true';
        surface.innerHTML = sanitizeHtml(input.value);
        input.value = surface.innerHTML;

        editor.querySelectorAll('[data-wb-rich-text-command]').forEach((button) => {
            button.addEventListener('click', function () {
                runCommand(editor, this.getAttribute('data-wb-rich-text-command'));
            });
        });

        surface.addEventListener('input', function () {
            syncEditor(editor, false);
        });

        surface.addEventListener('blur', function () {
            syncEditor(editor, true);
        });

        surface.addEventListener('paste', function (event) {
            event.preventDefault();

            const clipboard = event.clipboardData;
            const html = clipboard ? clipboard.getData('text/html') : '';
            const text = clipboard ? clipboard.getData('text/plain') : '';
            const sanitized = sanitizeHtml(html || escapeHtml(text).replace(/\n/g, '<br>'));

            if (sanitized) {
                insertHtmlAtSelection(surface, sanitized);
                syncEditor(editor, true);
            }
        });

        const form = editor.closest('form');

        if (form) {
            form.addEventListener('submit', function () {
                syncEditor(editor, true);
            });
        }
    }

    function initializeAllEditors() {
        document.querySelectorAll(EDITOR_SELECTOR).forEach(initializeEditor);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAllEditors);
    } else {
        initializeAllEditors();
    }
})();
