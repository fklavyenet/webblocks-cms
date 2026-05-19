<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Feature grid intro copy is translated per locale. Child feature item structure and optional links remain shared.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Feature Grid Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Feature Grid Subtitle</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Intro Text</label>
        <textarea id="content" name="content" class="wb-textarea" rows="5">{{ old('content', $block->content) }}</textarea>
    </div>

    @include('admin.blocks.partials.column-items-editor', [
        'block' => $block,
        'inputName' => 'feature_items',
        'itemBlockType' => $featureItemBlockType ?? null,
        'editorKey' => 'feature-item',
        'editorTitle' => 'Feature Items',
        'editorDescription' => 'Each visible feature card is a child block under this Feature Grid container.',
        'addButtonLabel' => 'Add Feature',
        'emptyTitle' => 'No feature items yet',
        'emptyDescription' => 'Add the first feature to render the public feature grid.',
        'newItemLabel' => 'New Feature Item',
        'titleLabel' => 'Feature Title',
        'titlePlaceholder' => 'Structured publishing',
        'subtitleLabel' => null,
        'subtitlePlaceholder' => null,
        'showSubtitle' => false,
        'urlLabel' => 'Optional Link',
        'contentLabel' => 'Feature Text',
        'contentPlaceholder' => 'Add a concise feature summary for this card.',
    ])
</div>
