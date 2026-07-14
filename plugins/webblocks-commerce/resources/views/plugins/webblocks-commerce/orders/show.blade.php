@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $statusClass = match ($order->status) {
        'paid' => 'wb-status-active',
        'failed', 'cancelled', 'expired' => 'wb-status-danger',
        'refunded' => 'wb-status-pending',
        default => 'wb-status-info',
    };

    $shortReference = function (?string $value): string {
        if ($value === null || $value === '') {
            return '-';
        }

        return strlen($value) > 16 ? substr($value, 0, 8).'...'.substr($value, -6) : $value;
    };
    $money = app(\WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\MoneyFormatter::class);
    $moneyLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $order->order_number,
        'description' => 'Review order details and payment attempts. Order status is read-only in this MVP slice.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <p><a href="{{ route('webblocks.plugins.webblocks_commerce.orders.index') }}">Back to Orders</a></p>

    <div class="wb-grid wb-grid-2 wb-gap-4">
        <section class="wb-card">
            <div class="wb-card-header">
                <strong>Overview</strong>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <span class="wb-status-pill {{ $statusClass }}">{{ ucfirst($order->status) }}</span>
                </div>

                <div class="wb-grid wb-grid-2">
                    <div>
                        <strong>Total</strong>
                        <div>{{ $money->format($order->total_amount, $order->currency, $moneyLocale) }}</div>
                    </div>
                    <div>
                        <strong>Subtotal (net)</strong>
                        <div>{{ $money->format($order->subtotal_amount, $order->currency, $moneyLocale) }}</div>
                    </div>
                    <div>
                        <strong>VAT{{ $order->tax_rate ? ' ('.number_format($order->tax_rate / 100, $order->tax_rate % 100 === 0 ? 0 : 2).'%'.($order->tax_country ? ' '.$order->tax_country : '').')' : '' }}</strong>
                        <div>{{ $money->format($order->tax_amount, $order->currency, $moneyLocale) }}</div>
                    </div>
                    <div>
                        <strong>Customer</strong>
                        <div>{{ data_get($order->metadata, 'customer.name') ?: '-' }}</div>
                        <div>{{ $order->customer_email ?: '-' }}</div>
                        @if (data_get($order->metadata, 'customer.phone'))
                            <div>{{ data_get($order->metadata, 'customer.phone') }}</div>
                        @endif
                    </div>
                    <div>
                        <strong>Site</strong>
                        <div>{{ $order->site?->name ?? 'Install-wide' }}</div>
                    </div>
                    <div>
                        <strong>Placed</strong>
                        <div>{{ $order->placed_at?->format('Y-m-d H:i') ?? '-' }}</div>
                    </div>
                    <div>
                        <strong>Paid</strong>
                        <div>{{ $order->paid_at?->format('Y-m-d H:i') ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="wb-card">
            <div class="wb-card-header">
                <strong>Gateway</strong>
            </div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-grid wb-grid-2">
                    <div>
                        <strong>Gateway</strong>
                        <div>{{ $order->gateway }}</div>
                    </div>
                    <div>
                        <strong>Checkout ID</strong>
                        <div><code>{{ $shortReference($order->gateway_checkout_id) }}</code></div>
                    </div>
                    <div>
                        <strong>Payment ID</strong>
                        <div><code>{{ $shortReference($order->gateway_payment_id) }}</code></div>
                    </div>
                    <div>
                        <strong>Customer ID</strong>
                        <div><code>{{ $shortReference($order->gateway_customer_id) }}</code></div>
                    </div>
                </div>

                <div class="wb-alert wb-alert-info">
                    <div>
                        <div class="wb-alert-title">Read-only order</div>
                        <div>Payment confirmation will be handled by the gateway webhook slice.</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="wb-card">
            <div class="wb-card-header">
                <strong>Delivery</strong>
            </div>
            <div class="wb-card-body wb-stack wb-gap-1">
                @if (data_get($order->metadata, 'shipping_address.line_1'))
                    <div>{{ data_get($order->metadata, 'customer.name') }}</div>
                    <div>{{ data_get($order->metadata, 'shipping_address.line_1') }}</div>
                    @if (data_get($order->metadata, 'shipping_address.line_2'))
                        <div>{{ data_get($order->metadata, 'shipping_address.line_2') }}</div>
                    @endif
                    <div>{{ data_get($order->metadata, 'shipping_address.postal_code') }} {{ data_get($order->metadata, 'shipping_address.city') }}</div>
                    <div>{{ data_get($order->metadata, 'shipping_address.country_code') }}</div>
                @else
                    <div class="wb-text-muted">No delivery address was captured.</div>
                @endif
            </div>
        </section>
    </div>

    <section class="wb-card">
        <div class="wb-card-header">
            <strong>Items</strong>
        </div>
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->title }}</strong>
                                    @if ($item->product)
                                        <div class="wb-text-sm wb-text-muted">
                                            <a href="{{ route('webblocks.plugins.webblocks_commerce.products.show', $item->product) }}">View product</a>
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $item->sku ?: '-' }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td class="wb-nowrap">{{ $money->format($item->unit_amount, $item->currency, $moneyLocale) }}</td>
                                <td class="wb-nowrap">{{ $money->format($item->total_amount, $item->currency, $moneyLocale) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="wb-card">
        <div class="wb-card-header">
            <strong>Payment Attempts</strong>
        </div>
        @if ($order->payments->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No payment attempts recorded.</div>
                </div>
            </div>
        @else
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Gateway</th>
                                <th>Amount</th>
                                <th>Checkout ID</th>
                                <th>Payment ID</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->payments as $payment)
                                <tr>
                                    <td>{{ ucfirst($payment->status) }}</td>
                                    <td>{{ $payment->gateway }}</td>
                                    <td class="wb-nowrap">{{ number_format($payment->amount / 100, 2) }} {{ $payment->currency }}</td>
                                    <td><code>{{ $shortReference($payment->gateway_checkout_id) }}</code></td>
                                    <td><code>{{ $shortReference($payment->gateway_payment_id) }}</code></td>
                                    <td class="wb-nowrap">{{ $payment->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>
@endsection
