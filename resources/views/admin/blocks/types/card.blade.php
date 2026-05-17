<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Card eyebrow or label, title, subtitle, description, action label, image alt text, and image caption are translated per locale. Media, URL, target, variant, image position, and image aspect stay shared across locales. Nested child blocks render before the legacy single-action fallback.</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label>Media</label>
        @include('admin.media.asset-picker-panel', [
            'name' => 'card-image',
            'inputId' => 'asset_id',
            'fieldName' => 'asset_id',
            'selectedAsset' => old('asset_id') ? null : ($selectedAsset ?? $block->asset),
            'buttonLabel' => 'Choose from Media',
            'replaceLabel' => 'Replace Image',
            'clearLabel' => 'Remove',
            'accept' => 'image',
            'panelMode' => 'overlay',
            'panelTitle' => 'Choose Image',
            'compactControls' => true,
            'resultsVariant' => 'compact-list',
            'showUpload' => false,
        ])
            <div class="wb-text-sm wb-text-muted">Selecting media enables the card image. Clearing media removes the image.</div>
    </div>

    @php
        $selectedMediaId = old('media_id', old('asset_id', $block->media_id));
        $defaultImagePlacement = $selectedMediaId ? $block->cardImagePosition() : 'top';
    @endphp

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="image_position">Image Placement</label>
            <select id="image_position" name="image_position" class="wb-select">
                <option value="top" @selected(old('image_position', $defaultImagePlacement) === 'top')>Top</option>
                <option value="middle" @selected(old('image_position', $defaultImagePlacement) === 'middle')>Middle</option>
                <option value="bottom" @selected(old('image_position', $defaultImagePlacement) === 'bottom')>Bottom</option>
            </select>
            <div class="wb-text-sm wb-text-muted">Placement controls where the selected image appears inside the card body.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="image_align">Image Alignment</label>
            <select id="image_align" name="image_align" class="wb-select">
                <option value="start" @selected(old('image_align', $block->cardImageAlign()) === 'start')>Start</option>
                <option value="center" @selected(old('image_align', $block->cardImageAlign()) === 'center')>Center</option>
                <option value="end" @selected(old('image_align', $block->cardImageAlign()) === 'end')>End</option>
                <option value="stretch" @selected(old('image_align', $block->cardImageAlign()) === 'stretch')>Stretch</option>
            </select>
            <div class="wb-text-sm wb-text-muted">Alignment controls the card media frame alignment.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="image_aspect">Image Aspect</label>
            <select id="image_aspect" name="image_aspect" class="wb-select">
                <option value="auto" @selected(old('image_aspect', $block->cardImageAspect()) === 'auto')>Auto</option>
                <option value="square" @selected(old('image_aspect', $block->cardImageAspect()) === 'square')>Square</option>
                <option value="wide" @selected(old('image_aspect', $block->cardImageAspect()) === 'wide')>Wide</option>
                <option value="portrait" @selected(old('image_aspect', $block->cardImageAspect()) === 'portrait')>Portrait</option>
            </select>
            <div class="wb-text-sm wb-text-muted">Aspect controls the card media frame rhythm.</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="eyebrow">Eyebrow / Label</label>
        <input id="eyebrow" name="eyebrow" class="wb-input" type="text" value="{{ old('eyebrow', $block->eyebrow) }}">
        <div class="wb-text-sm wb-text-muted">Optional translated promo label shown above the title for promo cards.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="title">Title</label>
        <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="subtitle">Subtitle</label>
        <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Description</label>
        <textarea id="content" name="content" class="wb-textarea" rows="4">{{ old('content', $block->content) }}</textarea>
        <div class="wb-text-sm wb-text-muted">Supports inline code with backticks, for example <code>`light`</code>, <code>`dark`</code>, or <code>`auto`</code>.</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="action_label">Action label</label>
        <input id="action_label" name="action_label" class="wb-input" type="text" value="{{ old('action_label', $block->meta) }}">
        <div class="wb-text-sm wb-text-muted">Legacy fallback action used only when the card has no child footer blocks. Preferred nested structure: Card &gt; Cluster &gt; Button Link.</div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="image_alt">Image Alt Text</label>
            <input id="image_alt" name="image_alt" class="wb-input" type="text" value="{{ old('image_alt', $block->image_alt) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="image_caption">Image Caption</label>
            <input id="image_caption" name="image_caption" class="wb-input" type="text" value="{{ old('image_caption', $block->image_caption) }}">
        </div>
    </div>
</div>
