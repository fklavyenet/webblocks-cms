@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.page_list.'.$key, $adminLocale);
    $settings = $block->pageListSettings();
    $pageTypes = \WebBlocks\Cms\Models\PageType::query()->orderBy('sort_order')->orderBy('name')->get();
    $boolValue = fn (string $field, bool $current) => old("{$prefix}.{$field}", $current ? '1' : '0') === '1';
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('system_title') }}</div>
            <div>{{ $adminText('inline_system_help') }}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_scope">{{ $adminText('scope_label') }}</label>
            <select id="block_{{ $index }}_page_list_scope" name="{{ $prefix }}[page_list_scope]" class="wb-select" required>
                @foreach (\WebBlocks\Cms\Support\Pages\PageListSettings::scopes() as $scope)
                    <option value="{{ $scope }}" @selected(old("{$prefix}.page_list_scope", $settings->scope) === $scope)>{{ $adminText('scope.'.$scope) }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_page_type">{{ $adminText('page_type_label') }}</label>
            <select id="block_{{ $index }}_page_list_page_type" name="{{ $prefix }}[page_list_page_type]" class="wb-select">
                <option value="">{{ $adminText('page_type_placeholder') }}</option>
                @foreach ($pageTypes as $pageType)
                    <option value="{{ $pageType->slug }}" @selected(old("{$prefix}.page_list_page_type", $settings->pageType) === $pageType->slug)>{{ $pageType->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_path_prefix">{{ $adminText('path_prefix_label') }}</label>
            <input type="text" id="block_{{ $index }}_page_list_path_prefix" name="{{ $prefix }}[page_list_path_prefix]" class="wb-input" value="{{ old("{$prefix}.page_list_path_prefix", $settings->pathPrefix) }}" placeholder="/guides">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_sort">{{ $adminText('sort_label') }}</label>
            <select id="block_{{ $index }}_page_list_sort" name="{{ $prefix }}[page_list_sort]" class="wb-select" required>
                @foreach (\WebBlocks\Cms\Support\Pages\PageListSettings::sorts() as $sort)
                    <option value="{{ $sort }}" @selected(old("{$prefix}.page_list_sort", $settings->sort) === $sort)>{{ $adminText('sort.'.$sort) }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_limit">{{ $adminText('limit_label') }}</label>
            <input
                type="number"
                id="block_{{ $index }}_page_list_limit"
                name="{{ $prefix }}[page_list_limit]"
                class="wb-input"
                min="{{ \WebBlocks\Cms\Support\Pages\PageListSettings::LIMIT_MIN }}"
                max="{{ \WebBlocks\Cms\Support\Pages\PageListSettings::LIMIT_MAX }}"
                value="{{ old("{$prefix}.page_list_limit", $settings->limit) }}"
                required
            >
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_layout">{{ $adminText('layout_label') }}</label>
            <select id="block_{{ $index }}_page_list_layout" name="{{ $prefix }}[page_list_layout]" class="wb-select" required>
                @foreach (\WebBlocks\Cms\Support\Pages\PageListSettings::layouts() as $layout)
                    <option value="{{ $layout }}" @selected(old("{$prefix}.page_list_layout", $settings->layout) === $layout)>{{ $adminText('layout.'.$layout) }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_columns">{{ $adminText('columns_label') }}</label>
            <select id="block_{{ $index }}_page_list_columns" name="{{ $prefix }}[page_list_columns]" class="wb-select" required>
                @foreach (\WebBlocks\Cms\Support\Pages\PageListSettings::columnOptions() as $columns)
                    <option value="{{ $columns }}" @selected(old("{$prefix}.page_list_columns", $settings->columns) === $columns)>{{ $columns }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_show_thumbnail">{{ $adminText('show_thumbnail_label') }}</label>
            <select id="block_{{ $index }}_page_list_show_thumbnail" name="{{ $prefix }}[page_list_show_thumbnail]" class="wb-select">
                <option value="1" @selected($boolValue('page_list_show_thumbnail', $settings->showThumbnail))>{{ $adminText('option_yes') }}</option>
                <option value="0" @selected(! $boolValue('page_list_show_thumbnail', $settings->showThumbnail))>{{ $adminText('option_no') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_show_description">{{ $adminText('show_description_label') }}</label>
            <select id="block_{{ $index }}_page_list_show_description" name="{{ $prefix }}[page_list_show_description]" class="wb-select">
                <option value="1" @selected($boolValue('page_list_show_description', $settings->showDescription))>{{ $adminText('option_yes') }}</option>
                <option value="0" @selected(! $boolValue('page_list_show_description', $settings->showDescription))>{{ $adminText('option_no') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_exclude_current">{{ $adminText('exclude_current_label') }}</label>
            <select id="block_{{ $index }}_page_list_exclude_current" name="{{ $prefix }}[page_list_exclude_current]" class="wb-select">
                <option value="1" @selected($boolValue('page_list_exclude_current', $settings->excludeCurrent))>{{ $adminText('option_yes') }}</option>
                <option value="0" @selected(! $boolValue('page_list_exclude_current', $settings->excludeCurrent))>{{ $adminText('option_no') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="block_{{ $index }}_page_list_clickable_card">{{ $adminText('clickable_card_label') }}</label>
            <select id="block_{{ $index }}_page_list_clickable_card" name="{{ $prefix }}[page_list_clickable_card]" class="wb-select">
                <option value="1" @selected($boolValue('page_list_clickable_card', $settings->clickableCard))>{{ $adminText('option_yes') }}</option>
                <option value="0" @selected(! $boolValue('page_list_clickable_card', $settings->clickableCard))>{{ $adminText('option_no') }}</option>
            </select>
        </div>
    </div>
</div>
