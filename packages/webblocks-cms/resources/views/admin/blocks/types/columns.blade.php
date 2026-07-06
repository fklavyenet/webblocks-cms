@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.columns.'.$key, $adminLocale);
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
        <label for="variant">{{ $adminText('variant_label') }}</label>
        <select id="variant" name="variant" class="wb-select">
            @foreach ([
                'cards' => $adminText('variant_cards'),
                'plain' => $adminText('variant_plain'),
                'stats' => $adminText('variant_stats'),
            ] as $value => $label)
                <option value="{{ $value }}" @selected(old('variant', $block->variant ?: 'cards') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('variant_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="content">{{ $adminText('intro_label') }}</label>
        <textarea id="content" name="content" class="wb-textarea" rows="5">{{ old('content', $block->content) }}</textarea>
    </div>

    @include('webblocks-cms::admin.blocks.partials.column-items-editor', [
        'block' => $block,
        'inputName' => 'column_items',
        'itemBlockType' => $columnItemBlockType ?? null,
        'editorKey' => 'column-item',
        'editorTitle' => $adminText('items_title'),
        'editorDescription' => $adminText('items_description'),
        'addButtonLabel' => $adminText('add_item'),
        'emptyTitle' => $adminText('empty_title'),
        'emptyDescription' => $adminText('empty_description'),
        'newItemLabel' => $adminText('new_item'),
        'titleLabel' => $adminText('item_title_label'),
        'titlePlaceholder' => null,
        'subtitleLabel' => null,
        'subtitlePlaceholder' => null,
        'showSubtitle' => false,
        'urlLabel' => $adminText('item_url_label'),
        'contentLabel' => $adminText('item_content_label'),
        'contentPlaceholder' => $adminText('item_content_placeholder'),
    ])
</div>
