@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.link_list.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>{!! $adminText('system_help') !!}</div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="subtitle">{{ $adminText('meta_label') }}</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('title_label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">{{ $adminText('description_label') }}</label>
        <textarea id="content" name="content" class="wb-textarea" rows="5" placeholder="{{ $adminText('description_placeholder') }}">{{ old('content', $block->content) }}</textarea>
    </div>

    @include('webblocks-cms::admin.blocks.partials.column-items-editor', [
        'block' => $block,
        'inputName' => 'link_list_items',
        'itemBlockType' => $linkListItemBlockType ?? null,
        'editorKey' => 'link-list-item',
        'editorTitle' => $adminText('items_title'),
        'editorDescription' => $adminText('items_description'),
        'addButtonLabel' => $adminText('add_item'),
        'emptyTitle' => $adminText('empty_title'),
        'emptyDescription' => $adminText('empty_description'),
        'newItemLabel' => $adminText('new_item'),
        'titleLabel' => $adminText('item_title_label'),
        'titlePlaceholder' => $adminText('item_title_placeholder'),
        'subtitleLabel' => $adminText('item_meta_label'),
        'subtitlePlaceholder' => $adminText('item_meta_placeholder'),
        'showSubtitle' => true,
        'urlLabel' => $adminText('item_url_label'),
        'contentLabel' => $adminText('item_content_label'),
        'contentPlaceholder' => $adminText('item_content_placeholder'),
        'enableAdminSortable' => true,
    ])
</div>
