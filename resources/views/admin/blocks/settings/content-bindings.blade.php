@php
    $sourceEditor = app(\WebBlocks\Cms\Support\ContentSources\ContentSourceEditor::class);
    $bindingTargets = $sourceEditor->targets($block);
    $contentSourceWarnings = $sourceEditor->warnings($block);
@endphp

@if ($contentSourceWarnings !== [])
    <div class="wb-alert wb-alert-warning wb-mb-4" role="alert">
        <strong>{{ $blockFormText('content_source_warning_title') }}</strong>
        <ul>@foreach ($contentSourceWarnings as $warning)<li>{{ $blockFormText($warning['key'], $warning['params']) }}</li>@endforeach</ul>
        <div class="wb-text-sm">{{ $blockFormText('content_source_warning_help') }}</div>
    </div>
@endif

@if ($bindingTargets !== [])
    <div class="wb-stack wb-gap-3 wb-mb-4">
        <div>
            <strong>{{ $blockFormText('content_binding_title') }}</strong>
            <div class="wb-text-sm wb-text-muted">{{ $blockFormText('content_binding_help') }}</div>
        </div>

        @foreach ($bindingTargets as $target => $acceptedTypes)
            @php
                $choices = $sourceEditor->choices($block, $acceptedTypes);
                $selected = old('content_binding_selection.'.$target, $sourceEditor->selectedValue($block, $target));
            @endphp

            @if ($choices !== [] || $selected !== '')
                <div class="wb-stack wb-gap-1">
                    <label for="content_binding_{{ $target }}">{{ $blockFormText('content_binding_field_'.$target) }}</label>
                    <select id="content_binding_{{ $target }}" name="content_binding_selection[{{ $target }}]" class="wb-select">
                        <option value="">{{ $blockFormText('content_binding_literal') }}</option>
                        @if ($selected !== '' && ! collect($choices)->contains('value', $selected))
                            <option value="{{ $selected }}" selected disabled>{{ $blockFormText('content_source_unavailable_selection') }}</option>
                        @endif
                        @foreach ($choices as $choice)
                            <option value="{{ $choice['value'] }}" @selected($selected === $choice['value'])>{{ $choice['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        @endforeach
    </div>
@endif

@if ($sourceEditor->supportsCollection($selectedBlockType ?? $block))
    @php
        $collectionChoices = $sourceEditor->collectionChoices();
        $selectedCollection = old('content_collection_source', $block->setting('content_collection.source', ''));
        $selectedTemplateId = (string) old('content_collection_template_id', $block->setting('content_collection.template_block_id', ''));
        $collectionPreview = $selectedCollection !== '' ? $sourceEditor->collectionPreview($block, $selectedCollection) : [];
        $collectionFields = $sourceEditor->collectionFieldChoices($selectedCollection);
        $collectionContainer = $selectedBlockType ?? $block;
        $templateBlocks = $block->children
            ->filter(fn ($child) => $sourceEditor->collectionTemplateIsAllowed($collectionContainer, (string) $child->typeSlug()));
    @endphp

    @if ($collectionChoices !== [] || $selectedCollection !== '')
        <div class="wb-stack wb-gap-3 wb-mb-4">
            <div>
                <strong>{{ $blockFormText('content_collection_title') }}</strong>
                <div class="wb-text-sm wb-text-muted">{{ $blockFormText('content_collection_help') }}</div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="content_collection_source">{{ $blockFormText('content_collection_source') }}</label>
                <select id="content_collection_source" name="content_collection_source" class="wb-select">
                    <option value="">{{ $blockFormText('content_collection_none') }}</option>
                    @if ($selectedCollection !== '' && ! array_key_exists($selectedCollection, $collectionChoices))
                        <option value="{{ $selectedCollection }}" selected disabled>{{ $blockFormText('content_source_unavailable_selection') }}</option>
                    @endif
                    @foreach ($collectionChoices as $handle => $label)
                        <option value="{{ $handle }}" @selected($selectedCollection === $handle)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="content_collection_template_id">{{ $blockFormText('content_collection_template') }}</label>
                    <select id="content_collection_template_id" name="content_collection_template_id" class="wb-select">
                        <option value="">{{ $blockFormText('content_collection_choose_template') }}</option>
                        @foreach ($templateBlocks as $templateBlock)
                            <option value="{{ $templateBlock->id }}" @selected($selectedTemplateId === (string) $templateBlock->id)>{{ $templateBlock->layoutAdminName() ?: $blockFormText('content_collection_block', ['id' => $templateBlock->id]) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="content_collection_limit">{{ $blockFormText('content_collection_limit') }}</label>
                    <input id="content_collection_limit" name="content_collection_limit" class="wb-input" type="number" min="1" max="50" value="{{ old('content_collection_limit', $block->setting('content_collection.limit', 12)) }}">
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="content_collection_empty_behavior">{{ $blockFormText('content_collection_empty_behavior') }}</label>
                <select id="content_collection_empty_behavior" name="content_collection_empty_behavior" class="wb-select">
                    <option value="hide_template" @selected(old('content_collection_empty_behavior', $block->setting('content_collection.empty_behavior', 'hide_template')) === 'hide_template')>{{ $blockFormText('content_collection_empty_hide') }}</option>
                    <option value="keep_template" @selected(old('content_collection_empty_behavior', $block->setting('content_collection.empty_behavior', 'hide_template')) === 'keep_template')>{{ $blockFormText('content_collection_empty_keep') }}</option>
                </select>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="content_collection_error_behavior">{{ $blockFormText('content_collection_error_behavior') }}</label>
                <select id="content_collection_error_behavior" name="content_collection_error_behavior" class="wb-select">
                    <option value="keep_template" @selected(old('content_collection_error_behavior', $block->setting('content_collection.error_behavior', 'keep_template')) === 'keep_template')>{{ $blockFormText('content_collection_error_keep') }}</option>
                    <option value="hide_template" @selected(old('content_collection_error_behavior', $block->setting('content_collection.error_behavior', 'keep_template')) === 'hide_template')>{{ $blockFormText('content_collection_error_hide') }}</option>
                </select>
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="content_collection_filter_field">{{ $blockFormText('content_collection_filter_field') }}</label>
                    <select id="content_collection_filter_field" name="content_collection_filter_field" class="wb-select">
                        <option value="">{{ $blockFormText('content_collection_field_none') }}</option>
                        @foreach ($collectionFields as $field => $label)<option value="{{ $field }}" @selected(old('content_collection_filter_field', $block->setting('content_collection.filter_field', '')) === $field)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="content_collection_filter_value">{{ $blockFormText('content_collection_filter_value') }}</label>
                    <input id="content_collection_filter_value" name="content_collection_filter_value" class="wb-input" value="{{ old('content_collection_filter_value', $block->setting('content_collection.filter_value', '')) }}">
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="content_collection_sort_field">{{ $blockFormText('content_collection_sort_field') }}</label>
                    <select id="content_collection_sort_field" name="content_collection_sort_field" class="wb-select">
                        <option value="">{{ $blockFormText('content_collection_field_none') }}</option>
                        @foreach ($collectionFields as $field => $label)<option value="{{ $field }}" @selected(old('content_collection_sort_field', $block->setting('content_collection.sort_field', '')) === $field)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="content_collection_sort_direction">{{ $blockFormText('content_collection_sort_direction') }}</label>
                    <select id="content_collection_sort_direction" name="content_collection_sort_direction" class="wb-select">
                        @foreach (['asc' => $blockFormText('content_collection_sort_asc'), 'desc' => $blockFormText('content_collection_sort_desc')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('content_collection_sort_direction', $block->setting('content_collection.sort_direction', 'asc')) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if (($selectedBlockType?->slug ?? $block->typeSlug()) !== 'slider')
                <div class="wb-grid wb-grid-2">
                    <label class="wb-check">
                        <input type="hidden" name="content_collection_paginate" value="0">
                        <input type="checkbox" name="content_collection_paginate" value="1" @checked((bool) old('content_collection_paginate', $block->setting('content_collection.paginate', false)))>
                        <span>{{ $blockFormText('content_collection_paginate') }}</span>
                    </label>
                    <div class="wb-stack wb-gap-1">
                        <label for="content_collection_per_page">{{ $blockFormText('content_collection_per_page') }}</label>
                        <input id="content_collection_per_page" name="content_collection_per_page" class="wb-input" type="number" min="1" max="50" value="{{ old('content_collection_per_page', $block->setting('content_collection.per_page', 12)) }}">
                    </div>
                </div>
            @endif

            @if ($collectionPreview !== [])
                <div class="wb-stack wb-gap-2">
                    <strong>{{ $blockFormText('content_collection_preview') }}</strong>
                    <div>
                        <table class="wb-table">
                            <thead><tr>@foreach (array_keys($collectionPreview[0]) as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($collectionPreview as $record)
                                    <tr>@foreach ($record as $value)<td>{{ $value }}</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="wb-text-sm wb-text-muted">{{ $blockFormText('content_collection_preview_help') }}</div>
                </div>
            @endif
        </div>
    @endif
@endif
