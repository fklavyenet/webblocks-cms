<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Brand title and subtitle are translated per locale. URL, target, and logo stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>This block renders the inner <code>.wb-navbar-brand</code> link only. Place it inside a Navbar block to build a header layout.</div>
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
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
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
        <label for="target">Target</label>
        <select id="target" name="target" class="wb-select">
            <option value="_self" @selected(old('target', $block->navbarBrandTarget()) === '_self')>Same tab</option>
            <option value="_blank" @selected(old('target', $block->navbarBrandTarget()) === '_blank')>New tab</option>
        </select>
    </div>
</div>
