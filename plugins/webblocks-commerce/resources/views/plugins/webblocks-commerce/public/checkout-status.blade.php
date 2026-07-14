@extends('webblocks-cms::layouts.public', [
    'title' => $title,
    'site' => $site,
    'publicLocaleCode' => $publicLocaleCode,
    'publicMeta' => [
        'title' => $title,
        'site_name' => $site?->publicDisplayName() ?? config('app.name'),
        'site_label' => $site?->display_name ?? $site?->name ?? config('app.name'),
        'meta_description' => $message,
        'og_title' => $title,
        'og_description' => $message,
    ],
])

@section('content')
    @php
        $money = app(\WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\MoneyFormatter::class);
        $commerceText = fn (string $key, array $replace = [], ?string $fallback = null): string => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->plugin('webblocks-commerce', 'public.'.$key, $publicLocaleCode, $replace, $fallback);
    @endphp
    <main class="wb-content-shell wb-py-8">
        <div class="wb-stack wb-gap-4 wb-container">
            <section class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-4">
                    <div class="wb-stack wb-gap-2">
                        <p class="wb-text-sm wb-text-muted">{{ $commerceText('status.order', fallback: 'Order') }} {{ $order->order_number }}</p>
                        <h1>{{ $heading }}</h1>
                        <p>{{ $message }}</p>
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div>
                            <strong>{{ $commerceText('status.status', fallback: 'Status') }}</strong>
                            <div>{{ ucfirst($order->status) }}</div>
                        </div>
                        <div>
                            <strong>{{ $commerceText('status.total', fallback: 'Total') }}</strong>
                            <div>{{ $money->format($order->total_amount, $order->currency, $publicLocaleCode) }}</div>
                        </div>
                    </div>

                    @if (data_get($order->metadata, 'shipping_address.line_1'))
                        <div class="wb-stack wb-gap-1">
                            <strong>{{ $commerceText('status.delivery_address', fallback: 'Delivery address') }}</strong>
                            <div>{{ data_get($order->metadata, 'customer.name') }}</div>
                            <div>{{ data_get($order->metadata, 'shipping_address.line_1') }}</div>
                            @if (data_get($order->metadata, 'shipping_address.line_2'))
                                <div>{{ data_get($order->metadata, 'shipping_address.line_2') }}</div>
                            @endif
                            <div>{{ data_get($order->metadata, 'shipping_address.postal_code') }} {{ data_get($order->metadata, 'shipping_address.city') }}</div>
                            <div>{{ data_get($order->metadata, 'shipping_address.country_code') }}</div>
                        </div>
                    @endif

                    <a href="{{ url('/') }}" class="wb-btn wb-btn-secondary">{{ $commerceText('status.return_to_site', fallback: 'Return to Site') }}</a>
                </div>
            </section>
        </div>
    </main>
@endsection
