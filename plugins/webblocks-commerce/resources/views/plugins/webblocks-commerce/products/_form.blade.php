@php
    $selectedSiteId = (string) old('site_id', $product->site_id ?? '');
    $selectedStatus = old('status', $product->status ?: 'draft');
@endphp

<div class="wb-stack wb-gap-4">
    @if ($errors->any())
        <div class="wb-alert wb-alert-danger">
            <div>
                <div class="wb-alert-title">Validation Error</div>
                <div>{{ $errors->first() }}</div>
            </div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2 wb-gap-4">
        <div class="wb-stack wb-gap-1">
            <label for="product_title">Title</label>
            <input id="product_title" name="title" class="wb-input" type="text" maxlength="255" value="{{ old('title', $product->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="product_slug">Slug</label>
            <input id="product_slug" name="slug" class="wb-input" type="text" maxlength="255" value="{{ old('slug', $product->slug) }}">
        </div>
    </div>

    <div class="wb-grid wb-grid-3 wb-gap-4">
        <div class="wb-stack wb-gap-1">
            <label for="product_status">Status</label>
            <select id="product_status" name="status" class="wb-select" required>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="product_site_id">Site</label>
            <select id="product_site_id" name="site_id" class="wb-select">
                <option value="">Install-wide</option>
                @foreach ($siteOptions as $value => $label)
                    <option value="{{ $value }}" @selected($selectedSiteId === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="product_sku">SKU</label>
            <input id="product_sku" name="sku" class="wb-input" type="text" maxlength="255" value="{{ old('sku', $product->sku) }}">
        </div>
    </div>

    <div class="wb-grid wb-grid-3 wb-gap-4">
        <div class="wb-stack wb-gap-1">
            <label for="product_price_amount">Price Amount</label>
            <input id="product_price_amount" name="price_amount" class="wb-input" type="number" min="1" step="1" value="{{ old('price_amount', $product->price_amount) }}" required>
            <div class="wb-text-sm wb-text-muted">Stored in minor units, for example cents.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="product_currency">Currency</label>
            <input id="product_currency" name="currency" class="wb-input" type="text" maxlength="3" value="{{ old('currency', $product->currency ?: 'USD') }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="product_inventory_quantity">Inventory</label>
            <input id="product_inventory_quantity" name="inventory_quantity" class="wb-input" type="number" min="0" step="1" value="{{ old('inventory_quantity', $product->inventory_quantity) }}">
        </div>
    </div>

    <div class="wb-grid wb-grid-3 wb-gap-4">
        <div class="wb-stack wb-gap-1">
            @php($selectedTaxClass = old('tax_class', $product->tax_class ?: 'standard'))
            <label for="product_tax_class">Tax class</label>
            <select id="product_tax_class" name="tax_class" class="wb-select" required>
                @foreach ($taxClassOptions as $value => $label)
                    <option value="{{ $value }}" @selected($selectedTaxClass === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">VAT rate is resolved from the store country and this class at checkout.</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="product_description">Description</label>
        <textarea id="product_description" name="description" class="wb-textarea" rows="5">{{ old('description', $product->description) }}</textarea>
        <div class="wb-text-sm wb-text-muted">Default language content. Add per-language versions below.</div>
    </div>

    @if (($translationLocales ?? collect())->isNotEmpty())
        <div class="wb-stack wb-gap-3">
            <strong>Translations</strong>
            <div class="wb-text-sm wb-text-muted">Leave a language blank to fall back to the default content above.</div>

            @foreach ($translationLocales as $locale)
                @php($existing = ($existingTranslations ?? collect())->get($locale->id))
                <fieldset class="wb-stack wb-gap-2">
                    <legend>{{ $locale->name }} ({{ $locale->code }})</legend>

                    <div class="wb-stack wb-gap-1">
                        <label for="product_translation_{{ $locale->id }}_title">Title</label>
                        <input id="product_translation_{{ $locale->id }}_title"
                            name="translations[{{ $locale->id }}][title]"
                            class="wb-input" type="text" maxlength="255"
                            value="{{ old('translations.'.$locale->id.'.title', $existing?->title) }}">
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="product_translation_{{ $locale->id }}_description">Description</label>
                        <textarea id="product_translation_{{ $locale->id }}_description"
                            name="translations[{{ $locale->id }}][description]"
                            class="wb-textarea" rows="3">{{ old('translations.'.$locale->id.'.description', $existing?->description) }}</textarea>
                    </div>
                </fieldset>
            @endforeach
        </div>
    @endif
</div>
