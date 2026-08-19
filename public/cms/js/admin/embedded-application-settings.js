(function () {
    var editor = document.querySelector('[data-wb-application-settings]');
    var modal = document.getElementById('embedded-application-setting-modal');

    if (!editor || !modal) {
        return;
    }

    var rows = editor.querySelector('[data-wb-settings-rows]');
    var table = editor.querySelector('[data-wb-settings-table]');
    var empty = editor.querySelector('[data-wb-settings-empty]');
    var title = modal.querySelector('[data-wb-setting-modal-title]');
    var save = modal.querySelector('[data-wb-setting-save]');
    var cancel = modal.querySelector('[data-wb-setting-cancel]');
    var activeRow = null;
    var fields = ['key', 'type', 'default', 'values', 'min', 'max', 'max_length'];

    function input(field) {
        return modal.querySelector('[data-wb-setting-input="' + field + '"]');
    }

    function value(field) {
        var element = input(field);

        return element ? String(element.value || '').trim() : '';
    }

    function label(name) {
        return String(editor.getAttribute('data-' + name) || '');
    }

    function resetModal() {
        activeRow = null;
        fields.forEach(function (field) {
            input(field).value = field === 'type' ? 'string' : '';
        });
        input('key').setCustomValidity('');
        title.textContent = label('add-title');
        syncTypeFields();
    }

    function populateModal(row) {
        activeRow = row;
        fields.forEach(function (field) {
            var stored = row.querySelector('[data-wb-setting-field="' + field + '"]');
            input(field).value = stored ? stored.value : '';
        });
        input('key').setCustomValidity('');
        title.textContent = label('edit-title');
        syncTypeFields();
    }

    function syncTypeFields() {
        var type = value('type') || 'string';

        modal.querySelectorAll('[data-wb-setting-group]').forEach(function (group) {
            group.hidden = group.getAttribute('data-wb-setting-group') !== type;
        });
    }

    function constraintSummary(setting) {
        var parts = [];

        if (setting.values) {
            parts.push(label('enum-label') + ': ' + setting.values);
        }
        if (setting.min) {
            parts.push(label('min-label') + ': ' + setting.min);
        }
        if (setting.max) {
            parts.push(label('max-label') + ': ' + setting.max);
        }
        if (setting.max_length) {
            parts.push(label('max-length-label') + ': ' + setting.max_length);
        }

        return parts.join(' · ') || label('no-constraints');
    }

    function actionButton(action, icon, actionLabel) {
        var button = document.createElement('button');
        var iconElement = document.createElement('i');

        button.type = 'button';
        button.className = 'wb-action-btn wb-action-btn-' + (action === 'edit' ? 'edit' : 'delete');
        button.setAttribute('data-wb-setting-' + action, '');
        button.title = actionLabel;
        button.setAttribute('aria-label', actionLabel);
        if (action === 'edit') {
            button.setAttribute('data-wb-toggle', 'modal');
            button.setAttribute('data-wb-target', '#embedded-application-setting-modal');
        }
        iconElement.className = 'wb-icon wb-icon-' + icon;
        iconElement.setAttribute('aria-hidden', 'true');
        button.appendChild(iconElement);

        return button;
    }

    function buildRow(setting) {
        var row = document.createElement('tr');
        var keyCell = document.createElement('td');
        var typeCell = document.createElement('td');
        var typeCode = document.createElement('code');
        var defaultCell = document.createElement('td');
        var constraintsCell = document.createElement('td');
        var actionsCell = document.createElement('td');
        var actions = document.createElement('div');

        row.setAttribute('data-wb-setting-row', '');
        keyCell.setAttribute('data-wb-setting-summary', 'key');
        typeCode.setAttribute('data-wb-setting-summary', 'type');
        defaultCell.setAttribute('data-wb-setting-summary', 'default');
        constraintsCell.setAttribute('data-wb-setting-summary', 'constraints');
        actionsCell.className = 'wb-table-actions';
        actions.className = 'wb-flex wb-items-center wb-gap-2';
        actions.appendChild(actionButton('edit', 'pencil', label('edit-label')));
        actions.appendChild(actionButton('delete', 'trash', label('delete-label')));
        actionsCell.appendChild(actions);
        typeCell.appendChild(typeCode);
        row.appendChild(keyCell);
        row.appendChild(typeCell);
        row.appendChild(defaultCell);
        row.appendChild(constraintsCell);
        row.appendChild(actionsCell);

        fields.forEach(function (field) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.setAttribute('data-wb-setting-field', field);
            actionsCell.appendChild(hidden);
        });

        updateRow(row, setting);

        return row;
    }

    function updateRow(row, setting) {
        fields.forEach(function (field) {
            row.querySelector('[data-wb-setting-field="' + field + '"]').value = setting[field] || '';
        });
        row.querySelector('[data-wb-setting-summary="key"]').textContent = setting.key;
        row.querySelector('[data-wb-setting-summary="type"]').textContent = setting.type;
        row.querySelector('[data-wb-setting-summary="default"]').textContent = setting.default || label('no-default');
        row.querySelector('[data-wb-setting-summary="constraints"]').textContent = constraintSummary(setting);
    }

    function reindex() {
        var currentRows = rows.querySelectorAll('[data-wb-setting-row]');

        currentRows.forEach(function (row, index) {
            fields.forEach(function (field) {
                row.querySelector('[data-wb-setting-field="' + field + '"]').name = 'settings[' + index + '][' + field + ']';
            });
        });
        table.hidden = currentRows.length === 0;
        empty.hidden = currentRows.length !== 0;
    }

    editor.querySelector('[data-wb-setting-add]').addEventListener('click', resetModal);
    input('type').addEventListener('change', syncTypeFields);

    rows.addEventListener('click', function (event) {
        var edit = event.target.closest('[data-wb-setting-edit]');
        var remove = event.target.closest('[data-wb-setting-delete]');

        if (edit) {
            populateModal(edit.closest('[data-wb-setting-row]'));
        }
        if (remove) {
            remove.closest('[data-wb-setting-row]').remove();
            reindex();
        }
    });

    save.addEventListener('click', function () {
        var key = input('key');
        var setting = {};
        var duplicate = Array.prototype.some.call(rows.querySelectorAll('[data-wb-setting-row]'), function (row) {
            return row !== activeRow && row.querySelector('[data-wb-setting-field="key"]').value === value('key');
        });

        key.setCustomValidity(duplicate ? label('duplicate-key') : '');
        if (!key.reportValidity()) {
            return;
        }

        fields.forEach(function (field) {
            setting[field] = value(field);
        });
        if (setting.type !== 'enum') {
            setting.values = '';
        }
        if (setting.type !== 'integer') {
            setting.min = '';
            setting.max = '';
        }
        if (setting.type !== 'string') {
            setting.max_length = '';
        }

        if (activeRow) {
            updateRow(activeRow, setting);
        } else {
            rows.appendChild(buildRow(setting));
        }
        reindex();
        cancel.click();
    });

    syncTypeFields();
    reindex();
}());
