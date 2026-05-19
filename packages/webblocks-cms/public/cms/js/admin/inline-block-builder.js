(function () {
    if (!document.querySelector('[data-wb-inline-builder]')) {
        return;
    }

    function addInlineBlock(builder, payload) {
        var template = builder.querySelector('[data-wb-inline-template]');
        var list = builder.querySelector('[data-wb-inline-list]');
        var emptyState = list ? list.querySelector('[data-wb-inline-empty]') : null;
        var defaultSlotType = builder.querySelector('[data-wb-default-slot-type]');
        var nextIndexInput = builder.querySelector('[data-wb-inline-next-index]');

        if (!template || !list) {
            return;
        }

        if (emptyState) {
            emptyState.remove();
        }

        var index = nextIndexInput ? Number(nextIndexInput.value || '0') : list.querySelectorAll('[data-wb-inline-block]').length;
        var wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.trim();
        var block = wrapper.firstElementChild;
        var defaultSlotTypeId = defaultSlotType ? defaultSlotType.value : '';

        if (nextIndexInput) {
            nextIndexInput.value = String(index + 1);
        }

        block.querySelector('[data-wb-inline-label]').textContent = payload.name || 'New Block';
        block.querySelector('[data-wb-inline-body]').innerHTML = buildInlineFields(index, payload, defaultSlotTypeId);
        list.appendChild(block);
        syncInlineBuilder(builder);
    }

    function buildInlineFields(index, payload, defaultSlotTypeId) {
        var typeId = payload.id || '';
        var title = payload.name || 'Block';
        var contentFields = '';

        switch (payload.slug) {
            case 'heading':
                contentFields = '<div class="wb-grid wb-grid-2"><div class="wb-stack wb-gap-1"><label>Heading Text</label><input class="wb-input" type="text" name="blocks[' + index + '][title]"></div><div class="wb-stack wb-gap-1"><label>Heading Level</label><select class="wb-select" name="blocks[' + index + '][variant]"><option value="h1">H1</option><option value="h2">H2</option><option value="h3">H3</option><option value="h4">H4</option><option value="h5">H5</option><option value="h6">H6</option></select></div></div>';
                break;
            case 'section':
                contentFields = '<div class="wb-grid wb-grid-2"><div class="wb-stack wb-gap-1"><label>Section Title</label><input class="wb-input" type="text" name="blocks[' + index + '][title]"></div><div class="wb-stack wb-gap-1"><label>Section Variant</label><select class="wb-select" name="blocks[' + index + '][variant]"><option value="default">default</option><option value="muted">muted</option><option value="accent">accent</option><option value="wide">wide</option></select></div></div><div class="wb-stack wb-gap-1"><label>Section Intro</label><textarea class="wb-textarea" rows="5" name="blocks[' + index + '][content]"></textarea></div>';
                break;
            case 'callout':
                contentFields = '<div class="wb-grid wb-grid-2"><div class="wb-stack wb-gap-1"><label>CTA Title</label><input class="wb-input" type="text" name="blocks[' + index + '][title]"></div><div class="wb-stack wb-gap-1"><label>Tone</label><select class="wb-select" name="blocks[' + index + '][variant]"><option value="info">info</option><option value="success">success</option><option value="warning">warning</option><option value="danger">danger</option></select></div></div><div class="wb-stack wb-gap-1"><label>CTA Content</label><textarea class="wb-textarea" rows="5" name="blocks[' + index + '][content]"></textarea></div>';
                break;
            case 'rich-text':
            case 'text':
            case 'html':
                contentFields = '<div class="wb-stack wb-gap-1"><label>Content</label><textarea class="wb-textarea" rows="8" name="blocks[' + index + '][content]"></textarea></div>';
                break;
            case 'button':
                contentFields = '<div class="wb-grid wb-grid-2"><div class="wb-stack wb-gap-1"><label>Button Label</label><input class="wb-input" type="text" name="blocks[' + index + '][title]"></div><div class="wb-stack wb-gap-1"><label>URL</label><input class="wb-input" type="text" name="blocks[' + index + '][url]"></div></div>';
                break;
            case 'download':
                contentFields = '<div class="wb-grid wb-grid-2"><div class="wb-stack wb-gap-1"><label>Download Label</label><input class="wb-input" type="text" name="blocks[' + index + '][title]"></div><div class="wb-stack wb-gap-1"><label>Document Asset ID</label><input class="wb-input" type="number" min="1" name="blocks[' + index + '][asset_id]"></div></div>';
                break;
            default:
                contentFields = '<div class="wb-stack wb-gap-1"><label>Content</label><textarea class="wb-textarea" rows="6" name="blocks[' + index + '][content]"></textarea></div>';
                break;
        }

        return '' +
            '<input type="hidden" name="blocks[' + index + '][id]" value="">' +
            '<input type="hidden" name="blocks[' + index + '][_delete]" value="0" data-wb-inline-delete>' +
            '<input type="hidden" name="blocks[' + index + '][sort_order]" value="' + index + '" data-wb-inline-sort>' +
            '<input type="hidden" name="blocks[' + index + '][block_type_id]" value="' + typeId + '">' +
            '<input type="hidden" name="blocks[' + index + '][is_system]" value="' + (payload.is_system ? '1' : '0') + '">' +
            '<div class="wb-grid wb-grid-4"><div class="wb-stack wb-gap-1"><label>Block Type</label><div class="wb-card wb-card-muted"><div class="wb-card-body"><strong>' + title + '</strong><div>' + (payload.description || 'Inline block editor') + '</div></div></div></div><div class="wb-stack wb-gap-1"><label>Slot Type ID</label><input class="wb-input" type="number" min="1" name="blocks[' + index + '][slot_type_id]" value="' + defaultSlotTypeId + '"></div><div class="wb-stack wb-gap-1"><label>Status</label><select class="wb-select" name="blocks[' + index + '][status]"><option value="published">published</option><option value="draft">draft</option></select></div><div class="wb-stack wb-gap-1"><label>Kind</label><div class="wb-card wb-card-muted"><div class="wb-card-body"><strong>' + (payload.is_system ? 'System Block' : 'Content Block') + '</strong></div></div></div></div>' +
            '<div class="wb-card wb-card-accent"><div class="wb-card-body">' + contentFields + '</div></div>';
    }

    function syncInlineBuilder(builder) {
        if (!builder) {
            return;
        }

        var list = builder.querySelector('[data-wb-inline-list]');

        if (!list) {
            return;
        }

        var blocks = Array.prototype.slice.call(list.querySelectorAll('[data-wb-inline-block]'));

        blocks.forEach(function (block, index) {
            var sortInput = block.querySelector('[data-wb-inline-sort]');
            var up = block.querySelector('[data-wb-inline-move="up"]');
            var down = block.querySelector('[data-wb-inline-move="down"]');

            if (sortInput) {
                sortInput.value = index;
            }

            if (up) {
                up.disabled = index === 0;
            }

            if (down) {
                down.disabled = index === blocks.length - 1;
            }
        });

        if (blocks.length === 0 && !list.querySelector('[data-wb-inline-empty]')) {
            var empty = document.createElement('div');
            empty.className = 'wb-empty';
            empty.setAttribute('data-wb-inline-empty', '');
            empty.innerHTML = '<div class="wb-empty-title">No blocks yet</div><div class="wb-empty-text">Add the first block to start composing this page inline.</div>';
            list.appendChild(empty);
        }
    }

    document.querySelectorAll('[data-wb-inline-builder]').forEach(function (builder) {
        syncInlineBuilder(builder);
    });

    document.addEventListener('click', function (event) {
        var addButton = event.target.closest('[data-wb-inline-add]');
        var moveButton = event.target.closest('[data-wb-inline-move]');
        var removeButton = event.target.closest('[data-wb-inline-remove]');
        var toggleButton = event.target.closest('[data-wb-inline-toggle]');

        if (addButton) {
            var builder = addButton.closest('[data-wb-inline-builder]');

            if (!builder) {
                return;
            }

            var payload = {};

            try {
                payload = JSON.parse(addButton.getAttribute('data-block-type') || '{}');
            } catch (error) {
                payload = {};
            }

            addInlineBlock(builder, payload);
            return;
        }

        if (moveButton) {
            var block = moveButton.closest('[data-wb-inline-block]');

            if (!block) {
                return;
            }

            var direction = moveButton.getAttribute('data-wb-inline-move');
            var sibling = direction === 'up' ? block.previousElementSibling : block.nextElementSibling;

            if (!sibling || !sibling.matches('[data-wb-inline-block]')) {
                return;
            }

            if (direction === 'up') {
                block.parentNode.insertBefore(block, sibling);
            } else {
                block.parentNode.insertBefore(sibling, block);
            }

            syncInlineBuilder(block.closest('[data-wb-inline-builder]'));
            return;
        }

        if (removeButton) {
            var inlineBlock = removeButton.closest('[data-wb-inline-block]');

            if (!inlineBlock) {
                return;
            }

            var deleteInput = inlineBlock.querySelector('[data-wb-inline-delete]');
            var builderRoot = inlineBlock.closest('[data-wb-inline-builder]');

            if (deleteInput && deleteInput.closest('form') && deleteInput.value !== undefined) {
                deleteInput.value = '1';
            }

            inlineBlock.remove();
            syncInlineBuilder(builderRoot);
            return;
        }

        if (!toggleButton) {
            return;
        }

        var toggleBlock = toggleButton.closest('[data-wb-inline-block]');
        var body = toggleBlock ? toggleBlock.querySelector('[data-wb-inline-body]') : null;

        if (!body) {
            return;
        }

        body.hidden = !body.hidden;
    });
}());
