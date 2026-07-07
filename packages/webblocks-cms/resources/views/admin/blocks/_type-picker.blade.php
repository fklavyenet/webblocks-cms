@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.blocks.'.$key, $adminLocale, $replace);
    $blockFormText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.block_form.'.$key, $replace);
    $search = strtolower(trim((string) request('block_type_search')));
    $availableBlockTypes = $blockTypes
        ->filter(function ($blockType) use ($search) {
            if ($search === '') {
                return true;
            }

            return str_contains(strtolower($blockType->name), $search)
                || str_contains(strtolower((string) $blockType->description), $search)
                || str_contains(strtolower((string) $blockType->category), $search)
                || str_contains(strtolower($blockType->slug), $search);
        })
        ->sortBy([fn ($blockType) => $blockType->sort_order, fn ($blockType) => $blockType->name])
        ->values();

    $groups = $availableBlockTypes->groupBy(fn ($blockType) => $blockType->is_system ? 'system' : 'content');

    $labelMap = [
        'callout' => 'CTA',
        'gallery' => $blockFormText('features'),
        'section' => $blockFormText('section'),
        'rich-text' => $blockFormText('rich_text'),
        'download' => $blockFormText('download'),
    ];
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-header">
        <strong>{{ $adminText('picker.title') }}</strong>
    </div>
    <div class="wb-card-body">
        <form method="GET" action="{{ $action }}" class="wb-stack wb-gap-3">
            @if ($block->page_id)
                <input type="hidden" name="page_id" value="{{ $block->page_id }}">
            @endif

            @if ($block->parent_id)
                <input type="hidden" name="parent_id" value="{{ $block->parent_id }}">
            @endif

            <div class="wb-stack wb-gap-1">
                <label for="block_type_search">{{ $adminText('picker.search_label') }}</label>
                <input id="block_type_search" name="block_type_search" class="wb-input" type="text" value="{{ request('block_type_search') }}" placeholder="{{ $adminText('picker.search_placeholder') }}">
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="block_type_id_picker">{{ $adminText('picker.choose_label') }}</label>
                    <select id="block_type_id_picker" name="block_type_id" class="wb-select" required>
                        <option value="">{{ $adminText('picker.choose_placeholder') }}</option>
                        @foreach ($groups as $category => $items)
                            <optgroup label="{{ $adminText('picker.groups.'.$category) }}">
                                @foreach ($items as $blockType)
                                    <option value="{{ $blockType->id }}" @selected((string) ($selectedBlockType?->id) === (string) $blockType->id)>
                                        {{ $labelMap[$blockType->slug] ?? $blockType->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <div class="wb-stack wb-gap-1">
                    <label>{{ $adminText('picker.selection') }}</label>
                    <div class="wb-card">
                        <div class="wb-card-body">
                            @if ($selectedBlockType)
                                <strong>{{ $labelMap[$selectedBlockType->slug] ?? $selectedBlockType->name }}</strong>
                                <div>{{ $selectedBlockType->description ?: ($selectedBlockType->is_system ? $adminText('picker.system_fallback') : $adminText('picker.content_fallback')) }}</div>
                            @else
                                <strong>{{ $adminText('picker.none_selected') }}</strong>
                                <div>{{ $adminText('picker.none_help') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                <div>
                    @if ($selectedBlockType)
                        <span class="wb-status-pill {{ $selectedBlockType->is_system ? 'wb-status-info' : 'wb-status-active' }}">
                            {{ $selectedBlockType->kindLabel() }}
                        </span>
                    @endif
                </div>

                <button type="submit" class="wb-btn wb-btn-primary">{{ $adminText('picker.open_form') }}</button>
            </div>
        </form>
    </div>
</div>
