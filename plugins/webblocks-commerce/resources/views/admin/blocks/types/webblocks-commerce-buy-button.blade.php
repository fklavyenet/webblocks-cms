@php
    use Illuminate\Support\Facades\Schema;
    use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;

    $settings = is_array($block->settings)
        ? $block->settings
        : (is_string($block->settings) ? json_decode($block->settings, true) : []);
    $settings = is_array($settings) ? $settings : [];
    $schemaReady = Schema::hasTable('webblocks_commerce_products');
    $products = $schemaReady
        ? CommerceProduct::query()->where('status', CommerceProduct::STATUS_ACTIVE)->orderBy('title')->get()
        : collect();
    $selectedProductId = (string) old('plugin_settings.commerce_product_id', $settings['commerce_product_id'] ?? '');
    $buttonLabel = old('plugin_settings.label', $settings['label'] ?? 'Buy Now');
    $showPrice = (string) old('plugin_settings.show_price', $settings['show_price'] ?? '1') === '1';
    $alignment = old('plugin_settings.alignment', $settings['alignment'] ?? 'start');
@endphp

<div class="wb-stack wb-gap-4">
    @unless ($schemaReady)
        <div class="wb-alert wb-alert-warning">
            <div>Commerce setup is required before products can be selected.</div>
        </div>
    @endunless

    @if ($schemaReady && $products->isEmpty())
        <div class="wb-alert wb-alert-info">
            <div>Create and activate a commerce product before adding this button.</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="commerce_product_id">Product</label>
        <select id="commerce_product_id" name="plugin_settings[commerce_product_id]" class="wb-select" required>
            <option value="">Select product</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected($selectedProductId === (string) $product->id)>
                    {{ $product->title }} · {{ number_format($product->price_amount / 100, 2) }} {{ $product->currency }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="commerce_button_label">Button Label</label>
        <input id="commerce_button_label" name="plugin_settings[label]" class="wb-input" type="text" value="{{ $buttonLabel }}" maxlength="80" placeholder="Buy Now">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="commerce_alignment">Alignment</label>
        <select id="commerce_alignment" name="plugin_settings[alignment]" class="wb-select">
            <option value="start" @selected($alignment === 'start')>Start</option>
            <option value="center" @selected($alignment === 'center')>Center</option>
            <option value="end" @selected($alignment === 'end')>End</option>
        </select>
    </div>

    <input type="hidden" name="plugin_settings[show_price]" value="0">
    <label class="wb-checkbox">
        <input type="checkbox" name="plugin_settings[show_price]" value="1" @checked($showPrice)>
        <span>Show price beside the button</span>
    </label>
</div>
