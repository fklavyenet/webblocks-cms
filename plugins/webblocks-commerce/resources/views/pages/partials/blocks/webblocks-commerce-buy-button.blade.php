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
    $label = trim((string) ($settings['label'] ?? '')) ?: 'Buy Now';
    $showPrice = (string) ($settings['show_price'] ?? '1') === '1';
@endphp

@if ($product?->isAvailableForCheckout())
    <div class="wb-cluster wb-gap-3 {{ $alignmentClass }}">
        <a class="wb-btn wb-btn-primary" href="{{ route('webblocks.commerce.products.buy', $product->slug) }}">
            {{ $label }}
        </a>

        @if ($showPrice)
            <span class="wb-text-sm wb-text-muted">{{ number_format($product->price_amount / 100, 2) }} {{ $product->currency }}</span>
        @endif
    </div>
@elseif (! app()->environment('production'))
    <div class="wb-alert wb-alert-warning">
        <div>Commerce product is unavailable for this buy button.</div>
    </div>
@endif
