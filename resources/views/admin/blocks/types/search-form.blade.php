<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Search label, placeholder, and button label are translated per locale. Button visibility and variant stay shared.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Accessible Label</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title ?: 'Search') }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="content">Placeholder</label>
            <input id="content" name="content" class="wb-input" type="text" value="{{ old('content', $block->content ?: 'Search this site') }}" required>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Button Label</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle ?: 'Search') }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">Button Variant</label>
            <select id="variant" name="variant" class="wb-select">
                @foreach (['primary', 'secondary'] as $variant)
                    <option value="{{ $variant }}" @selected(old('variant', $block->variant ?: 'primary') === $variant)>{{ ucfirst($variant) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <input type="hidden" name="show_button" value="0">
        <label class="wb-cluster wb-cluster-2" for="search_form_show_button">
            <input id="search_form_show_button" name="show_button" type="checkbox" value="1" @checked(old('show_button', $block->setting('show_button', true)))>
            <span>Show submit button</span>
        </label>
    </div>
</div>
