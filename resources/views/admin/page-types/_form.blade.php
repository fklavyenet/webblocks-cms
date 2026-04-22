<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="name">Name</label>
            <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $pageType->name) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slug">Slug</label>
            <input id="slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $pageType->slug) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="description">Description</label>
        <textarea id="description" name="description" class="wb-textarea" rows="4">{{ old('description', $pageType->description) }}</textarea>
    </div>

    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="sort_order">Sort Order</label>
            <input id="sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $pageType->sort_order ?? 0) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="status">Status</label>
            <select id="status" name="status" class="wb-select">
                <option value="draft" @selected(old('status', $pageType->status ?: 'published') === 'draft')>draft</option>
                <option value="published" @selected(old('status', $pageType->status ?: 'published') === 'published')>published</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="is_system">System</label>
            <select id="is_system" name="is_system" class="wb-select">
                <option value="0" @selected(! old('is_system', $pageType->is_system))>user</option>
                <option value="1" @selected((bool) old('is_system', $pageType->is_system))>system</option>
            </select>
        </div>
    </div>

    <x-admin.form-actions :cancel-url="route('admin.page-types.index')" />
</div>
