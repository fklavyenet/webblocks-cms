@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.page_list.'.$key, $adminLocale);
    $settings = $block->pageListSettings();
    $pageTypes = \WebBlocks\Cms\Models\PageType::query()->orderBy('sort_order')->orderBy('name')->get();
    $boolValue = fn (string $field, bool $current) => old($field, $current ? '1' : '0') === '1';
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('system_title') }}</div>
            <div>{{ $adminText('system_help') }}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="page_list_scope">{{ $adminText('scope_label') }}</label>
            <select id="page_list_scope" name="page_list_scope" class="wb-select" required>
                @foreach (\WebBlocks\Cms\Support\Pages\PageListSettings::scopes() as $scope)
                    <option value="{{ $scope }}" @selected(old('page_list_scope', $settings->scope) === $scope)>{{ $adminText('scope.'.$scope) }}</option>
                @endforeach
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('scope_help') }}</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_list_page_type">{{ $adminText('page_type_label') }}</label>
            <select id="page_list_page_type" name="page_list_page_type" class="wb-select">
                <option value="">{{ $adminText('page_type_placeholder') }}</option>
                @foreach ($pageTypes as $pageType)
                    <option value="{{ $pageType->slug }}" @selected(old('page_list_page_type', $settings->pageType) === $pageType->slug)>{{ $pageType->name }}</option>
                @endforeach
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('page_type_help') }}</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_list_path_prefix">{{ $adminText('path_prefix_label') }}</label>
            <input type="text" id="page_list_path_prefix" name="page_list_path_prefix" class="wb-input" value="{{ old('page_list_path_prefix', $settings->pathPrefix) }}" placeholder="/guides">
            <span class="wb-text-sm wb-text-muted">{{ $adminText('path_prefix_help') }}</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_list_sort">{{ $adminText('sort_label') }}</label>
            <select id="page_list_sort" name="page_list_sort" class="wb-select" required>
                @foreach (\WebBlocks\Cms\Support\Pages\PageListSettings::sorts() as $sort)
                    <option value="{{ $sort }}" @selected(old('page_list_sort', $settings->sort) === $sort)>{{ $adminText('sort.'.$sort) }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_list_limit">{{ $adminText('limit_label') }}</label>
            <input
                type="number"
                id="page_list_limit"
                name="page_list_limit"
                class="wb-input"
                min="{{ \WebBlocks\Cms\Support\Pages\PageListSettings::LIMIT_MIN }}"
                max="{{ \WebBlocks\Cms\Support\Pages\PageListSettings::LIMIT_MAX }}"
                value="{{ old('page_list_limit', $settings->limit) }}"
                required
            >
            <span class="wb-text-sm wb-text-muted">{{ $adminText('limit_help') }}</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_list_layout">{{ $adminText('layout_label') }}</label>
            <select id="page_list_layout" name="page_list_layout" class="wb-select" required>
                @foreach (\WebBlocks\Cms\Support\Pages\PageListSettings::layouts() as $layout)
                    <option value="{{ $layout }}" @selected(old('page_list_layout', $settings->layout) === $layout)>{{ $adminText('layout.'.$layout) }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_list_columns">{{ $adminText('columns_label') }}</label>
            <select id="page_list_columns" name="page_list_columns" class="wb-select" required>
                @foreach (\WebBlocks\Cms\Support\Pages\PageListSettings::columnOptions() as $columns)
                    <option value="{{ $columns }}" @selected(old('page_list_columns', $settings->columns) === $columns)>{{ $columns }}</option>
                @endforeach
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('columns_help') }}</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_list_show_thumbnail">{{ $adminText('show_thumbnail_label') }}</label>
            <select id="page_list_show_thumbnail" name="page_list_show_thumbnail" class="wb-select">
                <option value="1" @selected($boolValue('page_list_show_thumbnail', $settings->showThumbnail))>{{ $adminText('option_yes') }}</option>
                <option value="0" @selected(! $boolValue('page_list_show_thumbnail', $settings->showThumbnail))>{{ $adminText('option_no') }}</option>
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('show_thumbnail_help') }}</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_list_show_description">{{ $adminText('show_description_label') }}</label>
            <select id="page_list_show_description" name="page_list_show_description" class="wb-select">
                <option value="1" @selected($boolValue('page_list_show_description', $settings->showDescription))>{{ $adminText('option_yes') }}</option>
                <option value="0" @selected(! $boolValue('page_list_show_description', $settings->showDescription))>{{ $adminText('option_no') }}</option>
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('show_description_help') }}</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_list_exclude_current">{{ $adminText('exclude_current_label') }}</label>
            <select id="page_list_exclude_current" name="page_list_exclude_current" class="wb-select">
                <option value="1" @selected($boolValue('page_list_exclude_current', $settings->excludeCurrent))>{{ $adminText('option_yes') }}</option>
                <option value="0" @selected(! $boolValue('page_list_exclude_current', $settings->excludeCurrent))>{{ $adminText('option_no') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="page_list_clickable_card">{{ $adminText('clickable_card_label') }}</label>
            <select id="page_list_clickable_card" name="page_list_clickable_card" class="wb-select">
                <option value="1" @selected($boolValue('page_list_clickable_card', $settings->clickableCard))>{{ $adminText('option_yes') }}</option>
                <option value="0" @selected(! $boolValue('page_list_clickable_card', $settings->clickableCard))>{{ $adminText('option_no') }}</option>
            </select>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('clickable_card_help') }}</span>
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-1">
            <strong>{{ $adminText('system_block') }}</strong>
            <span class="wb-text-sm wb-text-muted">{{ $adminText('system_block_help') }}</span>
        </div>
    </div>
</div>
