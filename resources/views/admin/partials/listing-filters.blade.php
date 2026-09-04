@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $method = strtoupper($method ?? 'GET');
    $search = $search ?? null;
    $selects = $selects ?? [];
    $dates = $dates ?? [];
    $hidden = $hidden ?? [];
    $showActions = $showActions ?? true;
    $showReset = $showReset ?? false;
    $resetUrl = $resetUrl ?? null;
    $listingFiltersLocale = app(AdminLocaleResolver::class)->locale();
    $listingFiltersTranslator = app(CmsTranslator::class);
    $applyLabel = $applyLabel ?? $listingFiltersTranslator->admin('common.apply', $listingFiltersLocale);
    $resetLabel = $resetLabel ?? $listingFiltersTranslator->admin('common.clear_filters', $listingFiltersLocale);
    $resetFirst = $resetFirst ?? false;
@endphp

<form method="{{ $method }}" action="{{ $action }}" class="wb-filter-bar wb-filter-bar--fields" data-admin-listing-filters>
    @foreach ($hidden as $name => $value)
        @if ($value !== null && $value !== '')
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach

    <div class="wb-filter-bar-fields" data-admin-listing-filters-fields>
        @if ($search)
            <div class="wb-field wb-filter-bar-search" data-admin-listing-filters-search>
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
            @foreach ($selects as $select)
                @php($selectedValue = (string) ($select['selected'] ?? $select['value'] ?? ''))
                @php($includePlaceholder = ! array_key_exists('placeholder', $select) || $select['placeholder'] !== null)
                <div class="wb-field">
                    <label for="{{ $select['id'] }}" class="wb-label">{{ $select['label'] }}</label>
                    <select id="{{ $select['id'] }}" name="{{ $select['name'] }}" class="wb-filter-select" @if (! empty($select['submitOnChange'])) data-wb-submit-on-change @endif>
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
                <div class="wb-field">
                    <label for="{{ $date['id'] }}" class="wb-label">{{ $date['label'] }}</label>
                    <input
                        id="{{ $date['id'] }}"
                        name="{{ $date['name'] }}"
                        type="date"
                        class="wb-input"
                        value="{{ $date['value'] ?? '' }}"
                        @if (! empty($date['submitOnChange'])) data-wb-submit-on-change @endif
                    >
                </div>
            @endforeach
        @endif

        @if ($showActions)
            <div class="wb-filter-bar-actions" data-admin-listing-filters-actions>
                <div class="wb-action-group">
                    @if ($showReset && $resetUrl && $resetFirst)
                        <a href="{{ $resetUrl }}" class="wb-btn wb-btn-secondary">{{ $resetLabel }}</a>
                    @endif

                    <button type="submit" class="wb-btn wb-btn-primary">{{ $applyLabel }}</button>

                    @if ($showReset && $resetUrl && ! $resetFirst)
                        <a href="{{ $resetUrl }}" class="wb-btn wb-btn-secondary">{{ $resetLabel }}</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</form>
