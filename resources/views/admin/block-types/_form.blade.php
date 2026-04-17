<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="name">Name</label>
            <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $blockType->name) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slug">Slug</label>
            <input id="slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $blockType->slug) }}">
        </div>
    </div>

    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="category">Category</label>
            <input id="category" name="category" class="wb-input" type="text" value="{{ old('category', $blockType->category) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="source_type">Source Type</label>
            <input id="source_type" name="source_type" class="wb-input" type="text" value="{{ old('source_type', $blockType->source_type) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="wb-textarea" rows="3">{{ old('description', $blockType->description) }}</textarea>
        </div>
    </div>

    <div class="wb-grid wb-grid-4">
        <div class="wb-stack wb-gap-1">
            <label for="sort_order">Sort Order</label>
            <input id="sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $blockType->sort_order ?? 0) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="status">Status</label>
            <select id="status" name="status" class="wb-select">
                <option value="draft" @selected(old('status', $blockType->status ?: 'published') === 'draft')>draft</option>
                <option value="published" @selected(old('status', $blockType->status ?: 'published') === 'published')>published</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="is_container">Container</label>
            <select id="is_container" name="is_container" class="wb-select">
                <option value="0" @selected(! old('is_container', $blockType->is_container))>no</option>
                <option value="1" @selected((bool) old('is_container', $blockType->is_container))>yes</option>
            </select>
        </div>
    </div>

    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">Custom block type</div>
            <div>Core block types are product-owned catalog records. Use this form only for install-specific block types.</div>
        </div>
    </div>

    <div class="wb-row wb-row-middle wb-justify-between wb-gap-2">
        <a href="{{ route('admin.block-types.index') }}" class="wb-btn wb-btn-secondary">Back</a>
        <button type="submit" class="wb-btn wb-btn-primary">Save Block Type</button>
    </div>
</div>
