@php
    $method = strtoupper($method ?? 'GET');
    $search = $search ?? null;
    $selects = $selects ?? [];
    $dates = $dates ?? [];
    $hidden = $hidden ?? [];
    $showActions = $showActions ?? true;
    $showReset = $showReset ?? false;
    $resetUrl = $resetUrl ?? null;
    $applyLabel = $applyLabel ?? 'Apply';
    $resetLabel = $resetLabel ?? 'Reset';
    $resetFirst = $resetFirst ?? false;
@endphp

<form method="{{ $method }}" action="{{ $action }}" class="wb-admin-listing-filters" data-admin-listing-filters>
    @foreach ($hidden as $name => $value)
        @if ($value !== null && $value !== '')
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    @if ($search)
        <div class="wb-stack wb-gap-1 wb-field wb-admin-listing-filters-search" data-admin-listing-filters-search>
            <label for="{{ $search['id'] }}" class="wb-label">{{ $search['label'] }}</label>
            <input
                id="{{ $search['id'] }}"
                name="{{ $search['name'] }}"
                type="text"
                class="wb-input"
                value="{{ $search['value'] }}"
                placeholder="{{ $search['placeholder'] ?? '' }}"
            >
        </div>
    @endif

    @if ($selects !== [] || $dates !== [])
        <div class="wb-admin-listing-filters-fields" data-admin-listing-filters-fields>
            @foreach ($selects as $select)
                @php($selectedValue = (string) ($select['selected'] ?? $select['value'] ?? ''))
                @php($includePlaceholder = ! array_key_exists('placeholder', $select) || $select['placeholder'] !== null)
                <div class="wb-stack wb-gap-1 wb-field wb-admin-listing-filters-select">
                    <label for="{{ $select['id'] }}" class="wb-label">{{ $select['label'] }}</label>
                    <select id="{{ $select['id'] }}" name="{{ $select['name'] }}" class="wb-select" @if (! empty($select['submitOnChange'])) onchange="this.form.submit()" @endif>
                        @if ($includePlaceholder)
                            <option value="">{{ $select['placeholder'] ?? 'All' }}</option>
                        @endif
                        @foreach ($select['options'] as $value => $label)
                            <option value="{{ $value }}" @selected($selectedValue === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            @foreach ($dates as $date)
                <div class="wb-stack wb-gap-1 wb-field wb-admin-listing-filters-date">
                    <label for="{{ $date['id'] }}" class="wb-label">{{ $date['label'] }}</label>
                    <input
                        id="{{ $date['id'] }}"
                        name="{{ $date['name'] }}"
                        type="date"
                        class="wb-input"
                        value="{{ $date['value'] ?? '' }}"
                        @if (! empty($date['submitOnChange'])) onchange="this.form.submit()" @endif
                    >
                </div>
            @endforeach
        </div>
    @endif

    @if ($showActions)
        <div class="wb-admin-listing-filters-actions" data-admin-listing-filters-actions>
            @if ($showReset && $resetUrl && $resetFirst)
                <a href="{{ $resetUrl }}" class="wb-btn wb-btn-secondary">{{ $resetLabel }}</a>
            @endif

            <button type="submit" class="wb-btn wb-btn-primary">{{ $applyLabel }}</button>

            @if ($showReset && $resetUrl && ! $resetFirst)
                <a href="{{ $resetUrl }}" class="wb-btn wb-btn-secondary">{{ $resetLabel }}</a>
            @endif
        </div>
    @endif
</form>
