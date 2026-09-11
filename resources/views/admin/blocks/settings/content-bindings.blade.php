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
