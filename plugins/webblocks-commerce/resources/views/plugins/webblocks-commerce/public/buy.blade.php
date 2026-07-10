@extends('webblocks-cms::layouts.public', [
    'title' => $title,
    'site' => $site,
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
    <main class="wb-content-shell wb-py-8">
        <div class="wb-stack wb-gap-4 wb-container">
            <a href="{{ url('/') }}" class="wb-link">Back to site</a>

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
                            <strong>Price</strong>
                            <div>{{ number_format($product->price_amount / 100, 2) }} {{ $product->currency }}</div>
                            @if (($taxLine->rateBps ?? 0) > 0)
                                @php($ratePercent = number_format($taxLine->rateBps / 100, $taxLine->rateBps % 100 === 0 ? 0 : 2))
                                <div class="wb-text-sm wb-text-muted">
                                    @if ($taxLine->pricesIncludeTax)
                                        incl. {{ $ratePercent }}% VAT ({{ number_format($taxLine->tax / 100, 2) }} {{ $product->currency }})
                                    @else
                                        plus {{ $ratePercent }}% VAT — total {{ number_format($taxLine->gross / 100, 2) }} {{ $product->currency }}
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div>
                            <strong>Availability</strong>
                            <div>{{ $product->inventory_quantity === null ? 'Available' : ($product->inventory_quantity > 0 ? $product->inventory_quantity.' available' : 'Unavailable') }}</div>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">Checkout unavailable</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    @if ($checkoutReady)
                        <form method="POST" action="{{ route('webblocks.commerce.products.checkout', $product->slug) }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-primary">Start Checkout</button>
                        </form>
                    @else
                        <div class="wb-alert wb-alert-warning">
                            <div>
                                <div class="wb-alert-title">Checkout not ready</div>
                                <div>{{ $checkoutUnavailableMessage }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </main>
@endsection
