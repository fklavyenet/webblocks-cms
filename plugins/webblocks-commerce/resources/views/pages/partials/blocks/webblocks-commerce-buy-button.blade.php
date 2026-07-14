@php
    use Illuminate\Support\Facades\Schema;
    use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;

    $settings = is_array($block->settings)
        ? $block->settings
        : (is_string($block->settings) ? json_decode($block->settings, true) : []);
    $settings = is_array($settings) ? $settings : [];
    $productId = (int) ($settings['commerce_product_id'] ?? 0);
    $product = Schema::hasTable('webblocks_commerce_products') && $productId > 0
        ? CommerceProduct::query()->find($productId)
        : null;
    $alignment = in_array(($settings['alignment'] ?? 'start'), ['start', 'center', 'end'], true)
        ? $settings['alignment']
        : 'start';
    $alignmentClass = match ($alignment) {
        'center' => 'wb-justify-center',
        'end' => 'wb-justify-end',
        default => 'wb-justify-start',
    };
    $label = trim((string) ($settings['label'] ?? '')) ?: 'Add to Cart';
    $showPrice = (string) ($settings['show_price'] ?? '1') === '1';
    $money = app(\WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\MoneyFormatter::class);
    $moneyLocale = $publicLocaleCode ?? app()->getLocale();
@endphp

@if ($product?->isAvailableForCheckout())
    <div class="wb-cluster wb-gap-3 {{ $alignmentClass }}">
        <form method="POST" action="{{ route('webblocks.commerce.cart.items.add', $product->id) }}">
            @csrf
            <input type="hidden" name="quantity" value="1">
            <button class="wb-btn wb-btn-primary" type="submit">{{ $label }}</button>
        </form>

        @if ($showPrice)
            <span class="wb-text-sm wb-text-muted">{{ $money->format($product->price_amount, $product->currency, $moneyLocale) }}</span>
        @endif
    </div>
@elseif (! app()->environment('production'))
    <div class="wb-alert wb-alert-warning">
        <div>Commerce product is unavailable for this buy button.</div>
    </div>
@endif
