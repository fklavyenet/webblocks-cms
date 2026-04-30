(function () {
    if (!document.querySelector('[data-wb-builder-items-editor]')) {
        return;
    }

    var admin = window.WebBlocksCmsAdmin || {};
    var escapeHtml = admin.escapeHtml || function (value) {
        return String(value || '');
    };

    function syncBuilderItems(editor) {
        if (!editor) {
            return;
        }

        var editorKey = editor.getAttribute('data-wb-builder-items-editor');
        var list = editor.querySelector('[data-wb-builder-item-list="' + editorKey + '"]');

        if (!list) {
            return;
        }

        var rows = Array.prototype.slice.call(list.querySelectorAll('[data-wb-builder-item-row="' + editorKey + '"]')).filter(function (row) {
            return !row.hidden;
        });

        rows.forEach(function (row, index) {
            var sortInput = row.querySelector('[data-wb-builder-item-sort="' + editorKey + '"]');
            var up = row.querySelector('[data-wb-builder-item-move="up"]');
            var down = row.querySelector('[data-wb-builder-item-move="down"]');

            if (sortInput) {
                sortInput.value = index;
            }

            if (up) {
                up.disabled = index === 0;
            }

            if (down) {
                down.disabled = index === rows.length - 1;
            }

            syncBuilderItemToggle(row);
        });

        var emptyState = list.querySelector('[data-wb-builder-item-empty="' + editorKey + '"]');

        if (rows.length === 0 && !emptyState) {
            var template = editor.querySelector('[data-wb-builder-item-template="' + editorKey + '"]');
            var empty = document.createElement('div');
            empty.className = 'wb-empty';
            empty.setAttribute('data-wb-builder-item-empty', editorKey);
            empty.innerHTML = '<div class="wb-empty-title">' + escapeHtml(template ? template.getAttribute('data-empty-title') : 'No items yet') + '</div><div class="wb-empty-text">' + escapeHtml(template ? template.getAttribute('data-empty-description') : 'Add the first item to continue.') + '</div>';
            list.appendChild(empty);
        }
    }

    function updateBuilderItemLabel(row) {
        if (!row) {
            return;
        }

        var editorKey = row.getAttribute('data-wb-builder-item-row');
        var label = row.querySelector('[data-wb-builder-item-label="' + editorKey + '"]');
        var titleInput = row.querySelector('[data-wb-builder-item-title="' + editorKey + '"]');

        if (label && titleInput) {
            label.textContent = titleInput.value.trim() || 'New Item';
        }
    }

    function syncBuilderItemToggle(row) {
        if (!row) {
            return;
        }

        var toggleButton = row.querySelector('[data-wb-builder-item-toggle]');
        var body = row.querySelector('[data-wb-builder-item-body]');
        var icon = toggleButton ? toggleButton.querySelector('.wb-icon') : null;

        if (!toggleButton || !body || !icon) {
            return;
        }

        var expanded = !body.hidden;

        toggleButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggleButton.setAttribute('title', expanded ? 'Collapse item' : 'Expand item');
        toggleButton.setAttribute('aria-label', expanded ? 'Collapse item' : 'Expand item');
        icon.classList.toggle('wb-icon-minus', expanded);
        icon.classList.toggle('wb-icon-plus', !expanded);
    }

    function addBuilderItem(editor) {
        var editorKey = editor.getAttribute('data-wb-builder-items-editor');
        var template = editor.querySelector('[data-wb-builder-item-template="' + editorKey + '"]');
        var list = editor.querySelector('[data-wb-builder-item-list="' + editorKey + '"]');
        var nextIndexInput = editor.querySelector('[data-wb-builder-item-next-index="' + editorKey + '"]');

        if (!template || !list || !nextIndexInput) {
            return;
        }

        var index = Number(nextIndexInput.value || '0');
        var wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
        var row = wrapper.firstElementChild;
        var emptyState = list.querySelector('[data-wb-builder-item-empty="' + editorKey + '"]');

        if (emptyState) {
            emptyState.remove();
        }

        list.appendChild(row);
        nextIndexInput.value = String(index + 1);
        syncBuilderItems(editor);
    }

    document.querySelectorAll('[data-wb-builder-items-editor]').forEach(function (editor) {
        syncBuilderItems(editor);
    });

    document.addEventListener('click', function (event) {
        var addButton = event.target.closest('[data-wb-builder-item-add]');
        var moveButton = event.target.closest('[data-wb-builder-item-move]');
        var toggleButton = event.target.closest('[data-wb-builder-item-toggle]');
        var removeButton = event.target.closest('[data-wb-builder-item-remove]');

        if (addButton) {
            addBuilderItem(addButton.closest('[data-wb-builder-items-editor]'));
            return;
        }

        if (moveButton) {
            var row = moveButton.closest('[data-wb-builder-item-row]');
            var editor = moveButton.closest('[data-wb-builder-items-editor]');

            if (!row || !editor || row.hidden) {
                return;
            }

            var direction = moveButton.getAttribute('data-wb-builder-item-move');
            var editorKey = editor.getAttribute('data-wb-builder-items-editor');
            var sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;

            while (sibling && (!sibling.matches('[data-wb-builder-item-row="' + editorKey + '"]') || sibling.hidden)) {
                sibling = direction === 'up' ? sibling.previousElementSibling : sibling.nextElementSibling;
            }

            if (!sibling) {
                return;
            }

            if (direction === 'up') {
                row.parentNode.insertBefore(row, sibling);
            } else {
                row.parentNode.insertBefore(sibling, row);
            }

            syncBuilderItems(editor);
            return;
        }

        if (toggleButton) {
            var itemRow = toggleButton.closest('[data-wb-builder-item-row]');
            var body = itemRow ? itemRow.querySelector('[data-wb-builder-item-body]') : null;

            if (body) {
                body.hidden = !body.hidden;
                syncBuilderItemToggle(itemRow);
            }

            return;
        }

        if (!removeButton) {
            return;
        }

        var removableRow = removeButton.closest('[data-wb-builder-item-row]');
        var removableEditor = removeButton.closest('[data-wb-builder-items-editor]');

        if (!removableRow || !removableEditor) {
            return;
        }

        var removableEditorKey = removableEditor.getAttribute('data-wb-builder-items-editor');
        var deleteInput = removableRow.querySelector('[data-wb-builder-item-delete="' + removableEditorKey + '"]');
        var idInput = removableRow.querySelector('input[name$="[id]"]');

        if (deleteInput && idInput && idInput.value) {
            deleteInput.value = '1';
            removableRow.hidden = true;
        } else {
            removableRow.remove();
        }

        syncBuilderItems(removableEditor);
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-wb-builder-item-title]')) {
            updateBuilderItemLabel(event.target.closest('[data-wb-builder-item-row]'));
        }
    });
}());
