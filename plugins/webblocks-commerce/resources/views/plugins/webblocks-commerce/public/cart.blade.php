@extends('webblocks-cms::layouts.public', [
    'title' => $title,
    'site' => $site,
    'publicLocaleCode' => $publicLocaleCode,
    'publicMeta' => [
        'title' => $title,
        'site_name' => $site?->publicDisplayName() ?? config('app.name'),
        'site_label' => $site?->display_name ?? $site?->name ?? config('app.name'),
        'meta_description' => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->plugin('webblocks-commerce', 'public.cart.meta_description', $publicLocaleCode, fallback: 'Review your cart and continue to secure hosted checkout.'),
    ],
])

@section('content')
    @php($commerceText = fn (string $key, array $replace = [], ?string $fallback = null): string => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->plugin('webblocks-commerce', 'public.'.$key, $publicLocaleCode, $replace, $fallback))
    <main class="wb-content-shell wb-py-8">
        <div class="wb-stack wb-gap-5 wb-container">
            <div class="wb-cluster wb-cluster-between wb-gap-3 wb-flex-wrap">
                <div class="wb-stack wb-gap-1">
                    <p class="wb-text-sm wb-text-muted">WebBlocks Commerce</p>
                    <h1>{{ $commerceText('cart.title', fallback: 'Shopping Cart') }}</h1>
                </div>
                <a href="{{ url('/') }}" class="wb-btn wb-btn-secondary">{{ $commerceText('cart.continue_shopping', fallback: 'Continue shopping') }}</a>
            </div>

            @if (session('commerce_success'))
                <div class="wb-alert wb-alert-success">
                    <div>{{ session('commerce_success') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="wb-alert wb-alert-danger">
                    <div>
                        <div class="wb-alert-title">{{ $commerceText('cart.update_failed', fallback: 'Cart could not be updated') }}</div>
                        <div>{{ $errors->first() }}</div>
                    </div>
                </div>
            @endif

            @if ($summary['items'] === [])
                <section class="wb-card">
                    <div class="wb-card-body wb-stack wb-gap-3">
                        <h2>{{ $commerceText('cart.empty_title', fallback: 'Your cart is empty') }}</h2>
                        <p class="wb-text-muted">{{ $commerceText('cart.empty_description', fallback: 'Add a product to begin checkout.') }}</p>
                        <div><a href="{{ url('/') }}" class="wb-btn wb-btn-primary">{{ $commerceText('cart.browse_products', fallback: 'Browse products') }}</a></div>
                    </div>
                </section>
            @else
                <div class="wb-grid wb-grid-2 wb-gap-5">
                    <section class="wb-card">
                        <div class="wb-card-header">
                            <strong>{{ $commerceText('cart.products', fallback: 'Products') }}</strong>
                        </div>
                        <div class="wb-card-body wb-stack wb-gap-4">
                            @foreach ($summary['items'] as $line)
                                <div class="wb-stack wb-gap-2">
                                    <div class="wb-cluster wb-cluster-between wb-gap-3 wb-flex-wrap">
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $line['title'] ?? $commerceText('cart.unavailable_product', fallback: 'Unavailable product') }}</strong>
                                            @if ($line['available'])
                                                <span class="wb-text-sm wb-text-muted">
                                                    {{ number_format($line['unit_amount'] / 100, 2) }} {{ $line['currency'] }} {{ $commerceText('cart.each', fallback: 'each') }}
                                                </span>
                                            @else
                                                <span class="wb-status-pill wb-status-danger">{{ $commerceText('cart.unavailable', fallback: 'Unavailable') }}</span>
                                            @endif
                                        </div>

                                        @if ($line['available'])
                                            <strong>{{ number_format($line['line_total'] / 100, 2) }} {{ $line['currency'] }}</strong>
                                        @endif
                                    </div>

                                    @if ($line['available'])
                                        <div class="wb-cluster wb-gap-2 wb-flex-wrap">
                                            <form method="POST" action="{{ route('webblocks.commerce.cart.items.update', $line['product_id']) }}" class="wb-cluster wb-gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <label class="wb-sr-only" for="commerce-cart-quantity-{{ $line['product_id'] }}">{{ $commerceText('cart.quantity', fallback: 'Quantity') }}</label>
                                                <input id="commerce-cart-quantity-{{ $line['product_id'] }}" class="wb-input" type="number" name="quantity" value="{{ $line['quantity'] }}" min="0" max="99" inputmode="numeric">
                                                <button type="submit" class="wb-btn wb-btn-secondary">{{ $commerceText('cart.update', fallback: 'Update') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('webblocks.commerce.cart.items.remove', $line['product_id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-btn wb-btn-ghost">{{ $commerceText('cart.remove', fallback: 'Remove') }}</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="wb-card">
                        <div class="wb-card-header">
                            <strong>{{ $commerceText('cart.summary', fallback: 'Order summary') }}</strong>
                        </div>
                        <div class="wb-card-body wb-stack wb-gap-4">
                            <div class="wb-stack wb-gap-2">
                                <div class="wb-cluster wb-cluster-between wb-gap-3">
                                    <span>{{ $commerceText('cart.subtotal', fallback: 'Subtotal') }}</span>
                                    <span>{{ number_format($summary['subtotal_amount'] / 100, 2) }} {{ $summary['currency'] }}</span>
                                </div>
                                <div class="wb-cluster wb-cluster-between wb-gap-3">
                                    <span>{{ $commerceText('cart.vat', fallback: 'VAT') }}</span>
                                    <span>{{ number_format($summary['tax_amount'] / 100, 2) }} {{ $summary['currency'] }}</span>
                                </div>
                                <div class="wb-cluster wb-cluster-between wb-gap-3">
                                    <strong>{{ $commerceText('cart.total', fallback: 'Total') }}</strong>
                                    <strong>{{ number_format($summary['total_amount'] / 100, 2) }} {{ $summary['currency'] }}</strong>
                                </div>
                                @if ($summary['prices_include_tax'])
                                    <p class="wb-text-sm wb-text-muted">{{ $commerceText('cart.vat_included', fallback: 'VAT is included in the shown total.') }}</p>
                                @endif
                            </div>

                            @if ($checkoutReady)
                                <form method="POST" action="{{ route('webblocks.commerce.cart.checkout') }}" class="wb-stack wb-gap-3">
                                    @csrf
                                    <div class="wb-stack wb-gap-1">
                                        <label for="commerce-customer-email">{{ $commerceText('cart.email', fallback: 'Email for the order') }} <span class="wb-text-muted">({{ $commerceText('cart.optional', fallback: 'optional') }})</span></label>
                                        <input id="commerce-customer-email" class="wb-input" type="email" name="customer_email" value="{{ old('customer_email', $cart?->customer_email) }}" autocomplete="email">
                                    </div>
                                    <button type="submit" class="wb-btn wb-btn-primary">{{ $commerceText('cart.secure_payment', fallback: 'Continue to secure payment') }}</button>
                                </form>
                            @else
                                <div class="wb-alert wb-alert-warning">
                                    <div>
                                        <div class="wb-alert-title">{{ $commerceText('cart.checkout_not_ready', fallback: 'Checkout not ready') }}</div>
                                        <div>{{ $checkoutUnavailableMessage ?? $commerceText('cart.products_unavailable', fallback: 'One or more products are unavailable.') }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            @endif
        </div>
    </main>
@endsection
