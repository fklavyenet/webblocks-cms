(function () {
    var TONE_CLASS_PREFIX = 'wb-icon-tone-';
    var openField = null;

    function modal() {
        return document.querySelector('[data-wb-icon-picker-modal]');
    }

    function modalApi() {
        return window.WBModal || null;
    }

    function fieldState(field) {
        return {
            slug: (field.querySelector('[data-wb-icon-field-slug]') || {}).value || '',
            tone: (field.querySelector('[data-wb-icon-field-tone]') || {}).value || 'default',
            badgeTone: (field.querySelector('[data-wb-icon-field-badge-tone]') || {}).value || 'neutral',
        };
    }

    function iconClassFor(slug, tone) {
        var classes = ['wb-icon', 'wb-icon-' + slug];

        if (tone && tone !== 'default') {
            classes.push(TONE_CLASS_PREFIX + tone);
        }

        return classes.join(' ');
    }

    function badgeClassFor(tone) {
        return tone && tone !== 'neutral' ? 'wb-badge wb-badge-' + tone : 'wb-badge';
    }

    /**
     * The badge label lives outside the picker, so the preview reads whatever
     * the form currently holds rather than showing a stand-in for it.
     */
    function badgeLabelFor(field) {
        // Item editors repeat this field, so the badge label has to be read
        // from the field's own row before falling back to the block form.
        var scope = field.closest('[data-wb-builder-item-row]') || field.closest('form');
        var input = scope ? scope.querySelector('[data-wb-badge-label-input]') : null;

        return input ? String(input.value || '').trim() : '';
    }

    function renderTrigger(field) {
        var state = fieldState(field);
        var trigger = field.querySelector('[data-wb-icon-picker-open]');
        var preview = field.querySelector('[data-wb-icon-field-preview]');
        var label = field.querySelector('[data-wb-icon-field-label]');

        if (!trigger) {
            return;
        }

        if (preview) {
            preview.hidden = state.slug === '';
            preview.className = state.slug === '' ? '' : iconClassFor(state.slug, state.tone);
        }

        if (label) {
            label.textContent = state.slug === ''
                ? (trigger.getAttribute('data-choose-label') || 'Choose icon')
                : (trigger.getAttribute('data-change-label') || 'Change icon');
        }
    }

    function renderModalPreview(dialog, slug, tone, badgeTone, badgeLabel) {
        var icon = dialog.querySelector('[data-wb-icon-picker-preview-icon]');
        var badge = dialog.querySelector('[data-wb-icon-picker-preview-badge]');
        var empty = dialog.querySelector('[data-wb-icon-picker-preview-empty]');

        if (icon) {
            icon.hidden = !slug;
            icon.className = slug ? iconClassFor(slug, tone) : '';
        }

        if (badge) {
            badge.hidden = badgeLabel === '';
            badge.className = badgeClassFor(badgeTone);
            badge.textContent = badgeLabel;
        }

        if (empty) {
            empty.hidden = !!slug || badgeLabel !== '';
        }
    }

    function markSelected(dialog, slug) {
        Array.prototype.slice.call(dialog.querySelectorAll('[data-wb-icon-picker-option]')).forEach(function (option) {
            var isSelected = option.getAttribute('data-slug') === slug;

            option.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            option.classList.toggle('is-selected', isSelected);
        });
    }

    function currentDraft(dialog) {
        var selected = dialog.querySelector('[data-wb-icon-picker-option][aria-pressed="true"]');
        var tone = dialog.querySelector('[data-wb-icon-picker-tone]');
        var badgeTone = dialog.querySelector('[data-wb-icon-picker-badge-tone]');

        return {
            slug: selected ? selected.getAttribute('data-slug') : '',
            tone: tone ? tone.value : 'default',
            badgeTone: badgeTone ? badgeTone.value : 'neutral',
        };
    }

    function refreshPreview(dialog) {
        var draft = currentDraft(dialog);

        renderModalPreview(dialog, draft.slug, draft.tone, draft.badgeTone, openField ? badgeLabelFor(openField) : '');
    }

    function filterOptions(dialog, term) {
        var normalized = String(term || '').trim().toLowerCase();
        var visible = 0;

        Array.prototype.slice.call(dialog.querySelectorAll('[data-wb-icon-picker-option]')).forEach(function (option) {
            var matches = normalized === '' || (option.getAttribute('data-search') || '').indexOf(normalized) !== -1;

            option.hidden = !matches;

            if (matches) {
                visible += 1;
            }
        });

        Array.prototype.slice.call(dialog.querySelectorAll('[data-wb-icon-picker-group]')).forEach(function (group) {
            group.hidden = group.querySelectorAll('[data-wb-icon-picker-option]:not([hidden])').length === 0;
        });

        var noResults = dialog.querySelector('[data-wb-icon-picker-no-results]');

        if (noResults) {
            noResults.hidden = visible !== 0;
        }
    }

    function openFor(field) {
        var dialog = modal();

        if (!dialog) {
            return;
        }

        openField = field;

        var state = fieldState(field);
        var tone = dialog.querySelector('[data-wb-icon-picker-tone]');
        var badgeTone = dialog.querySelector('[data-wb-icon-picker-badge-tone]');
        var search = dialog.querySelector('[data-wb-icon-picker-search]');

        if (tone) {
            tone.value = state.tone;
        }

        if (badgeTone) {
            badgeTone.value = state.badgeTone;
        }

        if (search) {
            search.value = '';
        }

        filterOptions(dialog, '');
        markSelected(dialog, state.slug);
        refreshPreview(dialog);

        var runtime = modalApi();
        var trigger = field.querySelector('[data-wb-icon-picker-open]');

        if (runtime && typeof runtime.open === 'function') {
            runtime.open(dialog, trigger || null);
        } else {
            dialog.hidden = false;
            dialog.classList.add('is-open');
        }

        if (search) {
            window.setTimeout(function () {
                search.focus();
            }, 0);
        }
    }

    function closeModal(dialog) {
        var runtime = modalApi();

        if (runtime && typeof runtime.close === 'function') {
            runtime.close(dialog);
        } else {
            dialog.hidden = true;
            dialog.classList.remove('is-open');
        }
    }

    function applyToField(dialog) {
        if (!openField) {
            return;
        }

        var draft = currentDraft(dialog);
        var slugInput = openField.querySelector('[data-wb-icon-field-slug]');
        var toneInput = openField.querySelector('[data-wb-icon-field-tone]');
        var badgeToneInput = openField.querySelector('[data-wb-icon-field-badge-tone]');

        if (slugInput) {
            slugInput.value = draft.slug;
        }

        if (toneInput) {
            toneInput.value = draft.tone;
        }

        if (badgeToneInput) {
            badgeToneInput.value = draft.badgeTone;
        }

        renderTrigger(openField);

        // The block form watches inputs to know it has unsaved work.
        if (slugInput) {
            slugInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function run() {
        Array.prototype.slice.call(document.querySelectorAll('[data-wb-icon-field]')).forEach(renderTrigger);
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest ? event.target.closest('[data-wb-icon-picker-open]') : null;

        if (trigger) {
            event.preventDefault();
            openFor(trigger.closest('[data-wb-icon-field]'));

            return;
        }

        var dialog = modal();

        if (!dialog || !event.target.closest) {
            return;
        }

        var option = event.target.closest('[data-wb-icon-picker-option]');

        if (option && dialog.contains(option)) {
            event.preventDefault();
            // Clicking the selected icon clears it, so the grid alone can undo
            // a choice without hunting for the clear button.
            markSelected(dialog, option.getAttribute('aria-pressed') === 'true' ? '' : option.getAttribute('data-slug'));
            refreshPreview(dialog);

            return;
        }

        if (event.target.closest('[data-wb-icon-picker-clear]') && dialog.contains(event.target)) {
            event.preventDefault();
            markSelected(dialog, '');
            refreshPreview(dialog);

            return;
        }

        if (event.target.closest('[data-wb-icon-picker-apply]') && dialog.contains(event.target)) {
            event.preventDefault();
            applyToField(dialog);
            closeModal(dialog);
            openField = null;
        }
    });

    document.addEventListener('input', function (event) {
        var dialog = modal();

        if (!dialog || !event.target.closest || !dialog.contains(event.target)) {
            return;
        }

        if (event.target.closest('[data-wb-icon-picker-search]')) {
            filterOptions(dialog, event.target.value);
        }
    });

    document.addEventListener('change', function (event) {
        var dialog = modal();

        if (!dialog || !event.target.closest || !dialog.contains(event.target)) {
            return;
        }

        if (event.target.closest('[data-wb-icon-picker-tone], [data-wb-icon-picker-badge-tone]')) {
            refreshPreview(dialog);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }

    window.addEventListener('pageshow', run);
}());
