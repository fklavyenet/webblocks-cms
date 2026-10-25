@php([$fieldName, $fieldLabel, $fieldValue, $fieldMaximum] = $field)
<div class="wb-field"><label class="wb-sr-only" for="cleanup_{{ $fieldName }}">{{ $fieldLabel }}</label><input class="wb-input wb-cms-policy-input" id="cleanup_{{ $fieldName }}" name="{{ $fieldName }}" type="number" min="1" max="{{ $fieldMaximum }}" required value="{{ old($fieldName, $fieldValue) }}"></div>
