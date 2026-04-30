<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Optional intro copy is translated per locale. Child Link List Item structure remains shared, and each item URL stays shared.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>This is a container block for docs-style structured navigation rows. Use child Link List Item blocks to render the public <code>wb-link-list</code> pattern.</div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="subtitle">Optional Intro Meta</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="title">Optional Intro Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">Optional Intro Description</label>
        <textarea id="content" name="content" class="wb-textarea" rows="5" placeholder="Optional supporting copy for this container. The public link list renders only child items.">{{ old('content', $block->content) }}</textarea>
    </div>

    @include('admin.blocks.partials.column-items-editor', [
        'block' => $block,
        'inputName' => 'link_list_items',
        'itemBlockType' => $linkListItemBlockType ?? null,
        'editorKey' => 'link-list-item',
        'editorTitle' => 'Link List Items',
        'editorDescription' => 'Each visible row is a child Link List Item block under this Link List container.',
        'addButtonLabel' => 'Add Link',
        'emptyTitle' => 'No link list items yet',
        'emptyDescription' => 'Add the first Link List Item to render the public wb-link-list rows.',
        'newItemLabel' => 'New Link List Item',
        'titleLabel' => 'Link Title',
        'titlePlaceholder' => 'Getting Started',
        'subtitleLabel' => 'Meta',
        'subtitlePlaceholder' => 'Includes, root attributes, first workflow',
        'showSubtitle' => true,
        'urlLabel' => 'URL',
        'contentLabel' => 'Description',
        'contentPlaceholder' => 'Use this page first if you need the shortest correct setup path for a real project.',
    ])
</div>
