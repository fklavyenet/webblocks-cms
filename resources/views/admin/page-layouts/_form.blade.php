@php
    $isSystem = (bool) $pageLayout->is_system;
    $slotSchema = old('slot_schema', $pageLayout->slot_schema ? json_encode($pageLayout->slot_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
    $wrapperSchema = old('wrapper_schema', $pageLayout->wrapper_schema ? json_encode($pageLayout->wrapper_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
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
            <label for="page_layout_shell_type">Shell Type</label>
            <select id="page_layout_shell_type" name="shell_type" class="wb-select" @disabled($isSystem)>
                @foreach (\App\Models\Page::allowedPublicShellPresets() as $shellType)
                    <option value="{{ $shellType }}" @selected(old('shell_type', $pageLayout->shell_type ?: 'default') === $shellType)>{{ str($shellType)->headline() }}</option>
                @endforeach
            </select>
            @if ($isSystem)
                <input type="hidden" name="shell_type" value="{{ $pageLayout->shell_type }}">
            @endif
        </div>

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
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="page_layout_description">Description</label>
        <textarea id="page_layout_description" name="description" class="wb-textarea" rows="3">{{ old('description', $pageLayout->description) }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="page_layout_slot_schema">Slot Schema JSON</label>
            <textarea id="page_layout_slot_schema" name="slot_schema" class="wb-textarea wb-font-mono" rows="8">{{ $slotSchema }}</textarea>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_layout_wrapper_schema">Wrapper Schema JSON</label>
            <textarea id="page_layout_wrapper_schema" name="wrapper_schema" class="wb-textarea wb-font-mono" rows="8">{{ $wrapperSchema }}</textarea>
        </div>
    </div>

    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">Managed Page Layout</div>
            <div>Page Layout controls the public shell choice. Pages still store the selected layout handle on <code>public_shell</code> for backward compatibility in this release.</div>
        </div>
    </div>
</div>
