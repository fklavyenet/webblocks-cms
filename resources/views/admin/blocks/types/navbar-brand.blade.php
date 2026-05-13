<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Brand title and subtitle are translated per locale. URL, target, and logo stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>This block renders the inner <code>.wb-navbar-brand</code> link only. Place it inside a Navbar block to build a header layout. Provide a visible title, a logo, or both.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label>Logo</label>
        @include('admin.media.asset-picker-panel', [
            'name' => 'navbar-brand-logo',
            'inputId' => 'asset_id',
            'fieldName' => 'asset_id',
            'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
            'buttonLabel' => 'Choose from Media',
            'replaceLabel' => 'Replace Logo',
            'clearLabel' => 'Remove',
            'accept' => 'image',
        ])
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Brand Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
            <div class="wb-text-sm wb-text-muted">Optional when a logo is present.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $block->navbarBrandUrl()) }}" placeholder="Falls back to the site home URL" required>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">Subtitle</label>
        <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="navbar_brand_aria_label">Accessible Label</label>
        <input id="navbar_brand_aria_label" name="navbar_brand_aria_label" class="wb-input" type="text" value="{{ old('navbar_brand_aria_label', $block->navbarBrandAriaLabel()) }}">
        <div class="wb-text-sm wb-text-muted">Used for logo-only brands when visible title text is empty. Falls back to the site label if left blank.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="target">Target</label>
        <select id="target" name="target" class="wb-select">
            <option value="_self" @selected(old('target', $block->navbarBrandTarget()) === '_self')>Same tab</option>
            <option value="_blank" @selected(old('target', $block->navbarBrandTarget()) === '_blank')>New tab</option>
        </select>
    </div>
</div>
