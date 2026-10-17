@php
    $pickerText = fn ($key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-authoring.'.$key, app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale());
    $pickerSelected = $pickerSelected ?? '';
@endphp
<div class="wb-stack wb-gap-2" data-wb-binding-picker>
    <label for="{{ $pickerId }}">{{ $pickerLabel }}</label>
    <select id="{{ $pickerId }}" name="{{ $pickerName }}" class="wb-select" data-wb-binding-value>
        <option value="">{{ $pickerText('literal') }}</option>
        @if ($pickerSelected !== '' && ! collect($pickerChoices)->contains('value', $pickerSelected))
            <option value="{{ $pickerSelected }}" selected data-unavailable="1">{{ $pickerText('unavailable_selection') }}</option>
        @endif
        @foreach ($pickerChoices as $choice)
            <option value="{{ $choice['value'] }}" data-source="{{ $choice['source_label'] }}" data-record="{{ $choice['record_label'] }}" data-field="{{ $choice['field_label'] }}" @selected($pickerSelected === $choice['value'])>{{ $choice['label'] }}</option>
        @endforeach
    </select>
    <div class="wb-grid wb-grid-2" data-wb-binding-controls hidden>
        @foreach (['mode', 'source', 'record', 'field'] as $pickerPart)
            <div class="wb-stack wb-gap-1"><label for="{{ $pickerId }}-{{ $pickerPart }}">{{ $pickerText($pickerPart) }}</label><select id="{{ $pickerId }}-{{ $pickerPart }}" class="wb-select" data-wb-binding-part="{{ $pickerPart }}" data-empty-label="{{ $pickerText('choose') }}" @if ($pickerPart === 'mode') data-literal="{{ $pickerText('literal') }}" data-current="{{ $pickerText('current') }}" data-fixed="{{ $pickerText('fixed') }}" @endif></select></div>
        @endforeach
    </div>
    <label for="{{ $pickerId }}-search">{{ $pickerText('search_choices') }}</label>
    <input id="{{ $pickerId }}-search" type="search" class="wb-input" data-wb-binding-search autocomplete="off">
    <span class="wb-text-sm wb-text-muted" data-wb-binding-summary aria-live="polite">{{ $pickerText('fallback_help') }}</span>
</div>
