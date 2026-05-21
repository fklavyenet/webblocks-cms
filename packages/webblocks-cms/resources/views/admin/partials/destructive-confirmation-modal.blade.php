@php
    $description = $description ?? null;
    $method = $method ?? 'DELETE';
    $submitLabel = $submitLabel ?? 'Delete';
    $cancelLabel = $cancelLabel ?? 'Cancel';
    $formAttributes = $formAttributes ?? [];
    $submitAttributes = $submitAttributes ?? [];

    $formAttributeString = collect($formAttributes)
        ->map(function ($value, $attribute) {
            if (is_int($attribute)) {
                return e($value);
            }

            if (is_bool($value)) {
                return $value ? $attribute : null;
            }

            if ($value === null) {
                return null;
            }

            return sprintf('%s="%s"', $attribute, e($value));
        })
        ->filter()
        ->implode(' ');

    $submitAttributeString = collect($submitAttributes)
        ->map(function ($value, $attribute) {
            if (is_int($attribute)) {
                return e($value);
            }

            if (is_bool($value)) {
                return $value ? $attribute : null;
            }

            if ($value === null) {
                return null;
            }

            return sprintf('%s="%s"', $attribute, e($value));
        })
        ->filter()
        ->implode(' ');
@endphp

<div class="wb-modal wb-modal-lg" id="{{ $id }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" @if ($description) aria-describedby="{{ $id }}-description" @endif>
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <div>
                <h2 class="wb-modal-title" id="{{ $id }}-title">{{ $title }}</h2>
                @if ($description)
                    <p class="wb-text-sm wb-text-muted" id="{{ $id }}-description">{{ $description }}</p>
                @endif
            </div>

            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close {{ $title }}">
                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
            </button>
        </div>

        <form method="POST" action="{{ $action }}"{!! $formAttributeString ? ' '.$formAttributeString : '' !!}>
            @csrf
            @if (strtoupper($method) !== 'POST')
                @method($method)
            @endif

            <div class="wb-modal-body wb-stack wb-gap-4">
                {{ $slot }}
            </div>

            <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                <div class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                    <button type="submit" class="wb-btn wb-btn-danger"{!! $submitAttributeString ? ' '.$submitAttributeString : '' !!}>{{ $submitLabel }}</button>
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $cancelLabel }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
