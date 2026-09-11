@php
    $sourceEditor = app(\WebBlocks\Cms\Support\ContentSources\ContentSourceEditor::class);
    $bindingTargets = $sourceEditor->targets($block);
@endphp

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

            @if ($choices !== [])
                <div class="wb-stack wb-gap-1">
                    <label for="content_binding_{{ $target }}">{{ $blockFormText('content_binding_field_'.$target) }}</label>
                    <select id="content_binding_{{ $target }}" name="content_binding_selection[{{ $target }}]" class="wb-select">
                        <option value="">{{ $blockFormText('content_binding_literal') }}</option>
                        @foreach ($choices as $choice)
                            <option value="{{ $choice['value'] }}" @selected($selected === $choice['value'])>{{ $choice['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        @endforeach
    </div>
@endif

@if (($selectedBlockType?->slug ?? $block->typeSlug()) === 'slider')
    @php
        $collectionChoices = $sourceEditor->collectionChoices();
        $selectedCollection = old('content_collection_source', $block->setting('content_collection.source', ''));
        $selectedTemplateId = (string) old('content_collection_template_id', $block->setting('content_collection.template_block_id', ''));
        $slideTemplates = $block->children->filter(fn ($child) => $child->typeSlug() === 'slide');
    @endphp

    @if ($collectionChoices !== [])
        <div class="wb-stack wb-gap-3 wb-mb-4">
            <div>
                <strong>{{ $blockFormText('content_collection_title') }}</strong>
                <div class="wb-text-sm wb-text-muted">{{ $blockFormText('content_collection_help') }}</div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="content_collection_source">{{ $blockFormText('content_collection_source') }}</label>
                <select id="content_collection_source" name="content_collection_source" class="wb-select">
                    <option value="">{{ $blockFormText('content_collection_none') }}</option>
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
                        @foreach ($slideTemplates as $slide)
                            <option value="{{ $slide->id }}" @selected($selectedTemplateId === (string) $slide->id)>{{ $slide->layoutAdminName() ?: $blockFormText('content_collection_slide', ['id' => $slide->id]) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="content_collection_limit">{{ $blockFormText('content_collection_limit') }}</label>
                    <input id="content_collection_limit" name="content_collection_limit" class="wb-input" type="number" min="1" max="50" value="{{ old('content_collection_limit', $block->setting('content_collection.limit', 12)) }}">
                </div>
            </div>
        </div>
    @endif
@endif
