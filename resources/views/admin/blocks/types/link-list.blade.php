@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.link_list.'.$key, $adminLocale);
    $linkListSettings = is_array($block->settings ?? null)
        ? $block->settings
        : (json_decode((string) ($block->getRawOriginal('settings') ?? ''), true) ?: []);
    $selectedRowLayout = old('row_layout', $linkListSettings['row_layout'] ?? 'index');
    $selectedListFrame = old('list_frame', $linkListSettings['list_frame'] ?? 'joined');
    $selectedThumbSize = old('thumb_size', $linkListSettings['thumb_size'] ?? 'square');
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

    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="row_layout">{{ $adminText('row_layout_label') }}</label>
            <select id="row_layout" name="row_layout" class="wb-select">
                @foreach (['index' => $adminText('row_layout_index'), 'stacked' => $adminText('row_layout_stacked')] as $value => $label)
                    <option value="{{ $value }}" @selected($selectedRowLayout === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('row_layout_help') }}</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="list_frame">{{ $adminText('list_frame_label') }}</label>
            <select id="list_frame" name="list_frame" class="wb-select">
                @foreach (['joined' => $adminText('list_frame_joined'), 'cards' => $adminText('list_frame_cards')] as $value => $label)
                    <option value="{{ $value }}" @selected($selectedListFrame === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('list_frame_help') }}</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="thumb_size">{{ $adminText('thumb_size_label') }}</label>
            <select id="thumb_size" name="thumb_size" class="wb-select">
                @foreach (['square' => $adminText('thumb_size_square'), 'wide' => $adminText('thumb_size_wide')] as $value => $label)
                    <option value="{{ $value }}" @selected($selectedThumbSize === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('thumb_size_help') }}</span>
        </div>
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
