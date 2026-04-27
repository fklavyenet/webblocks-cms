<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Eyebrow, heading, and intro text are translated per locale. Child link list item structure and URLs remain shared.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Eyebrow</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="title">Heading</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Intro Text</label>
        <textarea id="content" name="content" class="wb-textarea" rows="5" placeholder="Introduce the links and give readers enough context to choose the next step.">{{ old('content', $block->content) }}</textarea>
    </div>

    @include('admin.blocks.partials.column-items-editor', [
        'block' => $block,
        'inputName' => 'link_list_items',
        'itemBlockType' => $linkListItemBlockType ?? null,
        'editorKey' => 'link-list-item',
        'editorTitle' => 'Link List Items',
        'editorDescription' => 'Each visible link is a child block under this Link List container.',
        'addButtonLabel' => 'Add Link',
        'emptyTitle' => 'No link list items yet',
        'emptyDescription' => 'Add the first link to render the public link list.',
        'newItemLabel' => 'New Link List Item',
        'titleLabel' => 'Link Title',
        'titlePlaceholder' => 'Getting Started',
        'subtitleLabel' => 'Meta',
        'subtitlePlaceholder' => 'Guide',
        'showSubtitle' => true,
        'urlLabel' => 'URL',
        'contentLabel' => 'Description',
        'contentPlaceholder' => 'Add short supporting copy for this link.',
    ])
</div>
