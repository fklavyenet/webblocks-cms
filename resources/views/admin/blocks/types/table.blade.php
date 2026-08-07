@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.table.'.$key, $adminLocale);
    $tableEditorLabels = collect([
        'move_row_up', 'move_row_down', 'remove_row',
        'move_column_left', 'move_column_right', 'remove_column',
        'row_number', 'row_actions', 'text_view', 'grid_view',
    ])->mapWithKeys(fn (string $key) => [$key => $adminText($key)])->all();
@endphp

@once
    @push('admin-scripts')
        @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/table-editor.js'])
    @endpush
@endonce

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
            <label for="variant">{{ $adminText('style_label') }}</label>
            <select id="variant" name="variant" class="wb-select">
                <option value="header-row" @selected(old('variant', $block->variant ?: 'header-row') === 'header-row')>{{ $adminText('header_row') }}</option>
                <option value="plain" @selected(old('variant', $block->variant) === 'plain')>{{ $adminText('plain_rows') }}</option>
            </select>
        </div>
    </div>

    <div
        class="wb-stack wb-gap-2"
        data-wb-table-editor
        data-wb-table-variant="variant"
        data-wb-table-labels="{{ json_encode($tableEditorLabels, JSON_UNESCAPED_UNICODE) }}"
    >
        <label for="content">{{ $adminText('rows_label') }}</label>

        <div class="wb-toolbar wb-toolbar-sm wb-admin-table-toolbar" role="toolbar" aria-label="{{ $adminText('rows_label') }}">
            <div class="wb-toolbar-start">
                <span class="wb-action-group" data-wb-table-grid-toolbar>
                    <button type="button" class="wb-btn wb-btn-sm wb-btn-secondary" data-wb-table-action="row-add" aria-label="{{ $adminText('add_row') }}">
                        <i class="wb-icon wb-icon-plus" aria-hidden="true"></i>
                        <span>{{ $adminText('add_row') }}</span>
                    </button>
                    <button type="button" class="wb-btn wb-btn-sm wb-btn-secondary" data-wb-table-action="column-add" aria-label="{{ $adminText('add_column') }}">
                        <i class="wb-icon wb-icon-plus" aria-hidden="true"></i>
                        <span>{{ $adminText('add_column') }}</span>
                    </button>
                </span>
            </div>

            <div class="wb-toolbar-end">
                <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-table-toggle aria-pressed="false">{{ $adminText('text_view') }}</button>
            </div>
        </div>

        <div class="wb-admin-table-grid-host" data-wb-table-grid role="group" aria-label="{{ $adminText('rows_label') }}"></div>

        <textarea id="content" name="content" class="wb-textarea wb-admin-table-source" rows="8" placeholder="{{ $adminText('rows_placeholder') }}" data-wb-table-source>{{ old('content', $block->content) }}</textarea>

        <div class="wb-text-sm wb-text-muted">{!! $adminText('rows_help') !!}</div>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('grid_help') }}</div>
    </div>
</div>
