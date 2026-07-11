@php
    $blockTypeFormText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.block_type_form.'.$key, $replace);
@endphp

<div class="wb-stack wb-gap-4">
    @if ($errors->any())
        <div class="wb-alert wb-alert-danger">
            <div>
                <div class="wb-alert-title">{{ $blockTypeFormText('validation_error') }}</div>
                <div>{{ $errors->first() }}</div>
            </div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="name">{{ $blockTypeFormText('name') }}</label>
            <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $blockType->name) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slug">{{ $blockTypeFormText('slug') }}</label>
            <input id="slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $blockType->slug) }}">
        </div>
    </div>

    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="category">{{ $blockTypeFormText('category') }}</label>
            <input id="category" name="category" class="wb-input" type="text" value="{{ old('category', $blockType->category) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="source_type">{{ $blockTypeFormText('source_type') }}</label>
            <input id="source_type" name="source_type" class="wb-input" type="text" value="{{ old('source_type', $blockType->source_type) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="description">{{ $blockTypeFormText('description') }}</label>
            <textarea id="description" name="description" class="wb-textarea" rows="3">{{ old('description', $blockType->description) }}</textarea>
        </div>
    </div>

    <div class="wb-grid wb-grid-4">
        <div class="wb-stack wb-gap-1">
            <label for="sort_order">{{ $blockTypeFormText('sort_order') }}</label>
            <input id="sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $blockType->sort_order ?? 0) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="status">{{ $blockTypeFormText('status') }}</label>
            <select id="status" name="status" class="wb-select">
                <option value="draft" @selected(old('status', $blockType->status ?: 'published') === 'draft')>{{ $blockTypeFormText('draft') }}</option>
                <option value="published" @selected(old('status', $blockType->status ?: 'published') === 'published')>{{ $blockTypeFormText('published') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="is_container">{{ $blockTypeFormText('container') }}</label>
            <select id="is_container" name="is_container" class="wb-select">
                <option value="0" @selected(! old('is_container', $blockType->is_container))>{{ $blockTypeFormText('no') }}</option>
                <option value="1" @selected((bool) old('is_container', $blockType->is_container))>{{ $blockTypeFormText('yes') }}</option>
            </select>
        </div>
    </div>

    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $blockTypeFormText('custom_title') }}</div>
            <div>{{ $blockTypeFormText('custom_help') }}</div>
        </div>
    </div>
</div>
