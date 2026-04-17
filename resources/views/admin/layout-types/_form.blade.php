<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="name">Name</label>
            <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $layoutType->name) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slug">Slug</label>
            <input id="slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $layoutType->slug) }}">
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="category">Category</label>
            <input id="category" name="category" class="wb-input" type="text" value="{{ old('category', $layoutType->category) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="wb-textarea" rows="3">{{ old('description', $layoutType->description) }}</textarea>
        </div>
    </div>

    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="sort_order">Sort Order</label>
            <input id="sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $layoutType->sort_order ?? 0) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="status">Status</label>
            <select id="status" name="status" class="wb-select">
                <option value="draft" @selected(old('status', $layoutType->status ?: 'published') === 'draft')>draft</option>
                <option value="published" @selected(old('status', $layoutType->status ?: 'published') === 'published')>published</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="is_system">System</label>
            <select id="is_system" name="is_system" class="wb-select">
                <option value="0" @selected(! old('is_system', $layoutType->is_system))>user</option>
                <option value="1" @selected((bool) old('is_system', $layoutType->is_system))>system</option>
            </select>
        </div>
    </div>

    <div class="wb-row wb-row-middle wb-justify-between wb-gap-2">
        <a href="{{ route('admin.layout-types.index') }}" class="wb-btn wb-btn-secondary">Back</a>
        <button type="submit" class="wb-btn wb-btn-primary">Save Layout Type</button>
    </div>
</div>
