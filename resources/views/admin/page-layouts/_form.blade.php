@php
    $isSystem = (bool) $pageLayout->is_system;
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="page_layout_name">Name</label>
            <input id="page_layout_name" name="name" class="wb-input" type="text" value="{{ old('name', $pageLayout->name) }}" maxlength="255" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_layout_handle">Handle</label>
            <input id="page_layout_handle" name="handle" class="wb-input" type="text" value="{{ old('handle', $pageLayout->handle) }}" maxlength="100" @readonly($isSystem) required>
            <div class="wb-text-sm wb-text-muted">Stable lowercase layout handle stored on pages as <code>public_shell</code>.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="page_layout_is_active">Status</label>
            <select id="page_layout_is_active" name="is_active" class="wb-select">
                <option value="1" @selected((bool) old('is_active', $pageLayout->is_active ?? true))>Active</option>
                <option value="0" @selected(! (bool) old('is_active', $pageLayout->is_active ?? true))>Inactive</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_layout_sort_order">Sort Order</label>
            <input id="page_layout_sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $pageLayout->sort_order ?? 0) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_layout_body_class">Body Class</label>
            <input id="page_layout_body_class" name="body_class" class="wb-input" type="text" value="{{ old('body_class', $pageLayout->body_class) }}" maxlength="1000">
            <div class="wb-text-sm wb-text-muted">Optional whitespace-separated classes added to the public <code>body</code>, for example <code>layout-docs</code>.</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="page_layout_description">Description</label>
        <textarea id="page_layout_description" name="description" class="wb-textarea" rows="3">{{ old('description', $pageLayout->description) }}</textarea>
    </div>

    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">Managed Page Layout</div>
            <div>Page Layout is the user-facing concept. Pages still store the selected layout handle on <code>public_shell</code> for backward compatibility in this release.</div>
        </div>
    </div>
</div>
