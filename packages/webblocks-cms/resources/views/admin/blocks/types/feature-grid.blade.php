@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.feature_grid.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('title_label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="subtitle">{{ $adminText('subtitle_label') }}</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">{{ $adminText('intro_label') }}</label>
        <textarea id="content" name="content" class="wb-textarea" rows="5">{{ old('content', $block->content) }}</textarea>
    </div>

    @include('webblocks-cms::admin.blocks.partials.column-items-editor', [
        'block' => $block,
        'inputName' => 'feature_items',
        'itemBlockType' => $featureItemBlockType ?? null,
        'editorKey' => 'feature-item',
        'editorTitle' => $adminText('items_title'),
        'editorDescription' => $adminText('items_description'),
        'addButtonLabel' => $adminText('add_item'),
        'emptyTitle' => $adminText('empty_title'),
        'emptyDescription' => $adminText('empty_description'),
        'newItemLabel' => $adminText('new_item'),
        'titleLabel' => $adminText('item_title_label'),
        'titlePlaceholder' => 'Structured publishing',
        'subtitleLabel' => null,
        'subtitlePlaceholder' => null,
        'showSubtitle' => false,
        'urlLabel' => $adminText('item_url_label'),
        'contentLabel' => $adminText('item_content_label'),
        'contentPlaceholder' => $adminText('item_content_placeholder'),
    ])
</div>
