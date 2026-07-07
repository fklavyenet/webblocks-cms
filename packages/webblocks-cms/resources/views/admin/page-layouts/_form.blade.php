@php
    $isSystem = (bool) $pageLayout->is_system;
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $pageLayoutsText = fn (string $key, array $replace = []) => $adminTranslator->admin('page_layouts.'.$key, $adminLocale, $replace);
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="page_layout_name">{{ $pageLayoutsText('name') }}</label>
            <input id="page_layout_name" name="name" class="wb-input" type="text" value="{{ old('name', $pageLayout->name) }}" maxlength="255" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_layout_handle">{{ $pageLayoutsText('handle') }}</label>
            <input id="page_layout_handle" name="handle" class="wb-input" type="text" value="{{ old('handle', $pageLayout->handle) }}" maxlength="100" @readonly($isSystem) required>
            <div class="wb-text-sm wb-text-muted">{!! $pageLayoutsText('handle_help') !!}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="page_layout_is_active">{{ $pageLayoutsText('status') }}</label>
            <select id="page_layout_is_active" name="is_active" class="wb-select">
                <option value="1" @selected((bool) old('is_active', $pageLayout->is_active ?? true))>{{ $pageLayoutsText('active') }}</option>
                <option value="0" @selected(! (bool) old('is_active', $pageLayout->is_active ?? true))>{{ $pageLayoutsText('inactive') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_layout_sort_order">{{ $pageLayoutsText('sort_order') }}</label>
            <input id="page_layout_sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $pageLayout->sort_order ?? 0) }}" required>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="page_layout_body_class">{{ $pageLayoutsText('body_class') }}</label>
        <input id="page_layout_body_class" name="body_class" class="wb-input" type="text" value="{{ old('body_class', $pageLayout->body_class) }}" maxlength="1000">
        <div class="wb-text-sm wb-text-muted">{!! $pageLayoutsText('body_class_help') !!}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="page_layout_description">{{ $pageLayoutsText('description') }}</label>
        <textarea id="page_layout_description" name="description" class="wb-textarea" rows="3">{{ old('description', $pageLayout->description) }}</textarea>
    </div>

    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $pageLayoutsText('managed_title') }}</div>
            <div>{!! $pageLayoutsText('managed_help') !!}</div>
        </div>
    </div>
</div>
