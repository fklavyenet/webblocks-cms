(function () {
    var COLUMN_LABELS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    var ACTION_ICONS = {
        'row-move-up': 'chevron-up',
        'row-move-down': 'chevron-down',
        'row-remove': 'trash',
        'column-move-left': 'chevron-left',
        'column-move-right': 'chevron-right',
        'column-remove': 'trash',
    };

    var admin = window.WebBlocksCmsAdmin || {};
    var escapeHtml = admin.escapeHtml || function (value) {
        return String(value === null || typeof value === 'undefined' ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    function columnLabel(index) {
        var label = '';
        var value = index;

        do {
            label = COLUMN_LABELS.charAt(value % 26) + label;
            value = Math.floor(value / 26) - 1;
        } while (value >= 0);

        return label;
    }

    function normalizeCell(value) {
        return String(value === null || typeof value === 'undefined' ? '' : value)
            .replace(/[|\r\n\t]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function padMatrix(rows) {
        var width = rows.reduce(function (max, row) {
            return Math.max(max, row.length);
        }, 0);

        width = Math.max(width, 1);

        return rows.map(function (row) {
            var padded = row.slice(0, width);

            while (padded.length < width) {
                padded.push('');
            }

            return padded;
        });
    }

    function parseSource(text) {
        var rows = String(text || '')
            .replace(/\r\n?/g, '\n')
            .split('\n')
            .filter(function (line) {
                return line.trim() !== '';
            })
            .map(function (line) {
                return line.split('|').map(normalizeCell);
            });

        if (!rows.length) {
            return padMatrix([['', '', ''], ['', '', '']]);
        }

        return padMatrix(rows);
    }

    function serializeMatrix(rows) {
        return rows
            .filter(function (row) {
                return row.some(function (cell) {
                    return normalizeCell(cell) !== '';
                });
            })
            .map(function (row) {
                return row.map(normalizeCell).join(' | ');
            })
            .join('\n');
    }

    function parseClipboardMatrix(text) {
        var lines = String(text || '')
            .replace(/\r\n?/g, '\n')
            .replace(/\n$/, '')
            .split('\n');

        if (lines.length === 1 && lines[0].indexOf('\t') === -1) {
            return null;
        }

        return padMatrix(lines.map(function (line) {
            return line.split('\t').map(normalizeCell);
        }));
    }

    function label(editor, key) {
        return editor.labels[key] || key;
    }

    function actionButton(editor, action, key, dataset, disabled) {
        var text = label(editor, key);
        var attributes = '';

        Object.keys(dataset || {}).forEach(function (name) {
            attributes += ' data-' + name + '="' + escapeHtml(dataset[name]) + '"';
        });

        return '<button type="button" class="wb-action-btn" data-wb-table-action="' + action + '"'
            + attributes
            + (disabled ? ' disabled' : '')
            + ' title="' + escapeHtml(text) + '" aria-label="' + escapeHtml(text) + '">'
            + '<i class="wb-icon wb-icon-' + (ACTION_ICONS[action] || 'plus') + '" aria-hidden="true"></i>'
            + '</button>';
    }

    function buildHead(editor) {
        var width = editor.rows[0].length;
        var html = '<thead><tr><th class="wb-admin-table-grid-corner" scope="col"><span class="wb-sr-only">'
            + escapeHtml(label(editor, 'row_number')) + '</span></th>';

        for (var column = 0; column < width; column += 1) {
            html += '<th class="wb-admin-table-grid-colhead" scope="col">'
                + '<span class="wb-admin-table-grid-colname">' + escapeHtml(columnLabel(column)) + '</span>'
                + '<span class="wb-admin-table-grid-colactions">'
                + actionButton(editor, 'column-move-left', 'move_column_left', { 'wb-table-column': column }, column === 0)
                + actionButton(editor, 'column-move-right', 'move_column_right', { 'wb-table-column': column }, column === width - 1)
                + actionButton(editor, 'column-remove', 'remove_column', { 'wb-table-column': column }, width <= 1)
                + '</span>'
                + '</th>';
        }

        html += '<th class="wb-admin-table-grid-corner" scope="col"><span class="wb-sr-only">'
            + escapeHtml(label(editor, 'row_actions')) + '</span></th>';

        return html + '</tr></thead>';
    }

    function buildBody(editor) {
        var headerRow = editor.isHeaderRow();
        var html = '<tbody>';

        editor.rows.forEach(function (row, rowIndex) {
            var classes = ['wb-admin-table-grid-row'];

            if (headerRow && rowIndex === 0) {
                classes.push('is-header');
            }

            html += '<tr class="' + classes.join(' ') + '">'
                + '<th class="wb-admin-table-grid-rowhead" scope="row">' + (rowIndex + 1) + '</th>';

            row.forEach(function (cell, columnIndex) {
                html += '<td class="wb-admin-table-grid-cell">'
                    + '<input type="text" class="wb-input wb-admin-table-grid-input" autocomplete="off"'
                    + ' value="' + escapeHtml(cell) + '"'
                    + ' data-wb-table-cell data-wb-table-row="' + rowIndex + '" data-wb-table-column="' + columnIndex + '"'
                    + ' aria-label="' + escapeHtml(columnLabel(columnIndex) + (rowIndex + 1)) + '">'
                    + '</td>';
            });

            html += '<td class="wb-admin-table-grid-rowactions">'
                + actionButton(editor, 'row-move-up', 'move_row_up', { 'wb-table-row': rowIndex }, rowIndex === 0)
                + actionButton(editor, 'row-move-down', 'move_row_down', { 'wb-table-row': rowIndex }, rowIndex === editor.rows.length - 1)
                + actionButton(editor, 'row-remove', 'remove_row', { 'wb-table-row': rowIndex }, editor.rows.length <= 1)
                + '</td></tr>';
        });

        return html + '</tbody>';
    }

    function render(editor) {
        var focused = document.activeElement;
        var restore = null;

        if (focused && editor.grid.contains(focused) && focused.hasAttribute('data-wb-table-cell')) {
            restore = {
                row: Number(focused.getAttribute('data-wb-table-row')),
                column: Number(focused.getAttribute('data-wb-table-column')),
                start: focused.selectionStart,
            };
        }

        editor.rows = padMatrix(editor.rows);
        editor.grid.innerHTML = '<div class="wb-admin-table-grid-scroll"><table class="wb-admin-table-grid">'
            + buildHead(editor)
            + buildBody(editor)
            + '</table></div>';

        if (editor.pendingFocus) {
            restore = editor.pendingFocus;
            editor.pendingFocus = null;
        }

        if (restore) {
            focusCell(editor, restore.row, restore.column, restore.start);
        }

        syncSource(editor);
    }

    function focusCell(editor, row, column, caret) {
        var maxRow = editor.rows.length - 1;
        var maxColumn = editor.rows[0].length - 1;
        var target = editor.grid.querySelector(
            '[data-wb-table-cell][data-wb-table-row="' + Math.max(0, Math.min(row, maxRow))
            + '"][data-wb-table-column="' + Math.max(0, Math.min(column, maxColumn)) + '"]'
        );

        if (!target) {
            return;
        }

        target.focus();

        if (typeof caret === 'number') {
            try {
                target.setSelectionRange(caret, caret);
            } catch (error) {
                // Ignore browsers that reject selection ranges on this input.
            }
        }
    }

    function syncSource(editor) {
        var serialized = serializeMatrix(editor.rows);

        // Leave the stored value untouched until the editor is actually used, so
        // rebuilding the grid on load never trips the unsaved-changes guard.
        if (editor.initializing || editor.source.value === serialized) {
            return;
        }

        editor.source.value = serialized;
        editor.source.dispatchEvent(new Event('input', { bubbles: true }));
        editor.source.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function emptyRow(editor) {
        return editor.rows[0].map(function () {
            return '';
        });
    }

    function addRow(editor, index) {
        var position = typeof index === 'number' ? index : editor.rows.length;

        editor.rows.splice(position, 0, emptyRow(editor));
        editor.pendingFocus = { row: position, column: 0 };
        render(editor);
    }

    function addColumn(editor) {
        editor.rows = editor.rows.map(function (row) {
            return row.concat(['']);
        });
        editor.pendingFocus = { row: 0, column: editor.rows[0].length - 1 };
        render(editor);
    }

    function moveItem(list, from, to) {
        if (to < 0 || to >= list.length) {
            return false;
        }

        list.splice(to, 0, list.splice(from, 1)[0]);

        return true;
    }

    function handleAction(editor, button) {
        var action = button.getAttribute('data-wb-table-action');
        var row = Number(button.getAttribute('data-wb-table-row'));
        var column = Number(button.getAttribute('data-wb-table-column'));

        if (action === 'row-add') {
            addRow(editor);
            return;
        }

        if (action === 'column-add') {
            addColumn(editor);
            return;
        }

        if (action === 'row-remove') {
            if (editor.rows.length <= 1) {
                return;
            }

            editor.rows.splice(row, 1);
            editor.pendingFocus = { row: Math.max(0, row - 1), column: 0 };
            render(editor);
            return;
        }

        if (action === 'column-remove') {
            if (editor.rows[0].length <= 1) {
                return;
            }

            editor.rows = editor.rows.map(function (current) {
                current.splice(column, 1);

                return current;
            });
            editor.pendingFocus = { row: 0, column: Math.max(0, column - 1) };
            render(editor);
            return;
        }

        if (action === 'row-move-up' || action === 'row-move-down') {
            var target = action === 'row-move-up' ? row - 1 : row + 1;

            if (moveItem(editor.rows, row, target)) {
                editor.pendingFocus = { row: target, column: 0 };
                render(editor);
            }

            return;
        }

        if (action === 'column-move-left' || action === 'column-move-right') {
            var targetColumn = action === 'column-move-left' ? column - 1 : column + 1;

            if (targetColumn < 0 || targetColumn >= editor.rows[0].length) {
                return;
            }

            editor.rows.forEach(function (current) {
                moveItem(current, column, targetColumn);
            });
            editor.pendingFocus = { row: 0, column: targetColumn };
            render(editor);
        }
    }

    function handleCellPaste(editor, input, event) {
        var clipboard = event.clipboardData || window.clipboardData;
        var text = clipboard && clipboard.getData ? clipboard.getData('text/plain') : '';
        var matrix = parseClipboardMatrix(text);

        if (!matrix) {
            return;
        }

        event.preventDefault();

        var startRow = Number(input.getAttribute('data-wb-table-row'));
        var startColumn = Number(input.getAttribute('data-wb-table-column'));
        var width = Math.max(editor.rows[0].length, startColumn + matrix[0].length);

        while (editor.rows.length < startRow + matrix.length) {
            editor.rows.push([]);
        }

        editor.rows = editor.rows.map(function (row) {
            var padded = row.slice();

            while (padded.length < width) {
                padded.push('');
            }

            return padded;
        });

        matrix.forEach(function (row, rowOffset) {
            row.forEach(function (cell, columnOffset) {
                editor.rows[startRow + rowOffset][startColumn + columnOffset] = cell;
            });
        });

        editor.pendingFocus = {
            row: startRow + matrix.length - 1,
            column: startColumn + matrix[0].length - 1,
        };
        render(editor);
    }

    function handleCellKeydown(editor, input, event) {
        var row = Number(input.getAttribute('data-wb-table-row'));
        var column = Number(input.getAttribute('data-wb-table-column'));

        if (event.key === 'Enter') {
            event.preventDefault();

            if (row === editor.rows.length - 1) {
                addRow(editor, row + 1);
                focusCell(editor, row + 1, column);

                return;
            }

            focusCell(editor, row + 1, column);

            return;
        }

        if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            event.preventDefault();
            focusCell(editor, event.key === 'ArrowUp' ? row - 1 : row + 1, column);
        }
    }

    function setTextMode(editor, enabled) {
        if (enabled) {
            editor.source.value = serializeMatrix(editor.rows);
        } else {
            editor.rows = parseSource(editor.source.value);
        }

        editor.textMode = enabled;
        editor.grid.hidden = enabled;
        editor.gridToolbar.hidden = enabled;
        editor.source.hidden = !enabled;
        editor.toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        editor.toggle.textContent = label(editor, enabled ? 'grid_view' : 'text_view');

        if (!enabled) {
            render(editor);
        }
    }

    function bindEditor(root) {
        if (root._wbTableEditor) {
            return root._wbTableEditor;
        }

        var source = root.querySelector('[data-wb-table-source]');
        var grid = root.querySelector('[data-wb-table-grid]');
        var gridToolbar = root.querySelector('[data-wb-table-grid-toolbar]');
        var toggle = root.querySelector('[data-wb-table-toggle]');
        var labels = {};

        if (!source || !grid || !gridToolbar || !toggle) {
            return null;
        }

        try {
            labels = JSON.parse(root.getAttribute('data-wb-table-labels') || '{}');
        } catch (error) {
            labels = {};
        }

        var variantSelect = root.getAttribute('data-wb-table-variant')
            ? document.getElementById(root.getAttribute('data-wb-table-variant'))
            : null;

        var editor = {
            root: root,
            source: source,
            grid: grid,
            gridToolbar: gridToolbar,
            toggle: toggle,
            labels: labels,
            rows: parseSource(source.value),
            textMode: false,
            pendingFocus: null,
            initializing: true,
            isHeaderRow: function () {
                return !variantSelect || variantSelect.value !== 'plain';
            },
        };

        root._wbTableEditor = editor;
        root.dataset.wbTableBound = 'true';

        root.addEventListener('click', function (event) {
            var button = event.target.closest('[data-wb-table-action]');

            if (button && root.contains(button)) {
                event.preventDefault();
                handleAction(editor, button);

                return;
            }

            if (event.target.closest('[data-wb-table-toggle]')) {
                event.preventDefault();
                setTextMode(editor, !editor.textMode);
            }
        });

        root.addEventListener('input', function (event) {
            var input = event.target;

            if (!input.hasAttribute || !input.hasAttribute('data-wb-table-cell')) {
                return;
            }

            var cleaned = input.value.replace(/[|\r\n\t]/g, ' ');

            if (cleaned !== input.value) {
                var caret = input.selectionStart;

                input.value = cleaned;

                try {
                    input.setSelectionRange(caret, caret);
                } catch (error) {
                    // Ignore browsers that reject selection ranges on this input.
                }
            }

            editor.rows[Number(input.getAttribute('data-wb-table-row'))][Number(input.getAttribute('data-wb-table-column'))] = input.value;
            syncSource(editor);
        });

        root.addEventListener('keydown', function (event) {
            var input = event.target;

            if (input.hasAttribute && input.hasAttribute('data-wb-table-cell')) {
                handleCellKeydown(editor, input, event);
            }
        });

        root.addEventListener('paste', function (event) {
            var input = event.target;

            if (input.hasAttribute && input.hasAttribute('data-wb-table-cell')) {
                handleCellPaste(editor, input, event);
            }
        });

        if (variantSelect) {
            variantSelect.addEventListener('change', function () {
                if (!editor.textMode) {
                    render(editor);
                }
            });
        }

        source.addEventListener('input', function () {
            if (editor.textMode) {
                editor.rows = parseSource(source.value);
            }
        });

        setTextMode(editor, false);
        editor.initializing = false;

        return editor;
    }

    function initializeEditors(context) {
        var scope = context || document;

        if (!scope.querySelectorAll) {
            return;
        }

        Array.prototype.slice.call(scope.querySelectorAll('[data-wb-table-editor]')).forEach(bindEditor);
    }

    window.WebBlocksCmsAdminTableEditor = {
        init: initializeEditors,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initializeEditors(document);
        });
    } else {
        initializeEditors(document);
    }
}());
