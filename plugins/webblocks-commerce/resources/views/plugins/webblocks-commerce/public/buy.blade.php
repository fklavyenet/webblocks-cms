@extends('webblocks-cms::layouts.public', [
    'title' => $title,
    'site' => $site,
    'publicLocaleCode' => $publicLocaleCode,
    'publicMeta' => [
        'title' => $title,
        'site_name' => $site?->publicDisplayName() ?? config('app.name'),
        'site_label' => $site?->display_name ?? $site?->name ?? config('app.name'),
        'meta_description' => $product->description ?? '',
        'og_title' => $title,
        'og_description' => $product->description ?? '',
    ],
])

@section('content')
    @php
        $commerceText = fn (string $key, array $replace = [], ?string $fallback = null): string => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->plugin('webblocks-commerce', 'public.'.$key, $publicLocaleCode, $replace, $fallback);
        $money = app(\WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\MoneyFormatter::class);
    @endphp
    <main class="wb-content-shell wb-py-8">
        <div class="wb-stack wb-gap-4 wb-container">
            <div class="wb-cluster wb-cluster-between wb-gap-3 wb-flex-wrap">
                <a href="{{ url('/') }}" class="wb-link">{{ $commerceText('buy.back', fallback: 'Back to site') }}</a>
                <a href="{{ route('webblocks.commerce.cart.show') }}" class="wb-btn wb-btn-secondary">{{ $commerceText('buy.view_cart', fallback: 'View cart') }}</a>
            </div>

            <section class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-4">
                    <div class="wb-stack wb-gap-2">
                        <p class="wb-text-sm wb-text-muted">WebBlocks Commerce</p>
                        <h1>{{ $displayTitle ?? $product->title }}</h1>
                        @php($buyDescription = $displayDescription ?? $product->description)
                        @if ($buyDescription)
                            <p>{{ $buyDescription }}</p>
                        @endif
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div>
                            <strong>{{ $commerceText('buy.price', fallback: 'Price') }}</strong>
                            <div>{{ $money->format($product->price_amount, $product->currency, $publicLocaleCode) }}</div>
                            @if (($taxLine->rateBps ?? 0) > 0)
                                @php($ratePercent = number_format($taxLine->rateBps / 100, $taxLine->rateBps % 100 === 0 ? 0 : 2))
                                <div class="wb-text-sm wb-text-muted">
                                    @if ($taxLine->pricesIncludeTax)
                                        {{ $commerceText('buy.vat_included', ['rate' => $ratePercent, 'amount' => $money->format($taxLine->tax, $product->currency, $publicLocaleCode)], 'incl. :rate% VAT (:amount)') }}
                                    @else
                                        {{ $commerceText('buy.vat_added', ['rate' => $ratePercent, 'amount' => $money->format($taxLine->gross, $product->currency, $publicLocaleCode)], 'plus :rate% VAT — total :amount') }}
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div>
                            <strong>{{ $commerceText('buy.availability', fallback: 'Availability') }}</strong>
                            <div>{{ $product->inventory_quantity === null ? $commerceText('buy.available', fallback: 'Available') : ($product->inventory_quantity > 0 ? $commerceText('buy.available_count', ['count' => $product->inventory_quantity], ':count available') : $commerceText('buy.unavailable', fallback: 'Unavailable')) }}</div>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $commerceText('buy.checkout_unavailable', fallback: 'Checkout unavailable') }}</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    @if ($checkoutReady)
                        <div class="wb-cluster wb-gap-3 wb-flex-wrap">
                            <form method="POST" action="{{ route('webblocks.commerce.cart.items.add', $product->id) }}">
                                @csrf
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="wb-btn wb-btn-primary">{{ $commerceText('buy.add_to_cart', fallback: 'Add to cart') }}</button>
                            </form>
                            <form method="POST" action="{{ route('webblocks.commerce.products.checkout', $product->slug) }}">
                                @csrf
                                <button type="submit" class="wb-btn wb-btn-secondary">{{ $commerceText('buy.buy_now', fallback: 'Buy now') }}</button>
                            </form>
                        </div>
                    @else
                        <div class="wb-alert wb-alert-warning">
                            <div>
                                <div class="wb-alert-title">{{ $commerceText('buy.checkout_not_ready', fallback: 'Checkout not ready') }}</div>
                                <div>{{ $checkoutUnavailableMessage }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </main>
@endsection
