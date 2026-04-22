@php
    $selectedLayoutTypeId = old('layout_type_id', $layout->layout_type_id ?: $layoutTypes->firstWhere('slug', $layout->slug)?->id ?: $layoutTypes->firstWhere('slug', 'default')?->id);
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="name">Name</label>
            <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $layout->name) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="layout_type_id">Layout Type</label>
            <select id="layout_type_id" name="layout_type_id" class="wb-select" required>
                @foreach ($layoutTypes as $layoutType)
                    <option value="{{ $layoutType->id }}" @selected((string) $selectedLayoutTypeId === (string) $layoutType->id)>{{ $layoutType->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slug">Slug</label>
            <input id="slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $layout->slug) }}">
        </div>
    </div>

    <x-admin.form-actions :cancel-url="route('admin.layouts.index')" />
</div>
