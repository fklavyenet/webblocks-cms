@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $statusClass = match ($product->status) {
        'active' => 'wb-status-active',
        'archived' => 'wb-status-pending',
        default => 'wb-status-info',
    };
    $money = app(\WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\MoneyFormatter::class);
    $moneyLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $product->title,
        'description' => 'Review product details and public buy URL readiness.',
        'actions' => auth()->user()?->can('webblocks-commerce.manage-products')
            ? '<a href="'.route('webblocks.plugins.webblocks_commerce.products.edit', $product).'" class="wb-btn wb-btn-primary">Edit Product</a>'
            : null,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <p><a href="{{ route('webblocks.plugins.webblocks_commerce.products.index') }}">Back to Products</a></p>

    <div class="wb-grid wb-grid-2 wb-gap-4">
        <section class="wb-card">
            <div class="wb-card-header">
                <strong>Overview</strong>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <strong>{{ $product->title }}</strong>
                    <div class="wb-text-sm wb-text-muted"><code>{{ $product->slug }}</code></div>
                </div>

                <div>
                    <span class="wb-status-pill {{ $statusClass }}">{{ ucfirst($product->status) }}</span>
                </div>

                <div class="wb-grid wb-grid-2">
                    <div>
                        <strong>Price</strong>
                        <div>{{ $money->format($product->price_amount, $product->currency, $moneyLocale) }}</div>
                    </div>
                    <div>
                        <strong>Inventory</strong>
                        <div>{{ $product->inventory_quantity === null ? 'Not tracked' : $product->inventory_quantity }}</div>
                    </div>
                    <div>
                        <strong>SKU</strong>
                        <div>{{ $product->sku ?: '-' }}</div>
                    </div>
                    <div>
                        <strong>Site</strong>
                        <div>{{ $product->site?->name ?? 'Install-wide' }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="wb-card">
            <div class="wb-card-header">
                <strong>Checkout Readiness</strong>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                @if ($product->isAvailableForCheckout())
                    <div class="wb-alert wb-alert-info">
                        <div>
                            <div class="wb-alert-title">Product Available</div>
                            <div>This product can be published with a public buy URL when a checkout gateway is configured.</div>
                        </div>
                    </div>
                    <div>
                        <strong>Public Buy URL</strong>
                        <input type="text" class="wb-input" value="{{ $buyUrl }}" readonly>
                    </div>
                @else
                    <div class="wb-alert wb-alert-warning">
                        <div>
                            <div class="wb-alert-title">Product Not Available</div>
                            <div>Use Active status and non-zero inventory before checkout publishing.</div>
                        </div>
                    </div>
                @endif

                @can('webblocks-commerce.manage-products')
                    @if ($product->status !== 'archived')
                        <form method="POST" action="{{ route('webblocks.plugins.webblocks_commerce.products.archive', $product) }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-secondary">Archive Product</button>
                        </form>
                    @endif
                @endcan
            </div>
        </section>
    </div>

    <section class="wb-card">
        <div class="wb-card-header">
            <strong>Description</strong>
        </div>
        <div class="wb-card-body">
            @if ($product->description)
                <p>{{ $product->description }}</p>
            @else
                <div class="wb-empty">
                    <div class="wb-empty-title">No description recorded.</div>
                </div>
            @endif
        </div>
    </section>
@endsection
