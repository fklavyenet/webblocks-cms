@php
    $inputName = $inputName ?? 'column_items';
    $itemBlockType = $itemBlockType ?? null;
    $editorKey = $editorKey ?? 'column-item';
    $columnItemRowLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $columnItemRowText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('column_items_editor_row.'.$key, $columnItemRowLocale, $replace);
    $newItemLabel = $newItemLabel ?? $columnItemRowText('new_item');
    $titleLabel = $titleLabel ?? $columnItemRowText('title');
    $titlePlaceholder = $titlePlaceholder ?? null;
    $subtitleLabel = $subtitleLabel ?? $columnItemRowText('subtitle');
    $subtitlePlaceholder = $subtitlePlaceholder ?? null;
    $showSubtitle = $showSubtitle ?? false;
    $urlLabel = $urlLabel ?? $columnItemRowText('url');
    $contentLabel = $contentLabel ?? $columnItemRowText('content');
    $contentPlaceholder = $contentPlaceholder ?? $columnItemRowText('content_placeholder');
    $enableAdminSortable = $enableAdminSortable ?? false;
    $rowPrefix = is_numeric($index) ? "{$inputName}[{$index}]" : "{$inputName}[__INDEX__]";
    $rowSortOrder = is_numeric($index) ? ($columnItem->sort_order ?? $index) : '__INDEX__';
    $itemSettings = is_array($columnItem->settings ?? null) ? $columnItem->settings : json_decode((string) ($columnItem->settings ?? ''), true);
    $itemSettings = is_array($itemSettings) ? $itemSettings : [];
    $selectedIcon = $itemSettings['icon_slug'] ?? '';
    $selectedIconTone = $itemSettings['icon_tone'] ?? 'default';
    $selectedTone = $itemSettings['badge_tone'] ?? 'neutral';
    $iconGroups = app(\WebBlocks\Cms\Support\Icons\IconCatalog::class)->groupedPickerOptions('content', $selectedIcon, $selectedIcon);
    $iconToneOptions = [
        'default' => $columnItemRowText('default'),
        'soft' => $columnItemRowText('soft'),
        'brand' => $columnItemRowText('brand'),
        'accent' => $columnItemRowText('accent'),
        'highlight' => $columnItemRowText('highlight'),
        'bold' => $columnItemRowText('bold'),
        'quiet' => $columnItemRowText('quiet'),
    ];
    $summaryText = $showSubtitle
        ? ($columnItem->content ? str(strip_tags((string) $columnItem->content))->squish()->limit(88) : $contentPlaceholder)
        : ($columnItem->content ? str(strip_tags((string) $columnItem->content))->squish()->limit(88) : $contentPlaceholder);
@endphp

<div class="wb-card" data-wb-builder-item-row="{{ $editorKey }}" @if ($enableAdminSortable) data-admin-sortable-item draggable="true" @endif>
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-stack wb-gap-1">
            <div class="wb-cluster wb-cluster-2">
                @if ($enableAdminSortable)
                    <button type="button" class="wb-action-btn" data-admin-sortable-handle aria-label="{{ $columnItemRowText('drag_to_reorder') }}" title="{{ $columnItemRowText('drag_to_reorder') }}">
                        <i class="wb-icon wb-icon-grip-vertical" aria-hidden="true"></i>
                    </button>
                @endif
                <strong data-wb-builder-item-label="{{ $editorKey }}">{{ $columnItem->title ?: $newItemLabel }}</strong>
            </div>
            <span class="wb-text-sm wb-text-muted">{{ $summaryText }}</span>
        </div>

        <div class="wb-action-group">
            <button type="button" class="wb-action-btn" data-wb-builder-item-move="up" title="{{ $columnItemRowText('move_up') }}" aria-label="{{ $columnItemRowText('move_up') }}"><i class="wb-icon wb-icon-chevron-up" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn" data-wb-builder-item-move="down" title="{{ $columnItemRowText('move_down') }}" aria-label="{{ $columnItemRowText('move_down') }}"><i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn" data-wb-builder-item-toggle title="{{ $columnItemRowText('collapse_item') }}" aria-label="{{ $columnItemRowText('collapse_item') }}" aria-expanded="true"><i class="wb-icon wb-icon-minus" aria-hidden="true"></i></button>
            <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-builder-item-remove title="{{ $columnItemRowText('remove_item') }}" aria-label="{{ $columnItemRowText('remove_item') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="wb-card-body wb-stack wb-gap-3" data-wb-builder-item-body="{{ $editorKey }}">
        <input type="hidden" name="{{ $rowPrefix }}[id]" value="{{ is_numeric($index) ? $columnItem->id : '' }}">
        <input type="hidden" name="{{ $rowPrefix }}[block_type_id]" value="{{ $itemBlockType?->id }}">
        <input type="hidden" name="{{ $rowPrefix }}[sort_order]" value="{{ $rowSortOrder }}" data-wb-builder-item-sort="{{ $editorKey }}" @if ($enableAdminSortable) data-admin-sortable-order @endif>
        <input type="hidden" name="{{ $rowPrefix }}[_delete]" value="0" data-wb-builder-item-delete="{{ $editorKey }}">

        <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
                <label>{{ $titleLabel }}</label>
                <input class="wb-input" type="text" name="{{ $rowPrefix }}[title]" value="{{ $columnItem->title }}" @if ($titlePlaceholder) placeholder="{{ $titlePlaceholder }}" @endif data-wb-builder-item-title="{{ $editorKey }}">
            </div>

            <div class="wb-stack wb-gap-1">
                <label>{{ $urlLabel }}</label>
                <input class="wb-input" type="text" name="{{ $rowPrefix }}[url]" value="{{ $columnItem->url }}">
            </div>
        </div>

        @if ($showSubtitle)
            <div class="wb-stack wb-gap-1">
                <label>{{ $subtitleLabel }}</label>
                <input class="wb-input" type="text" name="{{ $rowPrefix }}[subtitle]" value="{{ $columnItem->subtitle }}" @if ($subtitlePlaceholder) placeholder="{{ $subtitlePlaceholder }}" @endif>
            </div>
        @endif

        <div class="wb-grid wb-grid-4">
            <div class="wb-stack wb-gap-1">
                <label>{{ $columnItemRowText('icon') }}</label>
                <select class="wb-select" name="{{ $rowPrefix }}[icon_slug]">
                    <option value="">{{ $columnItemRowText('no_icon') }}</option>
                    @if ($iconGroups['suggested']->isNotEmpty())
                        <optgroup label="{{ $columnItemRowText('suggested_icons') }}">
                            @foreach ($iconGroups['suggested'] as $icon)
                                <option value="{{ $icon['slug'] }}" @selected($selectedIcon === $icon['slug'])>{{ $icon['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if ($iconGroups['all']->isNotEmpty())
                        <optgroup label="{{ $columnItemRowText('all_icons') }}">
                            @foreach ($iconGroups['all'] as $icon)
                                <option value="{{ $icon['slug'] }}" @selected($selectedIcon === $icon['slug'])>{{ $icon['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
            </div>

            <div class="wb-stack wb-gap-1">
                <label>{{ $columnItemRowText('icon_tone') }}</label>
                <select class="wb-select" name="{{ $rowPrefix }}[icon_tone]">
                    @foreach ($iconToneOptions as $value => $label)
                        <option value="{{ $value }}" @selected($selectedIconTone === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wb-stack wb-gap-1">
                <label>{{ $columnItemRowText('badge') }}</label>
                <input class="wb-input" type="text" name="{{ $rowPrefix }}[badge_label]" value="{{ $columnItem->eyebrow ?? $columnItem->translatedTextFieldValue('eyebrow') }}">
            </div>

            <div class="wb-stack wb-gap-1">
                <label>{{ $columnItemRowText('badge_tone') }}</label>
                <select class="wb-select" name="{{ $rowPrefix }}[badge_tone]">
                    @foreach (['neutral' => $columnItemRowText('neutral'), 'info' => $columnItemRowText('info'), 'success' => $columnItemRowText('success'), 'warning' => $columnItemRowText('warning'), 'danger' => $columnItemRowText('danger')] as $value => $label)
                        <option value="{{ $value }}" @selected($selectedTone === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label>{{ $contentLabel }}</label>
            <textarea class="wb-textarea" rows="4" name="{{ $rowPrefix }}[content]">{{ $columnItem->content }}</textarea>
        </div>

        <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
                <label>{{ $columnItemRowText('status') }}</label>
                <select class="wb-select" name="{{ $rowPrefix }}[status]">
                    <option value="draft" @selected(($columnItem->status ?? 'published') === 'draft')>{{ $columnItemRowText('draft') }}</option>
                    <option value="published" @selected(($columnItem->status ?? 'published') === 'published')>{{ $columnItemRowText('published') }}</option>
                </select>
            </div>

            <div class="wb-stack wb-gap-1">
                <label>{{ $columnItemRowText('kind') }}</label>
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body">
                        <strong>{{ $columnItemRowText('content_block') }}</strong>
                    </div>
                </div>
                <input type="hidden" name="{{ $rowPrefix }}[is_system]" value="0">
            </div>
        </div>
    </div>
</div>
