@props([
    'cancelUrl' => null,
    'cancelLabel' => 'Cancel',
    'cancelType' => 'link',
    'cancelAttributes' => [],
    'showSubmit' => true,
    'submitLabel' => 'Save',
    'submitType' => 'submit',
    'submitDisabled' => false,
    'submitAttributes' => [],
    'deleteHref' => null,
    'deleteFormAction' => null,
    'deleteSubmit' => false,
    'deleteLabel' => 'Delete',
    'deleteMethod' => 'DELETE',
    'deleteConfirm' => null,
    'deleteDisabled' => false,
    'deleteAttributes' => [],
    'containerClass' => 'wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap',
])

@php
    $renderAttributes = static function (array $attributes): string {
        return collect($attributes)
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
    };

    $cancelAttributesString = $renderAttributes($cancelAttributes);
    $submitAttributesString = $renderAttributes($submitAttributes);
    $deleteAttributesString = $renderAttributes($deleteAttributes);
    $deleteButtonClass = 'wb-btn wb-btn-secondary wb-text-danger';
    $hasDeleteAction = $deleteHref || $deleteFormAction || $deleteSubmit;
@endphp

<div class="{{ $containerClass }}">
    <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
        @if ($cancelType === 'button')
            <button type="button" class="wb-btn wb-btn-secondary"{!! $cancelAttributesString ? ' '.$cancelAttributesString : '' !!}>{{ $cancelLabel }}</button>
        @elseif ($cancelUrl)
            <a href="{{ $cancelUrl }}" class="wb-btn wb-btn-secondary"{!! $cancelAttributesString ? ' '.$cancelAttributesString : '' !!}>{{ $cancelLabel }}</a>
        @endif

        @if ($showSubmit)
            <button type="{{ $submitType }}" class="wb-btn wb-btn-primary" @disabled($submitDisabled){!! $submitAttributesString ? ' '.$submitAttributesString : '' !!}>{{ $submitLabel }}</button>
        @endif
    </div>

    @if ($hasDeleteAction)
        <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
        @if ($deleteFormAction)
            <form method="POST" action="{{ $deleteFormAction }}" @if ($deleteConfirm) onsubmit="return confirm({{ \Illuminate\Support\Js::from($deleteConfirm) }});" @endif>
                @csrf
                @if (strtoupper($deleteMethod) !== 'POST')
                    @method($deleteMethod)
                @endif
                <button type="submit" class="{{ $deleteButtonClass }}" @disabled($deleteDisabled){!! $deleteAttributesString ? ' '.$deleteAttributesString : '' !!}>{{ $deleteLabel }}</button>
            </form>
        @elseif ($deleteSubmit)
            <button type="submit" class="{{ $deleteButtonClass }}" @if ($deleteConfirm) onclick="return confirm({{ \Illuminate\Support\Js::from($deleteConfirm) }});" @endif @disabled($deleteDisabled){!! $deleteAttributesString ? ' '.$deleteAttributesString : '' !!}>{{ $deleteLabel }}</button>
        @elseif ($deleteHref && $deleteDisabled)
            <button type="button" class="{{ $deleteButtonClass }}" disabled{!! $deleteAttributesString ? ' '.$deleteAttributesString : '' !!}>{{ $deleteLabel }}</button>
        @elseif ($deleteHref)
            <a
                href="{{ $deleteHref }}"
                class="{{ $deleteButtonClass }}"
                @if ($deleteConfirm) onclick="return confirm({{ \Illuminate\Support\Js::from($deleteConfirm) }});" @endif
                {!! $deleteAttributesString ? ' '.$deleteAttributesString : '' !!}
            >{{ $deleteLabel }}</a>
        @endif
        </div>
    @endif
</div>
